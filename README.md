##ZnWP Entity Collection Taxonomy Plugin

This WordPress plugin allows 3rd party plugins to easily add custom taxonomies for entities and collections and register with custom post types via an action hook.

Collections can be color coded and entities can be linked to multiple collections.

Default terms for each taxonomy can be added during activation and removed during deactivation of the 3rd party plugin.

Demo code included at end of main plugin file - just uncomment to see it in action.

### Installation
Steps
  - Click the "Download Zip" button on the righthand side of this GitHub page
  - Uncompress the zip file on your desktop
  - Rename the uncompressed folder to `znwp_entity_collection_taxonomy_plugin` without the `-master` GitHub tag
  - Copy the folder to your WordPress plugins folder OR compress the renamed folder and upload via the WordPress admin interface

### Usage
This plugin only has 1 action hook - `znwp_entity_collection_taxonomy_run`. It is designed to be used by multiple 3rd party plugins on the same WordPress installation to add custom taxonomies.

The demo code is as follows:
```php
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
```
