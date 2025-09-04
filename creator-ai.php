<?php
/*
Plugin Name: Creator AI
Description: The most power Wordpress plugin ever created! Powered by an A.I. more intelligent than the universe itself. 
Version: 4.7.0
Author: <strong>Jordy Vandeput</strong> <em>(in reality written by A.I.)</em>
*/


if (!defined('ABSPATH')) exit;
include_once(ABSPATH . 'wp-admin/includes/plugin.php');


// ACTIONS ON PLUGIN ACTIVATION
    register_activation_hook(__FILE__, 'creator_ai_activate');
    function creator_ai_activate() {
        //Saves default GPT model and tokens
        if ( empty(get_option('cai_openai_model')) ) {
            update_option('cai_openai_model', 'gpt-4o');
        }
        if ( empty(get_option('cai_openai_tokens')) ) {
            update_option('cai_openai_tokens', 8000);
        }else{
            $current_tokens = get_option('cai_openai_tokens');
            // Cap excessive tokens to prevent hosting security blocks
            $safe_tokens = min(intval($current_tokens), 8000);
            if ($safe_tokens != intval($current_tokens)) {
                error_log("Capped token setting from " . intval($current_tokens) . " to {$safe_tokens} to prevent hosting blocks");
            }
            update_option('cai_openai_tokens', $safe_tokens);
        }
    }

    // INCLUDES
    require_once plugin_dir_path(__FILE__) . 'includes/default-variables.php';
    require_once plugin_dir_path(__FILE__) . 'includes/api.php';
    require_once plugin_dir_path(__FILE__) . 'includes/article-formatting.php';
    require_once plugin_dir_path(__FILE__) . 'includes/youtube-article-functions.php';
    require_once plugin_dir_path(__FILE__) . 'includes/searchai-functions.php';
    require_once plugin_dir_path(__FILE__) . 'includes/course-creator-functions.php';
    require_once plugin_dir_path(__FILE__) . 'includes/course-publisher-functions.php';




class Creator_AI {

    use Creator_AI_Default_Variables;
    use Creator_AI_API;
    use Creator_AI_Article_Formatting;
    use Creator_AI_YouTube_Article_Functions;
    use Creator_AI_SearchAI_Functions;
    use Creator_AI_Course_Creator_Functions;
    use Creator_AI_Course_Publisher_Functions; 


    // Define class properties explicitly to avoid dynamic property deprecation
    public $version;
    public $api_settings;

// PLUGIN SETUP
    public function __construct() {
        add_action('init', array($this, 'load_plugin_data'));
        
        // Admin hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('admin_init', array($this, 'register_settings'));
        
        // Debug Panel
        add_action('admin_init', array($this, 'clear_debug_data'));

        add_action('admin_footer', array($this, 'display_debug_panel'));

        // AJAX handlers
        add_action('wp_ajax_yta_fetch_videos', array($this, 'fetch_videos'));
        add_action('wp_ajax_yta_create_article', array($this, 'create_youtube_article'));
        add_action('wp_ajax_yta_upload_image', array($this, 'handle_image_upload'));
        add_action('wp_ajax_cai_test_openai', array($this, 'test_openai_api'));
        add_action('wp_ajax_cai_test_google_api', array($this, 'test_google_api'));
        add_action('wp_ajax_yta_check_post_exists', array($this, 'check_youtube_post_exists'));
        add_action('wp_ajax_cai_clear_debug_data', array($this, 'ajax_clear_debug_data'));
        add_action('wp_ajax_cai_get_debug_data', array($this, 'ajax_get_debug_data'));

        add_action('init', array($this, 'initialize_searchai_block'), 11);
        add_action('init', array($this, 'initialize_course_creator'), 11);
        add_action('init', array($this, 'initialize_course_publisher'), 11);

        // Google oAuth handlers
        add_action('admin_post_cai_google_connect', array($this, 'google_connect'));
        add_action('admin_post_cai_google_disconnect', array($this, 'google_disconnect'));
        add_action('admin_post_cai_google_callback', array($this, 'google_callback'));

        //Course Creator
        add_action('before_delete_post', array($this, 'check_courses_page_deletion'));
        add_action('admin_notices', array($this, 'show_courses_page_deleted_notice'));
    }

