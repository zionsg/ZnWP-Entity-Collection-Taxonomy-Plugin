<?php
/**
 * Plugin class
 *
 * This plugin is designed to allow multiple 3rd party plugins to use it to register custom taxonomies.
 * Default terms added for 3rd party plugins will be removed when those are deactivated, not when
 * this plugin is deactivated.
 *
 * Info on action hook for setup:
 *     @hook   string   znwp_entity_collection_taxonomy_run
 *     @param  callback No additional arguments for callback needed
 *     @return array    See $config_defaults and demo plugin for examples
 *
 * @to-do   issue with '-master' in zip file when downloading from GitHub
 *          @see http://stackoverflow.com/questions/23642392/create-release-zip-file-on-github-without-tag-name-in-it
 * @to-do   (fetch_all()): A query is done for each term to get the custom metadata - not efficient
 * @package ZnWP Entity Collection Taxonomy Plugin
 * @author  Zion Ng <zion@intzone.com>
 * @link    https://github.com/zionsg/ZnWP-Entity-Collection-Taxonomy-Plugin for canonical source repository
 */

class ZnWP_Entity_Collection_Taxonomy
{
    /**
     * Constants
     */
    const OPTION_NAME = __CLASS__; // option name for this plugin for storing info
    const ACTION_HOOK = 'znwp_entity_collection_taxonomy_run';
    const COLLECTION = 'collection';
    const ENTITY = 'entity';

    /**
     * Put types in array for convenient iteration
     *
     * @var array
     */
    protected $types = array(self::COLLECTION, self::ENTITY);

    /**
     * Logging flag for debug purposes
     *
     * @var bool
     */
    protected $logging_on = false;

    /**
     * Config defaults for each client
     *
     * @var array
     */
    protected $config_defaults = array(
        'plugin_name' => '',       // name of plugin using hook - use plugin_basename(__FILE__)
        'post_type' => array(),    // post types to register taxonomies for
        'collection' => array(
            'taxonomy' => '',      // taxonomy name consisting of lowercase letters and underscore
            'singular_name' => '', // eg. Collection
            'plural_name' => '',   // eg. Collections
            'terms' => array(),    // default terms to add during activation and remove during deactivation
        ),
        'entity' => array(
            'taxonomy' => '',
            'singular_name' => '',
            'plural_name' => '',
            'terms' => array(),
        ),
    );

    /**
     * Final config comprising config for each 3rd party plugin that has called the action hook
     *
     * Indexed by plugin name.
     *
     * @var array
     */
    protected $config_by_plugin = array();

    /**
     * All the post types where custom taxonomies have been registered for
     *
     * Indexed by post type.
     *
     * @var array
     */
    protected $post_types = array();

