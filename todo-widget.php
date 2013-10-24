<?php

/**
 * The WordPress Plugin Boilerplate.
 *
 * A foundation off of which to build well-documented WordPress plugins that also follow
 * WordPress coding standards and PHP best practices.
 *
 * @wordpress-plugin
 * Plugin Name: Todo Widget
 * Plugin URI:  http://www.gitgub.com/ivanbatic/wp-todo-widget
 * Description: Adds a todo widget to the dashboard.
 * Version:     1.0.0
 * Author:      Ivan Batić
 */
// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

require_once( plugin_dir_path(__FILE__) . 'class-todo-widget.php' );

// Register hooks that are fired when the plugin is activated or deactivated.
// When the plugin is deleted, the uninstall.php file is loaded.
register_activation_hook(__FILE__, array('Todo_Widget', 'activate'));
register_deactivation_hook(__FILE__, array('Todo_Widget', 'deactivate'));

add_action('plugins_loaded', array('Todo_Widget', 'get_instance'));