    public function load_plugin_data() {
        // Set default values first to avoid undefined property errors
        $this->version = '0.0.0'; // Default fallback version
        
        // Only try to get plugin data if we're in admin
        if (is_admin()) {
            $plugin_file = plugin_dir_path(__FILE__) . 'creator-ai.php';
            if (file_exists($plugin_file)) {
                $plugin_data = get_plugin_data($plugin_file);
                if (!empty($plugin_data['Version'])) {
                    $this->version = $plugin_data['Version'];
                }
            }
        }
        
        $this->api_settings = array(
            'openai_model'           => get_option('cai_openai_model'),
            'openai_temperature'     => '0.8',
            'max_tokens'             => intval(get_option('cai_openai_tokens')),
            'openai_timeout'         => 120,
            'progress_poll_interval' => 3000, // Slightly longer to reduce server load
            'progress_timeout'       => 300000  // 5 minutes instead of 10 to prevent hosting blocks
        );
    }
    public function add_admin_menu() {

        add_menu_page(
            'YouTube Article',
            'Creator AI',
            'manage_options',
            'creator-ai',
            array($this, 'youtube_article_page'),
            plugin_dir_url(__FILE__) . 'assets/creator-ai-icon.svg'
        );

        add_submenu_page(
            'creator-ai',
            'YouTube Article Generator',
            'YouTube Article',
            'manage_options',
            'creator-ai',
            array($this, 'youtube_article_page')
        );

        add_submenu_page(
            'creator-ai',
            'Course Creator',
            'Course Creator',
            'manage_options',
            'creator-ai-course-creator',
            array($this, 'course_creator_page')
        );

        add_submenu_page(
            'creator-ai',
            'Settings',
            'Settings',
            'manage_options',
            'creator-ai-settings',
            array($this, 'settings_page')
        );
    }
    public function enqueue_assets($hook) {
        // Only load scripts on plugin pages
        if (strpos($hook, 'creator-ai') === false) return;
        
        $version = isset($this->version) ? $this->version : '0.0.0';

        wp_register_script('cai-api-js', plugin_dir_url(__FILE__) . '/js/api.js', array('jquery'), $version, true);
        wp_enqueue_script('cai-api-js');

        wp_localize_script('cai-api-js', 'caiAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cai_nonce')
        ));
        
        wp_register_style('cai-debug-css', plugin_dir_url(__FILE__) . '/css/debug.css', array(), $version);
        wp_register_script('cai-debug-js', plugin_dir_url(__FILE__) . '/js/debug.js', array('jquery', 'cai-api-js'), $version, true);

        wp_register_script('cai-settings-js', plugin_dir_url(__FILE__) . '/js/settings.js', array('jquery', 'cai-api-js'), $version, true);
        wp_register_script('yt-article-js', plugin_dir_url(__FILE__) . '/js/yt-article.js', array('jquery', 'cai-api-js'), $version, true);
        wp_register_script('searchai-js', plugin_dir_url(__FILE__) . '/js/searchai.js', array('jquery', 'cai-api-js'), $version, true);
        wp_register_script('coursecreator-js', plugin_dir_url(__FILE__) . '/js/course-creator.js', array('jquery', 'cai-api-js'), $version, true);
        


        if (get_option('cai_debug', false) && strpos($hook, 'creator-ai') !== false) {
            wp_enqueue_style('cai-debug-css');
            wp_enqueue_script('cai-debug-js');
        }

        $init_dependencies = array('jquery', 'cai-api-js');

        // Settings Page
        if (isset($_GET['page']) && $_GET['page'] === 'creator-ai-settings') {
            wp_enqueue_media(); // Add this line
            wp_enqueue_style('settings-style-css', plugin_dir_url(__FILE__) . '/css/settings-style.css', array(), $version);
            wp_enqueue_script('cai-settings-js');
            wp_localize_script('cai-settings-js', 'caiDefaults', $this->get_default_prompts_for_js());
            $init_dependencies[] = 'cai-settings-js';
            wp_enqueue_script('cai-init-js', plugin_dir_url(__FILE__) . '/js/init.js', $init_dependencies, $version, true);
        }
        
        // YouTube Article Page
        if (isset($_GET['page']) && $_GET['page'] === 'creator-ai') {
            wp_enqueue_style('yt-article-styles', plugin_dir_url(__FILE__) . 'css/yt-article-style.css', array(), $version);
            wp_enqueue_script('yt-article-js');
            wp_localize_script('yt-article-js', 'ytaAjax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('yta_nonce')
            ));
            $init_dependencies[] = 'yt-article-js';
            wp_enqueue_script('cai-init-js', plugin_dir_url(__FILE__) . '/js/init.js', $init_dependencies, $version, true);
        }
        

        // Search AI
        wp_enqueue_style('searchai-styles', plugin_dir_url(__FILE__) . 'css/searchai-style.css', array(), $version);
        wp_enqueue_script('searchai-js');
        wp_localize_script('searchai-js', 'pwAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pw_nonce')
        ));
        $init_dependencies[] = 'searchai-js';
        
        
        // Course Creator
       if (isset($_GET['page']) && $_GET['page'] === 'creator-ai-course-creator') {
            // Enqueue WordPress media scripts
            wp_enqueue_media();
            
            wp_enqueue_style('course-creator-styles', plugin_dir_url(__FILE__) . 'css/course-creator-style.css', array(), $version);
            wp_enqueue_script('coursecreator-js', plugin_dir_url(__FILE__) . '/js/course-creator.js', array('jquery', 'cai-api-js'), $version, true);
            
            // Add this line to load the publisher script in admin
            wp_enqueue_script('course-publisher-js', plugin_dir_url(__FILE__) . '/js/course-publisher.js', array('jquery', 'cai-api-js'), $version, true);
            
            wp_localize_script('coursecreator-js', 'caiAjax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('cai_nonce'),
                'plugin_url' => plugin_dir_url(dirname(__FILE__)),
                'site_url' => site_url(),
                'wp_upload_dir' => wp_upload_dir()
            ));
            
            $init_dependencies[] = 'coursecreator-js';
            $init_dependencies[] = 'course-publisher-js'; // Add this line
            
            wp_enqueue_editor();
            wp_enqueue_media();

            wp_enqueue_script('cai-init-js', plugin_dir_url(__FILE__) . '/js/init.js', $init_dependencies, $version, true);
        }
    }
    public function get_default_prompts_for_js() {
        // Make sure default_prompts is loaded
        if (!isset($this->default_prompts) || !is_array($this->default_prompts)) {
            // If trait is not properly loaded, manually include the file
            require_once plugin_dir_path(__FILE__) . 'includes/default-variables.php';
            // Create a temporary instance to access the trait
            $defaults = new class {
                use Creator_AI_Default_Variables;
            };
            $default_prompts = $defaults->default_prompts;
        } else {
            $default_prompts = $this->default_prompts;
        }
        return array(
            'articleSystem' => $default_prompts['yta_prompt_article_system'],
            'seoSystem' => $default_prompts['yta_prompt_seo_system'],
            'anchorText' => $default_prompts['yta_prompt_anchor'],
            'imageSeo' => $default_prompts['yta_prompt_image_seo'],
        );
    }
    public function register_settings() {
        
        //API
        register_setting('creator_ai_settings_group', 'cai_openai_api_key');
        register_setting('creator_ai_settings_group', 'cai_openai_model');
        register_setting(
            'creator_ai_settings_group', 
            'cai_openai_tokens', 
            array(
                'sanitize_callback' => 'intval', 
            )
        );
        register_setting('creator_ai_settings_group', 'cai_youtube_channel_id');
        register_setting('creator_ai_settings_group', 'cai_google_client_id');
        register_setting('creator_ai_settings_group', 'cai_google_client_secret');

        register_setting('creator_ai_settings_group', 'cai_debug', array(
            'type' => 'boolean',
            'sanitize_callback' => function($input) {
                return (bool) $input;
            }
        ));

        //YouTube Article
        register_setting('creator_ai_settings_group', 'yta_internal_keywords', array(
            'sanitize_callback' => array($this, 'sanitize_keywords_array')
        ));
        register_setting('creator_ai_settings_group', 'yta_affiliate_links', array(
            'sanitize_callback' => array($this, 'sanitize_links'),
        ));
        register_setting('creator_ai_settings_group', 'yta_blacklist_links', array(
            'sanitize_callback' => array($this, 'sanitize_links'),
        ));
        
        //News Article
        register_setting('creator_ai_settings_group', 'au_update_links', array(
            'sanitize_callback' => array($this, 'sanitize_links')
        ));
        
        // Register all prompt settings with their default values
        foreach ($this->default_prompts as $setting => $default_value) {
            register_setting('creator_ai_settings_group', $setting, array(
                'default' => $default_value,
            ));
        }

        register_setting('creator_ai_settings_group', 'cai_courses_page_id', array(
            'type' => 'integer',
            'sanitize_callback' => array($this, 'sanitize_course_page'),
            'default' => 0
        ));

        register_setting('creator_ai_settings_group', 'cai_course_layout_settings', array(
            'type' => 'array',
            'sanitize_callback' => array($this, 'sanitize_layout_settings'),
            'default' => array()
        ));
        register_setting('creator_ai_settings_group', 'cai_course_appearance_settings', array(
            'type' => 'array',
            'sanitize_callback' => array($this, 'sanitize_appearance_settings'),
            'default' => array()
        ));
    }

