<?php
/*
Plugin Name: ZnWP Entity Collection Taxonomy Plugin
Plugin URI:  https://github.com/zionsg/ZnWP-Entity-Collection-Taxonomy-Plugin
Description: This plugin allows 3rd party plugins to easily add custom taxonomies for entities and collections and register with custom post types via an action hook. Collections can be color coded and entities can be linked to multiple collections. Default terms for each taxonomy can be added during activation and removed during deactivation of the 3rd party plugin. Demo code included at end of main plugin file.
Author:      Zion Ng
Author URI:  http://intzone.com/
Version:     1.0.0
*/

require_once 'ZnWP_Entity_Collection_Taxonomy.php'; // PSR-1 states files should not declare classes AND execute code

$znwp_entity_collection_taxonomy_plugin = new ZnWP_Entity_Collection_Taxonomy();
register_activation_hook(__FILE__,   array($znwp_entity_collection_taxonomy_plugin, 'on_activation'));
register_deactivation_hook(__FILE__, array($znwp_entity_collection_taxonomy_plugin, 'on_deactivation'));
register_uninstall_hook(__FILE__,    array($znwp_entity_collection_taxonomy_plugin, 'on_uninstall'));

// must be run after theme setup to allow functions.php in theme to add filter hook
add_action('after_setup_theme', array($znwp_entity_collection_taxonomy_plugin, 'init'));


/* DEMO CODE - Uncomment the following code to see the plugin in action */
// include 'demo.php';
