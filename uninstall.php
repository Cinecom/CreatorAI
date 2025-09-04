<?php
/**
 * Creator AI Uninstaller
 *
 * This file is triggered when the user deletes the plugin from the WordPress admin panel.
 * It is responsible for cleaning up all plugin data from the database and filesystem
 * to ensure a clean removal.
 *
 * @package CreatorAI
 */

// Exit if accessed directly to prevent malicious access.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    die;
}

/**
 * Deletes all posts of a specific custom post type.
 *
 * @param string $post_type The post type to delete.
 */
function creator_ai_uninstall_delete_custom_posts( $post_type ) {
    $args = array(
        'post_type'      => $post_type,
        'posts_per_page' => -1,
        'post_status'    => 'any',
        'fields'         => 'ids',
    );

    $posts = get_posts( $args );

    if ( ! empty( $posts ) ) {
        foreach ( $posts as $post_id ) {
            // true = force delete, bypass trash.
            wp_delete_post( $post_id, true );
        }
    }
}

/**
 * Recursively deletes a directory and all its contents.
 *
 * @param string $dirPath The path to the directory to delete.
 */
function creator_ai_uninstall_delete_directory( $dirPath ) {
    if ( ! is_dir( $dirPath ) ) {
        return;
    }
    // Add trailing slash if missing.
    if ( substr( $dirPath, -1 ) !== '/' ) {
        $dirPath .= '/';
    }
    // Use glob to find all files and directories.
    $files = glob( $dirPath . '*', GLOB_MARK );
    foreach ( $files as $file ) {
        if ( is_dir( $file ) ) {
            creator_ai_uninstall_delete_directory( $file );
        } else {
            unlink( $file );
        }
    }
    rmdir( $dirPath );
}

// 1. Delete all plugin options from the options table.
$options_to_delete = array(
    'cai_openai_api_key',
    'cai_openai_model',
    'cai_openai_tokens',
    'cai_youtube_channel_id',
    'cai_google_client_id',
    'cai_google_client_secret',
    'cai_google_access_token',
    'cai_google_refresh_token',
    'cai_google_expires',
    'cai_debug',
    'cai_courses_page_id',
    'cai_course_layout_settings',
    'cai_course_appearance_settings',
    'yta_internal_keywords',
    'yta_affiliate_links',
    'yta_blacklist_links',
    'yta_prompt_article_system',
    'yta_prompt_seo_system',
    'creator_ai_debug_data', // Debug data
);

foreach ( $options_to_delete as $option_name ) {
    delete_option( $option_name );
}

// Also delete any related transients.
global $wpdb;
$wpdb->query( "DELETE FROM `{$wpdb->options}` WHERE `option_name` LIKE ('_transient_cai_%') OR `option_name` LIKE ('_transient_timeout_cai_%')" );

// 2. Delete all 'cai_course' custom posts.
creator_ai_uninstall_delete_custom_posts( 'cai_course' );

// 3. Delete the 'creator-ai-courses' directory from the uploads folder.
$upload_dir = wp_upload_dir();
$course_files_dir = $upload_dir['basedir'] . '/creator-ai-courses';
if ( file_exists( $course_files_dir ) ) {
    creator_ai_uninstall_delete_directory( $course_files_dir );
}

// 4. Clean up user meta related to course progress.
$wpdb->query( "DELETE FROM `{$wpdb->usermeta}` WHERE `meta_key` LIKE ('cai_course_progress_%')" );

// 5. Flush rewrite rules to remove custom post type rewrite rules.
flush_rewrite_rules();

