<?php
/**
 * Course Creator Module
 *
 * @package CreatorAI
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Creator_AI_Course_Creator {

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'init', array( $this, 'register_post_type' ) );
        
        // AJAX hooks for course creation and management.
        add_action( 'wp_ajax_cai_course_create', array( $this, 'ajax_create_course' ) );
        add_action( 'wp_ajax_cai_process_course_chunk', array( $this, 'ajax_process_course_chunk' ) );
        add_action( 'wp_ajax_cai_get_all_courses', array( $this, 'ajax_get_all_courses' ) );
        add_action( 'wp_ajax_cai_course_delete', array( $this, 'ajax_delete_course' ) );
        add_action( 'wp_ajax_cai_course_get_single', array( $this, 'ajax_get_single_course' ) );
        add_action( 'wp_ajax_cai_course_update', array( $this, 'ajax_update_course' ) );
        add_action( 'wp_ajax_cai_publish_course', array( $this, 'ajax_publish_course' ) );

        // Frontend course display and interaction hooks.
        add_action( 'wp_ajax_cai_mark_section_complete', array( $this, 'ajax_mark_section_complete' ) );
        add_action( 'wp_ajax_nopriv_cai_mark_section_complete', array( $this, 'ajax_mark_section_complete' ) );
        add_action( 'wp_ajax_cai_submit_quiz', array( $this, 'ajax_submit_quiz' ) );
        add_action( 'wp_ajax_nopriv_cai_submit_quiz', array( $this, 'ajax_submit_quiz' ) );

        add_filter( 'the_content', array( $this, 'render_course_content' ) );
    }

    /**
     * Register the 'cai_course' custom post type.
     */
    public function register_post_type() {
        // Post type registration logic here...
    }
    
    /**
     * AJAX handler to start creating a course.
     */
    public function ajax_create_course() {
        check_ajax_referer( 'creator_ai_nonce', 'nonce' );
        // Initial course creation logic...
        // For now, we'll just simulate the start of the process.
        $course_id = 'course_' . uniqid();
        
        // Save initial empty data structure
        $this->save_course_file($course_id, ['id' => $course_id, 'status' => 'processing']);

        wp_send_json_success(['course_id' => $course_id, 'message' => 'Course creation started.']);
    }

    /**
     * AJAX handler for chunked course processing.
     */
    public function ajax_process_course_chunk() {
        check_ajax_referer( 'creator_ai_nonce', 'nonce' );
        // This would contain the logic for each step of course generation
        // e.g., analyzing images, generating outline, generating content for each section.
        // For now, we simulate a multi-step process.
        
        $course_id = sanitize_text_field($_POST['course_id']);
        $course_data = $this->load_course_file($course_id);

        // This is a simplified simulation
        $steps = ['outline', 'chapters', 'quiz', 'finalizing'];
        $current_step = isset($course_data['last_step']) ? array_search($course_data['last_step'], $steps) + 1 : 0;

        if ($current_step >= count($steps)) {
            $course_data['status'] = 'completed';
            $this->save_course_file($course_id, $course_data);
            wp_send_json_success(['complete' => true]);
        } else {
            $course_data['last_step'] = $steps[$current_step];
            $this->save_course_file($course_id, $course_data);
            wp_send_json_success([
                'complete' => false,
                'message' => 'Processing step: ' . $steps[$current_step],
                'percent' => ($current_step + 1) * 25
            ]);
        }
    }

    /**
     * AJAX handler to get all created courses.
     */
    public function ajax_get_all_courses() {
        check_ajax_referer( 'creator_ai_nonce', 'nonce' );
        $courses = $this->load_all_courses();
        wp_send_json_success( $courses );
    }

    /**
     * AJAX handler to get a single course's data.
     */
    public function ajax_get_single_course() {
        check_ajax_referer( 'creator_ai_nonce', 'nonce' );
        $course_id = sanitize_text_field($_POST['courseId']);
        $course_data = $this->load_course_file($course_id);
        if ($course_data) {
            wp_send_json_success($course_data);
        } else {
            wp_send_json_error('Course not found.');
        }
    }
    
    /**
     * AJAX handler to delete a course.
     */
    public function ajax_delete_course() {
        check_ajax_referer( 'creator_ai_nonce', 'nonce' );
        // Delete course logic...
        wp_send_json_success( array( 'message' => 'Course deleted.' ) );
    }

    /**
     * AJAX handler to update a course.
     */
    public function ajax_update_course() {
        check_ajax_referer( 'creator_ai_nonce', 'nonce' );
        // Update course logic...
        wp_send_json_success( array( 'message' => 'Course updated.' ) );
    }
    
    /**
     * AJAX handler to publish a course (create CPT).
     */
    public function ajax_publish_course() {
        check_ajax_referer( 'creator_ai_nonce', 'nonce' );
        // Publish course logic...
        wp_send_json_success( array( 'message' => 'Course published.' ) );
    }
    
    /**
     * AJAX handler to mark a course section as complete for a user.
     */
    public function ajax_mark_section_complete() {
        check_ajax_referer( 'creator_ai_nonce', 'nonce' );
        // Mark section complete logic...
        wp_send_json_success( array( 'message' => 'Progress saved.' ) );
    }

    /**
     * AJAX handler to submit quiz answers.
     */
    public function ajax_submit_quiz() {
        check_ajax_referer( 'creator_ai_nonce', 'nonce' );
        // Quiz submission and grading logic...
        wp_send_json_success( array( 'score' => 100, 'passed' => true ) );
    }

    /**
     * Render course content on the frontend.
     * @param string $content
     * @return string
     */
    public function render_course_content( $content ) {
        if ( is_singular( 'cai_course' ) ) {
            // Render course content logic...
            return "<h1>Course Content Will Render Here</h1>";
        }
        return $content;
    }
    
    /**
     * Helper to load all course JSON files.
     */
    private function load_all_courses() {
        $upload_dir = wp_upload_dir();
        $courses_dir = $upload_dir['basedir'] . '/creator-ai-courses';
        $courses = array();

        if (!file_exists($courses_dir)) {
            return $courses;
        }

        $files = glob($courses_dir . '/*.json');
        foreach ($files as $file) {
            $course_data = json_decode(file_get_contents($file), true);
            if ($course_data && isset($course_data['id'])) {
                $courses[] = $course_data;
            }
        }
        return $courses;
    }

    /**
     * Helper to load a single course JSON file.
     */
    private function load_course_file($course_id) {
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/creator-ai-courses/' . sanitize_file_name($course_id) . '.json';
        if (!file_exists($file_path)) {
            return false;
        }
        return json_decode(file_get_contents($file_path), true);
    }

    /**
     * Helper to save a course JSON file.
     */
    private function save_course_file($course_id, $data) {
        $upload_dir = wp_upload_dir();
        $courses_dir = $upload_dir['basedir'] . '/creator-ai-courses';
        if (!file_exists($courses_dir)) {
            wp_mkdir_p($courses_dir);
        }
        $file_path = $courses_dir . '/' . sanitize_file_name($course_id) . '.json';
        return file_put_contents($file_path, json_encode($data, JSON_PRETTY_PRINT));
    }
    
    /**
     * Get user progress for a course.
     * @param int $user_id
     * @param string $course_id
     * @return array
     */
    public static function get_user_progress($user_id, $course_id) {
        if (!$user_id) {
            return array();
        }
        return get_user_meta($user_id, 'cai_course_progress_' . $course_id, true) ?: array();
    }
}
