<?php
// Exit if accessed directly
if (!defined('ABSPATH')) exit;

/**
 * Handles chunked processing for course creation
 */
class CreatorAI_Course_Processor {
    private $course_id;
    private $step;
    private $chapter_index;
    private $section_index;
    private $image_index;
    private $parent; // Reference to parent class for accessing its methods
    
    public function __construct($course_id, $parent = null) {
        $this->course_id = $course_id;
        $this->step = $this->get_process_step();
        $this->chapter_index = $this->get_chapter_index();
        $this->section_index = $this->get_section_index();
        $this->image_index = $this->get_image_index();
        $this->parent = $parent; // Store reference to parent class
    }
    
    /**
     * Process next chunk of course creation
     */
    public function process_next_chunk() {
        error_log("Starting process_next_chunk - Step: {$this->step}, Chapter: {$this->chapter_index}, Section: {$this->section_index}, Image: {$this->image_index}");
        
        try {
            // Load course data
            $course_data = $this->load_course_data();
            if (!$course_data) {
                error_log("Failed to load course data in process_next_chunk");
                return array(
                    'success' => false,
                    'message' => 'Failed to load course data',
                    'complete' => false
                );
            }
            
            error_log("Course data loaded successfully, processing step: {$this->step}");
            
            // Process based on current step
            switch ($this->step) {
                case 'init':
                    error_log("Running init_process step");
                    $result = $this->init_process($course_data);
                    error_log("Completed init_process: " . json_encode($result));
                    return $result;
                
                case 'analyze_images':
                    error_log("Running analyze_images step");
                    $result = $this->analyze_images($course_data);
                    error_log("Completed analyze_images: " . json_encode($result));
                    return $result;
                
                case 'outline':
                    error_log("Running generate_outline step");
                    $result = $this->generate_outline($course_data);
                    error_log("Completed generate_outline: " . json_encode($result));
                    return $result;
                    
                case 'chapter_intro':
                    error_log("Running process_chapter_intro step");
                    $result = $this->process_chapter_intro($course_data);
                    error_log("Completed process_chapter_intro: " . json_encode($result));
                    return $result;
                    
                case 'section_content':
                    error_log("Running process_section_content step");
                    $result = $this->process_section_content($course_data);
                    error_log("Completed process_section_content: " . json_encode($result));
                    return $result;
                    
                case 'finalize':
                    error_log("Running finalize_course step");
                    $result = $this->finalize_course($course_data);
                    error_log("Completed finalize_course: " . json_encode($result));
                    return $result;
                    
                default:
                    error_log("Unknown processing step: {$this->step}");
                    return array(
                        'success' => false,
                        'message' => 'Unknown processing step',
                        'complete' => false
                    );
            }
        } catch (Exception $e) {
            error_log("Exception in process_next_chunk: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return array(
                'success' => false,
                'message' => 'Exception: ' . $e->getMessage(),
                'complete' => false
            );
        }
    }
    
    /**
     * Initialize the process
     */
    private function init_process($course_data) {
        error_log("Initialize process step started");
        
        try {
            // Initialize progress tracking
            $this->update_progress($course_data['id'], array(
                'percent_complete' => 5,
                'current_task' => 'Starting course creation...',
                'status' => 'initializing'
            ));
            
            // Check if we have any images to analyze
            $has_images_to_analyze = false;
            if (!empty($course_data['images'])) {
                foreach ($course_data['images'] as $image) {
                    if (empty($image['analysis'])) {
                        $has_images_to_analyze = true;
                        break;
                    }
                }
            }
            
            // Move to appropriate next step
            if ($has_images_to_analyze) {
                $this->set_process_step('analyze_images');
                $this->set_image_index(0);
            } else {
                // Skip image analysis if no images or all already analyzed
                $this->set_process_step('outline');
            }
            
            error_log("Initialize process completed successfully");
            return array(
                'success' => true,
                'message' => 'Process initialized',
                'complete' => false,
                'percent' => 5
            );
        } catch (Exception $e) {
            error_log("Error in init_process: " . $e->getMessage());
            return array(
                'success' => false,
                'message' => 'Error initializing process: ' . $e->getMessage(),
                'complete' => false,
                'percent' => 0
            );
        }
    }
    
