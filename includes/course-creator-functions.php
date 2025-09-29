<?php

trait Creator_AI_Course_Creator_Functions {
    
    /**
     * Initialize course creator functionality
     */
    public function initialize_course_creator() {
        // Register AJAX endpoints
        add_action('wp_ajax_cai_course_create', array($this, 'create_course'));
        add_action('wp_ajax_cai_process_course_chunk', array($this, 'process_course_chunk'));
        add_action('wp_ajax_cai_course_get_all', array($this, 'get_all_courses'));
        add_action('wp_ajax_cai_course_delete', array($this, 'delete_course'));
        add_action('wp_ajax_cai_course_get_single', array($this, 'get_single_course'));
        add_action('wp_ajax_cai_course_progress', array($this, 'get_course_progress'));
        add_action('wp_ajax_cai_course_update', array($this, 'update_course')); 
        add_action('wp_ajax_cai_unpublish_course', array($this, 'unpublish_course'));
        add_action('wp_ajax_cai_get_image_url', array($this, 'ajax_get_image_url'));
        
        // Register Gutenberg block
        add_action('init', array($this, 'register_course_block'));
        
        // Create upload directory if it doesn't exist
        $this->ensure_upload_directory_exists();

    }

    /**
     * Ensure the upload directory exists
     */
    private function ensure_upload_directory_exists() {
        $upload_dir = wp_upload_dir();
        $course_dir = $upload_dir['basedir'] . '/creator-ai-courses';
        
        if (!file_exists($course_dir)) {
            wp_mkdir_p($course_dir);
            
            // Also create placeholder files
            $placeholder_path = $course_dir . '/cover-image-placeholder.jpg';
            if (!file_exists($placeholder_path)) {
                // Copy placeholder from plugin assets
                copy(plugin_dir_path(dirname(__FILE__)) . 'assets/course-thumbnail-placeholder.jpg', $placeholder_path);
            }
        }
    }

    // Add to your existing course-creator-functions.php, inside the trait

    /**
     * Create a course with chunked processing
    */
    public function create_course() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'cai_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }

        // Get form data - use wp_unslash for all text fields
        $course_title = isset($_POST['course_title']) ? sanitize_text_field(wp_unslash($_POST['course_title'])) : '';
        $course_description = isset($_POST['course_description']) ? wp_kses_post(wp_unslash($_POST['course_description'])) : '';
        $target_audience = isset($_POST['target_audience']) ? sanitize_text_field(wp_unslash($_POST['target_audience'])) : '';
        $difficulty_level = isset($_POST['difficulty_level']) ? sanitize_text_field(wp_unslash($_POST['difficulty_level'])) : 'beginner';
        $learning_objectives = isset($_POST['learning_objectives']) ? wp_kses_post(wp_unslash($_POST['learning_objectives'])) : '';
        $prerequisites = isset($_POST['prerequisites']) ? sanitize_text_field(wp_unslash($_POST['prerequisites'])) : '';
        $main_topics = isset($_POST['main_topics']) ? wp_kses_post(wp_unslash($_POST['main_topics'])) : '';
        $additional_notes = isset($_POST['additional_notes']) ? wp_kses_post(wp_unslash($_POST['additional_notes'])) : '';
        $thumbnail_url = isset($_POST['thumbnail_url']) ? esc_url_raw(wp_unslash($_POST['thumbnail_url'])) : '';
        $thumbnail_id = isset($_POST['thumbnail_id']) ? intval($_POST['thumbnail_id']) : 0;


        // Process uploaded images - WITHOUT analyzing them (we'll do that in chunks)
        $course_images = array();
        if (isset($_POST['images']) && is_array($_POST['images'])) {
            foreach ($_POST['images'] as $image) {
                if (isset($image['url']) && isset($image['filename'])) {
                    // Store the original WordPress media URL and ID
                    $image_data = array(
                        'id' => isset($image['id']) ? intval($image['id']) : 0,
                        'url' => esc_url_raw($image['url']),
                        'filename' => sanitize_file_name($image['filename']),
                        'title' => isset($image['title']) ? sanitize_text_field($image['title']) : '',
                        'description' => isset($image['description']) ? sanitize_text_field($image['description']) : '',
                        'analyzed' => false
                    );
                    
                    $course_images[] = $image_data;
                }
            }
        }

        // Validate required fields
        if (empty($course_title) || empty($course_description) || empty($target_audience) || empty($learning_objectives) || empty($main_topics)) {
            wp_send_json_error('Required fields are missing');
            return;
        }

        // Generate a unique course ID
        $course_id = 'course-' . sanitize_title($course_title) . '-' . uniqid();

        // Extract thumbnail filename
        $thumbnail_url = '';
        $thumbnail_id = 0;
        if (!empty($_POST['thumbnail_url'])) {
            $thumbnail_url = esc_url_raw(wp_unslash($_POST['thumbnail_url']));
        }
        if (!empty($_POST['thumbnail_id'])) {
            $thumbnail_id = intval($_POST['thumbnail_id']);
        }

        // Create basic course structure
        $course_data = array(
            'id' => $course_id,
            'original_title' => $course_title,
            'original_description' => $course_description,
            'original_targetAudience' => $target_audience,
            'title' => $course_title,  
            'description' => $course_description,
            'targetAudience' => $target_audience,
            'difficulty' => $difficulty_level,
            'learningObjectives' => $learning_objectives,
            'prerequisites' => $prerequisites,
            'coverImage' => $thumbnail_url, // Store the URL directly
            'coverImageId' => $thumbnail_id, // Store the attachment ID
            'createdAt' => current_time('mysql'),
            'updatedAt' => current_time('mysql'),
            'images' => $course_images,
            'mainTopics' => $main_topics,
            'additionalNotes' => $additional_notes,
            'chapters' => array(),
            'quiz' => array(),
            'estimatedTime' => '2 hours'
        );

        // Save the course data to create the initial file
        $this->save_course_file($course_id, $course_data);
        error_log("Course data saved initially");

        // Initialize progress tracking
        $this->initialize_course_progress($course_id);

        // Start the course creation process via AJAX
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/course-creator-ajax-handler.php';
        $processor = new CreatorAI_Course_Processor($course_id, $this);
        
        error_log("About to call process_next_chunk for initial setup");
        $result = $processor->process_next_chunk();
        error_log("Initial process_next_chunk result: " . json_encode($result));