    /**
     * Plugin initialization
     *
     * This will loop thru each 3rd party plugin which has called the action hook instead of
     * a one-time do_action().
     *
     * @return void
     */
    public function init()
    {
        global $wp_filter;

        // Format for $actions:
        // array(<priority> => array(<hash for plugin 1> => array('function' => <callback>, 'accepted_args' => <num>)))
        $actions = isset($wp_filter[self::ACTION_HOOK]) ? $wp_filter[self::ACTION_HOOK] : array();

        $me = $this; // for passing to callbacks via use()
        $me_info = get_option(self::OPTION_NAME, array()); // info on which 3rd party plugin has init for this plugin

        foreach ($actions as $priority => $action) {
            foreach ($action as $hash => $info) {
                $callback = $info['function'];
                $config = $this->check_config($callback());
                if (false === $config) {
                    continue;
                }

                $plugin_name = $config['plugin_name'];
                $this->config_by_plugin[$config['plugin_name']] = $config;

                // Remember post types
                foreach ($this->get_post_types($plugin_name) as $post_type) {
                    $this->post_types[$post_type] = $post_type; // indexed to prevent duplicates
                }

                // Extra layer of lambda functions used in order to pass in plugin name to callbacks
                foreach ($this->types as $type) {
                    $taxonomy = $this->get_taxonomy($plugin_name, $type);
                    add_action(
                        'init',
                        function () use ($me, $plugin_name, $type) {
                            return $me->{"register_{$type}_taxonomy"}($plugin_name);
                        }
                    );
                    add_action(
                        "{$taxonomy}_add_form_fields",
                        function ($a) use ($me, $plugin_name, $type) {
                            return $me->{"{$type}_custom_fields"}($plugin_name, $a);
                        }
                    );
                    add_action(
                        "{$taxonomy}_edit_form_fields",
                        function ($a) use ($me, $plugin_name, $type) {
                            return $me->{"{$type}_custom_fields"}($plugin_name, $a);
                        }
                    );
                    add_action(
                        "created_{$taxonomy}",
                        function ($a) use ($me, $plugin_name, $type) {
                            return $me->{"{$type}_save_custom_fields"}($plugin_name, $a);
                        }
                    );
                    add_action(
                        "edited_{$taxonomy}",
                        function ($a) use ($me, $plugin_name, $type) {
                            return $me->{"{$type}_save_custom_fields"}($plugin_name, $a);
                        }
                    );
                    add_filter(
                        "manage_edit-{$taxonomy}_columns",
                        function ($a) use ($me, $plugin_name, $type) {
                            return $me->{"{$type}_columns"}($plugin_name, $a);
                        }
                    );
                    add_filter(
                        "manage_{$taxonomy}_custom_column",
                        function ($a, $b, $c) use ($me, $plugin_name, $type) {
                            return $me->{"{$type}_custom_column"}($plugin_name, $a, $b, $c);
                        },
                        10,
                        3
                    );
                    add_action(
                        'admin_menu',
                        function () use ($me, $plugin_name, $type) {
                            return $me->{"{$type}_meta_box"}($plugin_name);
                        }
                    );
                } // end type

                // For saving data in metabox when adding or editing post
                foreach ($this->get_post_types($plugin_name) as $post_type) {
                    add_action(
                        "save_post_{$post_type}",
                        function ($a) use ($me, $plugin_name) {
                            return $me->save_post_meta($plugin_name, $a);
                        }
                    );
                }

                // Check if default terms for 3rd party plugin have been added - add once only
                if (!isset($me_info[$plugin_name])) {
                    // Need taxonomies to be registered first hence hook on to init
                    add_action(
                        'init',
                        function () use ($me, $plugin_name, $type) {
                            return $me->add_terms($plugin_name);
                        }
                    );

                    // Store a copy in db to facilitate removal later. Terms not needed
                    unset($config[self::COLLECTION]['terms']);
                    unset($config[self::ENTITY]['terms']);
                    $me_info[$plugin_name] = $config;
                }
            } // end action
        } // end actions

        // Check if any of the 3rd party plugins that were init previously but has been deactivated and remove
        // default terms if so. is_plugin_active() does not exist at this point, so get option manually from db
        $active_plugins = get_option('active_plugins', array());
        foreach ($me_info as $plugin_name => $info) {
            if (!in_array($plugin_name, $active_plugins)) {
                foreach ($this->types as $type) {
                    // Need to register taxonomies first on init hook, remove terms, then unregister them
                    $plugin_config = $me_info[$plugin_name];
                    add_action(
                        'init',
                        function () use ($me, $plugin_name, $type, $plugin_config) {
                            register_taxonomy($plugin_config[$type]['taxonomy'], $plugin_config['post_type']);
                            $me->remove_terms($plugin_name, $plugin_config);
                            register_taxonomy($plugin_config[$type]['taxonomy'], array());
                        }
                    );
                }
                unset($me_info[$plugin_name]);
            }
        }

        // Update option in db
        update_option(self::OPTION_NAME, $me_info);
    }

