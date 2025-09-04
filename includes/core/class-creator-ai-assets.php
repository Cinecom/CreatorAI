<?php
/**
 * Asset Manager Class
 * Handles the enqueuing of all CSS and JavaScript files for the plugin.
 *
 * @package CreatorAI
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Creator_AI_Assets {

    /**
     * Enqueue scripts and styles for the admin area.
     *
     * @param string $hook The current admin page hook.
     */
    public function enqueue_admin_assets( $hook ) {
        // Only load assets on our plugin's pages to avoid conflicts.
        if ( strpos( $hook, 'creator-ai' ) === false ) {
            return;
        }

        // Enqueue combined admin stylesheet.
        wp_enqueue_style(
            'creator-ai-admin',
            CREATOR_AI_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            CREATOR_AI_VERSION
        );

        // Enqueue combined admin script.
        wp_enqueue_script(
            'creator-ai-admin',
            CREATOR_AI_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery', 'wp-i18n', 'wp-element', 'wp-components', 'wp-block-editor' ),
            CREATOR_AI_VERSION,
            true
        );

        // Localize script with necessary data for all admin pages.
        wp_localize_script(
            'creator-ai-admin',
            'caiAdmin',
            array(
                'ajax_url'      => admin_url( 'admin-ajax.php' ),
                'nonce'         => wp_create_nonce( 'creator_ai_nonce' ),
                'plugin_url'    => CREATOR_AI_PLUGIN_URL,
                'site_url'      => site_url(),
                'wp_upload_dir' => wp_upload_dir(),
                'default_prompts' => Creator_AI_Utils::get_default_prompts(),
            )
        );
        
        // Enqueue media uploader scripts only for the Course Creator page.
        if ( strpos( $hook, 'creator-ai-course-creator' ) !== false ) {
            wp_enqueue_media();
            wp_enqueue_editor();
        }
    }

    /**
     * Enqueue scripts and styles for the frontend (public-facing pages).
     */
    public function enqueue_frontend_assets() {
        global $post;
        
        $load_assets = false;

        // Check if we are on a single course page or if the page content has our SearchAI block.
        if ( is_singular( 'cai_course' ) || ( is_a( $post, 'WP_Post' ) && has_block( 'creator-ai/searchai', $post->post_content ) ) ) {
             $load_assets = true;
        }
        
        if ( $load_assets ) {
            wp_enqueue_style(
                'creator-ai-frontend',
                CREATOR_AI_PLUGIN_URL . 'assets/css/frontend.css',
                array(),
                CREATOR_AI_VERSION
            );
            
            wp_enqueue_script(
                'creator-ai-frontend',
                CREATOR_AI_PLUGIN_URL . 'assets/js/frontend.js',
                array( 'jquery' ),
                CREATOR_AI_VERSION,
                true
            );

            $frontend_data = array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'creator_ai_nonce' ),
            );

            // Add course-specific data if on a course page.
            if ( is_singular( 'cai_course' ) ) {
                $course_id = get_post_meta( $post->ID, '_cai_course_id', true );
                $user_id   = get_current_user_id();

                $frontend_data['course'] = array(
                     'post_id'       => $post->ID,
                     'course_id'     => $course_id,
                     'user_progress' => Creator_AI_Course_Creator::get_user_progress( $user_id, $course_id ),
                     'total_sections'=> Creator_AI_Utils::count_course_sections( $course_id ),
                );
            }

            wp_localize_script( 'creator-ai-frontend', 'caiFrontend', $frontend_data );
        }
    }
}

