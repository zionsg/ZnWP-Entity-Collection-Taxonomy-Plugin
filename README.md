##ZnWP Entity Collection Taxonomy Plugin

This WordPress plugin allows multiple 3rd party plugins to easily add custom entity & collection taxonomies and register with custom post types via an action hook.

Collections can be color coded and entities can be grouped under multiple collections.

Default terms for each taxonomy are added during activation and removed during deactivation of the 3rd party plugin.

Posts are linked to entities not collections - the latter is for visual grouping only.

Demo plugin included.

### Installation
Steps
  - Click the "Download Zip" button on the righthand side of this GitHub page
  - Uncompress the zip file on your desktop
  - Copy the `znwp_entity_collection_taxonomy_plugin` folder to your WordPress plugins folder
    OR compress that folder and upload via the WordPress admin interface
  - Activate the plugin

### Usage
This plugin only has 1 action hook - `znwp_entity_collection_taxonomy_run` which takes in a config array. It is designed to be used by multiple 3rd party plugins on the same WordPress installation to add custom taxonomies.

After installation, you will see 2 plugins installed - the plugin itself and a demo plugin. Activate the demo plugin to see it in action.

View the source code of the [Demo Plugin](https://raw.githubusercontent.com/zionsg/ZnWP-Entity-Collection-Taxonomy-Plugin/master/znwp_entity_collection_taxonomy_plugin/demo.php) to see how to use the action hook.