    /**
     * Callback used when this plugin is activated
     *
     * @return void
     */
    public function on_activation()
    {
        $this->log(__FUNCTION__, func_get_args());

        if (!current_user_can('activate_plugins')) {
            return;
        }
        $plugin = isset($_REQUEST['plugin']) ? $_REQUEST['plugin'] : '';
        check_admin_referer("activate-plugin_{$plugin}");

        // Create option in db to store info
        if (!get_option(self::OPTION_NAME)) {
            add_option(self::OPTION_NAME, array());
        }

        // Uncomment the following line to see the function in action
        // exit(var_dump($_GET));
    }

    /**
     * Callback used when this plugin is deactivated
     *
     * Data from 3rd party plugins is removed upon their deactivation, not in this.
     *
     * @return void
     */
    public function on_deactivation()
    {
        $this->log(__FUNCTION__, func_get_args());

        if (!current_user_can('activate_plugins')) {
            return;
        }
        $plugin = isset($_REQUEST['plugin']) ? $_REQUEST['plugin'] : '';
        check_admin_referer("deactivate-plugin_{$plugin}");

        delete_option(self::OPTION_NAME);

        // Uncomment the following line to see the function in action
        // exit(var_dump($_GET));
    }

    /**
     * Callback used when this plugin is uninstalled
     *
     * Data from 3rd party plugins is removed upon their deactivation, not in this.
     *
     * @return void
     */
    public function on_uninstall()
    {
        $this->log(__FUNCTION__, func_get_args());

        if (!current_user_can('activate_plugins')) {
            return;
        }
        check_admin_referer('bulk-plugins');

        // Important: Check if the file is the one that was registered during the uninstall hook
        if (__FILE__ != WP_UNINSTALL_PLUGIN) {
            return;
        }

        delete_option(self::OPTION_NAME);

        // Uncomment the following line to see the function in action
        // exit(var_dump($_GET));
    }

    /**
     * Register taxonomy for collection for plugin
     *
     * @param  string $plugin_name Name of 3rd party plugin
     * @return void
     */
    public function register_collection_taxonomy($plugin_name)
    {
        $this->log(__FUNCTION__, func_get_args());

        return $this->register_taxonomy($plugin_name, self::COLLECTION);
    }

    /**
     * Register taxonomy for entity for plugin
     *
     * @param  string $plugin_name Name of 3rd party plugin
     * @return void
     */
    public function register_entity_taxonomy($plugin_name)
    {
        $this->log(__FUNCTION__, func_get_args());

        return $this->register_taxonomy($plugin_name, self::ENTITY);
    }

    /**
     * Add default terms for both taxonomies for plugin
     *
     * @param string $plugin_name Name of 3rd party plugin
     * return void
     */
    public function add_terms($plugin_name)
    {
        $this->log(__FUNCTION__, func_get_args());

        foreach ($this->types as $type) {
            $taxonomy = $this->get_taxonomy($plugin_name, $type);

            $terms = $this->get_default_terms($plugin_name, $type);
            if (!is_array($terms)) {
                continue;
            }

            foreach ($terms as $term => $args) {
                $result = wp_insert_term(
                    $term,
                    $taxonomy,
                    $args
                );
                if (!$result instanceof WP_Error) {
                    $this->helper_save_custom_fields($result['term_id'], $taxonomy, $args);
                }
            }
        }
    }

    /**
     * Remove default terms for both taxonomies for plugin
     *
     * @param  string $plugin_name   Name of 3rd party plugin
     * @param  array  $plugin_config Config for 3rd party plugin. Needs to be passed in as info is not retained
     * @return void
     */
    public function remove_terms($plugin_name, $plugin_config)
    {
        $this->log(__FUNCTION__, func_get_args());

        foreach ($this->types as $type) {
            // $taxonomy  = $this->get_taxonomy($plugin_name, $type);
            $taxonomy = $plugin_config[$type]['taxonomy'];
            $tax_terms = get_terms($taxonomy, array('hide_empty' => false));

            if ($tax_terms instanceof WP_Error) {
                continue;
            }

            foreach ($tax_terms as $term) {
                wp_delete_term(
                    $term->term_id,
                    $taxonomy
                );
                delete_option("{$taxonomy}_term_{$term->term_id}");
            }
        }
    }

