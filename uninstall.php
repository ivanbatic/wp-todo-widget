<?php

/**
 * Fired when the plugin is uninstalled.
 *
 * @package   Plugin_Name
 * @author    Your Name <email@example.com>
 * @license   GPL-2.0+
 * @link      http://example.com
 * @copyright 2013 Your Name or Company Name
 */
// If uninstall, not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

include 'class-todo-widget.php';
if (function_exists('is_multisite') && is_multisite()) {
    $blog_ids = Todo_Widget::get_blog_ids();
    foreach ($blog_ids as $blog_id) {
        switch_to_blog($blog_id);
        $table_name = $wpdb->prefix . Todo_Widget::$table_name;
        $wpdb->query('DROP TABLE IF EXISTS ' . $table_name);
    }
}