    /**
     * Analyze course images in batches
     */
    private function analyze_images($course_data) {
        error_log("Analyze images step started");
        
        try {
            // Update progress
            $this->update_progress($course_data['id'], array(
                'percent_complete' => 8,
                'current_task' => 'Analyzing course images...',
                'status' => 'analyzing_images'
            ));
            
            // Check if we have images to analyze
            if (empty($course_data['images'])) {
                // No images, move to next step
                $this->set_process_step('outline');
                return array(
                    'success' => true,
                    'message' => 'No images to analyze',
                    'complete' => false,
                    'percent' => 10
                );
            }
            
            // Process images in batches of 3 per chunk
            $total_images = count($course_data['images']);
            $batch_size = 3;  // Process 3 images per chunk
            $processed_count = 0;
            
            // Process a batch of images
            for ($i = $this->image_index; $i < min($this->image_index + $batch_size, $total_images); $i++) {
                if (empty($course_data['images'][$i]['analysis'])) {
                    // For WordPress media library images, get the proper path
                    $image_path = '';
                    
                    if (!empty($course_data['images'][$i]['id'])) {
                        // Get path from attachment ID
                        $image_path = get_attached_file($course_data['images'][$i]['id']);
                    } else if (!empty($course_data['images'][$i]['url'])) {
                        // Parse URL to get local path
                        $upload_dir = wp_upload_dir();
                        $base_url = $upload_dir['baseurl'];
                        $base_dir = $upload_dir['basedir'];
                        
                        // Convert URL to local path
                        if (strpos($course_data['images'][$i]['url'], $base_url) === 0) {
                            $relative_path = str_replace($base_url, '', $course_data['images'][$i]['url']);
                            $image_path = $base_dir . $relative_path;
                        }
                    }
                    
                    if ($image_path && file_exists($image_path)) {
                        // Analyze the image
                        error_log("Analyzing image {$i}: {$course_data['images'][$i]['filename']}");
                        
                        if ($this->parent && method_exists($this->parent, 'analyze_image_content')) {
                            try {
                                $analysis = $this->parent->analyze_image_content($image_path);
                                $course_data['images'][$i]['analysis'] = $analysis;
                                $course_data['images'][$i]['analyzed'] = true;
                            } catch (Exception $e) {
                                error_log("Error analyzing image {$i}: " . $e->getMessage());
                                $course_data['images'][$i]['analysis'] = 'Error analyzing image';
                                $course_data['images'][$i]['analyzed'] = true;
                            }
                        }
                    } else {
                        error_log("Image file not found: {$image_path}");
                        $course_data['images'][$i]['analysis'] = 'Image file not found';
                        $course_data['images'][$i]['analyzed'] = true;
                    }
                }
                
                $processed_count++;
            }
            
            // Save the updated course data
            $this->save_course_data($course_data);
            
            // Calculate progress
            $new_image_index = $this->image_index + $processed_count;
            $percent = 8 + min(12, round(($new_image_index / $total_images) * 12));
            
            $this->update_progress($course_data['id'], array(
                'percent_complete' => $percent,
                'current_task' => "Analyzed " . $new_image_index . " of " . $total_images . " images",
                'status' => 'analyzing_images'
            ));
            
            // Check if we've processed all images
            if ($new_image_index >= $total_images) {
                // All images processed, move to next step
                $this->set_process_step('outline');
                $this->set_image_index(0);
                
                return array(
                    'success' => true,
                    'message' => 'All images analyzed',
                    'complete' => false,
                    'percent' => 20
                );
            } else {
                // Update image index for next chunk
                $this->set_image_index($new_image_index);
                
                return array(
                    'success' => true,
                    'message' => "Analyzed " . $new_image_index . " of " . $total_images . " images",
                    'complete' => false,
                    'percent' => $percent
                );
            }
        } catch (Exception $e) {
            error_log("Exception in analyze_images: " . $e->getMessage());
            return array(
                'success' => false,
                'message' => 'Error analyzing images: ' . $e->getMessage(),
                'complete' => false,
                'percent' => 8
            );
        }
    }
    
