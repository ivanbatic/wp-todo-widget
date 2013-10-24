<?php

/**
 * Todo Widget.
 *
 * @package   Todo_Widget
 * @author    Ivan Batić <ivan.batic@live.com>
 * @link      http://github.com/ivanbatic/wp-todo-widget
 */
require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

/**
 * Plugin class.
 * @package Todo_Widget
 */
class Todo_Widget {
    /**
     * Plugin version, used for cache-busting of style and script file references.
     * @since   1.0.0
     * @var     string
     */

    const VERSION = '1.0.0';

    /**
     * Unique identifier for your plugin.
     *
     * The variable name is used as the text domain when internationalizing strings of text.
     * Its value should match the Text Domain file header in the main plugin file.
     * @since    1.0.0
     * @var      string
     */
    protected $plugin_slug = 'todo_widget';

    /**
     * Instance of this class.
     * @since    1.0.0
     * @var      object
     */
    protected static $instance = null;

    /** @var string Table name */
    public static $table_name = 'todos';

    /**
     * @var array List of todos 
     */
    protected $todos = array();

    /**
     * Slug of the plugin screen.
     * @since    1.0.0
     * @var      string
     */
    protected $plugin_screen_hook_suffix = 'dashboard';

    /**
     * WordPress Database
     * @var wpdb 
     */
    protected $wpdb;

    /**
     * Initialize the plugin by setting localization, filters, and administration functions.
     * @since     1.0.0
     */
    private function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;

        // Load plugin text domain
        add_action('init', array($this, 'load_plugin_textdomain'));

        // Activate plugin when new blog is added
        add_action('wpmu_new_blog', array($this, 'activate_new_site'));

        // Load admin style sheet and JavaScript.
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // Register a dashboard widget
        add_action('wp_dashboard_setup', array($this, 'setup_dashboard'));