// PAGES
    public function settings_page() {
        require_once plugin_dir_path(__FILE__) . '/pages/settings.php';
    }
    public function youtube_article_page() {
        require_once plugin_dir_path(__FILE__) . '/pages/youtube-article.php';
    }

    public function course_creator_page() {
        require_once plugin_dir_path(__FILE__) . '/pages/course-creator.php';
    }

// DEBUG FUNCTIONS
    protected function is_debugging_enabled() {
        return get_option('cai_debug', false);
    }

    protected function log_debug_data($api_name, $data, $is_error = false) {
        if (!$this->is_debugging_enabled()) {
            return;
        }
        
        // Store in options
        $debug_data = get_option('creator_ai_debug_data', array());
        
        // Add new entry
        $debug_data[] = array(
            'timestamp' => current_time('mysql'),
            'api' => $api_name,
            'data' => $data,
            'is_error' => $is_error
        );
        
        // Limit to most recent 10 entries
        if (count($debug_data) > 10) {
            $debug_data = array_slice($debug_data, -10);
        }
        
        update_option('creator_ai_debug_data', $debug_data);
    }

    public function clear_debug_data() {
        if (isset($_GET['clear_debug']) && $_GET['clear_debug'] == 1 && 
            isset($_GET['page']) && (
                $_GET['page'] === 'creator-ai'
            ) && 
            get_option('cai_debug', false)) {
            
            delete_option('creator_ai_debug_data');
            wp_redirect(remove_query_arg('clear_debug'));
            exit;
        }
    }

    public function ajax_clear_debug_data() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'cai_nonce')) {
            wp_send_json_error('Nonce verification failed');
        }
        
        // Check if user has permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        // Delete debug data
        $deleted = delete_option('creator_ai_debug_data');
        
        wp_send_json_success(array('cleared' => $deleted));
    }

    public function display_debug_panel() {
        if (!get_option('cai_debug', false) || strpos($_SERVER['REQUEST_URI'], 'creator-ai') === false) {
            return;
        }
        
        // Get debug data from options
        $debug_data = get_option('creator_ai_debug_data', array());
        
        // Display the debug panel
        echo '<div class="cai-debug-panel">';
        echo '<div class="cai-debug-header">';
        echo '<h3>API Debugging Information</h3>';
        echo '<div class="cai-debug-actions">';
        echo '<a href="#" class="cai-debug-clear">Clear</a>';
        echo '<span class="cai-debug-toggle">Show/Hide Details</span>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="cai-debug-content">';
        
        if (empty($debug_data)) {
            echo '<div class="cai-debug-empty">No debug data available yet. Make API requests to see them here.</div>';
        } else {
            foreach (array_reverse($debug_data) as $index => $entry) {
                $timestamp = isset($entry['timestamp']) ? date('H:i:s', strtotime($entry['timestamp'])) : 'Unknown';
                $status_class = isset($entry['is_error']) && $entry['is_error'] ? 'error' : 'success';
                $api_name = isset($entry['api']) ? $entry['api'] : 'Unknown API';
                
                echo '<div class="cai-debug-item ' . $status_class . '">';
                echo '<div class="cai-debug-item-header">';
                echo '<span class="cai-debug-api">' . esc_html($api_name) . '</span>';
                echo '<span class="cai-debug-time">' . esc_html($timestamp) . '</span>';
                echo '<span class="cai-debug-item-toggle">+</span>';
                echo '</div>';
                
                echo '<div class="cai-debug-item-content">';
                echo '<pre>' . esc_html(json_encode(isset($entry['data']) ? $entry['data'] : array(), JSON_PRETTY_PRINT)) . '</pre>';
                echo '<button class="cai-debug-copy">Copy to clipboard</button>';
                echo '</div>';
                
                echo '</div>';
            }
        }
        
        echo '</div>'; // End debug content
        echo '</div>'; // End debug panel
    }

    public function ajax_get_debug_data() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'cai_nonce')) {
            wp_send_json_error('Nonce verification failed');
        }
        
        // Check if user has permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        // Get the debug data
        $debug_data = get_option('creator_ai_debug_data', array());
        
        wp_send_json_success($debug_data);
    }