    /**
     * Callback for custom fields for collection for plugin
     *
     * @param  string $plugin_name Name of 3rd party plugin
     * @return void
     */
    public function collection_custom_fields($plugin_name, $term)
    {
        $name = $this->get_singular_name($plugin_name, self::COLLECTION);

        print $this->helper_custom_fields($term, $this->get_taxonomy($plugin_name, self::COLLECTION), array(
            'background_color' => array(
                'label' => 'Background color for ' . $name,
                'hint'  => 'Use color picker if available or type in hexadecimal code, eg. #ff0000.',
                'type'  => 'color',
            ),
            'color' => array(
                'label' => 'Foreground color for ' . $name,
                'hint'  => 'Use color picker if available or type in hexadecimal code, eg. #ff0000.',
                'type'  => 'color',
            ),
        ));
    }

    /**
     * Callback for saving custom fields for collection for plugin
     *
     * @param  string $plugin_name Name of 3rd party plugin
     * @return void
     */
    public function collection_save_custom_fields($plugin_name, $term_id)
    {
        return $this->helper_save_custom_fields($term_id, $this->get_taxonomy($plugin_name, self::COLLECTION));
    }

    /**
     * Callback for custom admin columns for collection for plugin
     *
     * @param  string $plugin_name Name of 3rd party plugin
     * @return array
     */
    public function collection_columns($plugin_name, $columns)
    {
        return array(
            'name'  => __('Name'),
            'color' => __('Color'),
            // 'header_icon' => '',
            // 'description' => __('Description'),
            'slug'  => __('Slug'),
            'posts' => __('Posts')
        );
    }

    /**
     * Callback for handling custom admin column for collection for plugin
     *
     * @param  mixed  $out
     * @param  string $column_name
     * @param  int    $term_id
     * @param  string $plugin_name Name of 3rd party plugin
     * @return void
     */
    public function collection_custom_column($plugin_name, $out, $column_name, $term_id)
    {
        $taxonomy = $this->get_taxonomy($plugin_name, self::COLLECTION);

        if ('color' == $column_name) {
            $term_meta= get_option("{$taxonomy}_term_{$term_id}");
            printf(
                '<span style="background-color:%s; color:%s">&nbsp;Text&nbsp;</span>',
                isset($term_meta['background_color']) ? $term_meta['background_color'] : '',
                isset($term_meta['color']) ? $term_meta['color'] : ''
            );
        }
    }

    /**
     * Callback for metaboxes for collection for plugin
     *
     * @param  string $plugin_name Name of 3rd party plugin
     * @return void
     */
    public function collection_meta_box($plugin_name)
    {
        $taxonomy = $this->get_taxonomy($plugin_name, self::COLLECTION);

        foreach ($this->get_post_types($plugin_name) as $post_type) {
            remove_meta_box("tagsdiv-{$taxonomy}", 'post', 'side');
            remove_meta_box("{$taxonomy}div", 'post', 'side');
        }
    }

    /**
     * Callback for custom fields for entity for plugin
     *
     * @param  string $plugin_name Name of 3rd party plugin
     * @return void
     */
    public function entity_custom_fields($plugin_name, $term)
    {
        $taxonomy = $this->get_taxonomy($plugin_name, self::ENTITY);
        $collection_taxonomy = $this->get_taxonomy($plugin_name, self::COLLECTION);

        $options = array();
        $collections = $this->fetch_all($collection_taxonomy);
        foreach ($collections as $collection) {
            $options[$collection->name] = sprintf(
                '<span style="background-color:%s; color:%s;">&nbsp;%s&nbsp;</span>',
                $collection->background_color,
                $collection->color,
                $collection->name
            );
        }

        $term_meta = get_option("{$taxonomy}_term_{$term->term_id}");
        $selected_collections = isset($term_meta[$collection_taxonomy]) ? $term_meta[$collection_taxonomy] : array();
        print $this->helper_custom_fields($term, $taxonomy, array(
            $collection_taxonomy => array(
                'label' => $this->get_plural_name($plugin_name, self::COLLECTION),
                'hint' => '',
                'type' => 'multicheckbox',
                'options' => $options,
                'selected_options' => $selected_collections,
                'option_separator' => '<br /><br />',
            ),
        ));
    }