    /**
     * Generate course outline
     */
    private function generate_outline($course_data) {
        error_log("Generate outline step started");
        
        try {
            // Update progress
            $this->update_progress($course_data['id'], array(
                'percent_complete' => 20,
                'current_task' => 'Generating course outline...',
                'status' => 'generating_outline'
            ));
            
            // Check if we have a parent reference and it has the necessary method
            if ($this->parent && method_exists($this->parent, 'generate_course_outline')) {
                error_log("Using parent's generate_course_outline method");
                
                try {
                    // Call the parent method to generate an AI-powered outline
                    $outline = $this->parent->generate_course_outline($course_data);
                    
                    // Check if we got a valid outline
                    if (is_array($outline) && !empty($outline)) {
                        error_log("Successfully generated course outline");
                        
                        // Merge the AI-generated outline with the course data
                        if (isset($outline['chapters'])) {
                            $course_data['chapters'] = $outline['chapters'];
                        }
                        
                        if (isset($outline['quiz'])) {
                            $course_data['quiz'] = $outline['quiz'];
                        }
                        
                        if (isset($outline['estimatedTime'])) {
                            $course_data['estimatedTime'] = $outline['estimatedTime'];
                        }
                    } else {
                        error_log("generate_course_outline returned invalid data, using fallback");
                        $this->use_fallback_outline($course_data);
                    }
                } catch (Exception $e) {
                    error_log("Error in generate_course_outline: " . $e->getMessage());
                    $this->use_fallback_outline($course_data);
                }
            } else {
                // Fallback to a simple outline if parent method is not available
                error_log("Parent method 'generate_course_outline' not available, using fallback");
                $this->use_fallback_outline($course_data);
            }
            
            // Save course data
            $this->save_course_data($course_data);
            
            // Update progress
            $this->update_progress($course_data['id'], array(
                'percent_complete' => 30,
                'current_task' => 'Course outline created',
                'status' => 'outline_complete',
                'total_chapters' => count($course_data['chapters'])
            ));
            
            // Move to chapter intro step
            $this->set_process_step('chapter_intro');
            $this->set_chapter_index(0);
            
            error_log("Generate outline completed successfully");
            return array(
                'success' => true,
                'message' => 'Outline generated',
                'complete' => false,
                'percent' => 30
            );
        } catch (Exception $e) {
            error_log("Exception in generate_outline: " . $e->getMessage());
            return array(
                'success' => false,
                'message' => 'Error generating outline: ' . $e->getMessage(),
                'complete' => false,
                'percent' => 20
            );
        }
    }

    // Helper method for fallback outline
    private function use_fallback_outline(&$course_data) {
        error_log("Creating fallback outline");
        
        $topics = explode("\n", $course_data['mainTopics']);
        $topics = array_map('trim', $topics);
        $topics = array_filter($topics);
        
        // Get valid image filenames
        $valid_image_filenames = array();
        if (!empty($course_data['images'])) {
            foreach ($course_data['images'] as $image) {
                if (!empty($image['filename'])) {
                    $valid_image_filenames[] = $image['filename'];
                }
            }
        }
        
        // If parent has fallback method, use it
        if ($this->parent && method_exists($this->parent, 'generate_fallback_outline')) {
            error_log("Using parent's generate_fallback_outline method");
            $outline = $this->parent->generate_fallback_outline($course_data, $topics, $valid_image_filenames);
            
            if (isset($outline['chapters'])) {
                $course_data['chapters'] = $outline['chapters'];
            }
            
            if (isset($outline['quiz'])) {
                $course_data['quiz'] = $outline['quiz'];
            }
            
            if (isset($outline['estimatedTime'])) {
                $course_data['estimatedTime'] = $outline['estimatedTime'];
            }
        } else {
            // Basic fallback if parent method isn't available
            error_log("Using internal basic fallback outline creator");
            $chapters = array();
            
            if (empty($topics)) {
                $topics = array('Introduction', 'Key Concepts', 'Practical Applications', 'Advanced Topics');
            }
            
            // Use at most 6 topics
            $chapter_topics = array_slice($topics, 0, 6);
            
            foreach ($chapter_topics as $index => $topic) {
                $chapter = array(
                    'title' => "Chapter " . ($index + 1) . ": " . $topic,
                    'introduction' => "Introduction to " . $topic,
                    'sections' => array(
                        array(
                            'title' => 'Section 1: Overview',
                            'content' => 'Content for section 1',
                            'image' => !empty($valid_image_filenames) && isset($valid_image_filenames[$index % count($valid_image_filenames)]) ? 
                                    $valid_image_filenames[$index % count($valid_image_filenames)] : ''
                        ),
                        array(
                            'title' => 'Section 2: Details',
                            'content' => 'Content for section 2',
                            'image' => ''
                        )
                    )
                );
                $chapters[] = $chapter;
            }
            
            // Add quiz
            $quiz = array(
                'title' => 'Final Assessment',
                'description' => 'Test your knowledge',
                'questions' => array(
                    array(
                        'question' => 'Sample question 1?',
                        'type' => 'multiple-choice',
                        'options' => array('Option A', 'Option B', 'Option C', 'Option D'),
                        'correctAnswer' => 0
                    ),
                    array(
                        'question' => 'Sample question 2?',
                        'type' => 'true-false',
                        'correctAnswer' => 0
                    )
                )
            );
            
            // Update course data
            $course_data['chapters'] = $chapters;
            $course_data['quiz'] = $quiz;
            $course_data['estimatedTime'] = '2 hours';
        }
        
        error_log("Fallback outline created with " . count($course_data['chapters']) . " chapters");
    }
    
