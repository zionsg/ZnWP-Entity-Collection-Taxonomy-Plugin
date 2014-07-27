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
This plugin only has 1 action hook - `znwp_entity_collection_taxonomy_run` which takes in a config array. It is designed to be used by multiple 3rd party plugins on the same WordPress installation to add custom taxonomies.

See the [Demo Code](https://raw.githubusercontent.com/zionsg/ZnWP-Entity-Collection-Taxonomy-Plugin/master/demo.php) on how to use the action hook and where to find the custom taxonomies after running it.