    /**
     * Callback for saving custom fields for entity for plugin
     *
     * @param  string $plugin_name Name of 3rd party plugin
     * @return void
     */
    public function entity_save_custom_fields($plugin_name, $term_id)
    {
        return $this->helper_save_custom_fields($term_id, $this->get_taxonomy($plugin_name, self::ENTITY));
    }

    /**
     * Callback for custom admin columns for entity for plugin
     *
     * @param  string $plugin_name Name of 3rd party plugin
     * @return array
     */
    public function entity_columns($plugin_name, $columns)
    {
        return array(
            'name'  => __('Name'),
            $this->get_taxonomy($plugin_name, self::COLLECTION) =>
                __($this->get_plural_name($plugin_name, self::COLLECTION)),
            // 'header_icon' => '',
            // 'description' => __('Description'),
            'slug'  => __('Slug'),
            'posts' => __('Posts')
        );
    }

    /**
     * Callback for handling custom admin column for entity for plugin
     *
     * @param  mixed  $out
     * @param  string $column_name
     * @param  int    $term_id
     * @param  string $plugin_name Name of 3rd party plugin
     * @return void
     */
    public function entity_custom_column($plugin_name, $out, $column_name, $term_id)
    {
        $taxonomy = $this->get_taxonomy($plugin_name, self::ENTITY);
        $collection_taxonomy = $this->get_taxonomy($plugin_name, self::COLLECTION);

        $collections = $this->fetch_all($collection_taxonomy);

        if ($collection_taxonomy == $column_name) {
            $term_meta = get_option("{$taxonomy}_term_{$term_id}");
            if (isset($term_meta[$collection_taxonomy])) {
                foreach ($term_meta[$collection_taxonomy] as $collection_name) {
                    printf(
                        '<span style="background-color:%s; color:%s;">&nbsp;%s&nbsp;</span><br />',
                        $collections[$collection_name]->background_color,
                        $collections[$collection_name]->color,
                        $collection_name
                    );
                }
            }
        }
    }

    /**
     * Callback for metaboxes for entity for plugin
     *
     * @param  string $plugin_name Name of 3rd party plugin
     * @return void
     */
    public function entity_meta_box($plugin_name)
    {
        $me = $this;

        $taxonomy = $this->get_taxonomy($plugin_name, self::ENTITY);
        $collection_taxonomy = $this->get_taxonomy($plugin_name, self::COLLECTION);

        $entities    = $this->fetch_all($taxonomy );
        $collections = $this->fetch_all($collection_taxonomy);

        foreach ($this->get_post_types($plugin_name) as $post_type) {
            remove_meta_box("tagsdiv-{$taxonomy}", 'post', 'side');
            remove_meta_box("{$taxonomy}div", 'post', 'side');

            add_meta_box(
                "{$taxonomy}div",
                __($this->get_plural_name($plugin_name, self::ENTITY)),
                function () use (
                    $me, $plugin_name, $taxonomy, $collection_taxonomy, $entities, $collections
                ) {
                    // Pass in null for post ID so as to use the current post ID at the time the metabox is shown
                    print $me->entity_generate_form_html(
                        $plugin_name, null, $taxonomy, $collection_taxonomy, $entities, $collections
                    );
                },
                $post_type,
                'normal',
                'default'
            );
        }
    }