    /**
     * Process chapter introduction
     */
    private function process_chapter_intro($course_data) {
        // Get current chapter
        if (!isset($course_data['chapters'][$this->chapter_index])) {
            // No more chapters, move to section content
            $this->set_process_step('section_content');
            $this->set_chapter_index(0);
            $this->set_section_index(0);
            
            return array(
                'success' => true,
                'message' => 'All chapter introductions processed',
                'complete' => false,
                'percent' => 40
            );
        }
        
        // Process current chapter intro
        $chapter = &$course_data['chapters'][$this->chapter_index];
        
        // Use AI to expand the chapter introduction
        if ($this->parent && method_exists($this->parent, 'expand_chapter_introduction')) {
            // Call the parent method to generate improved introduction
            $chapter['introduction'] = $this->parent->expand_chapter_introduction(
                $chapter['title'],
                $chapter['introduction'],
                $course_data['title']
            );
        } else {
            // Fallback to a simple enhancement
            error_log("Parent method 'expand_chapter_introduction' not available, using fallback");
            $chapter['introduction'] = "<p>This is an expanded introduction to the chapter on {$chapter['title']}. This chapter will cover the fundamental concepts, practical applications, and best practices related to this topic.</p><p>By the end of this chapter, you will have a solid understanding of the key principles and be ready to apply them in real-world scenarios.</p>";
        }
        
        // Save course data
        $this->save_course_data($course_data);
        
        // Update progress
        $percent = 40 + round($this->chapter_index / count($course_data['chapters']) * 10);
        $this->update_progress($course_data['id'], array(
            'percent_complete' => $percent,
            'current_task' => "Expanded chapter " . ($this->chapter_index + 1) . " introduction",
            'current_chapter' => $this->chapter_index + 1,
            'total_chapters' => count($course_data['chapters']),
            'status' => 'processing_chapters'
        ));
        
        // Move to next chapter
        $this->set_chapter_index($this->chapter_index + 1);
        
        return array(
            'success' => true,
            'message' => 'Chapter introduction processed',
            'complete' => false,
            'percent' => $percent
        );
    }
    
