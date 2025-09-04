<?php
/**
 * Utility Class
 * Provides static helper functions used across the plugin.
 *
 * @package CreatorAI
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Creator_AI_Utils {

    /**
     * Sanitize an array of keywords from a comma-separated string or an array.
     *
     * @param mixed $input The input to sanitize.
     * @return array Sanitized array of keywords.
     */
    public static function sanitize_keywords_array( $input ) {
        if ( empty( $input ) ) {
            return array();
        }
        $keywords = is_array( $input ) ? $input : explode( ',', $input );
        $sanitized = array();
        foreach($keywords as $keyword) {
            $trimmed = trim($keyword);
            if(!empty($trimmed)) {
                $sanitized[] = sanitize_text_field($trimmed);
            }
        }
        return $sanitized;
    }

    /**
     * Sanitize an array of URLs.
     *
     * @param array $input The array of URLs.
     * @return array Sanitized array of valid URLs.
     */
    public static function sanitize_url_array( $input ) {
        $sanitized = array();
        if ( is_array( $input ) ) {
            foreach ( $input as $url ) {
                $clean_url = esc_url_raw( trim( $url ) );
                if ( ! empty( $clean_url ) ) {
                    $sanitized[] = $clean_url;
                }
            }
        }
        return $sanitized;
    }
    
    /**
     * Get default prompts for JS localization and settings defaults.
     *
     * @return array
     */
    public static function get_default_prompts() {
        return array(
            'yta_prompt_article_system' => "You are an expert content writer specializing in SEO. Write a well-structured SEO article based on a YouTube video transcript. The article should be at least 1500 words. Ensure to include relevant keywords and actionable steps. Write in a friendly tone from the perspective of the video creator that is easy to read.\n\nYou must start the article with a 100-150 words paragraph summarizing the video transcript with key SEO terms, then continue with the rest of the content.\n\nCRITICAL STRUCTURE REQUIREMENTS:\n- Every heading MUST be followed by at least 2-3 paragraphs of relevant content.\n- Each paragraph should be 3-4 sentences long maximum for improved readability.\n- Use a mix of H2 and H3 headings for proper hierarchy.\n\nEXTERNAL LINK REQUIREMENTS:\n- Include EXACTLY 3 external links that are highly relevant to the context.\n- Each link MUST be from a COMPLETELY DIFFERENT domain.\n- Spread out the external links throughout the article.\n- All links must open in a new tab using target=\"_blank\" rel=\"noopener noreferrer\".\n\nOUTPUT FORMAT:\nOutput everything in raw HTML code, but exclude the use of the <html>, <head>, <body> tags. Start immediately from the <p> tag. Make use of <p>, <h2>, <h3>, <ul>, <li> and <strong> tags only.",
            'yta_prompt_seo_system'     => "You are an SEO specialist. Given an article text, write a meta description between 100–160 characters that focuses on important SEO keywords.",
        );
    }

    /**
     * Sanitize course layout settings.
     * @param array $settings The raw settings from the form.
     * @return array The sanitized settings.
     */
    public static function sanitize_layout_settings($settings) {
        $sanitized = array();
        $defaults = array(
            'sidebar_layout' => 'default',
            'disable_featured_image' => false,
            'disable_title' => false,
            'content_width' => 'default'
        );
        $settings = wp_parse_args( (array) $settings, $defaults );

        $sanitized['sidebar_layout'] = in_array($settings['sidebar_layout'], ['default', 'no-sidebar', 'left-sidebar', 'right-sidebar'], true) ? $settings['sidebar_layout'] : 'default';
        $sanitized['disable_featured_image'] = (bool) $settings['disable_featured_image'];
        $sanitized['disable_title'] = (bool) $settings['disable_title'];
        $sanitized['content_width'] = in_array($settings['content_width'], ['default', 'full-width', 'contained'], true) ? $settings['content_width'] : 'default';

        return $sanitized;
    }

    /**
     * Sanitize course appearance settings.
     * @param array $settings The raw settings from the form.
     * @return array The sanitized settings.
     */
    public static function sanitize_appearance_settings($settings) {
        $sanitized = array();
        $settings = (array) $settings;
        foreach ($settings as $key => $value) {
            if (strpos($key, 'color') !== false) {
                $sanitized[$key] = sanitize_hex_color($value);
            } elseif (is_numeric($value)) {
                 $sanitized[$key] = intval($value);
            } else {
                $sanitized[$key] = sanitize_text_field($value);
            }
        }
        return $sanitized;
    }

    /**
     * Count total sections in a course from its ID by reading its JSON file.
     * @param string $course_id The unique ID of the course.
     * @return int The total number of sections.
     */
    public static function count_course_sections($course_id) {
        if (empty($course_id)) {
            return 0;
        }
        
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/creator-ai-courses/' . sanitize_file_name($course_id) . '.json';

        if (!file_exists($file_path)) {
            return 0;
        }

        $course_data = json_decode(file_get_contents($file_path), true);
        $total = 0;
        
        if (isset($course_data['chapters']) && is_array($course_data['chapters'])) {
            foreach ($course_data['chapters'] as $chapter) {
                if (isset($chapter['sections']) && is_array($chapter['sections'])) {
                    $total += count($chapter['sections']);
                }
            }
        }
        return $total;
    }
}