    /**
     * Generate form HTML for all entities and group under collections
     *
     * Optional params are to allow entity_meta_box() to pass in pre-computed variables
     * instead of querying in each iteration of the loop.
     *
     * Method is set as public to allow for use in frontend pages.
     *
     * @param  string $plugin_name         Name of 3rd party plugin
     * @param  int    $post_id             Optional post ID. This param is needed as the form may not
     *                                     be for the current post, eg. on frontend pages
     * @param  string $taxonomy            Optional entity taxonomy
     * @param  string $collection_taxonomy Optional collection taxonomy
     * @param  array  $entities            Optional entities
     * @param  array  $collections         Optional collections
     * @return string
     */
    public function entity_generate_form_html(
        $plugin_name,
        $post_id = null,
        $taxonomy = null,
        $collection_taxonomy = null,
        $entities = null,
        $collections = null
    ) {
        global $post;

        $post_id             = (null === $post_id) ? $post->ID : $post_id;
        $taxonomy            = (null === $taxonomy) ? $this->get_taxonomy($plugin_name, self::ENTITY) : $taxonomy;
        $collection_taxonomy = (null === $collection_taxonomy)
                             ? $this->get_taxonomy($plugin_name, self::COLLECTION)
                             : $collection_taxonomy;
        $entities            = (null === $entities) ? $this->fetch_all($taxonomy) : $entities;
        $collections         = (null === $collections) ? $this->fetch_all($collection_taxonomy) : $collections;

        $curr_entities = get_post_meta($post_id, $taxonomy, true) ?: array();

        $html = '';
        foreach ($collections as $collection) {
            $html .= sprintf(
                '<h4><span style="background-color:%s; color:%s">&nbsp;%s&nbsp;</span></h4>',
                $collection->background_color,
                $collection->color,
                $collection->name
            );

            $cols = 3;
            $col_width = (100 / 3) . '%';
            $col_cnt = 0;

            $html .= '<table width="100%" border="0">';
            foreach ($entities as $entity) {
                if (!in_array($collection->name, $entity->{$collection_taxonomy})) {
                    continue;
                }
                $html .= ((0 == $col_cnt % $cols) ? '<tr>' : '');

                $html .= sprintf(
                    '<td width="%1$s"><input id="%2$s" name="%2$s[]" type="checkbox" '
                    . 'value="%3$s" %4$s style="width:auto;" />%3$s</td>',
                    $col_width,
                    $taxonomy,
                    $entity->name,
                    in_array($entity->name, $curr_entities) ? 'checked="checked"' : ''
                );

                $html .= ((($cols - 1) == $col_cnt % $cols) ? '</tr>' : '');
                $col_cnt++;
            }
            $html .= '</table>';
        }

        return $html;
    }

    /**
     * Callback for saving metadata when a post is saved
     *
     * This saves all the checked entities for the post.
     *
     * @param  string $plugin_name Name of 3rd party plugin
     * @param  int    $post_id     The ID of the post
     * @return void
     */
    public function save_post_meta($plugin_name, $post_id)
    {
        $field = $this->get_taxonomy($plugin_name, self::ENTITY);

        // Update the post metadata
        if (isset($_POST[$field])) {
            update_post_meta($post_id, $field, $_POST[$field]);
        }
    }

    /**
     * Logging function to help with debugging
     *
     * @param  string $name Usually caller will use __FUNCTION__
     * @param  mixed  $info Optional extra information. Usually func_get_args() is used
     * @return void
     */
    protected function log($name, $info = null)
    {
        if (!$this->logging_on) {
            return;
        }
        mail('log@localhost', $name, var_export($info, true));
    }

    /**
     * Check config
     *
     * @param  array      $config Config from 3rd party plugin as per $config_defaults
     * @return array|bool Sanitized config. FALSE returned if config is faulty
     */
    protected function check_config($config)
    {
        $config = array_merge(
            $this->config_defaults,
            $config
        );
        if ($config == $this->config_defaults) {
            return false;
        }

        // Ensure post_type is array
        if (is_string($config['post_type'])) {
            $config['post_type'] = array($config['post_type']);
        }

        // Ensure taxonomy names consist only of lowercase letters and underscore
        foreach ($this->types as $type) {
            $config[$type]['taxonomy'] = preg_replace('/[^a-z_]/', '', strtolower($config[$type]['taxonomy']));
        }

        return $config;
    }