    /**
     * Process section content
     */
    private function process_section_content($course_data) {
        // Check if we need to move to the next chapter
        if (!isset($course_data['chapters'][$this->chapter_index])) {
            // No more chapters, move to finalize
            $this->set_process_step('finalize');
            
            return array(
                'success' => true,
                'message' => 'All sections processed',
                'complete' => false,
                'percent' => 90
            );
        }
        
        $chapter = &$course_data['chapters'][$this->chapter_index];
        
        // Check if we need to move to the next section
        if (!isset($chapter['sections'][$this->section_index])) {
            // Move to next chapter
            $this->set_chapter_index($this->chapter_index + 1);
            $this->set_section_index(0);
            
            return array(
                'success' => true,
                'message' => 'Moving to next chapter',
                'complete' => false,
                'percent' => 50 + round(($this->chapter_index / count($course_data['chapters'])) * 40)
            );
        }
        
        // Process current section
        $section = &$chapter['sections'][$this->section_index];
        
        // Find image info for this section if available
        $image_info = null;
        if (!empty($section['image']) && isset($course_data['images'])) {
            foreach ($course_data['images'] as $image) {
                if (isset($image['filename']) && $image['filename'] === $section['image']) {
                    $image_info = array(
                        'filename' => $image['filename'],
                        'description' => isset($image['description']) ? $image['description'] : '',
                        'analysis' => isset($image['analysis']) ? $image['analysis'] : ''
                    );
                    break;
                }
            }
        }
        
        // Use AI to expand the section content
        if ($this->parent && method_exists($this->parent, 'expand_section_content')) {
            // Call the parent method to generate detailed content
            $section['content'] = $this->parent->expand_section_content(
                $chapter['title'],
                $section['title'],
                $section['content'],
                $course_data['title'],
                $image_info
            );
            
            // Add estimated time if not present
            if (!isset($section['estimatedTime'])) {
                // Roughly estimate based on word count (stripped of HTML tags)
                $word_count = str_word_count(strip_tags($section['content']));
                $minutes = ceil($word_count / 350) + 2; // Average reading speed of 350 wpm + time for reflection
                $section['estimatedTime'] = "$minutes minutes";
            }
        } else {
            // Fallback to a simple enhancement
            error_log("Parent method 'expand_section_content' not available, using fallback");
            
            // Use fallback method if available
            if ($this->parent && method_exists($this->parent, 'format_section_content_fallback')) {
                $section['content'] = $this->parent->format_section_content_fallback($section['content'], $image_info);
            } else {
                // Very basic fallback
                $section['content'] = "<p>This section covers key concepts related to <strong>{$section['title']}</strong> within the context of {$chapter['title']}.</p>";
                
                // Add some structured content
                $section['content'] .= "<h4>Key Points</h4><ul><li>Understanding the fundamental principles</li><li>Practical applications in real-world scenarios</li><li>Best practices and common pitfalls</li></ul>";
                
                // Add image if available
                if ($image_info) {
                    $alt_text = !empty($image_info['description']) ? $image_info['description'] : 'Image illustration for ' . $section['title'];
                    $section['content'] .= "<figure class=\"section-image\"><img src=\"{$image_info['filename']}\" alt=\"" . esc_attr($alt_text) . "\" /><figcaption>Visual illustration related to this section.</figcaption></figure>";
                    $section['content'] .= "<p>As shown in the image above, these concepts can be better understood through visual representation.</p>";
                }
                
                $section['content'] .= "<h4>Summary</h4><p>By mastering the content in this section, you'll be better equipped to apply these concepts in various contexts and continue building your skills in this area.</p>";
            }
            
            // Set default estimated time
            $section['estimatedTime'] = "15 minutes";
        }
        
        // Save course data after each section (crucial for reliability)
        $this->save_course_data($course_data);
        
        // Calculate progress
        $total_sections = 0;
        $completed_sections = 0;
        
        foreach ($course_data['chapters'] as $ch_index => $ch) {
            $total_sections += count($ch['sections']);
            
            if ($ch_index < $this->chapter_index) {
                $completed_sections += count($ch['sections']);
            } else if ($ch_index == $this->chapter_index) {
                $completed_sections += $this->section_index + 1;
            }
        }
        
        $percent = 50 + round(($completed_sections / $total_sections) * 40);
        
        // Update progress
        $this->update_progress($course_data['id'], array(
            'percent_complete' => $percent,
            'current_task' => "Processed section " . ($this->section_index + 1) . " in chapter " . ($this->chapter_index + 1),
            'current_chapter' => $this->chapter_index + 1,
            'current_section' => $this->section_index + 1,
            'status' => 'processing_sections'
        ));
        
        // Move to next section
        $this->set_section_index($this->section_index + 1);
        
        return array(
            'success' => true,
            'message' => 'Section content processed',
            'complete' => false,
            'percent' => $percent
        );
    }

