<?php
/**
 * Core Plugin Class
 *
 * @package CreatorAI
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Creator_AI_Core {

    /**
     * The single instance of the class.
     * @var object
     */
    private static $instance = null;

    /**
     * The plugin version.
     * @var string
     */
    public $version;

    /**
     * Main Creator_AI_Core Instance.
     * Ensures only one instance of the class is loaded.
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    public function __construct() {
        $this->version = CREATOR_AI_VERSION;
        $this->load_dependencies();
    }

    /**
     * Load the required dependencies for this plugin.
     */
    private function load_dependencies() {
        // Core functionality classes.
        require_once CREATOR_AI_PLUGIN_DIR . 'includes/core/class-creator-ai-api.php';
        require_once CREATOR_AI_PLUGIN_DIR . 'includes/core/class-creator-ai-utils.php';
        require_once CREATOR_AI_PLUGIN_DIR . 'includes/core/class-creator-ai-assets.php';
        
        // Module classes for each feature.
        require_once CREATOR_AI_PLUGIN_DIR . 'includes/modules/class-creator-ai-youtube-article.php';
        require_once CREATOR_AI_PLUGIN_DIR . 'includes/modules/class-creator-ai-course-creator.php';
        require_once CREATOR_AI_PLUGIN_DIR . 'includes/modules/class-creator-ai-search-ai.php';
    }
    
    /**
     * Run the plugin.
     * Initializes all the hooks and functionality.
     */
    public function run() {
        // Initialize Assets Manager.
        $assets_manager = new Creator_AI_Assets();
        add_action( 'admin_enqueue_scripts', array( $assets_manager, 'enqueue_admin_assets' ) );
        add_action( 'wp_enqueue_scripts', array( $assets_manager, 'enqueue_frontend_assets' ) );

        // Initialize Modules.
        $youtube_article = new Creator_AI_YouTube_Article();
        $course_creator = new Creator_AI_Course_Creator();
        $search_ai = new Creator_AI_Search_AI();
        
        // General admin hooks.
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    /**
     * Add admin menu pages.
     */
    public function add_admin_menu() {
        add_menu_page(
            __( 'Creator AI', 'creator-ai' ),
            __( 'Creator AI', 'creator-ai' ),
            'manage_options',
            'creator-ai',
            array( $this, 'render_youtube_article_page' ),
            'data:image/svg+xml;base64,' . base64_encode( file_get_contents( CREATOR_AI_PLUGIN_DIR . 'assets/images/creator-ai-icon.svg' ) ),
            25
        );

        add_submenu_page(
            'creator-ai',
            __( 'YouTube Article Generator', 'creator-ai' ),
            __( 'YouTube Article', 'creator-ai' ),
            'manage_options',
            'creator-ai',
            array( $this, 'render_youtube_article_page' )
        );

        add_submenu_page(
            'creator-ai',
            __( 'Course Creator', 'creator-ai' ),
            __( 'Course Creator', 'creator-ai' ),
            'manage_options',
            'creator-ai-course-creator',
            array( $this, 'render_course_creator_page' )
        );

        add_submenu_page(
            'creator-ai',
            __( 'Settings', 'creator-ai' ),
            __( 'Settings', 'creator-ai' ),
            'manage_options',
            'creator-ai-settings',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Render the admin pages by including the view files.
     */
    public function render_youtube_article_page() {
        require_once CREATOR_AI_PLUGIN_DIR . 'views/admin-page-youtube-article.php';
    }

    public function render_course_creator_page() {
        require_once CREATOR_AI_PLUGIN_DIR . 'views/admin-page-course-creator.php';
    }

    public function render_settings_page() {
        require_once CREATOR_AI_PLUGIN_DIR . 'views/admin-page-settings.php';
    }

    /**
     * Register all plugin settings.
     */
    public function register_settings() {
        $settings_group = 'creator_ai_settings_group';

        // API Settings
        register_setting( $settings_group, 'cai_openai_api_key', 'sanitize_text_field' );
        register_setting( $settings_group, 'cai_openai_model', 'sanitize_text_field' );
        register_setting( $settings_group, 'cai_openai_tokens', 'intval' );
        register_setting( $settings_group, 'cai_youtube_channel_id', 'sanitize_text_field' );
        register_setting( $settings_group, 'cai_google_client_id', 'sanitize_text_field' );
        register_setting( $settings_group, 'cai_google_client_secret', 'sanitize_text_field' );
        register_setting( $settings_group, 'cai_debug', 'boolval' );

        // YouTube Article Settings
        register_setting( $settings_group, 'yta_internal_keywords', array( 'sanitize_callback' => array( 'Creator_AI_Utils', 'sanitize_keywords_array' ) ) );
        register_setting( $settings_group, 'yta_affiliate_links', array( 'sanitize_callback' => array( 'Creator_AI_Utils', 'sanitize_url_array' ) ) );
        register_setting( $settings_group, 'yta_blacklist_links', array( 'sanitize_callback' => array( 'Creator_AI_Utils', 'sanitize_url_array' ) ) );
        register_setting( $settings_group, 'yta_prompt_article_system', 'wp_kses_post' );
        register_setting( $settings_group, 'yta_prompt_seo_system', 'wp_kses_post' );

        // Course Creator Settings
        register_setting( $settings_group, 'cai_courses_page_id', 'intval' );
        register_setting( $settings_group, 'cai_course_layout_settings', 'Creator_AI_Utils::sanitize_layout_settings' );
        register_setting( $settings_group, 'cai_course_appearance_settings', 'Creator_AI_Utils::sanitize_appearance_settings' );
    }
}