    /**
     * Get all post types to register taxonomies for
     *
     * @param  string $plugin_name If plugin name is passed in, only those post types registered
     *                             for that plugin are returned.
     * @return array
     */
    protected function get_post_types($plugin_name = null)
    {
        if (null === $plugin_name) {
            return $this->post_types;
        }

        return isset($this->config_by_plugin[$plugin_name]['post_type']) ? $this->config_by_plugin[$plugin_name]['post_type'] : array();
    }

    /**
     * Get taxonomy for plugin
     *
     * @param  string $plugin_name Name of 3rd party plugin
     * @param  string $type        Collection or entity
     * @return string
     */
    protected function get_taxonomy($plugin_name, $type)
    {
        return $this->config_by_plugin[$plugin_name][$type]['taxonomy'];
    }

    /**
     * Get singular name for plugin
     *
     * @param  string $plugin_name Name of 3rd party plugin
     * @param  string $type        Collection or entity
     * @return string
     */
    protected function get_singular_name($plugin_name, $type)
    {
        return $this->config_by_plugin[$plugin_name][$type]['singular_name'];
    }

    /**
     * Get plural name for plugin
     *
     * @param  string $plugin_name Name of 3rd party plugin
     * @param  string $type        Collection or entity
     * @return string
     */
    protected function get_plural_name($plugin_name, $type)
    {
        return $this->config_by_plugin[$plugin_name][$type]['plural_name'];
    }

    /**
     * Get default terms for plugin
     *
     * @param  string $plugin_name Name of 3rd party plugin
     * @param  string $type        Collection or entity
     * @return array
     */
    protected function get_default_terms($plugin_name, $type)
    {
        return $this->config_by_plugin[$plugin_name][$type]['terms'];
    }

    /**
     * Register taxonomy for plugin
     *
     * @param  string $plugin_name Name of 3rd party plugin
     * @param  string $type        Collection or entity
     * @return void
     */
    protected function register_taxonomy($plugin_name, $type)
    {
        $singular = $this->get_singular_name($plugin_name, $type);
        $plural   = $this->get_plural_name($plugin_name, $type);

        $labels = array(
            'name'                       => __("{$plural}"),
            'singular_name'              => __("{$singular}"),
            'all_items'                  => __("All {$plural}"),
            'parent_item'                => __("Parent {$singular}"),
            'add_new_item'               => __("Add New {$singular}"),
            'new_item_name'              => __("New {$singular}"),
            'edit_item'                  => __("Edit {$singular}"),
            'update_item'                => __("Update {$singular}"),
            'add_or_remove_items'        => __("Add or remove {$plural}"),
            'separate_items_with_commas' => __("Separate {$plural} with commas"),
            'search_items'               => __("Search {$plural}"),
            'popular_items'              => __("Popular {$plural}"),
            'choose_from_most_used'      => __("Choose from most used {$plural}"),
        );

        $args = array(
            'labels'            => $labels,
            'public'            => true,
            'hierarchical'      => false,
            'show_ui'           => true,
            'show_in_nav_menus' => false,
            'query_var'         => true,
            'rewrite'           => true,
        );

        register_taxonomy($this->get_taxonomy($plugin_name, $type), $this->get_post_types($plugin_name), $args);
    }

