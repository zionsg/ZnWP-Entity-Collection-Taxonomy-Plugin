<?php
/**
 * Demo code
 *
 * This will register 'Primary Colors' as the collection taxonomy and 'Secondary Colors' as the entity taxonomy.
 * The taxonomies are registered with the WordPress default 'post' type and can be managed like Post Categories.
 * When adding or editing a post, a metabox will display all the entities grouped under the collections.
 *
 * @package ZnWP Entity Collection Taxonomy Plugin
 * @author  Zion Ng <zion@intzone.com>
 * @link    https://github.com/zionsg/ZnWP-Entity-Collection-Taxonomy-Plugin for canonical source repository
 */

$collection_terms = array(
    'Red' => array(
        'slug' => 'primary-color-red',
        'term_meta' => array('background_color' => '#ff0000', 'color' => '#ffffff'),
    ),
    'Green' => array(
        'slug' => 'primary-color-green',
        'term_meta' => array('background_color' => '#00ff00', 'color' => '#000000'),
    ),
    'Blue' => array(
        'slug' => 'primary-color-blue',
        'term_meta' => array('background_color' => '#0000ff', 'color' => '#ffffff'),
    ),
);

$entity_terms = array(
    'Cyan' => array(
        'slug' => 'secondary-color-cyan',
        'term_meta' => array('primary_color' => array('Green', 'Blue')),
    ),
    'Magenta' => array(
        'slug' => 'secondary-color-magenta',
        'term_meta' => array('primary_color' => array('Red', 'Blue')),
    ),
    'Yellow' => array(
        'slug' => 'secondary-color-yellow',
        'term_meta' => array('primary_color' => array('Red', 'Green')),
    ),
);

add_action('znwp_entity_collection_taxonomy_run', function () use ($collection_terms, $entity_terms) {
    return array(
        'plugin_name' => plugin_basename(__FILE__),
        'post_type' => array('post'),
        'collection' => array(
            'taxonomy' => 'primary_color',
            'singular_name' => 'Primary Color',
            'plural_name' => 'Primary Colors',
            'terms' => $collection_terms,
        ),
        'entity' => array(
            'taxonomy' => 'secondary_color',
            'singular_name' => 'Secondary Color',
            'plural_name' => 'Secondary Colors',
            'terms' => $entity_terms,
        ),
    );
});