        // Ajax handlers
        add_action('wp_ajax_todo_read', array($this, 'get_todos'));
        add_action('wp_ajax_todo_create', array($this, 'create_todo'));
        add_action('wp_ajax_todo_delete', array($this, 'remove_todos'));
        add_action('wp_ajax_todo_update', array($this, 'update_todo'));
        add_action('wp_ajax_todo_reorder', array($this, 'reorder_todos'));
    }

    /**
     * Return an instance of this class.
     * @since     1.0.0
     * @return    object    A single instance of this class.
     */
    public static function get_instance() {

        // If the single instance hasn't been set, set it now.
        if (null == self::$instance) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /**
     * Fired when the plugin is activated.
     * @since    1.0.0
     * @param    boolean    $network_wide    True if WPMU superadmin uses "Network Activate" action, false if WPMU is disabled or plugin is activated on an individual blog.
     */
    public static function activate($network_wide) {
        if (function_exists('is_multisite') && is_multisite() && $network_wide) {
            // Get all blog ids
            $blog_ids = self::get_blog_ids();

            foreach ($blog_ids as $blog_id) {
                switch_to_blog($blog_id);
                self::single_activate();
            }
            restore_current_blog();
        } else {
            self::single_activate();
        }
    }

    /**
     * Fired when the plugin is deactivated.
     * @since    1.0.0
     * @param    boolean    $network_wide    True if WPMU superadmin uses "Network Deactivate" action, false if WPMU is disabled or plugin is deactivated on an individual blog.
     */
    public static function deactivate($network_wide) {
        if (function_exists('is_multisite') && is_multisite() && $network_wide) {
            // Get all blog ids
            $blog_ids = self::get_blog_ids();

            foreach ($blog_ids as $blog_id) {
                switch_to_blog($blog_id);
                self::single_deactivate();
            }
            restore_current_blog();
        } else {
            self::single_deactivate();
        }
    }

    /**
     * Fired when a new site is activated with a WPMU environment.
     * @since    1.0.0
     * @param	int	$blog_id ID of the new blog.
     */
    public function activate_new_site($blog_id) {
        if (1 !== did_action('wpmu_new_blog'))
            return;

        switch_to_blog($blog_id);
        self::single_activate();
        restore_current_blog();
    }

    /**
     * Get all blog ids of blogs in the current network that are:
     * - not archived
     * - not spam
     * - not deleted
     * @since    1.0.0
     * @return	array|false	The blog ids, false if no matches.
     */
    public static function get_blog_ids() {
        // get an array of blog ids
        global $wpdb;
        $table_name = $wpdb->prefix . 'blogs';
        $sql = "SELECT blog_id FROM $table_name
			WHERE archived = '0' AND spam = '0'
			AND deleted = '0'";
        return $wpdb->get_col($sql);
    }

    /**
     * Fired for each blog when the plugin is activated.
     * @since    1.0.0
     */
    private static function single_activate() {
        global $wpdb;
        $table_name = $wpdb->prefix . self::$table_name;

        $sql = "CREATE TABLE $table_name (
        id int(11) unsigned NOT NULL AUTO_INCREMENT,
        content text NOT NULL,
        done tinyint(1) DEFAULT 0 NOT NULL,
        time_created timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        time_done timestamp NULL DEFAULT NULL,
        user_id bigint(20) unsigned NOT NULL,
        position int(11) unsigned DEFAULT 0 NOT NULL,
        PRIMARY KEY  (id),
        KEY user_id (user_id)
        );";

        dbDelta($sql);
    }

    /**
     * Fired for each blog when the plugin is deactivated.
     * @since    1.0.0
     */
    private static function single_deactivate() {
        
    }

    /**
     * Load the plugin text domain for translation.
     * @since    1.0.0
     */
    public function load_plugin_textdomain() {

        $domain = $this->plugin_slug;
        $locale = apply_filters('plugin_locale', get_locale(), $domain);

        load_textdomain($domain, trailingslashit(WP_LANG_DIR) . $domain . '/' . $domain . '-' . $locale . '.mo');
        load_plugin_textdomain($domain, FALSE, basename(dirname(__FILE__)) . '/languages');
    }

    /**
     * Register and enqueue admin-specific style sheet.
     * @since     1.0.0
     * @return    null    Return early if no settings page is registered.
     */
    public function enqueue_admin_styles() {

        if (!isset($this->plugin_screen_hook_suffix)) {
            return;
        }

        $screen = get_current_screen();
        if ($screen->id == $this->plugin_screen_hook_suffix) {
            wp_enqueue_style($this->plugin_slug . '-admin-styles', plugins_url('css/main.css', __FILE__), array(), self::VERSION);
        }
    }

    /**
     * Register and enqueue admin-specific JavaScript.
     * @since     1.0.0
     * @return    null    Return early if no settings page is registered.
     */
    public function enqueue_admin_scripts($hook) {
        if (!isset($this->plugin_screen_hook_suffix)) {
            return;
        }
        $screen = get_current_screen();
        if ($screen->id == $this->plugin_screen_hook_suffix) {
            wp_enqueue_script($this->plugin_slug . '-jq-sortable-plugin', plugins_url('js/jquery.sortable.min.js', __FILE__), array('jquery'), self::VERSION);
            wp_enqueue_script($this->plugin_slug . '-admin-script', plugins_url('js/scripts.js', __FILE__), array('jquery', $this->plugin_slug . '-jq-sortable-plugin'), self::VERSION);
            wp_localize_script($this->plugin_slug . '-admin-script', 'TodoAjax', array(
                'url'      => admin_url('admin-ajax.php'),
                '_wpnonce' => wp_create_nonce()
            ));
        }
    }

    public function setup_dashboard() {
        wp_add_dashboard_widget($this->plugin_slug, __('Todo Tasks'), array($this, 'render'));
    }

    public function render() {
        include(__DIR__ . '/views/todo-widget.php');
    }

    /**
     * Get todos from the database
     * @return array
     */
    protected function fetch_todos() {
        $table_name = $this->wpdb->prefix . self::$table_name;
        $user_id = get_current_user_id();
        return $this->wpdb->get_results("SELECT * FROM {$table_name} WHERE user_id = {$user_id} ORDER BY position, time_created DESC");
    }

    /**
     * Get todos
     */
    public function get_todos() {
        $this->check_nonce();
        $todos = $this->fetch_todos();
        $this->json_respond($todos);
    }

    /**
     * Create todos
     */
    public function create_todo() {
        $this->check_nonce();
        if (empty($_POST['content'])) {
            $this->json_respond(null, false, __('Content must not empty.'), $no_nonce);
        }
        $table_name = $this->wpdb->prefix . self::$table_name;
        $insert = $this->wpdb->insert($table_name, array(
            'content' => $_POST['content'],
            'user_id' => get_current_user_id()
            ), array('%s', '%d'));
        $this->json_respond(array(
            'insert_id' => $this->wpdb->insert_id
            ), $insert);
    }

    /**
     * Delete todos
     */
    public function remove_todos() {
        $this->check_nonce();
        $todos = $_POST['todos'];
        if (!is_array($todos)) {
            $this->json_respond('null', false, __('Need an array of ids in order to remove todos.'), true);
        }

        $todos[] = 0;
        $joined = esc_sql(join(',', $todos));
        $where_clause = "WHERE id IN ({$joined})";
        $table_name = $this->wpdb->prefix . self::$table_name;
        $query = "DELETE FROM {$table_name} {$where_clause};";
        $remove = $this->wpdb->query($query);
        $this->json_respond(array('deleted' => $remove, 'query'   => $query), $remove);
    }

    public function reorder_todos() {
        $this->check_nonce();
        $order = $_POST['order'];
        if (!is_array($order) || empty($order)) {
            $this->json_respond(null, false, __('Reordering must be done on a set of todos.'), true);
        }
        $order[] = 0;
        $ordering = join(',', $order);
        $user_id = get_current_user_id();
        $table_name = $this->wpdb->prefix . self::$table_name;

        $this->wpdb->query('SET @i := 0;');
        $statement = "
            UPDATE {$table_name}
            SET position = (select @i := @i + 1)
            WHERE user_id = {$user_id} 
            AND id IN ({$ordering})
            ORDER BY FIELD (id, {$ordering});
        ";
        $this->wpdb->query($statement);
        $this->json_respond($ordered, true, $statement);
    }

    /**
     * Update todos
     */
    public function update_todo() {
        $this->check_nonce();
        // Early escape if there's no ID
        if (!$_POST['id'] || !is_numeric($_POST['id'])) {
            $this->json_respond(null, false, __('You have to provide a valid id.'), true);
        }

        // Prepare update data array
        // The null time won't work because the statement is prepared,
        // I could write my own query, but that column isn't really that important at the moment
        $update_data = array(
            'content'   => $_POST['content'] ? : false,
            'time_done' => $_POST['done'] ? date('Y-m-d H:i:s', time()) : null,
            'done'      => isset($_POST['done']) ? $_POST['done'] : false
        );
        // Remove false elements
        $update_data = array_filter($update_data, function($e) {
                if ($e !== false)
                    return true;
            });
        $table_name = $this->wpdb->prefix . self::$table_name;
        $update = $this->wpdb->update($table_name, $update_data, array(
            'id' => $_POST['id']
        ));

        $this->json_respond($update, $update);
    }

    /**
     * Checks if nonce is valid,
     * breaks the excecution if it's not
     */
    private function check_nonce() {
        $nonce = $_REQUEST['_wpnonce'];
        if (!wp_verify_nonce($nonce)) {
            $this->json_respond(__('Shoo! Go away!'), false, true);
        }
    }

    /**
     * Echo a json response
     * @param type $data Response content
     * @param type If the response is considered successful
     */
    protected function json_respond($data = null, $status = true, $message = null, $no_nonce = false) {
        $response = array(
            'status'   => (bool) $status,
            'data'     => $data,
            'message'  => null,
            '_wpnonce' => $no_nonce ? null : wp_create_nonce()
        );
        header("Content-Type: application/json");
        echo json_encode($response);
        exit();
    }

}