    /**
     * Helper function to generate custom form fields for taxonomy
     *
     * @param  object $term
     * @param  string $taxonomy
     * @param  array  $fields   Custom fields with labels and description, example:
     *                              array(field1 => array(
     *                                  'label' => <label>,
     *                                  'hint' => <hint>,
     *                                  'type' => <text|dropdown|multicheckbox>,
     *                                  'options' => <array of option-label pairs if type is dropdown or multicheckbox>,
     *                                  'selected_options' => <array of selected options if applicable>,
     *                                  'option_separator' => <text separating options in multicheckbox, eg. '<br>'>,
     *                              ), ...)
     * @return string
     */
    protected function helper_custom_fields($term, $taxonomy, array $fields)
    {
        // Check for existing taxonomy meta for the term being edited
        $term_id = $term->term_id; // Get the ID of the term being edited
        $term_meta = get_option("{$taxonomy}_term_{$term_id}");

        $defaults = array(
            'label' => '',
            'hint' => '',
            'type' => 'text',
            'options' => array(),
            'selected_options' => array(),
            'option_separator' => '',
        );

        $html = '';
        foreach ($fields as $field => $info) {
            extract(array_merge($defaults, $info));

            $value = isset($term_meta[$field]) ? $term_meta[$field] : '';
            if ('dropdown' == $type) {
                $element = sprintf('<select id="term_meta[%1$s]" name="term_meta[%1$s]">', $field);
                foreach ($options as $option => $optionLabel) { // cannot use $label as it is used already
                    $element .= sprintf(
                        '<option value="%s" %s>%s</option>' . PHP_EOL,
                        $option,
                        (in_array($option, $selected_options) ? 'selected="selected"' : ''),
                        $optionLabel
                    );
                }
                $element .= '</select>';
            } elseif ('multicheckbox' == $type) {
                // Style for checkbox set to width:auto else will elongate when editing term
                $element = '';
                foreach ($options as $option => $optionLabel) {
                    $element .= sprintf( // note the [] for name
                        '<input id="term_meta[%1$s]" name="term_meta[%1$s][]" type="checkbox" '
                        . 'value="%2$s" %3$s style="width:auto;" />%4$s%5$s',
                        $field,
                        $option,
                        (in_array($option, $selected_options) ? 'checked="checked"' : ''),
                        $optionLabel,
                        $option_separator
                    );
                }
            } else {
                $element = sprintf(
                    '<input id="term_meta[%1$s]" name="term_meta[%1$s]" type="%2$s" size="40" value="%3$s" />',
                    $field,
                    $type,
                    $value
                );
            }

            $html .= sprintf('
                <tr class="form-field">
                  <th><label for="%s">%s</label></th>
                  <td>%s<p>%s</p></td>
                </tr>
                ',
                $field,
                $label,
                $element,
                $hint
            );
        }

        return $html;
    }

    /**
     * Helper function to save custom fields for taxonomy
     *
     * @param  string $term_id
     * @param  string $taxonomy
     * @param  array  $data    Array containing 'term_meta' key. If not specified, POST data is used.
     * @return void
     */
    protected function helper_save_custom_fields($term_id, $taxonomy, array $data = null)
    {
        if (null === $data) {
            $data = $_POST;
        }
        if (!isset($data['term_meta'])) {
            return;
        }

        $term_meta = get_option("{$taxonomy}_term_{$term_id}");
        foreach ($data['term_meta'] as $key => $value) {
            $term_meta[$key] = $value;
        }

        update_option("{$taxonomy}_term_{$term_id}", $term_meta);
    }

    /**
     * Fetch all terms for a taxonomy including the term metadata
     *
     * To facilitate searching, terms are put into an associative array with term_name as key.
     *
     * @param  string $taxonomy
     * @return object[]
     */
    protected function fetch_all($taxonomy)
    {
        $terms = get_terms($taxonomy, array('hide_empty' => false));
        $terms_by_name = array();

        foreach ($terms as $key => $term) {
            $term_meta = get_option("{$taxonomy}_term_{$term->term_id}", array());
            foreach ($term_meta as $meta => $value) {
                $term->$meta = $value;
            }
            $terms_by_name[$term->name] = $term;
        }

        return $terms_by_name;
    }
}
