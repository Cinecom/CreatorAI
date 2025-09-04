<?php
/**
 * Plugin Name: Creator AI
 * Description: An AI-powered WordPress plugin to automatically write articles, generate courses, and enhance your content creation workflow.
 * Version: 5.0.0
 * Author: Jordy Vandeput (Refactored by AI)
 * Author URI: https://www.cinecom.net
 * Text Domain: creator-ai
 * Domain Path: /languages
 */

// Exit if accessed directly to prevent external execution.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants for easy access to paths and version.
define( 'CREATOR_AI_VERSION', '5.0.0' );
define( 'CREATOR_AI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CREATOR_AI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Include the main core class that orchestrates the plugin.
require_once CREATOR_AI_PLUGIN_DIR . 'includes/core/class-creator-ai-core.php';

/**
 * Begins execution of the plugin.
 *
 * Instantiates the core class and runs the plugin's setup hooks.
 *
 * @since 5.0.0
 */
function run_creator_ai() {
    $plugin = new Creator_AI_Core();
    $plugin->run();
}

// Launch the plugin.
run_creator_ai();

/**
 * Activation hook.
 * Sets up default options when the plugin is first activated.
 * This prevents the need for immediate configuration.
 */
register_activation_hook( __FILE__, 'creator_ai_activate' );
function creator_ai_activate() {
    // Set default GPT model if not already set.
    if ( ! get_option( 'cai_openai_model' ) ) {
        update_option( 'cai_openai_model', 'gpt-4o' );
    }
    // Set default tokens if not already set.
    if ( ! get_option( 'cai_openai_tokens' ) ) {
        update_option( 'cai_openai_tokens', 8192 );
    }
    // Ensure rewrite rules are flushed on the next load to register the course CPT slug.
    set_transient( 'cai_flush_rules_needed', true );
}