        // Return success with the course ID
        wp_send_json_success(array(
            'course_id' => $course_id,
            'message' => 'Course creation started',
            'next_step' => isset($result['message']) ? $result['message'] : 'Initializing',
            'percent' => isset($result['percent']) ? $result['percent'] : 5
        ));
    }

    /**
     * Process next chunk of course creation
     */
    public function process_course_chunk() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'cai_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }

        // Get course ID
        $course_id = isset($_POST['course_id']) ? sanitize_text_field($_POST['course_id']) : '';

        if (empty($course_id)) {
            wp_send_json_error('No course ID provided');
            return;
        }

        error_log("Processing course chunk for course ID: $course_id");

        try {
            // Enable error reporting for debugging
            @ini_set('display_errors', 1);
            @error_reporting(E_ALL);
            
            // Increase memory and time limits
            @ini_set('memory_limit', '512M');
            @set_time_limit(300); // 5 minutes
            
            error_log("Memory limit: " . ini_get('memory_limit'));
            error_log("Time limit: " . ini_get('max_execution_time'));
            
            // Initialize processor with debug logging
            $handler_file = plugin_dir_path(dirname(__FILE__)) . 'includes/course-creator-ajax-handler.php';
            error_log("Loading handler file: " . $handler_file);
            
            if (!file_exists($handler_file)) {
                error_log("ERROR: Handler file does not exist: " . $handler_file);
                wp_send_json_error('Handler file not found');
                return;
            }
            
            require_once $handler_file;
            error_log("Handler file loaded successfully");
            error_log("Initializing CreatorAI_Course_Processor for course: $course_id");
            
            if (!class_exists('CreatorAI_Course_Processor')) {
                error_log("ERROR: CreatorAI_Course_Processor class not found!");
                wp_send_json_error('Course processor class not found');
                return;
            }
            
            $processor = new CreatorAI_Course_Processor($course_id, $this);
            error_log("Course processor initialized successfully");
            
            // Process next chunk with debugging
            error_log("Calling process_next_chunk method");
            
            if (!method_exists($processor, 'process_next_chunk')) {
                error_log("ERROR: process_next_chunk method not found!");
                wp_send_json_error('Process method not found');
                return;
            }
            
            $result = $processor->process_next_chunk();
            
            if ($result === null) {
                error_log("ERROR: process_next_chunk returned null");
                wp_send_json_error('Process returned null result');
                return;
            }
            
            error_log("Chunk processing result: " . json_encode($result));
            
            // Check if successful
            if (!isset($result['success']) || $result['success'] === false) {
                error_log("Error in chunk processing: " . (isset($result['message']) ? $result['message'] : 'Unknown error'));
                wp_send_json_error($result['message'] ?? 'Error processing chunk');
                return;
            }
            
            // Return progress
            if (isset($result['complete']) && $result['complete']) {
                error_log("Course creation completed for ID: $course_id");
                wp_send_json_success(array(
                    'course_id' => $course_id,
                    'message' => 'Course creation completed',
                    'complete' => true,
                    'percent' => 100
                ));
            } else {
                wp_send_json_success(array(
                    'course_id' => $course_id,
                    'message' => $result['message'] ?? 'Processing course chunk',
                    'complete' => false,
                    'percent' => $result['percent'] ?? 0
                ));
            }
        } catch (Exception $e) {
            error_log("Exception in process_course_chunk: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
            wp_send_json_error('Exception: ' . $e->getMessage());
        } catch (Error $e) {
            error_log("Fatal Error in process_course_chunk: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
            wp_send_json_error('Fatal Error: ' . $e->getMessage());
        } catch (Throwable $e) {
            error_log("Throwable in process_course_chunk: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
            wp_send_json_error('Critical Error: ' . $e->getMessage());
        }
    }


    /**
     * Update an existing course
     */
    public function update_course() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'cai_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }

        // Get course ID
        $course_id = isset($_POST['course_id']) ? sanitize_text_field(wp_unslash($_POST['course_id'])) : '';

        if (empty($course_id)) {
            wp_send_json_error('Course ID is required');
            return;
        }

        // Get the existing course data
        $course_data = $this->load_course_file($course_id);

        if (!$course_data) {
            wp_send_json_error('Course not found');
            return;
        }

        // Update basic course info
        if (isset($_POST['title'])) {
            $course_data['title'] = sanitize_text_field(wp_unslash($_POST['title']));
        }

        if (isset($_POST['description'])) {
            $course_data['description'] = wp_kses_post(wp_unslash($_POST['description']));
        }

        if (isset($_POST['targetAudience'])) {
            $course_data['targetAudience'] = sanitize_text_field(wp_unslash($_POST['targetAudience']));
        }

        if (isset($_POST['difficulty'])) {
            $course_data['difficulty'] = sanitize_text_field(wp_unslash($_POST['difficulty']));
        }

        if (isset($_POST['coverImage'])) {
            $course_data['coverImage'] = sanitize_text_field(wp_unslash($_POST['coverImage']));
        }

        // Update chapters
        if (isset($_POST['chapters']) && is_array($_POST['chapters'])) {
            $chapters = array();
            
            foreach ($_POST['chapters'] as $chapter_data) {
                $chapter = array(
                    'title' => isset($chapter_data['title']) ? sanitize_text_field(wp_unslash($chapter_data['title'])) : '',
                    'introduction' => isset($chapter_data['introduction']) ? wp_kses_post(wp_unslash($chapter_data['introduction'])) : '',
                    'sections' => array()
                );
                
                // Add sections
                if (isset($chapter_data['sections']) && is_array($chapter_data['sections'])) {
                    foreach ($chapter_data['sections'] as $section_data) {
                        $section = array(
                            'title' => isset($section_data['title']) ? sanitize_text_field(wp_unslash($section_data['title'])) : '',
                            'content' => isset($section_data['content']) ? wp_kses_post(wp_unslash($section_data['content'])) : '',
                            'image' => isset($section_data['image']) ? sanitize_text_field(wp_unslash($section_data['image'])) : ''
                        );
                        
                        $chapter['sections'][] = $section;
                    }
                }
                
                $chapters[] = $chapter;
            }
            
            $course_data['chapters'] = $chapters;
        }

        // Update quiz
        if (isset($_POST['quiz']) && is_array($_POST['quiz'])) {
            $quiz_data = $_POST['quiz'];
            
            $quiz = array(
                'title' => 'Final Assessment',
                'description' => isset($quiz_data['description']) ? sanitize_text_field(wp_unslash($quiz_data['description'])) : 'Test your knowledge',
                'questions' => array()
            );
            
            // Add questions
            if (isset($quiz_data['questions']) && is_array($quiz_data['questions'])) {
                foreach ($quiz_data['questions'] as $question_data) {
                    $question = array(
                        'question' => isset($question_data['question']) ? sanitize_text_field(wp_unslash($question_data['question'])) : '',
                        'type' => isset($question_data['type']) ? sanitize_text_field(wp_unslash($question_data['type'])) : 'multiple-choice',
                        'correctAnswer' => isset($question_data['correctAnswer']) ? intval($question_data['correctAnswer']) : 0
                    );
                    
                    // Add options based on type
                    if ($question['type'] === 'multiple-choice' && isset($question_data['options']) && is_array($question_data['options'])) {
                        $options = array();
                        $image_options = array();
                        
                        foreach ($question_data['options'] as $option) {
                            $options[] = sanitize_text_field(wp_unslash($option));
                        }
                        
                        // Handle image options for multiple choice
                        if (isset($question_data['image_options']) && is_array($question_data['image_options'])) {
                            foreach ($question_data['image_options'] as $image_option) {
                                $image_options[] = sanitize_text_field(wp_unslash($image_option));
                            }
                        }
                        
                        $question['options'] = $options;
                        if (!empty($image_options)) {
                            $question['image_options'] = $image_options;
                        }
                    } else if ($question['type'] === 'image-choice' && isset($question_data['image_options']) && is_array($question_data['image_options'])) {
                        $image_options = array();
                        
                        foreach ($question_data['image_options'] as $image_option) {
                            $image_options[] = sanitize_text_field(wp_unslash($image_option));
                        }
                        
                        $question['image_options'] = $image_options;
                    }
                    
                    $quiz['questions'][] = $question;
                }
            }
            
            $course_data['quiz'] = $quiz;
        }

        // Update timestamp
        $course_data['updatedAt'] = current_time('mysql');

        // Save the updated course
     $saved = $this->save_course_file($course_id, $course_data);

        // NEW CODE: Check if course is published and update post meta
        $existing_post_id = $this->get_course_post_id($course_id);
        if ($existing_post_id) {
            // Update the post with new data
            $this->update_course_post($existing_post_id, $course_data);
        }

        if ($saved) {
            wp_send_json_success(array(
                'message' => 'Course updated successfully',
                'course_id' => $course_id,
                'is_published' => !empty($existing_post_id),
                'post_id' => $existing_post_id,
                'permalink' => $existing_post_id ? get_permalink($existing_post_id) : ''
            ));
        } else {
            wp_send_json_error('Failed to save course data');
        }
    }

    /**
     * Get all available courses
     */
    public function get_all_courses() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'cai_nonce')) {
            wp_send_json_error('Security check failed');
        }

        $courses = $this->load_all_courses();
        
        // Add courses page info
        $courses_page_id = get_option('cai_courses_page_id', 0);
        $courses_page_set = !empty($courses_page_id) && get_post_status($courses_page_id) === 'publish';
        
        // Add published status to courses
        if (is_array($courses)) {
            foreach ($courses as &$course) {
                // Check if course has a published post
                $post_id = $this->get_course_post_id($course['id']);
                if ($post_id) {
                    $course['is_published'] = true;
                    $course['post_id'] = $post_id;
                    $course['permalink'] = get_permalink($post_id);
                } else {
                    $course['is_published'] = false;
                }
            }
        }
        
        // Return both courses and page setting
        wp_send_json_success(array(
            'courses' => $courses,
            'courses_page_set' => $courses_page_set
        ));
    }

    /**
     * Get a single course by ID
     */
    public function get_single_course() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'cai_nonce')) {
            wp_send_json_error('Security check failed');
        }

        // Get course ID
        $course_id = isset($_POST['courseId']) ? sanitize_text_field($_POST['courseId']) : '';

        if (empty($course_id)) {
            wp_send_json_error('No course ID provided');
        }

        $course_data = $this->load_course_file($course_id);

        if (!$course_data) {
            wp_send_json_error('Course not found');
        }

        wp_send_json_success($course_data);
    }

    /**
     * Delete a course
     */
    public function delete_course() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'cai_nonce')) {
            wp_send_json_error('Security check failed');
        }

        // Get course ID
        $course_id = isset($_POST['courseId']) ? sanitize_text_field($_POST['courseId']) : '';

        if (empty($course_id)) {
            wp_send_json_error('No course ID provided');
        }

        // Get file path
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/creator-ai-courses/' . $course_id . '.json';

        // Check if file exists
        if (!file_exists($file_path)) {
            wp_send_json_error('Course not found');
        }

        // Delete the file
        if (!unlink($file_path)) {
            wp_send_json_error('Failed to delete course');
        }

        // Also delete progress tracking
        delete_transient('cai_course_progress_' . $course_id);

        wp_send_json_success(array('message' => 'Course deleted successfully'));
    }

    /**
     * Handle image analysis request
     */
    public function analyze_image() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'cai_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }

        // Get image path
        $image_path = isset($_POST['image_path']) ? sanitize_text_field($_POST['image_path']) : '';

        if (empty($image_path)) {
            wp_send_json_error('No image provided');
            return;
        }

        // Analyze image content
        $analysis = $this->analyze_image_content($image_path);

        if ($analysis) {
            wp_send_json_success(array('analysis' => $analysis));
        } else {
            wp_send_json_error('Failed to analyze image');
        }
    }

    /**
     * Save an image to the course directory with SEO-friendly filename
     */
    private function save_course_image($image_url, $filename) {
        // If the image is already a full URL (not just a filename), return it
        if (filter_var($filename, FILTER_VALIDATE_URL)) {
            return $filename;
        }
        
        // If image_url is a full WordPress media URL, just return it
        if (filter_var($image_url, FILTER_VALIDATE_URL)) {
            return $image_url;
        }
        
        // If filename has path components, return just the filename
        return basename($filename);
    }

    /**
     * Generate SEO-friendly filename from original filename and description
     * @param string $original_filename Original filename
     * @param string $description Description to use for filename
     * @return string SEO-friendly filename
     */
    private function generate_seo_filename($original_filename, $description = '') {
        // Get file extension
        $extension = pathinfo($original_filename, PATHINFO_EXTENSION);
        
        // Create base filename
        $base_name = '';
        
        // If we have a description, use it for the filename
        if (!empty($description)) {
            $base_name = $description;
        } else {
            // Try to extract meaningful words from original filename
            $file_base = pathinfo($original_filename, PATHINFO_FILENAME);
            
            // Remove date patterns (common in camera-generated filenames)
            $file_base = preg_replace('/^(\d{8}_\d{6})|(\d{14})/', '', $file_base);
            
            // If filename is now empty, use a generic name based on timestamp
            if (empty(trim($file_base))) {
                $image_type = $this->detect_image_content($original_filename);
                $base_name = !empty($image_type) ? $image_type : 'image';
            } else {
                $base_name = $file_base;
            }
        }
        
        // Clean up the base name for URL usage
        $base_name = sanitize_title($base_name); // Convert to lowercase, replace spaces with hyphens
        $base_name = preg_replace('/[^a-z0-9\-]/', '', $base_name); // Remove special characters
        $base_name = preg_replace('/-+/', '-', $base_name); // Replace multiple hyphens with a single one
        $base_name = trim($base_name, '-'); // Trim hyphens from beginning and end
        
        // If base name is too short, add descriptive prefix
        if (strlen($base_name) < 3) {
            $base_name = 'photo-' . time();
        }
        
        // Add random suffix to ensure uniqueness
        $suffix = substr(md5(time() . rand(1000, 9999)), 0, 5);
        $seo_filename = $base_name . '-' . $suffix . '.' . $extension;
        
        return $seo_filename;
    }

    /**
     * Try to detect the content of an image based on analysis or filename
     * @param string $filename Original filename
     * @return string Image content type description
     */
    private function detect_image_content($filename) {
        // Common patterns in filenames that might indicate content
        $patterns = [
            '/portrait|selfie|face/i' => 'portrait',
            '/landscape|scene|vista/i' => 'landscape',
            '/dog|puppy|canine/i' => 'dog',
            '/cat|kitten|feline/i' => 'cat',
            '/food|meal|dish/i' => 'food',
            '/building|architecture/i' => 'building',
            '/beach|ocean|sea/i' => 'beach',
            '/mountain|hill/i' => 'mountain',
            '/flower|plant/i' => 'flower',
            '/sunset|sunrise/i' => 'sunset',
            '/bird|animal|wildlife/i' => 'wildlife',
        ];
        
        foreach ($patterns as $pattern => $type) {
            if (preg_match($pattern, $filename)) {
                return $type;
            }
        }
        
        // Default to generic "photography" if no pattern matches
        return 'photography';
    }

    /**
     * Generate SEO-friendly alt text based on image analysis and context
     * @param array $image_info Image information including analysis
     * @param string $section_title The title of the section where the image appears
     * @param string $course_title The title of the course
     * @return string SEO-friendly alt text
     */
    private function generate_seo_alt_text($image_info, $section_title = '', $course_title = '') {
        // Start with a basic description
        $alt_text = '';
        
        // If we have image analysis, extract key concepts (first 100 chars)
        if (!empty($image_info['analysis'])) {
            // Extract first sentence or part of the analysis
            $analysis_text = is_array($image_info['analysis']) ? json_encode($image_info['analysis']) : (string)$image_info['analysis'];
            $first_sentence = preg_match('/^([^\.!?]+[\.!?])/', $analysis_text, $matches) ? $matches[1] : substr($analysis_text, 0, 100);
            
            // Clean up and format
            $alt_text = trim(strip_tags($first_sentence));
            $alt_text = preg_replace('/\s+/', ' ', $alt_text); // Replace multiple spaces with single space
        } 
        // If we have a description, use that
        else if (!empty($image_info['description'])) {
            $description_text = is_array($image_info['description']) ? json_encode($image_info['description']) : (string)$image_info['description'];
            $alt_text = $description_text;
        }
        // Otherwise build from context
        else {
            // Try to determine content from filename
            $image_type = $this->detect_image_content($image_info['filename']);
            
            // Build alt text from context
            if (!empty($section_title)) {
                if (!empty($course_title)) {
                    $alt_text = $image_type . ' - ' . $section_title . ' - ' . $course_title;
                } else {
                    $alt_text = $image_type . ' illustrating ' . $section_title;
                }
            } else {
                $alt_text = $image_type . ' photography';
            }
        }
        
        // Ensure alt text isn't too long (125 chars is a good limit)
        if (strlen($alt_text) > 125) {
            $alt_text = substr($alt_text, 0, 122) . '...';
        }
        
        return $alt_text;
    }

    /**
     * Process section content to ensure images have full URLs and proper HTML structure
     * @param string $content The original section content
     * @param array $image_info Image information
     * @return string Updated content with fixed image URLs
     */
    private function process_section_content($content, $image_info = null) {
        // Bail early if no content
        if (empty($content)) {
            return $content;
        }
        
        // Replace any relative image paths with full URLs
        if ($image_info && !empty($image_info['id'])) {
            $image_url = wp_get_attachment_url($image_info['id']);
            if ($image_url) {
                // Replace both filename and attachment ID references
                if (!empty($image_info['filename'])) {
                    $content = str_replace('src="' . $image_info['filename'] . '"', 'src="' . esc_url($image_url) . '"', $content);
                }
                $content = str_replace('src="' . $image_info['id'] . '"', 'src="' . esc_url($image_url) . '"', $content);
            }
        }
        
        // Also look for any other images that might be in the content using attachment IDs
        $content = preg_replace_callback('/<img\s+src=([\'"])((?!https?:\/\/)[^\'"]+)([\'"])/i', function($matches) {
            $image_ref = $matches[2];
            // If it's numeric, treat as attachment ID
            if (is_numeric($image_ref)) {
                $image_url = wp_get_attachment_url($image_ref);
                if ($image_url) {
                    return '<img src=' . $matches[1] . $image_url . $matches[3];
                }
            }
            // Otherwise try as filename lookup (legacy)
            $image_url = $this->get_course_image_url($image_ref);
            return '<img src=' . $matches[1] . $image_url . $matches[3];
        }, $content);
        
        return $content;
    }

    /**
     * Analyze image content using OpenAI Vision API
     */
    public static function analyze_image_content($image_path) {
        // Get API key
        $api_key = Creator_AI::get_credential('cai_openai_api_key');
        
        // Check if API key exists
        if (empty($api_key) || !file_exists($image_path)) {
            return 'Image analysis unavailable';
        }

        // Encode image to base64
        $image_data = file_get_contents($image_path);
        if ($image_data === false) {
            return 'Unable to read image file';
        }
        
        $base64_image = base64_encode($image_data);

        // Prepare the API request with improved educational focus
        $url = 'https://api.openai.com/v1/chat/completions';
        $data = array(
            'model' => 'gpt-4o', // Use GPT-4o with vision capabilities
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => 'You are an expert educational content analyzer. Your task is to provide a detailed, educational analysis of images for course content. Focus on identifying subjects, concepts, techniques, and educational value that could be taught using this image. Your analysis will be used to generate course content that incorporates this image as a teaching tool.'
                ),
                array(
                    'role' => 'user',
                    'content' => array(
                        array(
                            'type' => 'text',
                            'text' => 'Analyze this image for educational course content creation. Provide a detailed analysis in JSON format with the following structure:

{
  "educational_analysis": "What specific concepts or skills could be taught using this image? What educational value does it provide? How would you incorporate this image into a lesson? (150-200 words)",
  "visual_elements": "What are the key visual elements, techniques, or characteristics visible in this image? (50-100 words)",
  "concepts_represented": ["concept1", "concept2", "concept3"],
  "quiz_potential": {
    "suitable_for_quiz": true/false,
    "quiz_concepts": ["testable concept 1", "testable concept 2"],
    "distinguishing_features": "What specific visual features make this image distinguishable from others for quiz purposes? (50 words)",
    "comparison_points": "What aspects of this image could be compared against other images in a meaningful educational test? (50 words)"
  }
}

Be thorough in assessing whether this image would work well in a quiz context - only mark suitable_for_quiz as true if the image has clear, teachable visual elements that could be meaningfully tested against other images.'
                        ),
                        array(
                            'type' => 'image_url',
                            'image_url' => array(
                                'url' => 'data:image/jpeg;base64,' . $base64_image
                            )
                        )
                    )
                )
            ),
            'max_tokens' => 500
        );

        // Make API request
        $response = cai_remote_post($url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key
            ),
            'body' => json_encode($data),
            'timeout' => 30
        ));

        // Check for errors
        if (is_wp_error($response)) {
            return 'Error analyzing image: ' . $response->get_error_message();
        }

        // Parse response
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        if (isset($result['choices'][0]['message']['content'])) {
            $content = $result['choices'][0]['message']['content'];
            
            // Try to parse as JSON first (new format)
            $parsed_json = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($parsed_json['educational_analysis'])) {
                // Return structured analysis
                return $parsed_json;
            } else {
                // Fallback: return as legacy text format for backward compatibility
                return array(
                    'educational_analysis' => $content,
                    'visual_elements' => '',
                    'concepts_represented' => array(),
                    'quiz_potential' => array(
                        'suitable_for_quiz' => false,
                        'quiz_concepts' => array(),
                        'distinguishing_features' => '',
                        'comparison_points' => ''
                    )
                );
            }
        } else {
            return array(
                'educational_analysis' => 'Could not analyze image',
                'visual_elements' => '',
                'concepts_represented' => array(),
                'quiz_potential' => array(
                    'suitable_for_quiz' => false,
                    'quiz_concepts' => array(),
                    'distinguishing_features' => '',
                    'comparison_points' => ''
                )
            );
        }
    }



    /**
     * Generate a course outline based on provided data
     * Updated to include better image quiz questions
     */
    public static function generate_course_outline($course_data) {
        error_log("=== Starting generate_course_outline static method ===");
        
        // Wrap entire method in try-catch to prevent fatal errors
        try {
        
        // Get API key and model
        $api_key = Creator_AI::get_credential('cai_openai_api_key');
        $model = get_option('cai_openai_model', 'gpt-4o');
        
        error_log("API key exists: " . (!empty($api_key) ? 'Yes' : 'No'));
        error_log("Using model: " . $model);
        
        // Validate course_data
        if (!is_array($course_data)) {
            error_log("ERROR: course_data is not an array: " . gettype($course_data));
            return false;
        }
        
        if (!isset($course_data['mainTopics'])) {
            error_log("ERROR: mainTopics not found in course_data. Available keys: " . implode(', ', array_keys($course_data)));
            return false;
        }
        
        // Extract topics from main_topics field
        $topics = explode("\n", $course_data['mainTopics']);
        $topics = array_map('trim', $topics);
        $topics = array_filter($topics);
        
        error_log("Extracted topics: " . implode(', ', $topics));
        
        // Make sure we have actual image data to work with
        if (empty($course_data['images'])) {
            $course_data['images'] = array();
        }
        
        // Create array of image data for the AI to work with
        $image_data_for_ai = array();
        $quiz_suitable_images = array();
        
        foreach ($course_data['images'] as $image) {
            if (!empty($image['analysis'])) {
                // Handle both old (string) and new (structured) analysis formats
                if (is_array($image['analysis'])) {
                    $analysis_text = $image['analysis']['educational_analysis'];
                    $visual_elements = $image['analysis']['visual_elements'];
                    $concepts = isset($image['analysis']['concepts_represented']) ? $image['analysis']['concepts_represented'] : array();
                    $quiz_potential = isset($image['analysis']['quiz_potential']) ? $image['analysis']['quiz_potential'] : array();
                } else {
                    // Legacy text format
                    $analysis_text = $image['analysis'];
                    $visual_elements = '';
                    $concepts = array();
                    $quiz_potential = array('suitable_for_quiz' => false);
                }
                
                $image_data = array(
                    'id' => isset($image['id']) ? $image['id'] : 0,
                    'filename' => $image['filename'],
                    'description' => !empty($image['description']) ? $image['description'] : '',
                    'analysis' => $analysis_text,
                    'visual_elements' => $visual_elements,
                    'concepts' => $concepts,
                    'quiz_potential' => $quiz_potential
                );
                
                $image_data_for_ai[] = $image_data;
                
                // Track images that are suitable for quizzes
                if (isset($quiz_potential['suitable_for_quiz']) && $quiz_potential['suitable_for_quiz']) {
                    $quiz_suitable_images[] = $image_data;
                }
            }
        }
        
        // Prepare the system message with better image integration instructions
        $system_message = "You are an expert educational curriculum designer creating a course outline. Your task is to create a logical structure based on the provided information, with a special focus on integrating educational images throughout the course content. The uploaded images should be incorporated into the course text as teaching tools, not just as decorative headers.";
        
        // Prepare the user message
        $user_message = "Create a detailed educational course outline based on this information:\n\n";
        $user_message .= "COURSE TITLE: " . $course_data['title'] . "\n\n";
        $user_message .= "DESCRIPTION: " . $course_data['description'] . "\n\n";
        $user_message .= "TARGET AUDIENCE: " . $course_data['targetAudience'] . "\n\n";
        $user_message .= "DIFFICULTY LEVEL: " . $course_data['difficulty'] . "\n\n";
        $user_message .= "LEARNING OBJECTIVES:\n" . $course_data['learningObjectives'] . "\n\n";
        
        if (!empty($course_data['prerequisites'])) {
            $user_message .= "PREREQUISITES: " . $course_data['prerequisites'] . "\n\n";
        }
        
        $user_message .= "MAIN TOPICS:\n";
        foreach ($topics as $topic) {
            $user_message .= "- " . $topic . "\n";
        }
        $user_message .= "\n";
        
        // Add available images with clearer integration instructions
        if (!empty($image_data_for_ai)) {
            $user_message .= "EDUCATIONAL IMAGES TO INTEGRATE IN CONTENT:\n";
            foreach ($image_data_for_ai as $index => $image) {
                $user_message .= $index + 1 . ". FILENAME: " . $image['filename'] . "\n";
                if (!empty($image['description'])) {
                    $user_message .= "   DESCRIPTION: " . $image['description'] . "\n";
                }
                $user_message .= "   EDUCATIONAL ANALYSIS: " . $image['analysis'] . "\n\n";
            }
            
            $user_message .= "IMAGE INTEGRATION INSTRUCTIONS:\n";
            $image_count = count($image_data_for_ai);
            $user_message .= "1. YOU MUST USE ALL {$image_count} IMAGES PROVIDED - every single image must be integrated into the course content\n";
            $user_message .= "2. Each image should be used exactly once where it best illustrates concepts being taught\n";
            $user_message .= "3. Create enough sections across chapters to accommodate all images\n";
            $user_message .= "4. Use the educational analysis provided for each image to determine the best placement\n";
            $user_message .= "5. Use 'imageReference' placeholder to describe how an image should be discussed within the content\n";
            $user_message .= "6. Do NOT use images as section headers\n";
            $user_message .= "7. The course structure should be designed to naturally incorporate every image as a teaching tool\n\n";
            
            // Add information about quiz-suitable images
            if (!empty($quiz_suitable_images)) {
                $user_message .= "IMAGES SUITABLE FOR QUIZ QUESTIONS:\n";
                $user_message .= "The following images have been analyzed and found suitable for educational quiz questions:\n\n";
                
                foreach ($quiz_suitable_images as $index => $image) {
                    $user_message .= ($index + 1) . ". FILENAME: " . $image['filename'] . "\n";
                    if (!empty($image['quiz_potential']['quiz_concepts'])) {
                        $user_message .= "   TESTABLE CONCEPTS: " . implode(', ', $image['quiz_potential']['quiz_concepts']) . "\n";
                    }
                    if (!empty($image['quiz_potential']['distinguishing_features'])) {
                        $user_message .= "   DISTINGUISHING FEATURES: " . $image['quiz_potential']['distinguishing_features'] . "\n";
                    }
                    if (!empty($image['quiz_potential']['comparison_points'])) {
                        $user_message .= "   COMPARISON POINTS: " . $image['quiz_potential']['comparison_points'] . "\n";
                    }
                    $user_message .= "\n";
                }
                
                $user_message .= "SMART QUIZ IMAGE GUIDELINES:\n";
                $user_message .= "- ONLY use images from the 'SUITABLE FOR QUIZ' list above for image-choice questions\n";
                $user_message .= "- You may use 2-4 images per question (don't feel obligated to use all suitable images)\n";
                $user_message .= "- Select images that have meaningful visual differences for the concept being tested\n";
                $user_message .= "- Ensure the question tests understanding of visual concepts, not just recognition\n";
                $user_message .= "- If no images are suitable for meaningful quiz questions, create text-based questions only\n\n";
            } else {
                $user_message .= "QUIZ IMAGE GUIDANCE:\n";
                $user_message .= "- No images have been identified as suitable for educational quiz questions\n";
                $user_message .= "- Focus on creating high-quality text-based quiz questions instead\n";
                $user_message .= "- Do NOT create image-choice questions unless images have clear educational differences\n\n";
            }
            
            // Add specific instruction about using ALL images IN CONTENT (not necessarily quizzes)
            $user_message .= "IMPORTANT REQUIREMENTS:\n";
            $user_message .= "1. You MUST use ALL provided images in the course CONTENT sections (not necessarily in quizzes).\n";
            $user_message .= "2. Each image should be assigned to a section where it best supports learning.\n";
            $user_message .= "3. Use the exact FILENAME in the 'image' field to reference each image.\n";
            $user_message .= "4. If multiple images are related, use them in the same section for comprehensive learning.\n\n";

        }
        
        if (!empty($course_data['additionalNotes'])) {
            $user_message .= "ADDITIONAL NOTES: " . $course_data['additionalNotes'] . "\n\n";
        }
        
        $user_message .= "Create a course with 4-8 chapters and 2-4 sections per chapter. Include a quiz with at least 10 questions.\n\n";
        
        $user_message .= "SMART QUIZ CREATION GUIDELINES:\n";
        $user_message .= "1. CREATE IMAGE-CHOICE QUESTIONS ONLY if you have images marked as 'SUITABLE FOR QUIZ' above\n";
        $user_message .= "2. For image-choice questions, focus on testing UNDERSTANDING, not just recognition:\n";
        $user_message .= "   - Test ability to distinguish between different techniques/concepts\n";
        $user_message .= "   - Test understanding of visual principles and their applications\n";
        $user_message .= "   - Test ability to identify correct examples of taught concepts\n";
        $user_message .= "3. Example GOOD image-choice questions (when images support them):\n";
        $user_message .= "   - 'Which image demonstrates proper use of [specific technique taught]?'\n";
        $user_message .= "   - 'Select the image that shows the [concept] principle in action'\n";
        $user_message .= "   - 'Which image best exemplifies the [rule/guideline] discussed in the course?'\n";
        $user_message .= "4. AVOID generic questions like 'What does this image show?' or 'Which image do you prefer?'\n";
        $user_message .= "5. If images don't have clear educational differences, create excellent text-based questions instead\n";
        $user_message .= "6. Use 2-4 images per image-choice question - don't force all suitable images into one question\n";
        $user_message .= "7. Ensure each image-choice question has ONE clearly correct answer based on course content\n\n";
        
        $user_message .= "Return a JSON object with this exact structure:\n";
        $user_message .= "{\n";
        $user_message .= '  "chapters": [\n';
        $user_message .= "    {\n";
        $user_message .= '      "title": "Chapter title",\n';
        $user_message .= '      "introduction": "Brief intro text",\n';
        $user_message .= '      "sections": [\n';
        $user_message .= "        {\n";
        $user_message .= '          "title": "Section title",\n';
        $user_message .= '          "content": "Brief content description",\n';
        $user_message .= '          "imageReference": "How this section should discuss the image (if applicable)",\n';
        $user_message .= '          "image": "filename.jpg"\n';
        $user_message .= "        }\n";
        $user_message .= "      ]\n";
        $user_message .= "    }\n";
        $user_message .= "  ],\n";
        $user_message .= '  "quiz": {\n';
        $user_message .= '    "description": "Quiz description",\n';
        $user_message .= '    "questions": [\n';
        $user_message .= "      {\n";
        $user_message .= '        "question": "Question text",\n';
        $user_message .= '        "type": "multiple-choice",\n';
        $user_message .= '        "options": ["Option 1", "Option 2", "Option 3", "Option 4"],\n';
        $user_message .= '        "correctAnswer": 0\n';
        $user_message .= "      },\n";
        $user_message .= "      {\n";
        $user_message .= '        "question": "Which image best illustrates the concept of X?",\n';
        $user_message .= '        "type": "image-choice",\n';
        $user_message .= '        "image_options": ["image1.jpg", "image2.jpg", "image3.jpg", "image4.jpg"],\n';
        $user_message .= '        "correctAnswer": 0\n';
        $user_message .= "      }\n";
        $user_message .= "    ]\n";
        $user_message .= "  }\n";
        $user_message .= "}\n\n";
        
        // Add validation reminder for using all images
        if (!empty($image_data_for_ai)) {
            $image_count = count($image_data_for_ai);
            $user_message .= "\nVALIDATION REQUIREMENT:\n";
            $user_message .= "Before finalizing your response, ensure that ALL {$image_count} images have been used exactly once across all sections.\n";
            $user_message .= "Each section should specify exactly one image filename in the 'image' field.\n";
            $user_message .= "If you cannot fit all images naturally, create additional sections or expand existing chapters.\n\n";
        }
        
        $user_message .= "IMPORTANT: Return ONLY the JSON object, with NO explanations or markdown formatting.";
        
        // Make the API request
        $messages = array(
            array('role' => 'system', 'content' => $system_message),
            array('role' => 'user', 'content' => $user_message)
        );
        
        $data = array(
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.7,
            'max_tokens' => 4000
        );
        
        try {
            error_log("Making OpenAI request for course outline");
            
            if (empty($api_key)) {
                error_log("API key is missing, using fallback outline");
                return self::generate_static_fallback_outline($course_data, $topics, $image_data_for_ai);
            }
            
            $response = Creator_AI::openai_request($data, $api_key, 120);
            
            if (!$response || !isset($response['choices'][0]['message']['content'])) {
                error_log("Failed to get valid OpenAI response for course outline");
                return self::generate_static_fallback_outline($course_data, $topics, $image_data_for_ai);
            }
            
            error_log("OpenAI response received successfully");
            
            $content = $response['choices'][0]['message']['content'];
            
            // Clean up any code blocks or extra formatting
            $content = preg_replace('/```(?:json)?\s*(.*?)\s*```/s', '$1', $content);
            $content = trim($content);
            
            // Parse JSON
            $outline = json_decode($content, true);
            
            if (json_last_error() !== JSON_ERROR_NONE || !isset($outline['chapters'])) {
                error_log("JSON parsing error for outline: " . json_last_error_msg());
                return self::generate_static_fallback_outline($course_data, $topics, $image_data_for_ai);
            }
            
            // Validate and ensure all images are used
            if (!empty($image_data_for_ai)) {
                try {
                    $outline = self::ensure_all_images_used_static($outline, $image_data_for_ai);
                } catch (Exception $e) {
                    error_log('Error in ensure_all_images_used_static: ' . $e->getMessage());
                    // Continue with original outline
                }
            }
            
            // Additionally validate and fix any problematic image quiz questions
            if (isset($outline['quiz']) && isset($outline['quiz']['questions'])) {
                foreach ($outline['quiz']['questions'] as $key => $question) {
                    if ($question['type'] === 'image-choice') {
                        // Check if the question contains problematic phrasing
                        $problematic_phrases = [
                            'what does this image show',
                            'what is shown in this image',
                            'what does the image display',
                            'what is in this image',
                            'identify this image',
                            'recognize this image'
                        ];
                        
                        $lower_question = strtolower($question['question']);
                        $needs_fixing = false;
                        
                        foreach ($problematic_phrases as $phrase) {
                            if (strpos($lower_question, $phrase) !== false) {
                                $needs_fixing = true;
                                break;
                            }
                        }
                        
                        // Fix problematic questions
                        if ($needs_fixing) {
                            // Replace with a better formulation
                            $outline['quiz']['questions'][$key]['question'] = 'Which image best illustrates the concepts covered in this course?';
                        }
                    }
                }
            }
            
            // Calculate estimated time
            $outline['estimatedTime'] = self::calculate_estimated_time_static($outline);
            
            error_log("Successfully generated course outline for course ID: " . $course_data['id']);
            
            // Save processed outline to course data
            $course_data['chapters'] = $outline['chapters'];
            $course_data['quiz'] = $outline['quiz'];
            $course_data['estimatedTime'] = $outline['estimatedTime'];
            // Note: Course data will be saved by the calling processor, not here in static context
            
            return $outline;
            
        } catch (Exception $e) {
            error_log("Exception generating outline: " . $e->getMessage());
            return self::generate_static_fallback_outline($course_data, $topics, $image_data_for_ai);
        }
        
        } catch (Exception $outer_e) {
            error_log("FATAL ERROR in generate_course_outline: " . $outer_e->getMessage() . "\nTrace: " . $outer_e->getTraceAsString());
            
            // Return basic fallback outline
            return array(
                'chapters' => array(
                    array(
                        'title' => 'Chapter 1: Introduction',
                        'introduction' => 'Introduction to the topic',
                        'sections' => array(
                            array(
                                'title' => 'Getting Started',
                                'content' => 'Basic overview of the concepts',
                                'image' => ''
                            )
                        )
                    )
                ),
                'quiz' => array(
                    'title' => 'Course Quiz',
                    'description' => 'Test your knowledge',
                    'questions' => array(
                        array(
                            'question' => 'What did you learn in this course?',
                            'type' => 'multiple-choice',
                            'options' => array('A lot', 'Some things', 'Basic concepts', 'Everything'),
                            'correctAnswer' => 0
                        )
                    )
                ),
                'estimatedTime' => '30 minutes'
            );
        }
    }

    /**
     * Ensure all images are used in the course outline
     */
    protected function ensure_all_images_used($outline, $image_data) {
        error_log("Validating that all " . count($image_data) . " images are used in the outline");
        
        // Get list of all image filenames
        $all_images = array();
        foreach ($image_data as $image) {
            $all_images[] = $image['filename'];
        }
        
        // Get list of used images from the outline
        $used_images = array();
        foreach ($outline['chapters'] as $chapter) {
            if (isset($chapter['sections'])) {
                foreach ($chapter['sections'] as $section) {
                    if (!empty($section['image'])) {
                        $used_images[] = $section['image'];
                    }
                }
            }
        }
        
        // Find unused images
        $unused_images = array_diff($all_images, $used_images);
        
        if (!empty($unused_images)) {
            error_log("Found " . count($unused_images) . " unused images. Adding them to sections...");
            
            // Add unused images to existing sections or create new ones
            $image_index = 0;
            foreach ($unused_images as $unused_image) {
                $added = false;
                
                // Try to add to an existing section without an image
                foreach ($outline['chapters'] as &$chapter) {
                    if (isset($chapter['sections'])) {
                        foreach ($chapter['sections'] as &$section) {
                            if (empty($section['image'])) {
                                $section['image'] = $unused_image;
                                error_log("Added image {$unused_image} to existing section: {$section['title']}");
                                $added = true;
                                break 2;
                            }
                        }
                    }
                }
                
                // If no empty section found, add new sections
                if (!$added) {
                    $chapter_index = $image_index % count($outline['chapters']);
                    $new_section_number = count($outline['chapters'][$chapter_index]['sections']) + 1;
                    
                    $new_section = array(
                        'title' => "Section " . $new_section_number . ": Additional Concepts",
                        'content' => "This section explores additional concepts related to the chapter topic, incorporating visual learning elements.",
                        'image' => $unused_image,
                        'imageReference' => "The image in this section provides visual context for the concepts being discussed."
                    );
                    
                    $outline['chapters'][$chapter_index]['sections'][] = $new_section;
                    error_log("Created new section for image {$unused_image} in chapter " . ($chapter_index + 1));
                }
                
                $image_index++;
            }
        } else {
            error_log("All images are already used in the outline");
        }
        
        return $outline;
    }
    
    /**
     * Updated fallback outline generator to include better image quiz questions
     */
    public function generate_fallback_outline($course_data, $topics, $image_data = array()) {
        error_log("Using fallback outline generator with ALL images");
        
        // Create a basic outline based on available topics
        $chapters = array();
        
        // Make sure we have topics
        if (empty($topics)) {
            $topics = array('Introduction', 'Key Concepts', 'Practical Applications', 'Advanced Techniques');
        }
        
        // Use at most 6 topics for chapters
        $chapter_topics = array_slice($topics, 0, 6);
        
        // Calculate minimum sections needed to use all images
        $total_images = count($image_data);
        $total_chapters = count($chapter_topics);
        $min_sections_per_chapter = ceil($total_images / $total_chapters);
        
        // Ensure we have enough sections to use all images
        $sections_per_chapter = max(2, $min_sections_per_chapter);
        
        // Distribute images across sections
        $image_index = 0;
        
        foreach ($chapter_topics as $chapter_index => $topic) {
            $chapter = array(
                'title' => "Chapter " . ($chapter_index + 1) . ": " . $topic,
                'introduction' => "Welcome to the chapter on " . $topic . ". This chapter explores the fundamental concepts and applications related to " . $topic . ", providing you with a comprehensive understanding of this important area.",
                'sections' => array()
            );
            
            // Create enough sections to use all images
            for ($i = 0; $i < $sections_per_chapter; $i++) {
                $section_title = $this->generate_section_title($topic, $i);
                
                $section = array(
                    'title' => "Section " . ($i + 1) . ": " . $section_title,
                    'content' => "This section provides a comprehensive exploration of " . $topic . ", covering essential concepts and practical applications.",
                    'image' => '',
                    'imageReference' => ''
                );
                
                // Assign image if available (use filename for AI context)
                if ($image_index < $total_images) {
                    $section['image'] = $image_data[$image_index]['filename'];
                    $section['imageReference'] = "The image illustrates key concepts related to " . $topic . ".";
                    $image_index++;
                }
                
                $chapter['sections'][] = $section;
            }
            
            $chapters[] = $chapter;
        }
        
        // If we still have unused images, add them to existing sections
        if ($image_index < $total_images) {
            $section_count = 0;
            foreach ($chapters as &$chapter) {
                foreach ($chapter['sections'] as &$section) {
                    if ($image_index < $total_images) {
                        // If section already has an image, add a note about multiple images
                        if (!empty($section['image'])) {
                            $section['additionalImages'] = array();
                            while ($image_index < $total_images && count($section['additionalImages']) < 2) {
                                $section['additionalImages'][] = $image_data[$image_index]['filename'];
                                $image_index++;
                            }
                            $section['imageReference'] .= " Additional images provide further illustration of these concepts.";
                        }
                    }
                    $section_count++;
                }
            }
        }

        
        // Create educational quiz with better image questions
        $quiz = array(
            'title' => 'Final Assessment',
            'description' => 'Test your understanding of ' . $course_data['title'] . ' by applying the concepts you\'ve learned throughout this course',
            'questions' => array()
        );
        
        // Create standard multiple choice and true/false questions
        for ($i = 0; $i < 10; $i++) {
            $question_topic = $topics[$i % count($topics)];
            
            if ($i % 2 == 0) {
                // Multiple choice question
                $quiz['questions'][] = array(
                    'question' => "Which of the following best describes a key principle of " . $question_topic . "?",
                    'type' => 'multiple-choice',
                    'options' => array(
                        "The foundational framework that establishes how " . $question_topic . " functions in practice",
                        "The relationship between theoretical concepts and practical applications in " . $question_topic,
                        "The historical development and evolution of " . $question_topic . " over time",
                        "The regulatory guidelines that govern professional practice in " . $question_topic
                    ),
                    'correctAnswer' => 1
                );
            } else {
                // True/false question
                $quiz['questions'][] = array(
                    'question' => "When implementing " . $question_topic . ", it's more important to focus on theoretical principles than practical applications.",
                    'type' => 'true-false',
                    'correctAnswer' => 1 // False
                );
            }
        }
        
        // Only add image-based questions if we have suitable images
        $quiz_suitable_images = array_filter($image_data, function($image) {
            return isset($image['quiz_potential']['suitable_for_quiz']) && $image['quiz_potential']['suitable_for_quiz'] === true;
        });
        
        if (!empty($quiz_suitable_images) && count($quiz_suitable_images) >= 2) {
            error_log("Creating smart image-choice question with " . count($quiz_suitable_images) . " suitable images");
            
            // Create one well-designed image-choice question
            $suitable_images = array_values($quiz_suitable_images); // Re-index array
            $selected_images = array_slice($suitable_images, 0, min(4, count($suitable_images)));
            
            // Try to create a meaningful question based on the first image's quiz concepts
            $first_image = $selected_images[0];
            $quiz_concepts = isset($first_image['quiz_potential']['quiz_concepts']) ? $first_image['quiz_potential']['quiz_concepts'] : array();
            
            if (!empty($quiz_concepts)) {
                $primary_concept = $quiz_concepts[0];
                $question_text = "Which image best demonstrates the concept of " . $primary_concept . "?";
            } else {
                // Fallback to topic-based question
                $question_text = "Which image best illustrates the principles of " . $topics[0] . "?";
            }
            
            $quiz['questions'][] = array(
                'question' => $question_text,
                'type' => 'image-choice',
                'image_options' => array_column($selected_images, 'filename'),
                'correctAnswer' => 0 // First suitable image is correct
            );
        } else {
            error_log("No suitable images for quiz questions, creating text-only quiz");
        }
        
        $outline = array(
            'chapters' => $chapters,
            'quiz' => $quiz,
            'estimatedTime' => '3-4 hours'
        );
        
        error_log("Fallback outline generated successfully with improved quizzes");
        
        // Also update the course data
        $course_data['chapters'] = $chapters;
        $course_data['quiz'] = $quiz;
        $course_data['estimatedTime'] = '3-4 hours';
        $this->save_course_file($course_data['id'], $course_data);
        
        return $outline;
    }

    /**
     * Generate a section title based on the topic and index
     */
    private function generate_section_title($topic, $index) {
        $prefixes = array(
            array('Introduction to', 'Fundamentals of', 'Basics of'),
            array('Understanding', 'Exploring', 'Diving into'),
            array('Advanced', 'Practical Applications of', 'Case Studies in')
        );
        
        return $prefixes[$index][$index % count($prefixes[$index])] . ' ' . $topic;
    }

    /**
     * Calculate estimated course completion time
     */
    private function calculate_estimated_time($outline) {
        // Count total sections
        $total_sections = 0;
        $word_count = 0;
        
        foreach ($outline['chapters'] as $chapter) {
            // Count words in introduction
            if (!empty($chapter['introduction'])) {
                $word_count += str_word_count(strip_tags($chapter['introduction']));
            }
            
            // Count sections and their content
            if (!empty($chapter['sections'])) {
                $total_sections += count($chapter['sections']);
                
                foreach ($chapter['sections'] as $section) {
                    if (!empty($section['content'])) {
                        $word_count += str_word_count(strip_tags($section['content']));
                    }
                }
            }
        }
        
        // Estimate reading time (average 350 words per minute - increased from 225)
        $reading_minutes = ceil($word_count / 350);
        
        // Add time for exercises and reflection (2 minutes per section - reduced from 5)
        $exercise_minutes = $total_sections * 2;
        
        // Add time for quiz (1 minute per question - reduced from 3)
        $quiz_minutes = 0;
        if (isset($outline['quiz']['questions'])) {
            $quiz_minutes = count($outline['quiz']['questions']) * 1;
        }
        
        // Total minutes
        $total_minutes = $reading_minutes + $exercise_minutes + $quiz_minutes;
        
        // Format time
        if ($total_minutes < 60) {
            return $total_minutes . ' minutes';
        } else {
            $hours = floor($total_minutes / 60);
            $minutes = $total_minutes % 60;
            
            if ($minutes === 0) {
                return $hours . ' hour' . ($hours > 1 ? 's' : '');
            } else {
                return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ' . $minutes . ' minutes';
            }
        }
    }

    /**
     * Expand course content with detailed educational material
     */
    private function expand_course_content($course_data) {
        // Initialize progress tracking
        $course_id = $course_data['id'];
        
        // Calculate total sections for progress tracking
        $total_chapters = count($course_data['chapters']);
        $total_sections = 0;
        foreach ($course_data['chapters'] as $chapter) {
            $total_sections += count($chapter['sections']);
        }
        
        // Update progress with totals
        $this->update_course_progress($course_id, array(
            'total_chapters' => $total_chapters,
            'total_sections' => $total_sections,
            'status' => 'expanding_content',
            'percent_complete' => 40,
            'current_task' => 'Expanding course content with detailed explanations'
        ));
        
        // Track expanded word count for time estimation
        $total_words = 0;
        $completed_sections = 0;
        
        // Process each chapter INDIVIDUALLY
        foreach ($course_data['chapters'] as $chapter_index => &$chapter) {
            // Log chapter processing
            error_log("Processing chapter {$chapter_index}: {$chapter['title']}");
            
            // Update progress to show current chapter
            $this->update_course_progress($course_id, array(
                'current_chapter' => $chapter_index + 1,
                'current_section' => 0,
                'current_task' => "Expanding Chapter " . ($chapter_index + 1) . ": " . $chapter['title']
            ));
            
            try {
                // Expand the chapter introduction
                $chapter['introduction'] = $this->expand_chapter_introduction(
                    $chapter['title'], 
                    $chapter['introduction'],
                    $course_data['title']
                );
                
                $total_words += str_word_count(strip_tags($chapter['introduction']));
                
                // Save progress after each chapter introduction
                $this->save_course_file($course_id, $course_data);
                
                // Expand each section INDIVIDUALLY
                foreach ($chapter['sections'] as $section_index => &$section) {
                    // Update progress to show current section
                    $current_section = $section_index + 1;
                    $total_completed = $completed_sections + 1;
                    $percent_complete = 40 + round(($total_completed / $total_sections) * 60); // 40% to 100%
                    
                    $this->update_course_progress($course_id, array(
                        'current_section' => $current_section,
                        'completed_sections' => $completed_sections,
                        'percent_complete' => $percent_complete,
                        'current_task' => "Expanding Section " . $current_section . ": " . $section['title'] . " (" . $percent_complete . "% complete)"
                    ));
                    
                    error_log("Processing section {$section_index} in chapter {$chapter_index}: {$section['title']}");
                    
                    try {
                        // Find image info if this section has an image
                        $image_info = null;
                        if (!empty($section['image'])) {
                            foreach ($course_data['images'] as $image) {
                                // Match by attachment ID first, then fall back to filename
                                $match = false;
                                if (is_numeric($section['image']) && isset($image['id']) && $image['id'] == $section['image']) {
                                    $match = true;
                                } elseif ($image['filename'] === $section['image']) {
                                    $match = true;
                                }
                                
                                if ($match) {
                                    $image_info = array(
                                        'id' => isset($image['id']) ? $image['id'] : 0,
                                        'filename' => $image['filename'],
                                        'description' => $image['description'],
                                        'analysis' => isset($image['analysis']) ? $image['analysis'] : ''
                                    );
                                    break;
                                }
                            }
                        }
                        
                        // Skip if the section already has substantial content
                        if (str_word_count(strip_tags($section['content'])) > 500 || strpos($section['content'], '<') !== false) {
                            error_log("Section already has substantial content, skipping expansion");
                            $total_words += str_word_count(strip_tags($section['content']));
                            $completed_sections++;
                            continue;
                        }
                        
                        // Expand the section content with reduced size requirements
                        $section['content'] = $this->expand_section_content(
                            $chapter['title'],
                            $section['title'],
                            $section['content'],
                            $course_data['title'],
                            $image_info
                        );
                        
                        // Count words in expanded content (strip HTML tags first)
                        $plain_content = strip_tags($section['content']);
                        $word_count = str_word_count($plain_content);
                        $total_words += $word_count;
                        
                        // Set more accurate estimated time based on word count
                        $minutes = ceil($word_count / 225) + 5; // Add 5 minutes for exercises/reflection
                        $section['estimatedTime'] = "$minutes minutes";
                        
                        error_log("Expanded section to $word_count words, estimated time: $minutes minutes");
                        
                        // Save progress after EACH section (crucial for reliability)
                        $this->save_course_file($course_id, $course_data);
                        
                        // Update completed sections count
                        $completed_sections++;
                        
                    } catch (Exception $e) {
                        error_log("Error expanding section: " . $e->getMessage());
                        // Use fallback content if expansion fails
                        $section['content'] = $this->format_section_content_fallback($section['content'], $image_info);
                        
                        // Count words and set estimated time for fallback content
                        $word_count = str_word_count(strip_tags($section['content']));
                        $total_words += $word_count;
                        $minutes = ceil($word_count / 225) + 5;
                        $section['estimatedTime'] = "$minutes minutes";
                        
                        // Save progress even for fallback content
                        $this->save_course_file($course_id, $course_data);
                        $completed_sections++;
                    }
                }
            } catch (Exception $e) {
                error_log("Error expanding chapter: " . $e->getMessage());
                // Format introduction with fallback method if needed
                if (empty($chapter['introduction']) || strlen($chapter['introduction']) < 100) {
                    $chapter['introduction'] = $this->format_introduction_fallback($chapter['introduction'] ?: 'Introduction to ' . $chapter['title']);
                }
                // Continue with next chapter
            }
        }
        
        // Recalculate total course time
        $total_minutes = ceil($total_words / 225) + ($total_sections * 5);
        $hours = floor($total_minutes / 60);
        $minutes = $total_minutes % 60;
        
        if ($hours > 0) {
            $course_data['estimatedTime'] = "$hours hour" . ($hours > 1 ? "s" : "");
            if ($minutes > 0) {
                $course_data['estimatedTime'] .= " $minutes minute" . ($minutes > 1 ? "s" : "");
            }
        } else {
            $course_data['estimatedTime'] = "$minutes minute" . ($minutes > 1 ? "s" : "");
        }
        
        // Update progress to show final stage
        $this->update_course_progress($course_id, array(
            'completed_sections' => $total_sections,
            'percent_complete' => 95,
            'current_task' => "Finalizing course with estimated time: {$course_data['estimatedTime']}",
            'status' => 'finalizing'
        ));
        
        // Final save of complete course
        $this->save_course_file($course_id, $course_data);
        
        return $course_data;
    }
    /**
     * Expand a chapter introduction with detailed content
     */
    public static function expand_chapter_introduction($chapter_title, $introduction, $course_title) {
        // Get OpenAI API key and model
        $api_key = Creator_AI::get_credential('cai_openai_api_key');
        $model = get_option('cai_openai_model', 'gpt-4o');
        
        // Prepare the system message
        $system_message = "You are an expert educational content creator. Your task is to expand this chapter introduction into a comprehensive, engaging introduction that prepares students for the chapter content. Use only HTML formatting for any text styling or structure.";
        
        // Prepare the user message
        $user_message = "Please expand the following chapter introduction for a course titled \"$course_title\".\n\n";
        $user_message .= "Chapter: $chapter_title\n\n";
        $user_message .= "Current Introduction: $introduction\n\n";
        $user_message .= "Please create a detailed, educational introduction (200-300 words) that:
        1. Explains what the chapter covers
        2. Why it's important
        3. What students will learn
        4. How it connects to the overall course topic

        Use proper HTML formatting (<p>, <strong>, <em>, <ul>/<li>, etc.) and create a well-structured introduction that engages the reader. DO NOT use markdown formatting.";

        // Prepare the API request
        $messages = array(
            array(
                'role' => 'system',
                'content' => $system_message
            ),
            array(
                'role' => 'user',
                'content' => $user_message
            )
        );
        
        $data = array(
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.7,
        );
        
        // Make the API request
                    $response = Creator_AI::openai_request($data, $api_key);
        
        if ($response === false) {
            // Return original if failed
            return self::format_introduction_fallback_static($introduction);
        }
        
        // Get the expanded introduction
        $expanded_introduction = $response['choices'][0]['message']['content'];
        
        // Convert any markdown to HTML just in case
        $expanded_introduction = self::convert_markdown_to_html_static($expanded_introduction);
        
        return $expanded_introduction;
    }

    /**
     * Format introduction as HTML (fallback if API fails)
     */
    private function format_introduction_fallback($introduction) {
        // Ensure the introduction has proper HTML formatting
        if (strpos($introduction, '<p>') === false) {
            return '<p>' . str_replace("\n\n", '</p><p>', $introduction) . '</p>';
        }
        return $introduction;
    }

    /**
     * Expand a section's content with detailed, educational material
     */
    public function expand_section_content($chapter_title, $section_title, $content, $course_title, $image_info = null) {
        // Get OpenAI API key and model
        $api_key = Creator_AI::get_credential('cai_openai_api_key');
        $model = get_option('cai_openai_model', 'gpt-4o');
        
        if (empty($api_key)) {
            return $this->format_section_content_fallback($content, $image_info);
        }
        
        // Prepare the system message with enhanced image integration focus
        $system_message = "You are an expert educational content creator specializing in creating engaging, informative course material that effectively integrates visual learning elements. Your task is to create content that not only teaches effectively through text but also uses images as core educational tools rather than mere illustrations.";
        
        // Prepare the user message
        $user_message = "Create educational content for this course section:\n\n";
        $user_message .= "COURSE: " . $course_title . "\n";
        $user_message .= "CHAPTER: " . $chapter_title . "\n";
        $user_message .= "SECTION: " . $section_title . "\n\n";
        $user_message .= "CURRENT CONTENT: " . $content . "\n\n";
        
        // Image integration instructions
        if ($image_info && !empty($image_info['filename'])) {
            $user_message .= "IMAGE TO INTEGRATE IN THIS SECTION:\n";
            $user_message .= "Filename: " . $image_info['filename'] . "\n";
            
            // Handle main image
            $user_message .= "Main image URL: " . (isset($image_info['url']) ? $image_info['url'] : $image_info['filename']) . "\n";
            
            // Handle additional images if they exist
            if (!empty($image_info['additionalImages'])) {
                $user_message .= "\nADDITIONAL IMAGES TO INTEGRATE:\n";
                foreach ($image_info['additionalImages'] as $additional_image) {
                    $user_message .= "- " . $additional_image . "\n";
                }
                $user_message .= "\nIMPORTANT: You must integrate ALL these images into the section content. Use them to illustrate different aspects or examples of the topic.\n";
            }
            
            if (!empty($image_info['description'])) {
                $description_text = is_array($image_info['description']) ? json_encode($image_info['description']) : (string)$image_info['description'];
                $user_message .= "Description: " . $description_text . "\n";
            }
            
            if (!empty($image_info['analysis'])) {
                $analysis_text = is_array($image_info['analysis']) ? json_encode($image_info['analysis']) : (string)$image_info['analysis'];
                $user_message .= "Educational Analysis: " . $analysis_text . "\n";
            }
            
            if (!empty($image_info['imageReference'])) {
                $user_message .= "Suggested Integration: " . $image_info['imageReference'] . "\n";
            }
            
            $user_message .= "\nIMAGE INTEGRATION INSTRUCTIONS:\n";
            $user_message .= "1. The image should be a central teaching tool, not just decoration\n";
            // Use attachment ID in figure tag - will be converted to full URL during rendering
            $image_src = isset($image_info['id']) && $image_info['id'] > 0 ? $image_info['id'] : $image_info['filename'];
            $user_message .= "2. When you integrate this image, use this exact HTML markup:\n";
            $user_message .= "   <figure class=\"section-image\">\n";
            $user_message .= "     <img src=\"" . $image_src . "\" alt=\"Educational illustration related to " . esc_attr($section_title) . "\" />\n";
            $user_message .= "     <figcaption>Write an educational caption here that describes what the image teaches</figcaption>\n";
            $user_message .= "   </figure>\n\n";
            
            $user_message .= "3. Place the image at a point in your content where it serves the learning objectives best\n";
            $user_message .= "4. IMPORTANT: Immediately after the image, include at least two paragraphs that directly reference and explain what's shown in the image\n";
            $user_message .= "5. Use specific, direct language like \"As shown in the image above...\" or \"Looking at this illustration, we can observe...\"\n";
            $user_message .= "6. Make sure students understand exactly what they should learn from studying this image\n\n";
        } else {
            $user_message .= "NOTE: This section does not have an educational image to integrate. Focus on creating rich textual content with clear explanations.\n\n";
        }
        
        $user_message .= "Create educational content (600-800 words) that:\n";
        $user_message .= "1. Provides a clear introduction to the topic\n";
        $user_message .= "2. Offers detailed explanations with concrete examples\n";
        $user_message .= "3. Uses a teaching approach appropriate for the course difficulty level\n";
        $user_message .= "4. Has a logical structure with clear headings\n";
        $user_message .= "5. Includes practical applications or exercises where appropriate\n";
        $user_message .= "6. Concludes with key takeaways\n\n";
        
        $user_message .= "Format your content with HTML:\n";
        $user_message .= "- Use <p> tags for paragraphs\n";
        $user_message .= "- Use <h4> tags for subsection headings\n";
        $user_message .= "- Use <ul> and <li> for bullet points\n";
        $user_message .= "- Use <strong> and <em> for emphasis\n\n";
        
        $user_message .= "Return ONLY the content with HTML formatting, not your understanding of this task or any other commentary.";
        
        // Make API request
        $messages = array(
            array('role' => 'system', 'content' => $system_message),
            array('role' => 'user', 'content' => $user_message)
        );
        
        $data = array(
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.7,
            'max_tokens' => 3000
        );
        
        try {
            error_log("Making OpenAI request for section content: " . $section_title);
            $response = Creator_AI::openai_request($data, $api_key, 180);
            
            if (!$response || !isset($response['choices'][0]['message']['content'])) {
                error_log("Failed to get valid OpenAI response for section content");
                return $this->format_section_content_fallback($content, $image_info);
            }
            
            $expanded_content = $response['choices'][0]['message']['content'];
            
            // Clean up content
            $expanded_content = $this->cleanup_content($expanded_content);
            
            // Verify image integration if image info is provided
            if ($image_info && ((!empty($image_info['id']) && $image_info['id'] > 0) || !empty($image_info['filename']))) {
                $image_ref = isset($image_info['id']) && $image_info['id'] > 0 ? $image_info['id'] : $image_info['filename'];
                if (strpos($expanded_content, (string)$image_ref) === false) {
                    // Image not included properly, add it with educational context
                    $expanded_content = $this->add_educational_image($expanded_content, $image_info);
                }
            }
            
            error_log("Successfully expanded section content with image integration: " . $section_title);
            return $expanded_content;
            
        } catch (Exception $e) {
            error_log("Exception expanding section content: " . $e->getMessage());
            return $this->format_section_content_fallback($content, $image_info);
        }
    }

    public function preprocess_course_content($course_data) {
        if (empty($course_data) || empty($course_data['chapters'])) {
            return $course_data;
        }
        
        // Build a map of image filenames to their info
        $image_map = [];
        if (!empty($course_data['images'])) {
            foreach ($course_data['images'] as $image) {
                if (!empty($image['filename'])) {
                    $image_map[$image['filename']] = $image;
                }
            }
        }
        
        // Process all chapters and sections
        foreach ($course_data['chapters'] as $chapter_index => &$chapter) {
            if (empty($chapter['sections'])) {
                continue;
            }
            
            foreach ($chapter['sections'] as $section_index => &$section) {
                // Skip if no content or no image
                if (empty($section['content'])) {
                    continue;
                }
                
                // Check if the section has an image
                if (!empty($section['image']) && isset($image_map[$section['image']])) {
                    $image_info = $image_map[$section['image']];
                    
                    // Process the content to update image paths and alt text
                    $section['content'] = $this->process_section_content(
                        $section['content'],
                        $image_info,
                        $section['title'],
                        $course_data['title']
                    );
                }
            }
        }
        
        return $course_data;
    }


    private function add_educational_image($content, $image_info) {
        // Create educationally-focused image HTML using attachment ID if available
        $image_src = isset($image_info['id']) && $image_info['id'] > 0 ? $image_info['id'] : $image_info['filename'];
        $image_html = "<figure class=\"section-image\">\n";
        $description_text = !empty($image_info['description']) ? (is_array($image_info['description']) ? json_encode($image_info['description']) : (string)$image_info['description']) : 'Educational illustration';
        $image_html .= "  <img src=\"" . $image_src . "\" alt=\"" . esc_attr($description_text) . "\" />\n";
        $image_html .= "  <figcaption>Educational illustration: " . ($description_text !== 'Educational illustration' ? $description_text : 'Visual representation of key concepts') . "</figcaption>\n";
        $image_html .= "</figure>\n";
        
        // Create educational reference paragraphs that actually teach using the image
        $reference_paragraphs = "<p><strong>Learning from this visual:</strong> The image above provides a visual representation of key concepts we're discussing in this section. By examining this illustration, you can see how the theoretical principles translate into practical elements.</p>\n";
        
        if (!empty($image_info['analysis'])) {
            // Extract a brief teaching point from the analysis
            $analysis_text = is_array($image_info['analysis']) ? json_encode($image_info['analysis']) : (string)$image_info['analysis'];
            $analysis_summary = substr($analysis_text, 0, 150) . '...';
            $reference_paragraphs .= "<p>Notice specifically how " . $analysis_summary . " This visual aid helps reinforce your understanding of these important concepts.</p>\n";
        } else {
            $reference_paragraphs .= "<p>As you study this image, pay attention to the details and think about how they connect to the main learning objectives of this section. Visual learning can significantly enhance your understanding and retention of these concepts.</p>\n";
        }
        
        // Find a good position to insert - after a heading if possible
        $h4_pos = strpos($content, '</h4>');
        if ($h4_pos !== false) {
            // Find the first paragraph after this heading
            $p_end = strpos($content, '</p>', $h4_pos);
            if ($p_end !== false) {
                return substr_replace($content, '</p>' . "\n" . $image_html . $reference_paragraphs, $p_end, 4);
            }
        }
        
        // If no good position found, add after the first paragraph
        $first_p_end = strpos($content, '</p>');
        if ($first_p_end !== false) {
            return substr_replace($content, '</p>' . "\n" . $image_html . $reference_paragraphs, $first_p_end, 4);
        }
        
        // Last resort: add at the beginning
        return $image_html . $reference_paragraphs . $content;
    }
    public function optimize_course_metadata($course_data) {
        // Get API key and model
        $api_key = Creator_AI::get_credential('cai_openai_api_key');
        $model = get_option('cai_openai_model', 'gpt-4o');
        
        if (empty($api_key)) {
            return $course_data;
        }
        
        // Capture original values
        $original_title = isset($course_data['original_title']) ? $course_data['original_title'] : $course_data['title'];
        $original_description = isset($course_data['original_description']) ? $course_data['original_description'] : $course_data['description'];
        $original_audience = isset($course_data['original_targetAudience']) ? $course_data['original_targetAudience'] : $course_data['targetAudience'];
        
        // Prepare system message
        $system_message = "You are an expert in educational content marketing, SEO, and course design. Your task is to improve the given course title, description, and target audience to make them more engaging, clear, and optimized for search.";
        
        // Prepare user message
        $user_message = "Please optimize the following educational course metadata to make it more engaging and SEO-friendly:\n\n";
        $user_message .= "TITLE: " . $original_title . "\n\n";
        $user_message .= "DESCRIPTION: " . $original_description . "\n\n";
        $user_message .= "TARGET AUDIENCE: " . $original_audience . "\n\n";
        
        if (!empty($course_data['mainTopics'])) {
            $user_message .= "MAIN TOPICS: " . $course_data['mainTopics'] . "\n\n";
        }
        
        if (!empty($course_data['learningObjectives'])) {
            $user_message .= "LEARNING OBJECTIVES: " . $course_data['learningObjectives'] . "\n\n";
        }
        
        $user_message .= "For each element, create a more compelling version that:\n";
        $user_message .= "1. For the TITLE: Create a highly clickable, SEO-optimized course title that creates interest while remaining educational and accurate\n";
        $user_message .= "2. For the DESCRIPTION: Write a compelling description that clearly explains benefits and value\n";
        $user_message .= "3. For the TARGET AUDIENCE: Create a specific, targeted description of ideal students\n\n";
        
        $user_message .= "Format your response as a clean JSON object with these exact keys: 'title', 'description', 'targetAudience'\n";
        $user_message .= "DO NOT include any explanations, markdown formatting, or code blocks. Return ONLY the JSON.";
        
        // Make API request
        $messages = array(
            array('role' => 'system', 'content' => $system_message),
            array('role' => 'user', 'content' => $user_message)
        );
        
        $data = array(
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.7,
            'max_tokens' => 1000
        );
        
        try {
            error_log("Making OpenAI request for metadata optimization");
            // FIX: In the main trait, use openai_request directly (no parent reference)
            $response = Creator_AI::openai_request($data, $api_key);
            
            if (!$response || !isset($response['choices'][0]['message']['content'])) {
                error_log("Failed to get valid OpenAI response for metadata optimization");
                return $course_data;
            }
            
            $content = $response['choices'][0]['message']['content'];
            
            // Clean up any code blocks or extra formatting
            $content = preg_replace('/```(?:json)?\s*(.*?)\s*```/s', '$1', $content);
            $content = trim($content);
            
            // Parse JSON
            $optimized = json_decode($content, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("JSON parsing error: " . json_last_error_msg());
                error_log("Raw content: " . $content);
                return $course_data;
            }
            
            // Store original values if not already saved
            if (!isset($course_data['original_title'])) {
                $course_data['original_title'] = $course_data['title'];
            }
            if (!isset($course_data['original_description'])) {
                $course_data['original_description'] = $course_data['description'];
            }
            if (!isset($course_data['original_targetAudience'])) {
                $course_data['original_targetAudience'] = $course_data['targetAudience'];
            }
            
            // Update with optimized content
            if (!empty($optimized['title'])) {
                $course_data['title'] = $optimized['title'];
            }
            if (!empty($optimized['description'])) {
                $course_data['description'] = $optimized['description'];
            }
            if (!empty($optimized['targetAudience'])) {
                $course_data['targetAudience'] = $optimized['targetAudience'];
            }
            
            $course_data['metadata_optimized'] = true;
            
            error_log("Successfully optimized course metadata for course ID: " . $course_data['id']);
            return $course_data;
            
        } catch (Exception $e) {
            error_log("Exception optimizing metadata: " . $e->getMessage());
            return $course_data;
        }
    }

    // Add these to the trait as well
    private function add_missing_image($content, $image_info) {
        // Create image HTML using attachment ID if available
        $image_src = isset($image_info['id']) && $image_info['id'] > 0 ? $image_info['id'] : $image_info['filename'];
        $image_html = "<figure class=\"section-image\">\n";
        $description_text = !empty($image_info['description']) ? (is_array($image_info['description']) ? json_encode($image_info['description']) : (string)$image_info['description']) : 'Educational illustration';
        $image_html .= "  <img src=\"" . $image_src . "\" alt=\"" . esc_attr($description_text) . "\" />\n";
        $image_html .= "  <figcaption>Visual illustration related to " . ($description_text !== 'Educational illustration' ? $description_text : 'this topic') . "</figcaption>\n";
        $image_html .= "</figure>\n";
        
        // Add reference paragraph
        $reference_paragraph = "<p><strong>Note:</strong> The image above illustrates key concepts we're discussing. You can see how this visual representation helps in understanding the material presented in this section.</p>\n";
        
        // Find a good position to insert - after the first heading if possible
        $h4_pos = strpos($content, '</h4>');
        if ($h4_pos !== false) {
            // Insert after the first heading and paragraph
            $p_end = strpos($content, '</p>', $h4_pos);
            if ($p_end !== false) {
                return substr_replace($content, '</p>' . "\n" . $image_html . $reference_paragraph, $p_end, 4);
            }
        }
        
        // If no good position found, just add at the beginning
        return $image_html . $reference_paragraph . $content;
    }

    private function cleanup_content($content) {
        // Remove any code block markers
        $content = preg_replace('/```(?:html|json|javascript|css|php)?\s*/s', '', $content);
        $content = str_replace('```', '', $content);
        
        // Remove any extra markdown
        $content = preg_replace('/\*\*(.*?)\*\*/s', '<strong>$1</strong>', $content);
        $content = preg_replace('/\*(.*?)\*/s', '<em>$1</em>', $content);
        $content = preg_replace('/^### (.*?)$/m', '<h4>$1</h4>', $content);
        $content = preg_replace('/^## (.*?)$/m', '<h4>$1</h4>', $content);
        $content = preg_replace('/^# (.*?)$/m', '<h3>$1</h3>', $content);
        
        // Ensure paragraphs have proper tags
        if (strpos($content, '<p>') === false) {
            $paragraphs = preg_split('/\n{2,}/', $content);
            $content = '';
            foreach ($paragraphs as $paragraph) {
                $paragraph = trim($paragraph);
                if (!empty($paragraph) && !preg_match('/^<(\/?h[1-6]|ul|ol|li|div|p|figure)/i', $paragraph)) {
                    $content .= '<p>' . $paragraph . '</p>' . "\n";
                } else {
                    $content .= $paragraph . "\n";
                }
            }
        }
        
        return $content;
    }



    /**
     * Format section content as HTML (fallback if API fails)
     */
    private function format_section_content_fallback($content, $image_info = null) {
        error_log("Using fallback section formatter with educational image integration");
        
        // Ensure content has proper HTML formatting
        if (strpos($content, '<p>') === false) {
            $formatted = '<p>' . str_replace("\n\n", '</p><p>', $content) . '</p>';
        } else {
            $formatted = $content;
        }
        
        // Add standard teaching structure
        $formatted = '<h4>Introduction</h4>' . $formatted;
        $formatted .= '<h4>Key Concepts</h4>';
        $formatted .= '<p>This section covers several important concepts that build upon the introduction:</p>';
        $formatted .= '<ul>';
        $formatted .= '<li><strong>Fundamental principles</strong> - Understanding the core theory</li>';
        $formatted .= '<li><strong>Practical applications</strong> - How to apply these concepts</li>';
        $formatted .= '<li><strong>Best practices</strong> - Recommended approaches based on experience</li>';
        $formatted .= '</ul>';
        
        // Add image if available - integrated educationally within the content
        if ($image_info && ((!empty($image_info['id']) && $image_info['id'] > 0) || !empty($image_info['filename']))) {
            $description_text = !empty($image_info['description']) ? (is_array($image_info['description']) ? json_encode($image_info['description']) : (string)$image_info['description']) : 'Educational illustration';
            $alt_text = $description_text;
            $image_src = isset($image_info['id']) && $image_info['id'] > 0 ? $image_info['id'] : $image_info['filename'];
            $img_tag = "<figure class=\"section-image\">\n";
            $img_tag .= "  <img src=\"" . $image_src . "\" alt=\"" . esc_attr($alt_text) . "\" />\n";
            $img_tag .= "  <figcaption>Educational illustration: " . esc_html($alt_text) . "</figcaption>\n";
            $img_tag .= "</figure>\n";
            
            $formatted .= '<h4>Visual Learning Component</h4>';
            $formatted .= $img_tag;
            $formatted .= '<p><strong>Understanding through visualization:</strong> The image above illustrates key concepts discussed in this section. Visual learning is an important educational technique that helps reinforce understanding through different cognitive pathways.</p>';
            
            // Add specific educational context based on image analysis if available
            if (!empty($image_info['analysis'])) {
                $analysis_text = is_array($image_info['analysis']) ? json_encode($image_info['analysis']) : (string)$image_info['analysis'];
                $formatted .= '<p>This visual specifically demonstrates ' . substr($analysis_text, 0, 150) . '... Take time to study the details in this image and connect them to the concepts we\'ve discussed.</p>';
            } else {
                $formatted .= '<p>As you study this image, notice how it relates to the key concepts outlined above. Try to identify specific elements that correspond to the principles we\'ve covered.</p>';
            }
            
            $formatted .= '<p>Remember that visual information often helps with retention and understanding of abstract concepts. Take a moment to analyze this image before continuing to the next section.</p>';
        }
        
        $formatted .= '<h4>Practical Application</h4>';
        $formatted .= '<p>Now that we\'ve covered the theoretical aspects and examined visual representations, let\'s consider how to apply these concepts in real-world scenarios:</p>';
        $formatted .= '<ol>';
        $formatted .= '<li>Begin by identifying the key elements in your specific context</li>';
        $formatted .= '<li>Apply the principles we\'ve discussed in a systematic manner</li>';
        $formatted .= '<li>Evaluate results and adjust your approach as needed</li>';
        $formatted .= '</ol>';
        
        $formatted .= '<h4>Summary</h4>';
        $formatted .= '<p>In this section, we\'ve explored the core concepts related to ' . strip_tags($content) . ' through both textual explanations and visual learning tools. These concepts build a foundation for more advanced topics that will be discussed in subsequent sections.</p>';
        
        return $formatted;
    }


    /**
     * Improve image references in content to be more user-friendly
     */
    private function improve_image_references($content, $image_info) {
        // Replace explicit filename references in text with more natural descriptions
        $pattern = '/\'?' . preg_quote($image_info['filename'], '/') . '\'?/';
        $replacement = 'the image';
        $content = preg_replace($pattern, $replacement, $content);
        
        // Ensure the img tag has proper class and alt attributes
        $img_pattern = '/<img\s+src=[\'"]' . preg_quote($image_info['filename'], '/') . '[\'"][^>]*>/i';
        
        if (preg_match($img_pattern, $content, $matches)) {
            $original_img_tag = $matches[0];
            
            // Prepare the new attributes
            $description_text = !empty($image_info['description']) ? (is_array($image_info['description']) ? json_encode($image_info['description']) : (string)$image_info['description']) : 'Image for this section';
            $alt_text = $description_text;
            
            // Check if it already has an alt attribute
            if (strpos($original_img_tag, 'alt=') === false) {
                // No alt attribute, add one with the description
                $new_img_tag = str_replace('<img', '<img alt="' . esc_attr($alt_text) . '"', $original_img_tag);
                $content = str_replace($original_img_tag, $new_img_tag, $content);
            }
            
            // Check if it's part of a figure element
            if (strpos($content, '<figure') === false && strpos($content, '<img') !== false) {
                // Not in a figure, wrap it in one
                $content = preg_replace(
                    '/<img([^>]*)>/i',
                    '<figure class="section-image"><img$1><figcaption>Visual illustration related to the content</figcaption></figure>',
                    $content,
                    1
                );
            }
        }
        
        // Make sure there's content that references the image
        if (strpos(strtolower($content), 'in the image') === false && 
            strpos(strtolower($content), 'this image') === false && 
            strpos(strtolower($content), 'the image above') === false) {
            
            // Find a good position to insert an image reference
            $paragraph_after_img = strpos($content, '</p>', strpos($content, $image_info['filename']));
            if ($paragraph_after_img !== false) {
                // Add a reference to the image after the paragraph that contains the image
                $image_reference = '<p><strong>Note:</strong> As shown in the image above, this visual representation helps illustrate the key concepts we\'re discussing. You can see specific elements that relate directly to what we\'ve been learning.</p>';
                $content = substr_replace($content, '</p>' . $image_reference, $paragraph_after_img, 4);
            }
        }
        
        return $content;
    }

    /**
     * Convert markdown to HTML
     */
    private function convert_markdown_to_html($text) {
        // Bold: **text** or __text__ to <strong>text</strong>
        $text = preg_replace('/\*\*(.*?)\*\*|__(.*?)__/s', '<strong>$1$2</strong>', $text);
        
        // Italic: *text* or _text_ to <em>text</em>
        $text = preg_replace('/(?<!\*)\*((?!\*).+?)\*(?!\*)|(?<!_)_((?!_).+?)_(?!_)/s', '<em>$1$2</em>', $text);
        
        // Headers: ### to <h3>, etc.
        $text = preg_replace('/^### (.*?)$/m', '<h3>$1</h3>', $text);
        $text = preg_replace('/^## (.*?)$/m', '<h2>$1</h2>', $text);
        $text = preg_replace('/^# (.*?)$/m', '<h1>$1</h1>', $text);
        
        // Lists: bulletpoints to <ul><li>
        $text = preg_replace_callback('/(?:^\s*[-*+]\s+.+?$(?:\n|$))+/m', function($matches) {
            $list = $matches[0];
            $items = preg_split('/^\s*[-*+]\s+/m', $list, -1, PREG_SPLIT_NO_EMPTY);
            $html = "<ul>\n";
            foreach ($items as $item) {
                $html .= "  <li>" . trim($item) . "</li>\n";
            }
            $html .= "</ul>";
            return $html;
        }, $text);
        
        // Lists: numbered to <ol><li>
        $text = preg_replace_callback('/(?:^\s*\d+\.\s+.+?$(?:\n|$))+/m', function($matches) {
            $list = $matches[0];
            $items = preg_split('/^\s*\d+\.\s+/m', $list, -1, PREG_SPLIT_NO_EMPTY);
            $html = "<ol>\n";
            foreach ($items as $item) {
                $html .= "  <li>" . trim($item) . "</li>\n";
            }
            $html .= "</ol>";
            return $html;
        }, $text);
        
        // Make sure paragraphs have p tags
        if (strpos($text, '<p>') === false) {
            $paragraphs = preg_split('/\n{2,}/', $text);
            $text = '';
            foreach ($paragraphs as $paragraph) {
                $paragraph = trim($paragraph);
                if (!empty($paragraph) && !preg_match('/^<(\/?h[1-6]|ul|ol|li|div|p|img|pre)/i', $paragraph)) {
                    $text .= '<p>' . $paragraph . '</p>';
                } else {
                    $text .= $paragraph;
                }
            }
        }
        
        return $text;
    }

    /**
     * Initialize progress tracking for a course
     */
    private function initialize_course_progress($course_id) {
        $progress = array(
            'course_id' => $course_id,
            'started' => time(),
            'current_chapter' => 0,
            'current_section' => 0,
            'total_chapters' => 0,
            'total_sections' => 0,
            'completed_sections' => 0,
            'percent_complete' => 0,
            'status' => 'initializing',
            'current_task' => 'Preparing course creation',
            'completed' => false
        );
        
        // Store the progress in a transient
        set_transient('cai_course_progress_' . $course_id, $progress, 3600); // Expires after 1 hour
        
        return $progress;
    }

    /**
     * Update progress tracking for a course
     */
    private function update_course_progress($course_id, $update_data) {
        $progress = get_transient('cai_course_progress_' . $course_id);
        
        if (!$progress) {
            // Initialize if not exists
            $progress = $this->initialize_course_progress($course_id);
        }
        
        // Update with new data
        $progress = array_merge($progress, $update_data);
        
        // Update timestamp
        $progress['last_updated'] = time();
        
        // Store updated progress
        set_transient('cai_course_progress_' . $course_id, $progress, 3600);
        
        return $progress;
    }

    /**
     * Get the current progress of a course creation/expansion
     */
    public function get_course_progress() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'cai_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        // Get course ID
        $course_id = isset($_POST['course_id']) ? sanitize_text_field($_POST['course_id']) : '';
        
        if (empty($course_id)) {
            wp_send_json_error('No course ID provided');
            return;
        }
        
        // Get current progress
        $progress = get_transient('cai_course_progress_' . $course_id);
        
        if (!$progress) {
            wp_send_json_error('No progress data found for this course');
            return;
        }
        
        // Return progress data
        wp_send_json_success($progress);
    }

    /**
     * Mark course progress as complete
     */
    private function complete_course_progress($course_id) {
        $progress = get_transient('cai_course_progress_' . $course_id);
        
        if (!$progress) {
            // Initialize if not exists
            $progress = $this->initialize_course_progress($course_id);
        }
        
        // Mark as complete
        $progress['completed'] = true;
        $progress['percent_complete'] = 100;
        $progress['status'] = 'completed';
        $progress['current_task'] = 'Course creation completed';
        $progress['completed_at'] = time();
        
        // Store final progress state
        set_transient('cai_course_progress_' . $course_id, $progress, 3600);
        
        return $progress;
    }

    /**
     * Load all courses from JSON files
     */
    private function load_all_courses() {
        $upload_dir = wp_upload_dir();
        $courses_dir = $upload_dir['basedir'] . '/creator-ai-courses';
        $courses = array();

        // Check if directory exists
        if (!file_exists($courses_dir)) {
            return $courses;
        }

        // Get all JSON files
        $files = glob($courses_dir . '/*.json');
        
        foreach ($files as $file) {
            // Skip temporary files and other non-course files
            if (strpos($file, '_outline.json') !== false || strpos($file, '_temp.json') !== false) {
                continue;
            }
            
            $course_data = json_decode(file_get_contents($file), true);
            
            if ($course_data && isset($course_data['id']) && isset($course_data['title'])) {
                
                // MODIFICATION: Resolve the image URL on the backend to prevent an AJAX flood on the frontend.
                $cover_image_url = '';
                if (isset($course_data['coverImage']) && !empty($course_data['coverImage'])) {
                    $cover_image_url = $this->get_course_image_url($course_data['coverImage']);
                }

                $courses[] = array(
                    'id' => $course_data['id'],
                    'title' => $course_data['title'],
                    'description' => isset($course_data['description']) ? $course_data['description'] : '',
                    // Use the fully resolved URL.
                    'coverImage' => $cover_image_url,
                    'estimatedTime' => isset($course_data['estimatedTime']) ? $course_data['estimatedTime'] : '',
                    'updatedAt' => isset($course_data['updatedAt']) ? $course_data['updatedAt'] : '',
                    'chapterCount' => isset($course_data['chapters']) ? count($course_data['chapters']) : 0
                );
            }
        }

        return $courses;
    }

    /**
     * Load a course from file
     */
    private function load_course_file($course_id) {
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/creator-ai-courses/' . $course_id . '.json';
        
        if (!file_exists($file_path)) {
            return false;
        }
        
        $course_data = json_decode(file_get_contents($file_path), true);
        
        if ($course_data === null) {
            return false;
        }
        
        return $course_data;
    }

    /**
     * Save course data to file
     */
    private function save_course_file($course_id, $course_data) {
        $upload_dir = wp_upload_dir();
        $courses_dir = $upload_dir['basedir'] . '/creator-ai-courses';
        
        if (!file_exists($courses_dir)) {
            wp_mkdir_p($courses_dir);
        }
        
        $file_path = $courses_dir . '/' . $course_id . '.json';
        return file_put_contents($file_path, json_encode($course_data, JSON_PRETTY_PRINT));
    }

    /**
     * Get a course image URL with optional size
     */
    public function get_course_image_url($image_input, $size = 'full') {
        if (empty($image_input)) {
            return plugin_dir_url(dirname(__FILE__)) . 'assets/course-thumbnail-placeholder.jpg';
        }
        
        // If it's already a full URL, return it
        if (filter_var($image_input, FILTER_VALIDATE_URL)) {
            return $image_input;
        }
        
        // Check if this is an attachment ID
        if (is_numeric($image_input)) {
            $attachment_url = wp_get_attachment_url($image_input);
            if ($attachment_url) {
                return $attachment_url;
            }
        }
        
        // Try to find attachment by filename (for AI-assigned filenames)
        if (!is_numeric($image_input)) {
            $attachment_id = $this->get_attachment_id_by_filename($image_input);
            if ($attachment_id) {
                $attachment_url = wp_get_attachment_url($attachment_id);
                if ($attachment_url) {
                    return $attachment_url;
                }
            }
        }
        
        // Fallback: return placeholder
        return plugin_dir_url(dirname(__FILE__)) . 'assets/course-thumbnail-placeholder.jpg';
    }

    public function get_course_image_base_url() {
        // Images are now stored in standard WordPress media library
        // No custom directory needed
        $upload_dir = wp_upload_dir();
        return trailingslashit($upload_dir['baseurl']);
    }

    private function get_attachment_id_by_filename($filename) {
        global $wpdb;
        
        // Extract filename without extension and path
        $filename_only = pathinfo($filename, PATHINFO_FILENAME);
        
        // First, try exact match by post_name (WordPress slug)
        $attachment_id = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM $wpdb->posts 
             WHERE post_type = 'attachment' 
             AND post_name = %s",
            $filename_only
        ));
        
        if ($attachment_id) {
            return (int) $attachment_id;
        }
        
        // If no exact match, try searching in the guid field (original filename)
        $attachment_id = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM $wpdb->posts 
             WHERE post_type = 'attachment' 
             AND guid LIKE %s",
            '%' . $filename . '%'
        ));
        
        if ($attachment_id) {
            return (int) $attachment_id;
        }
        
        // Last resort: search by post_title
        $attachment_id = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM $wpdb->posts 
             WHERE post_type = 'attachment' 
             AND post_title LIKE %s",
            '%' . $filename_only . '%'
        ));
        
        return $attachment_id ? (int) $attachment_id : null;
    }

    /**
     * AJAX handler to get image URL from filename or attachment ID
     */
    public function ajax_get_image_url() {
        // Check nonce for security
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'cai_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }

        if (!isset($_POST['image_input'])) {
            wp_send_json_error('No image input provided');
            return;
        }

        $image_input = sanitize_text_field($_POST['image_input']);
        $image_url = $this->get_course_image_url($image_input);
        
        wp_send_json_success(array('url' => $image_url));
    }

    public function unpublish_course() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'cai_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('You do not have permission to unpublish courses');
            return;
        }
        
        // Get course ID
        $course_id = isset($_POST['course_id']) ? sanitize_text_field($_POST['course_id']) : '';
        
        if (empty($course_id)) {
            wp_send_json_error('No course ID provided');
            return;
        }
        
        // Get post ID from course ID
        $post_id = $this->get_course_post_id($course_id);
        
        if (!$post_id) {
            wp_send_json_error('Course is not published');
            return;
        }
        
        // Delete the post
        $deleted = wp_delete_post($post_id, true);
        
        if ($deleted) {
            wp_send_json_success(array(
                'message' => 'Course unpublished successfully',
                'course_id' => $course_id
            ));
        } else {
            wp_send_json_error('Failed to unpublish course');
        }
    }

    /**
     * Static version of generate_fallback_outline for use in static contexts
     */
    public static function generate_static_fallback_outline($course_data, $topics, $image_data = array()) {
        error_log("Using static fallback outline generator with ALL images");
        
        // Create a basic outline based on available topics
        $chapters = array();
        
        // Make sure we have topics
        if (empty($topics)) {
            $topics = array('Introduction', 'Key Concepts', 'Practical Applications', 'Advanced Techniques');
        }
        
        // Use at most 6 topics for chapters
        $chapter_topics = array_slice($topics, 0, 6);
        
        // Calculate minimum sections needed to use all images
        $total_images = is_array($image_data) ? count($image_data) : 0;
        $total_chapters = count($chapter_topics);
        $min_sections_per_chapter = $total_images > 0 ? ceil($total_images / $total_chapters) : 2;
        
        // Ensure we have enough sections to use all images
        $sections_per_chapter = max(2, $min_sections_per_chapter);
        
        // Convert image_data to filenames if needed
        $image_filenames = array();
        if (!empty($image_data)) {
            foreach ($image_data as $image) {
                if (is_array($image) && isset($image['filename'])) {
                    $image_filenames[] = $image['filename'];
                } else if (is_string($image)) {
                    $image_filenames[] = $image;
                }
            }
        }
        
        $image_index = 0;
        
        foreach ($chapter_topics as $chapter_index => $topic) {
            $chapter = array(
                'title' => "Chapter " . ($chapter_index + 1) . ": " . $topic,
                'introduction' => "This chapter explores " . $topic . " and provides practical insights for application.",
                'sections' => array()
            );
            
            // Create sections for this chapter
            for ($section_num = 1; $section_num <= $sections_per_chapter; $section_num++) {
                $section = array(
                    'title' => "Section {$section_num}: " . ($section_num === 1 ? "Overview" : "Advanced Concepts"),
                    'content' => "This section covers important aspects of {$topic}.",
                    'image' => ''
                );
                
                // Assign an image if available
                if ($image_index < count($image_filenames)) {
                    $section['image'] = $image_filenames[$image_index];
                    $image_index++;
                }
                
                $chapter['sections'][] = $section;
                
                // Stop creating sections if we've used all images and have minimum sections
                if ($image_index >= count($image_filenames) && count($chapter['sections']) >= 2) {
                    break;
                }
            }
            
            $chapters[] = $chapter;
        }
        
        // Create a comprehensive quiz with at least 10 questions
        $quiz = array(
            'title' => 'Course Assessment',
            'description' => 'Test your understanding of the course material',
            'questions' => array()
        );
        
        // Generate 10 questions using available topics
        for ($i = 0; $i < 10; $i++) {
            $question_topic = $topics[$i % count($topics)];
            
            if ($i % 2 == 0) {
                // Multiple choice question
                $quiz['questions'][] = array(
                    'question' => "Which of the following best describes a key aspect of " . $question_topic . "?",
                    'type' => 'multiple-choice',
                    'options' => array(
                        "The fundamental principles that guide " . $question_topic . " implementation",
                        "The historical background and development of " . $question_topic,
                        "The advanced techniques used in " . $question_topic . " applications",
                        "The regulatory requirements for " . $question_topic . " practice"
                    ),
                    'correctAnswer' => 0
                );
            } else {
                // True/false question
                $quiz['questions'][] = array(
                    'question' => "Understanding " . $question_topic . " requires both theoretical knowledge and practical experience.",
                    'type' => 'true-false',
                    'correctAnswer' => 0 // True
                );
            }
        }
        
        return array(
            'chapters' => $chapters,
            'quiz' => $quiz,
            'estimatedTime' => self::calculate_estimated_time_static(array(
                'chapters' => $chapters,
                'quiz' => $quiz
            ))
        );
    }

    /**
     * Static version of calculate_estimated_time for use in static contexts
     */
    public static function calculate_estimated_time_static($outline) {
        // Count total sections
        $total_sections = 0;
        $word_count = 0;
        
        if (isset($outline['chapters'])) {
            foreach ($outline['chapters'] as $chapter) {
                // Count words in introduction
                if (!empty($chapter['introduction'])) {
                    $word_count += str_word_count(strip_tags($chapter['introduction']));
                }
                
                // Count sections and their content
                if (!empty($chapter['sections'])) {
                    $total_sections += count($chapter['sections']);
                    
                    foreach ($chapter['sections'] as $section) {
                        if (!empty($section['content'])) {
                            $word_count += str_word_count(strip_tags($section['content']));
                        }
                    }
                }
            }
        }
        
        // Estimate reading time (average 350 words per minute)
        $reading_minutes = $word_count > 0 ? ceil($word_count / 350) : 10;
        
        // Add time for exercises and reflection (2 minutes per section)
        $exercise_minutes = $total_sections * 2;
        
        // Add time for quiz (1 minute per question)
        $quiz_minutes = 0;
        if (isset($outline['quiz']['questions'])) {
            $quiz_minutes = count($outline['quiz']['questions']) * 1;
        }
        
        // Total minutes
        $total_minutes = $reading_minutes + $exercise_minutes + $quiz_minutes;
        
        // Format time
        if ($total_minutes < 60) {
            return $total_minutes . ' minutes';
        } else {
            $hours = floor($total_minutes / 60);
            $minutes = $total_minutes % 60;
            
            if ($minutes === 0) {
                return $hours . ' hour' . ($hours > 1 ? 's' : '');
            } else {
                return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ' . $minutes . ' minutes';
            }
        }
    }

    /**
     * Static version of ensure_all_images_used for use in static contexts
     */
    public static function ensure_all_images_used_static($outline, $image_data) {
        error_log("Validating that all " . count($image_data) . " images are used in the outline");
        
        // Get list of all image filenames
        $all_images = array();
        foreach ($image_data as $image) {
            if (is_array($image) && isset($image['filename'])) {
                $all_images[] = $image['filename'];
            } else if (is_string($image)) {
                $all_images[] = $image;
            }
        }
        
        // Get list of used images from the outline
        $used_images = array();
        if (isset($outline['chapters'])) {
            foreach ($outline['chapters'] as $chapter) {
                if (isset($chapter['sections'])) {
                    foreach ($chapter['sections'] as $section) {
                        if (!empty($section['image'])) {
                            $used_images[] = $section['image'];
                        }
                    }
                }
            }
        }
        
        // Find unused images
        $unused_images = array_diff($all_images, $used_images);
        
        if (!empty($unused_images)) {
            error_log("Found " . count($unused_images) . " unused images. Adding them to sections...");
            
            // Add unused images to existing sections or create new ones
            foreach ($unused_images as $unused_image) {
                $added = false;
                
                // Try to add to an existing section without an image
                foreach ($outline['chapters'] as &$chapter) {
                    if (isset($chapter['sections'])) {
                        foreach ($chapter['sections'] as &$section) {
                            if (empty($section['image'])) {
                                $section['image'] = $unused_image;
                                $added = true;
                                break 2; // Break out of both loops
                            }
                        }
                    }
                }
                
                // If no empty section found, add a new section to the first chapter
                if (!$added && isset($outline['chapters'][0]['sections'])) {
                    $outline['chapters'][0]['sections'][] = array(
                        'title' => 'Additional Content',
                        'content' => 'Additional educational content.',
                        'image' => $unused_image
                    );
                }
            }
        }
        
        return $outline;
    }

    /**
     * Static version of format_introduction_fallback for static contexts
     */
    public static function format_introduction_fallback_static($introduction) {
        // Ensure the introduction has proper HTML formatting
        if (strpos($introduction, '<p>') === false) {
            return '<p>' . str_replace("\n\n", '</p><p>', $introduction) . '</p>';
        }
        return $introduction;
    }

    /**
     * Static version of convert_markdown_to_html for static contexts
     */
    public static function convert_markdown_to_html_static($text) {
        // Bold: **text** or __text__ to <strong>text</strong>
        $text = preg_replace('/\*\*(.*?)\*\*|__(.*?)__/s', '<strong>$1$2</strong>', $text);
        
        // Italic: *text* or _text_ to <em>text</em>
        $text = preg_replace('/(?<!\*)\*((?!\*).+?)\*(?!\*)|(?<!_)_((?!_).+?)_(?!_)/s', '<em>$1$2</em>', $text);
        
        // Headers: ### to <h3>, etc.
        $text = preg_replace('/^### (.*?)$/m', '<h3>$1</h3>', $text);
        $text = preg_replace('/^## (.*?)$/m', '<h2>$1</h2>', $text);
        $text = preg_replace('/^# (.*?)$/m', '<h1>$1</h1>', $text);
        
        // Lists: bulletpoints to <ul><li>
        $text = preg_replace_callback('/(?:^\s*[-*+]\s+.+?$(?:\n|$))+/m', function($matches) {
            $list = $matches[0];
            $items = preg_split('/^\s*[-*+]\s+/m', $list, -1, PREG_SPLIT_NO_EMPTY);
            $html = "<ul>\n";
            foreach ($items as $item) {
                $html .= "  <li>" . trim($item) . "</li>\n";
            }
            $html .= "</ul>";
            return $html;
        }, $text);
        
        // Line breaks: double newlines to paragraph breaks
        $text = preg_replace('/\n\n+/', "</p>\n<p>", $text);
        
        // Wrap in paragraphs if not already wrapped
        if (strpos($text, '<p>') === false && !empty(trim($text))) {
            $text = '<p>' . $text . '</p>';
        }
        
        return $text;
    }



}