// UTILITY
    public function sanitize_links($input) {
        $sanitized = array();
        
        if (is_array($input)) {
            foreach ($input as $url) {
                $clean_url = esc_url_raw(wp_unslash(trim($url)));
                if (!empty($clean_url)) {
                    $sanitized[] = $clean_url;
                }
            }
        }
        
        return $sanitized;
    }

    public function sanitize_course_page($page_id) {
        $page_id = intval($page_id);
        
        // Check if the page exists
        if ($page_id > 0) {
            $page = get_post($page_id);
            if (!$page || $page->post_type !== 'page' || $page->post_status !== 'publish') {
                // Page doesn't exist or is not a published page
                return 0;
            }
        }
        
        return $page_id;
    }

    // Add a method to check if a courses page is set (for later use)
    public function has_courses_page() {
        $page_id = get_option('cai_courses_page_id', 0);
        if ($page_id > 0) {
            $page = get_post($page_id);
            return ($page && $page->post_type === 'page' && $page->post_status === 'publish');
        }
        return false;
    }

    public function check_courses_page_deletion($post_id) {
        $courses_page_id = get_option('cai_courses_page_id', 0);
        
        if ($post_id == $courses_page_id) {
            // The courses page is being deleted, reset the option
            update_option('cai_courses_page_id', 0);
            
            // You could also set a transient to show a notice on the next admin page load
            set_transient('cai_courses_page_deleted', true, 30);
        }
    }

    public function show_courses_page_deleted_notice() {
        if (get_transient('cai_courses_page_deleted')) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p><?php _e('The selected courses page has been deleted. Please select a new page in the Creator AI settings.', 'creator-ai'); ?></p>
            </div>
            <?php
            delete_transient('cai_courses_page_deleted');
        }
    }

    public function sanitize_layout_settings($settings) {
        $sanitized = array();
        
        // Sanitize sidebar layout
        if (isset($settings['sidebar_layout'])) {
            $sanitized['sidebar_layout'] = sanitize_text_field($settings['sidebar_layout']);
        }
        
        // Sanitize disable_featured_image (checkbox)
        $sanitized['disable_featured_image'] = isset($settings['disable_featured_image']) ? (bool) $settings['disable_featured_image'] : false;
        
        // Sanitize disable_title (checkbox)
        $sanitized['disable_title'] = isset($settings['disable_title']) ? (bool) $settings['disable_title'] : false;
        
        // Sanitize content width
        if (isset($settings['content_width'])) {
            $sanitized['content_width'] = sanitize_text_field($settings['content_width']);
        }
        
        return $sanitized;
    }

    /**
     * Sanitize appearance settings
     */
    public function sanitize_appearance_settings($settings) {
        // Create sanitized array
        $sanitized = array();
        
        // Add debug output to see what's coming in from the form
        error_log("Appearance settings before sanitization: " . print_r($settings, true));
        
        // Sanitize each setting
        $sanitized['use_theme_styling'] = isset($settings['use_theme_styling']) ? (bool) $settings['use_theme_styling'] : false;

        // Color Settings
        $sanitized['primary_color'] = isset($settings['primary_color']) ? sanitize_hex_color($settings['primary_color']) : '';
        $sanitized['secondary_color'] = isset($settings['secondary_color']) ? sanitize_hex_color($settings['secondary_color']) : '';
        $sanitized['accent_color'] = isset($settings['accent_color']) ? sanitize_hex_color($settings['accent_color']) : '';
        $sanitized['text_color'] = isset($settings['text_color']) ? sanitize_hex_color($settings['text_color']) : '';
        $sanitized['background_color'] = isset($settings['background_color']) ? sanitize_hex_color($settings['background_color']) : '';
        $sanitized['sidebar_bg_color'] = isset($settings['sidebar_bg_color']) ? sanitize_hex_color($settings['sidebar_bg_color']) : '';

        // Typography Settings
        $sanitized['heading_font'] = isset($settings['heading_font']) ? sanitize_text_field($settings['heading_font']) : '';
        $sanitized['body_font'] = isset($settings['body_font']) ? sanitize_text_field($settings['body_font']) : '';
        $sanitized['base_font_size'] = isset($settings['base_font_size']) ? intval($settings['base_font_size']) : '';
        $sanitized['heading_font_size'] = isset($settings['heading_font_size']) ? intval($settings['heading_font_size']) : '';

        // Layout Settings
        $sanitized['sidebar_width'] = isset($settings['sidebar_width']) ? intval($settings['sidebar_width']) : '';
        $sanitized['sidebar_position'] = isset($settings['sidebar_position']) ? sanitize_text_field($settings['sidebar_position']) : '';
        $sanitized['sidebar_behavior'] = isset($settings['sidebar_behavior']) ? sanitize_text_field($settings['sidebar_behavior']) : '';
        $sanitized['border_radius'] = isset($settings['border_radius']) ? intval($settings['border_radius']) : '';

        // Progress Tracking
        $sanitized['progress_tracking'] = isset($settings['progress_tracking']) ? sanitize_text_field($settings['progress_tracking']) : '';
        $sanitized['progress_indicator'] = isset($settings['progress_indicator']) ? sanitize_text_field($settings['progress_indicator']) : '';
        $sanitized['display_progress_bar'] = isset($settings['display_progress_bar']) ? (bool) $settings['display_progress_bar'] : false;

        // Quiz Settings
        $sanitized['quiz_pass_percentage'] = isset($settings['quiz_pass_percentage']) ? intval($settings['quiz_pass_percentage']) : '';
        $sanitized['quiz_highlight_correct'] = isset($settings['quiz_highlight_correct']) ? (bool) $settings['quiz_highlight_correct'] : false;
        $sanitized['show_quiz_results'] = isset($settings['show_quiz_results']) ? (bool) $settings['show_quiz_results'] : false;

        // Certificate Settings
        $sanitized['certificate_enabled'] = isset($settings['certificate_enabled']) ? (bool) $settings['certificate_enabled'] : false;
        $sanitized['certificate_layout'] = isset($settings['certificate_layout']) ? sanitize_text_field($settings['certificate_layout']) : '';
        $sanitized['certificate_font'] = isset($settings['certificate_font']) ? sanitize_text_field($settings['certificate_font']) : '';
        $sanitized['certificate_logo'] = isset($settings['certificate_logo']) ? esc_url_raw($settings['certificate_logo']) : '';
        $sanitized['certificate_signature_image'] = isset($settings['certificate_signature_image']) ? esc_url_raw($settings['certificate_signature_image']) : '';
        $sanitized['certificate_company_name'] = isset($settings['certificate_company_name']) ? sanitize_text_field($settings['certificate_company_name']) : '';
        $sanitized['certificate_title_size'] = isset($settings['certificate_title_size']) ? intval($settings['certificate_title_size']) : '';
        $sanitized['certificate_title_color'] = isset($settings['certificate_title_color']) ? sanitize_hex_color($settings['certificate_title_color']) : '';
        $sanitized['certificate_border_color'] = isset($settings['certificate_border_color']) ? sanitize_hex_color($settings['certificate_border_color']) : '';
        $sanitized['certificate_border_width'] = isset($settings['certificate_border_width']) ? intval($settings['certificate_border_width']) : '';
        $sanitized['certificate_background'] = isset($settings['certificate_background']) ? esc_url_raw($settings['certificate_background']) : '';

        // Access Control
        $sanitized['course_access'] = isset($settings['course_access']) ? sanitize_text_field($settings['course_access']) : '';
        $sanitized['required_role'] = isset($settings['required_role']) ? sanitize_text_field($settings['required_role']) : '';
        
        error_log("Appearance settings after sanitization: " . print_r($sanitized, true));
        return $sanitized;
    }

    function cai_track_request() {
        $requests = get_transient('cai_request_count');
        if ($requests === false) {
            $requests = 0;
        }
        $requests++;
        set_transient('cai_request_count', $requests, 60);
    }

    function cai_check_request_limit() {
        $requests = get_transient('cai_request_count');
        if ($requests !== false && $requests >= 490) {
            return new WP_Error('request_limit_exceeded', 'The plugin has exceeded the maximum number of requests per minute. Please try again in a moment.');
        }
        return true;
    }

    function cai_remote_get($url, $args = array()) {
        $limit_check = $this->cai_check_request_limit();
        if (is_wp_error($limit_check)) {
            return $limit_check;
        }

        $this->cai_track_request();
        return wp_remote_get($url, $args);
    }

    function cai_remote_post($url, $args = array()) {
        $limit_check = $this->cai_check_request_limit();
        if (is_wp_error($limit_check)) {
            return $limit_check;
        }

        $this->cai_track_request();
        return wp_remote_post($url, $args);
    }

}

new Creator_AI();

function cai_track_request() {
    $requests = get_transient('cai_request_count');
    if ($requests === false) {
        $requests = 0;
    }
    $requests++;
    set_transient('cai_request_count', $requests, 60);
}

function cai_check_request_limit() {
    $requests = get_transient('cai_request_count');
    if ($requests !== false && $requests >= 490) {
        return new WP_Error('request_limit_exceeded', 'The plugin has exceeded the maximum number of requests per minute. Please try again in a moment.');
    }
    return true;
}

function cai_remote_get($url, $args = array()) {
    $limit_check = cai_check_request_limit();
    if (is_wp_error($limit_check)) {
        return $limit_check;
    }

    cai_track_request();
    return wp_remote_get($url, $args);
}

function cai_remote_post($url, $args = array()) {
    $limit_check = cai_check_request_limit();
    if (is_wp_error($limit_check)) {
        return $limit_check;
    }

    cai_track_request();
    return wp_remote_post($url, $args);
}