    /**
     * Finalize course
     */
    private function finalize_course($course_data) {
        // Ensure metadata is optimized one last time
        if ($this->parent && method_exists($this->parent, 'optimize_course_metadata')) {
            $course_data = $this->parent->optimize_course_metadata($course_data);
        }
        
        // Calculate total course time based on section times but with new metrics
        $total_minutes = 0;
        $total_words = 0;
        
        foreach ($course_data['chapters'] as $chapter) {
            // Add words from chapter introduction
            if (!empty($chapter['introduction'])) {
                $total_words += str_word_count(strip_tags($chapter['introduction']));
            }
            
            foreach ($chapter['sections'] as $section) {
                // Add words from section content
                if (!empty($section['content'])) {
                    $total_words += str_word_count(strip_tags($section['content']));
                }
            }
        }
        
        // Calculate reading time at 350 words per minute
        $reading_minutes = ceil($total_words / 350);
        
        // Add time for exercises and reflection (2 minutes per section)
        $exercise_minutes = count($course_data['chapters']) * 2;
        
        // Add time for quiz (1 minute per question) if quiz exists
        $quiz_minutes = 0;
        if (isset($course_data['quiz']['questions'])) {
            $quiz_minutes = count($course_data['quiz']['questions']) * 1;
        }
        
        // Use the word-count based calculation
        $total_minutes = $reading_minutes + $exercise_minutes + $quiz_minutes;
        
        // Format total time
        if ($total_minutes < 60) {
            $course_data['estimatedTime'] = "$total_minutes minutes";
        } else {
            $hours = floor($total_minutes / 60);
            $remaining_minutes = $total_minutes % 60;
            
            if ($remaining_minutes > 0) {
                $course_data['estimatedTime'] = "$hours hour" . ($hours > 1 ? "s" : "") . " $remaining_minutes minute" . ($remaining_minutes > 1 ? "s" : "");
            } else {
                $course_data['estimatedTime'] = "$hours hour" . ($hours > 1 ? "s" : "");
            }
        }
        
        // Set final metadata
        $course_data['completed'] = true;
        $course_data['updatedAt'] = current_time('mysql');
        
        // Save course data
        $this->save_course_data($course_data);
        
        // Update progress
        $this->update_progress($course_data['id'], array(
            'percent_complete' => 100,
            'current_task' => 'Course creation completed',
            'status' => 'completed',
            'completed' => true
        ));
        
        return array(
            'success' => true,
            'message' => 'Course creation completed',
            'complete' => true,
            'percent' => 100
        );
    }

    // Helper methods
    private function load_course_data() {
        error_log("Loading course data for ID: {$this->course_id}");
        
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/creator-ai-courses/' . $this->course_id . '.json';
        
        if (!file_exists($file_path)) {
            error_log("Course file does not exist: {$file_path}");
            return false;
        }
        
        $json_data = file_get_contents($file_path);
        $course_data = json_decode($json_data, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON decode error: " . json_last_error_msg());
            return false;
        }
        
        error_log("Course data loaded successfully");
        return $course_data;
    }
    
    private function save_course_data($course_data) {
        $upload_dir = wp_upload_dir();
        $courses_dir = $upload_dir['basedir'] . '/creator-ai-courses';
        
        if (!file_exists($courses_dir)) {
            wp_mkdir_p($courses_dir);
        }
        
        $file_path = $courses_dir . '/' . $this->course_id . '.json';
        file_put_contents($file_path, json_encode($course_data, JSON_PRETTY_PRINT));
    }
    
    private function update_progress($course_id, $data) {
        $progress = get_transient('cai_course_progress_' . $course_id);
        
        if (!$progress) {
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
        }
        
        $progress = array_merge($progress, $data);
        $progress['last_updated'] = time();
        
        set_transient('cai_course_progress_' . $course_id, $progress, HOUR_IN_SECONDS);
    }

    // Add these public methods to allow external access
    public function set_process_step($step) {
        set_transient('cai_course_process_step_' . $this->course_id, $step, DAY_IN_SECONDS);
        $this->step = $step;
    }

    public function set_chapter_index($index) {
        set_transient('cai_course_chapter_index_' . $this->course_id, $index, DAY_IN_SECONDS);
        $this->chapter_index = $index;
    }

    public function set_section_index($index) {
        set_transient('cai_course_section_index_' . $this->course_id, $index, DAY_IN_SECONDS);
        $this->section_index = $index;
    }

    public function set_image_index($index) {
        set_transient('cai_course_image_index_' . $this->course_id, $index, DAY_IN_SECONDS);
        $this->image_index = $index;
    }

    private function get_process_step() {
        return get_transient('cai_course_process_step_' . $this->course_id) ?: 'init';
    }
    
    private function get_chapter_index() {
        return (int) get_transient('cai_course_chapter_index_' . $this->course_id) ?: 0;
    }
    
    private function get_section_index() {
        return (int) get_transient('cai_course_section_index_' . $this->course_id) ?: 0;
    }
    
    private function get_image_index() {
        return (int) get_transient('cai_course_image_index_' . $this->course_id) ?: 0;
    }
}