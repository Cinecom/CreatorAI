<?php

trait Creator_AI_Course_Publisher_Functions {


    
    /**
     * Initialize course publisher functionality
     */
    public function initialize_course_publisher() {


        // 1) Register the custom post type with a dynamic slug based on your “Courses” page
        if ( ! post_type_exists( 'cai_course' ) ) {

            // Determine the Courses page
            $courses_page_id = get_option( 'cai_courses_page_id', 0 );
            $courses_page    = $courses_page_id ? get_post( $courses_page_id ) : null;

            // Bail if no published Courses page is set
            if ( ! $courses_page || $courses_page->post_status !== 'publish' ) {
                return;
            }

            // Use the page’s slug as your CPT rewrite base
            $courses_slug = $courses_page->post_name;

            $labels = array(
                'name'               => _x( 'Courses', 'post type general name', 'creator-ai' ),
                'singular_name'      => _x( 'Course', 'post type singular name', 'creator-ai' ),
                'menu_name'          => _x( 'AI Courses', 'admin menu', 'creator-ai' ),
                'name_admin_bar'     => _x( 'Course', 'add new on admin bar', 'creator-ai' ),
                'add_new'            => _x( 'Add New', 'course', 'creator-ai' ),
                'add_new_item'       => __( 'Add New Course', 'creator-ai' ),
                'new_item'           => __( 'New Course', 'creator-ai' ),
                'edit_item'          => __( 'Edit Course', 'creator-ai' ),
                'view_item'          => __( 'View Course', 'creator-ai' ),
                'all_items'          => __( 'All Courses', 'creator-ai' ),
                'search_items'       => __( 'Search Courses', 'creator-ai' ),
                'parent_item_colon'  => __( 'Parent Courses:', 'creator-ai' ),
                'not_found'          => __( 'No courses found.', 'creator-ai' ),
                'not_found_in_trash' => __( 'No courses found in Trash.', 'creator-ai' ),
            );

            $args = array(
                'labels'             => $labels,
                'description'        => __( 'AI-generated educational courses', 'creator-ai' ),
                'public'             => true,
                'publicly_queryable' => true,
                'show_ui'            => true,
                'show_in_menu'       => 'creator-ai',
                'query_var'          => true,
                'rewrite'            => array(
                    'slug'       => $courses_slug,
                    'with_front' => false,
                ),
                'has_archive'        => false,
                'hierarchical'       => false,
                'supports'           => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields' ),
                'show_in_rest'       => true,
            );

            register_post_type( 'cai_course', $args );

            // 2) Immediately add the single-course rewrite rule
            add_rewrite_rule(
                '^' . $courses_slug . '/([^/]+)/?$',
                'index.php?post_type=cai_course&name=$matches[1]',
                'top'
            );
            set_transient( 'cai_flush_rules_needed', true );
        }

        // 3) Core hooks (shortcode, permalink filter, AJAX, content, assets, access control)
        add_shortcode( 'creator_ai_course', array( $this, 'course_shortcode' ) );
        add_filter( 'post_type_link',   array( $this, 'filter_course_permalink' ), 10, 2 );
        add_action( 'wp_ajax_cai_publish_course',        array( $this, 'publish_course' ) );
        add_action( 'wp_ajax_cai_get_course_progress_user', array( $this, 'get_user_course_progress' ) );
        add_action( 'wp_ajax_cai_mark_section_complete', array( $this, 'mark_section_complete' ) );
        add_action( 'wp_ajax_cai_submit_quiz',           array( $this, 'submit_quiz' ) );
        add_action( 'wp_ajax_cai_generate_certificate',  array( $this, 'generate_certificate' ) );
        add_action( 'wp_ajax_nopriv_cai_get_course_preview', array( $this, 'get_course_preview' ) );
        add_filter( 'the_content', array( $this, 'display_course_content' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_assets' ) );
        add_action( 'template_redirect',   array( $this, 'check_course_access' ) );

        // 4) Flush rewrite rules once, after our rule has been added
        add_action( 'init', function() {
            if ( get_transient( 'cai_flush_rules_needed' ) ) {
                flush_rewrite_rules();
                delete_transient( 'cai_flush_rules_needed' );
            }
        }, 20 );

        // Initialize layout filters
        $this->initialize_layout_filters();
    }


    public function filter_course_permalink($permalink, $post) {
        if ($post->post_type !== 'cai_course') {
            return $permalink;
        }
        
        // Get courses page
        $courses_page_id = get_option('cai_courses_page_id', 0);
        if (!$courses_page_id) {
            return $permalink;
        }
        
        $courses_page = get_post($courses_page_id);
        if (!$courses_page || $courses_page->post_status !== 'publish') {
            return $permalink;
        }
        
        // Build URL with courses page as parent
        $courses_url = get_permalink($courses_page_id);
        $course_slug = $post->post_name;
        
        // Remove trailing slash if present
        $courses_url = rtrim($courses_url, '/');
        
        // Create new URL
        return $courses_url . '/' . $course_slug . '/';
    }



    /**
     * Add custom rewrite rules for courses
     */
    public function add_course_rewrite_rules() {
        $courses_page_id = get_option('cai_courses_page_id', 0);
        
        if (!$courses_page_id) {
            return;
        }
        
        $courses_page = get_post($courses_page_id);
        
        if (!$courses_page || $courses_page->post_status !== 'publish') {
            return;
        }
        
        // Get the slug of the courses page
        $courses_slug = $courses_page->post_name;
        
        // Add rewrite rule for courses under the courses page
        add_rewrite_rule(
            '^' . $courses_slug . '/([^/]+)/?$',
            'index.php?post_type=cai_course&name=$matches[1]',
            'top'
        );
        
        // Add rewrite rule for certificate view
        add_rewrite_rule(
            '^' . $courses_slug . '/([^/]+)/certificate/?$',
            'index.php?post_type=cai_course&name=$matches[1]&certificate=view',
            'top'
        );
        
        // Tell WordPress that 'certificate' is a valid query var
        global $wp;
        $wp->add_query_var('certificate');
        
        // Set flag to flush rewrite rules (will be handled in register_course_post_type)
        set_transient('cai_flush_rules_needed', true);
    }
    
    /**
     * Enqueue public-facing scripts and styles
     */
    public function enqueue_public_assets() {
        // Only load on course pages
        if (is_singular('cai_course') || $this->is_courses_page()) {
            // Get settings
            $appearance_settings = $this->get_course_appearance_settings();
            
            // Enqueue styles
            wp_enqueue_style(
                'cai-course-publisher-style',
                plugin_dir_url(dirname(__FILE__)) . 'css/course-publisher-style.css',
                array(),
                $this->version
            );
            
            // Generate dynamic CSS based on settings
            $dynamic_css = $this->generate_dynamic_course_css($appearance_settings);
            wp_add_inline_style('cai-course-publisher-style', $dynamic_css);
            
            // Enqueue scripts
            wp_enqueue_script(
                'cai-course-publisher-js',
                plugin_dir_url(dirname(__FILE__)) . 'js/course-publisher.js',
                array('jquery'),
                $this->version,
                true
            );
            
            // Add PDF library for certificates (only when needed)
            if (is_singular('cai_course') && isset($_GET['certificate'])) {
                wp_enqueue_script(
                    'html2pdf', 
                    'https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js',
                    array(),
                    '0.10.1',
                    true
                );
            }
            
            // Localize script with data
            $localize_data = array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('cai_course_nonce'),
                'i18n' => array(
                    'mark_complete' => __('Mark Complete', 'creator-ai'),
                    'marked_complete' => __('Completed', 'creator-ai'),
                    'submit_quiz' => __('Submit Answers', 'creator-ai'),
                    'try_again' => __('Try Again', 'creator-ai'),
                    'certificate_title' => __('Certificate of Completion', 'creator-ai'),
                    'generating_certificate' => __('Generating your certificate...', 'creator-ai'),
                    'download_certificate' => __('Download Certificate', 'creator-ai')
                )
            );
            
            // If on a course page, add course-specific data
            if (is_singular('cai_course')) {
                global $post;
                $course_id = get_post_meta($post->ID, '_cai_course_id', true);
                
                if ($course_id) {
                    $course_data = $this->load_course_file($course_id);
                    if ($course_data) {
                        $user_id = get_current_user_id();
                        $user_progress = $this->get_user_course_progress_data($user_id, $course_id);
                        
                        $localize_data['course_id'] = $course_id;
                        $localize_data['post_id'] = $post->ID;
                        $localize_data['user_progress'] = $user_progress;
                        $localize_data['course_title'] = $course_data['title'];
                        $localize_data['total_sections'] = $this->count_total_sections($course_data);
                    }
                }
            }
            
            wp_localize_script('cai-course-publisher-js', 'caiCourse', $localize_data);
        }
    }
    
    /**
     * Generate dynamic CSS for courses based on settings
     */
    private function generate_dynamic_course_css($settings) {
        $css = ":root {";
        
        // Colors
        $css .= "--cai-primary-color: " . esc_attr($settings['primary_color']) . ";";
        $css .= "--cai-secondary-color: " . esc_attr($settings['secondary_color']) . ";";
        $css .= "--cai-text-color: " . esc_attr($settings['text_color']) . ";";
        $css .= "--cai-background-color: " . esc_attr($settings['background_color']) . ";";
        $css .= "--cai-sidebar-bg-color: " . esc_attr($settings['sidebar_bg_color']) . ";";
        $css .= "--cai-accent-color: " . esc_attr($settings['accent_color']) . ";";
        
        // Typography
        $css .= "--cai-heading-font: " . esc_attr($settings['heading_font']) . ";";
        $css .= "--cai-body-font: " . esc_attr($settings['body_font']) . ";";
        $css .= "--cai-base-font-size: " . esc_attr($settings['base_font_size']) . "px;";
        $css .= "--cai-heading-font-size: " . esc_attr($settings['heading_font_size']) . "px;";
        $css .= "--cai-subheading-font-size: " . esc_attr($settings['subheading_font_size']) . "px;";
        
        // Spacing
        $css .= "--cai-content-width: " . esc_attr($settings['content_width']) . ";";
        $css .= "--cai-content-padding: " . esc_attr($settings['content_padding']) . "px;";
        $css .= "--cai-section-spacing: " . esc_attr($settings['section_spacing']) . "px;";
        
        // Layout
        $css .= "--cai-border-radius: " . esc_attr($settings['border_radius']) . "px;";
        $css .= "--cai-sidebar-width: " . esc_attr($settings['sidebar_width']) . "px;";
        
        $css .= "}";
        
        // Certificate styles
        $css .= ".cai-certificate {";
        $css .= "background-image: url(" . esc_url($settings['certificate_background']) . ");";
        $css .= "border: " . esc_attr($settings['certificate_border_width']) . "px solid " . esc_attr($settings['certificate_border_color']) . ";";
        $css .= "}";
        
        $css .= ".cai-certificate-title {";
        $css .= "color: " . esc_attr($settings['certificate_title_color']) . ";";
        $css .= "font-family: " . esc_attr($settings['certificate_font']) . ";";
        $css .= "font-size: " . esc_attr($settings['certificate_title_size']) . "px;";
        $css .= "}";
        
        return $css;
    }
    
    /**
     * Get course appearance settings with defaults
     */
    private function get_course_appearance_settings() {
        $defaults = array(
            // Colors
            'primary_color' => '#3e69dc',
            'secondary_color' => '#2c3e50',
            'text_color' => '#333333',
            'background_color' => '#ffffff',
            'sidebar_bg_color' => '#f8f9fa',
            'accent_color' => '#e74c3c',
            
            // Typography
            'heading_font' => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif',
            'body_font' => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif',
            'base_font_size' => 16,
            'heading_font_size' => 28,
            'subheading_font_size' => 20,
            
            // Layout
            'content_width' => '1100px',
            'content_padding' => 20,
            'section_spacing' => 30,
            'border_radius' => 6,
            'sidebar_width' => 280,
            'sidebar_position' => 'left',
            'sidebar_behavior' => 'sticky',
            
            // Progress
            'progress_tracking' => 'enabled',
            'progress_indicator' => 'circle',
            'display_progress_bar' => true,
            
            // Quiz
            'quiz_highlight_correct' => true,
            'show_quiz_results' => true,
            'quiz_pass_percentage' => 70,
            
            // Certificate 
            'certificate_enabled' => true,
            'certificate_layout' => 'standard',
            'certificate_border_width' => 5,
            'certificate_border_color' => '#c0a080',
            'certificate_background' => plugin_dir_url(dirname(__FILE__)) . 'assets/certificate-background.jpg',
            'certificate_logo' => '',
            'certificate_font' => 'Georgia, serif',
            'certificate_title_color' => '#3e69dc',
            'certificate_text_color' => '#333333',
            'certificate_title_size' => 32,
            'certificate_signature_image' => '',
            'certificate_company_name' => get_bloginfo('name'),
            
            // Access Control
            'course_access' => 'public',
            'required_role' => 'subscriber'
        );
        
        // Get saved settings
        $saved_settings = get_option('cai_course_appearance_settings', array());
        
        // Merge with defaults
        return wp_parse_args($saved_settings, $defaults);
    }

    /**
     * Display course content
     */
    public function display_course_content($content) {
        if (!is_singular('cai_course')) {
            return $content;
        }
        
        global $post;
        
        // Get course ID from post meta
        $course_id = get_post_meta($post->ID, '_cai_course_id', true);
        
        if (!$course_id) {
            return $content;
        }
        
        // Check if viewing certificate
        if (isset($_GET['certificate']) && $_GET['certificate'] == 'view') {
            return $this->generate_certificate_html($course_id, $post->ID);
        }
        
        // Load course data from JSON
        $course_data = $this->load_course_file($course_id);
        
        if (!$course_data) {
            return '<div class="cai-course-error">' . __('Course data not found. Please contact the administrator.', 'creator-ai') . '</div>';
        }
        
        // Render course content
        $course_html = $this->render_course_content($course_data, $post->ID);
        
        // Replace the content
        return $course_html;
    }
    
    /**
     * Render full course content
     */
    private function render_course_content($course_data, $post_id) {
        // Get course access settings
        $course_settings = $this->get_course_appearance_settings();
        $user_id = get_current_user_id();
        
        // Get user progress data
        $user_progress = $this->get_user_course_progress_data($user_id, $course_data['id']);
        
        // Start building the HTML
        ob_start();
        ?>
        <div class="cai-course-container" data-course-id="<?php echo esc_attr($course_data['id']); ?>">
            <!-- Course Header -->
            <header class="cai-course-header">
                <?php if (!empty($course_data['coverImage'])): ?>
                <div class="cai-course-banner">
                    <img src="<?php echo esc_url($this->get_course_image_url($course_data['coverImage'])); ?>" 
                         alt="<?php echo esc_attr($course_data['title']); ?>" 
                         class="cai-course-banner-img">
                </div>
                <?php endif; ?>
                
                <div class="cai-course-meta">
                    <?php if (!empty($course_data['estimatedTime'])): ?>
                    <div class="cai-course-time">
                        <span class="cai-course-header-text">
                            <span class="cai-course-time-icon">⏱</span>
                            <span class="cai-course-time-text"><?php echo esc_html($course_data['estimatedTime']); ?></span>
                        </span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($course_data['difficulty'])): ?>
                    <div class="cai-course-difficulty">
                        <span class="cai-course-header-text">
                            <span class="cai-course-difficulty-icon">📊</span>
                            <span class="cai-course-difficulty-text"><?php echo esc_html(ucfirst($course_data['difficulty'])); ?></span>
                        </span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($course_settings['progress_tracking'] === 'enabled' && $user_id): ?>
                    <div class="cai-course-progress">
                        <span class="cai-course-header-text">
                            <span class="cai-course-progress-icon">📈</span>
                            <span class="cai-course-progress-text">
                                <?php 
                                $completed = isset($user_progress['completed_sections']) ? count($user_progress['completed_sections']) : 0;
                                $total = $this->count_total_sections($course_data);
                                $percentage = $total > 0 ? round(($completed / $total) * 100) : 0;
                                echo sprintf(__('%d%% Complete', 'creator-ai'), $percentage); 
                                ?>
                            </span>
                        </span>
                        <?php if ($course_settings['display_progress_bar']): ?>
                        <div class="cai-progress-bar">
                            <div class="cai-progress-bar-fill" style="width: <?php echo esc_attr($percentage); ?>%"></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <h1 class="cai-course-title"><?php echo esc_html($course_data['title']); ?></h1>
                
                <div class="cai-course-description">
                    <?php echo wp_kses_post($course_data['description']); ?>
                </div>
                
                <?php if (!empty($course_data['targetAudience'])): ?>
                <div class="cai-course-audience">
                    <h3><?php _e('Who This Course is For', 'creator-ai'); ?></h3>
                    <p><?php echo esc_html($course_data['targetAudience']); ?></p>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($course_data['learningObjectives'])): ?>
                <div class="cai-course-objectives">
                    <h3><?php _e('Learning Objectives', 'creator-ai'); ?></h3>
                    <div class="cai-course-objectives-content">
                        <?php echo wp_kses_post($course_data['learningObjectives']); ?>
                    </div>
                </div>
                <?php endif; ?>
            </header>
            
            <!-- Course Content Area -->
            <div class="cai-course-content-wrapper">
                <!-- Course Navigation Sidebar -->
                <nav class="cai-course-sidebar cai-sidebar-<?php echo esc_attr($course_settings['sidebar_position']); ?> cai-sidebar-<?php echo esc_attr($course_settings['sidebar_behavior']); ?>">
                    <div class="cai-course-nav">
                        <h3 class="cai-course-nav-title"><?php _e('Course Content', 'creator-ai'); ?></h3>
                        
                        <ul class="cai-course-chapters">
                            <?php foreach ($course_data['chapters'] as $chapter_index => $chapter): ?>
                            <li class="cai-course-chapter" data-chapter-id="<?php echo esc_attr($chapter_index); ?>">
                                <div class="cai-chapter-header">
                                    <span class="cai-chapter-toggle"></span>
                                    <h4 class="cai-chapter-title"><?php echo esc_html($chapter['title']); ?></h4>
                                </div>
                                
                                <ul class="cai-chapter-sections">
                                    <?php foreach ($chapter['sections'] as $section_index => $section): 
                                        $section_id = "section-{$chapter_index}-{$section_index}";
                                        $is_completed = isset($user_progress['completed_sections']) && 
                                                        in_array($section_id, $user_progress['completed_sections']);
                                    ?>
                                    <li class="cai-section-item <?php echo $is_completed ? 'cai-section-completed' : ''; ?>" 
                                        data-section-id="<?php echo esc_attr($section_id); ?>">
                                        <a href="#<?php echo esc_attr($section_id); ?>" class="cai-section-link">
                                            <?php if ($course_settings['progress_indicator'] === 'circle'): ?>
                                            <span class="cai-progress-circle"></span>
                                            <?php endif; ?>
                                            <span class="cai-section-title"><?php echo esc_html($section['title']); ?></span>
                                        </a>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </li>
                            <?php endforeach; ?>
                            
                            <?php if (isset($course_data['quiz']) && !empty($course_data['quiz']['questions'])): ?>
                            <li class="cai-course-quiz-nav">
                                <a href="#course-quiz" class="cai-quiz-link">
                                    <span class="cai-quiz-icon">📝</span>
                                    <span class="cai-quiz-title"><?php _e('Final Quiz', 'creator-ai'); ?></span>
                                </a>
                            </li>
                            <?php endif; ?>
                            
                            <?php if ($course_settings['certificate_enabled'] && $user_id): ?>
                            <li class="cai-course-certificate-nav">
                                <a href="#course-certificate" class="cai-certificate-link" data-action="view-certificate">
                                    <span class="cai-certificate-icon">🎓</span>
                                    <span class="cai-cert-title"><?php _e('Get Certificate', 'creator-ai'); ?></span>
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </nav>
                
                <!-- Main Course Content -->
                <main class="cai-course-main">
                    <div class="cai-course-chapters-content">
                        <?php foreach ($course_data['chapters'] as $chapter_index => $chapter): ?>
                        <div class="cai-chapter" id="chapter-<?php echo esc_attr($chapter_index); ?>">
                            <h2 class="cai-chapter-heading"><?php echo esc_html($chapter['title']); ?></h2>
                            
                            <div class="cai-chapter-intro">
                                <?php echo wp_kses_post($chapter['introduction']); ?>
                            </div>
                            
                            <?php foreach ($chapter['sections'] as $section_index => $section): 
                                $section_id = "section-{$chapter_index}-{$section_index}";
                                $is_completed = isset($user_progress['completed_sections']) && 
                                                in_array($section_id, $user_progress['completed_sections']);
                            ?>
                            <div class="cai-section" id="<?php echo esc_attr($section_id); ?>">
                                <div class="cai-section-header">
                                    <h3 class="cai-section-heading"><?php echo esc_html($section['title']); ?></h3>
                                    
                                    <?php if ($course_settings['progress_tracking'] === 'enabled' && $user_id): ?>
                                    <div class="cai-section-completion">
                                        <button class="cai-mark-complete <?php echo $is_completed ? 'cai-completed' : ''; ?>" 
                                                data-section-id="<?php echo esc_attr($section_id); ?>"
                                                <?php echo $is_completed ? 'disabled' : ''; ?>>
                                            <span class="cai-complete-icon"></span>
                                            <span class="cai-complete-text">
                                                <?php echo $is_completed ? __('Completed', 'creator-ai') : __('Mark Complete', 'creator-ai'); ?>
                                            </span>
                                        </button>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="cai-section-content creator-ai-section-content">
                                    <?php 
                                    // Process content to ensure images have proper URLs
                                    $processed_content = $this->process_course_content_images($section['content'], $section['image'] ?? '');
                                    echo wp_kses_post($processed_content); 
                                    ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php if (isset($course_data['quiz']) && !empty($course_data['quiz']['questions'])): ?>
                        <div class="cai-course-quiz" id="course-quiz">
                            <h2 class="cai-quiz-heading"><?php _e('Final Quiz', 'creator-ai'); ?></h2>
                            
                            <?php if (!empty($course_data['quiz']['description'])): ?>
                            <div class="cai-quiz-description">
                                <?php echo wp_kses_post($course_data['quiz']['description']); ?>
                            </div>
                            <?php endif; ?>
                            
                            <form class="cai-quiz-form" id="cai-quiz-form">
                                <input type="hidden" name="course_id" value="<?php echo esc_attr($course_data['id']); ?>">
                                <input type="hidden" name="post_id" value="<?php echo esc_attr($post_id); ?>">
                                
                                <div class="cai-quiz-questions">
                                    <?php foreach ($course_data['quiz']['questions'] as $question_index => $question): ?>
                                    <div class="cai-quiz-question" data-question-index="<?php echo esc_attr($question_index); ?>">
                                        <h4 class="cai-question-text"><?php echo esc_html($question['question']); ?></h4>
                                        
                                        <?php if ($question['type'] === 'multiple-choice'): ?>
                                        <div class="cai-question-options">
                                            <?php foreach ($question['options'] as $option_index => $option): ?>
                                            <div class="cai-question-option">
                                                <input type="radio" 
                                                       id="q<?php echo esc_attr($question_index); ?>-opt<?php echo esc_attr($option_index); ?>" 
                                                       name="q<?php echo esc_attr($question_index); ?>" 
                                                       value="<?php echo esc_attr($option_index); ?>">
                                                <label for="q<?php echo esc_attr($question_index); ?>-opt<?php echo esc_attr($option_index); ?>">
                                                    <?php echo esc_html($option); ?>
                                                    
                                                    <?php 
                                                    // If there's an image for this option
                                                    if (isset($question['image_options']) && !empty($question['image_options'][$option_index])):
                                                        $img_url = $this->get_course_image_url($question['image_options'][$option_index]);
                                                    ?>
                                                    <div class="cai-option-image">
                                                        <img src="<?php echo esc_url($img_url); ?>" 
                                                             alt="<?php echo esc_attr($option); ?>">
                                                    </div>
                                                    <?php endif; ?>
                                                </label>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                        
                                        <?php elseif ($question['type'] === 'true-false'): ?>
                                        <div class="cai-question-options">
                                            <div class="cai-question-option">
                                                <input type="radio" 
                                                       id="q<?php echo esc_attr($question_index); ?>-true" 
                                                       name="q<?php echo esc_attr($question_index); ?>" 
                                                       value="0">
                                                <label for="q<?php echo esc_attr($question_index); ?>-true">
                                                    <?php _e('True', 'creator-ai'); ?>
                                                </label>
                                            </div>
                                            <div class="cai-question-option">
                                                <input type="radio" 
                                                       id="q<?php echo esc_attr($question_index); ?>-false" 
                                                       name="q<?php echo esc_attr($question_index); ?>" 
                                                       value="1">
                                                <label for="q<?php echo esc_attr($question_index); ?>-false">
                                                    <?php _e('False', 'creator-ai'); ?>
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <?php elseif ($question['type'] === 'image-choice' && isset($question['image_options'])): ?>
                                        <div class="cai-question-image-options">
                                            <?php foreach ($question['image_options'] as $option_index => $image_option): 
                                                $img_url = $this->get_course_image_url($image_option);
                                            ?>
                                            <div class="cai-image-option">
                                                <input type="radio" 
                                                       id="q<?php echo esc_attr($question_index); ?>-img<?php echo esc_attr($option_index); ?>" 
                                                       name="q<?php echo esc_attr($question_index); ?>" 
                                                       value="<?php echo esc_attr($option_index); ?>">
                                                <label for="q<?php echo esc_attr($question_index); ?>-img<?php echo esc_attr($option_index); ?>">
                                                    <div class="cai-option-image">
                                                        <img src="<?php echo esc_url($img_url); ?>" 
                                                             alt="<?php _e('Option', 'creator-ai'); ?> <?php echo esc_attr($option_index + 1); ?>">
                                                    </div>
                                                    <span class="cai-image-option-text">
                                                        <?php _e('Option', 'creator-ai'); ?> <?php echo esc_attr($option_index + 1); ?>
                                                    </span>
                                                </label>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <div class="cai-question-feedback" style="display: none;">
                                            <div class="cai-feedback-correct">
                                                <span class="cai-feedback-icon">✓</span>
                                                <span class="cai-feedback-text"><?php _e('Correct!', 'creator-ai'); ?></span>
                                            </div>
                                            <div class="cai-feedback-incorrect">
                                                <span class="cai-feedback-icon">✗</span>
                                                <span class="cai-feedback-text"><?php _e('Incorrect.', 'creator-ai'); ?></span>
                                                <span class="cai-correct-answer"></span>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div class="cai-quiz-actions">
                                    <button type="submit" class="cai-quiz-submit">
                                        <?php _e('Submit Answers', 'creator-ai'); ?>
                                    </button>
                                </div>
                                
                                <div class="cai-quiz-results" style="display: none;">
                                    <h3 class="cai-results-heading"><?php _e('Quiz Results', 'creator-ai'); ?></h3>
                                    <div class="cai-results-summary">
                                        <div class="cai-results-score">
                                            <span class="cai-score-label"><?php _e('Your Score:', 'creator-ai'); ?></span>
                                            <span class="cai-score-value">0%</span>
                                        </div>
                                        <div class="cai-results-message"></div>
                                    </div>
                                    
                                    <div class="cai-results-actions">
                                        <button type="button" class="cai-retry-quiz">
                                            <?php _e('Try Again', 'creator-ai'); ?>
                                        </button>
                                        
                                        <?php if ($course_settings['certificate_enabled'] && $user_id): ?>
                                        <button type="button" class="cai-get-certificate" disabled>
                                            <?php _e('Get Certificate', 'creator-ai'); ?>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($course_settings['certificate_enabled'] && $user_id): ?>
                        <div class="cai-certificate-section" id="course-certificate">
                            <h2 class="cai-certificate-heading"><?php _e('Certificate of Completion', 'creator-ai'); ?></h2>
                            
                            <div class="cai-certificate-info">
                                <p><?php _e('Complete the course to earn your certificate.', 'creator-ai'); ?></p>
                                
                                <?php 
                                $completed = isset($user_progress['completed_sections']) ? count($user_progress['completed_sections']) : 0;
                                $total = $this->count_total_sections($course_data);
                                $percentage = $total > 0 ? round(($completed / $total) * 100) : 0;
                                
                                if ($percentage == 100):
                                ?>
                                <div class="cai-certificate-ready">
                                    <p><?php _e('Congratulations! You have completed the course.', 'creator-ai'); ?></p>
                                    <a href="<?php echo esc_url(add_query_arg('certificate', 'view')); ?>" 
                                       class="cai-view-certificate-btn" 
                                       target="_blank">
                                        <?php _e('View Certificate', 'creator-ai'); ?>
                                    </a>
                                </div>
                                <?php else: ?>
                                <div class="cai-certificate-progress">
                                    <p><?php printf(__('Your progress: %d%%. Complete the course to earn your certificate.', 'creator-ai'), $percentage); ?></p>
                                    <div class="cai-progress-bar">
                                        <div class="cai-progress-bar-fill" style="width: <?php echo esc_attr($percentage); ?>%"></div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </main>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Generate certificate HTML
     */
    private function generate_certificate_html($course_id, $post_id) {
        // Load course data
        $course_data = $this->load_course_file($course_id);
        
        if (!$course_data) {
            return '<div class="cai-course-error">' . __('Course data not found.', 'creator-ai') . '</div>';
        }
        
        // Get user data
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            return '<div class="cai-course-error">' . __('You must be logged in to view your certificate.', 'creator-ai') . '</div>';
        }
        
        $user = get_userdata($user_id);
        $username = $user ? $user->display_name : __('Student', 'creator-ai');
        
        // Get certificate settings
        $settings = $this->get_course_appearance_settings();
        
        // Get completion date
        $user_progress = $this->get_user_course_progress_data($user_id, $course_id);
        $completed_date = isset($user_progress['completion_date']) ? $user_progress['completion_date'] : current_time('mysql');
        $formatted_date = date_i18n(get_option('date_format'), strtotime($completed_date));
        
        // Build certificate
        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="<?php echo esc_attr(get_locale()); ?>">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo esc_html__('Certificate of Completion', 'creator-ai'); ?></title>
            <style>
                body {
                    margin: 0;
                    padding: 0;
                    font-family: <?php echo esc_attr($settings['certificate_font']); ?>;
                    background-color: #f5f5f5;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    min-height: 100vh;
                }
                
                .certificate-container {
                    width: 100%;
                    max-width: 1000px;
                    margin: 20px auto;
                    text-align: center;
                }
                
                .certificate {
                    width: 100%;
                    background-color: white;
                    border: <?php echo esc_attr($settings['certificate_border_width']); ?>px solid <?php echo esc_attr($settings['certificate_border_color']); ?>;
                    padding: 50px;
                    box-sizing: border-box;
                    position: relative;
                    background-image: url('<?php echo esc_url($settings['certificate_background']); ?>');
                    background-size: cover;
                    background-position: center;
                    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
                    page-break-inside: avoid;
                }
                
                .certificate-inner {
                    padding: 20px;
                    border: 1px solid #d0d0d0;
                    background-color: rgba(255, 255, 255, 0.9);
                }
                
                .certificate-header {
                    margin-bottom: 30px;
                }
                
                .certificate-logo {
                    max-width: 200px;
                    max-height: 100px;
                    margin-bottom: 20px;
                }
                
                .certificate-title {
                    font-size: <?php echo esc_attr($settings['certificate_title_size']); ?>px;
                    color: <?php echo esc_attr($settings['certificate_title_color']); ?>;
                    text-transform: uppercase;
                    margin-bottom: 10px;
                    letter-spacing: 2px;
                }
                
                .certificate-subtitle {
                    font-size: 20px;
                    color: #666;
                    margin-bottom: 30px;
                }
                
                .certificate-recipient {
                    font-size: 30px;
                    font-weight: bold;
                    color: #333;
                    margin: 30px 0;
                    font-family: "Times New Roman", Times, serif;
                    border-bottom: 1px solid #e0e0e0;
                    display: inline-block;
                    padding-bottom: 5px;
                }
                
                .certificate-course {
                    font-size: 24px;
                    color: #444;
                    margin: 20px 0;
                }
                
                .certificate-text {
                    font-size: 16px;
                    color: #555;
                    margin: 20px 0;
                    line-height: 1.5;
                }
                
                .certificate-date {
                    font-size: 18px;
                    color: #666;
                    margin: 30px 0;
                }
                
                .certificate-signature {
                    margin: 40px 0 20px;
                }
                
                .signature-image {
                    max-width: 200px;
                    max-height: 80px;
                    margin-bottom: 10px;
                }
                
                .signature-name {
                    font-size: 18px;
                    font-weight: bold;
                    color: #333;
                }
                
                .signature-title {
                    font-size: 14px;
                    color: #666;
                }
                
                .certificate-footer {
                    margin-top: 50px;
                    font-size: 12px;
                    color: #888;
                }
                
                .certificate-verification {
                    font-size: 10px;
                    color: #999;
                    margin-top: 10px;
                }
                
                .certificate-controls {
                    margin: 20px 0;
                }
                
                .download-btn {
                    display: inline-block;
                    padding: 10px 20px;
                    background-color: #3e69dc;
                    color: white;
                    text-decoration: none;
                    border-radius: 4px;
                    font-family: system-ui, sans-serif;
                    font-size: 16px;
                    cursor: pointer;
                    border: none;
                }
                
                .download-btn:hover {
                    background-color: #2d4fa8;
                }
                
                @media print {
                    body {
                        background: none;
                    }
                    
                    .certificate-container {
                        margin: 0;
                    }
                    
                    .certificate {
                        border: none;
                        box-shadow: none;
                    }
                    
                    .certificate-controls {
                        display: none;
                    }
                }
            </style>
        </head>
        <body>
            <div class="certificate-container">
                <div class="certificate-controls">
                    <button id="download-certificate" class="download-btn">
                        <?php _e('Download Certificate (PDF)', 'creator-ai'); ?>
                    </button>
                </div>
                
                <div class="certificate" id="certificate-content">
                    <div class="certificate-inner">
                        <div class="certificate-header">
                            <?php if (!empty($settings['certificate_logo'])): ?>
                            <img src="<?php echo esc_url($settings['certificate_logo']); ?>" 
                                alt="<?php echo esc_attr($settings['certificate_company_name']); ?>" 
                                class="certificate-logo">
                            <?php else: ?>
                            <h2 class="certificate-organization"><?php echo esc_html($settings['certificate_company_name']); ?></h2>
                            <?php endif; ?>
                            
                            <h1 class="certificate-title"><?php _e('Certificate of Completion', 'creator-ai'); ?></h1>
                            <p class="certificate-subtitle"><?php _e('This certifies that', 'creator-ai'); ?></p>
                        </div>
                        
                        <div class="certificate-body">
                            <div class="certificate-recipient"><?php echo esc_html($username); ?></div>
                            
                            <p class="certificate-text">
                                <?php _e('has successfully completed the online course', 'creator-ai'); ?>
                            </p>
                            
                            <h2 class="certificate-course"><?php echo esc_html($course_data['title']); ?></h2>
                            
                            <p class="certificate-date">
                                <?php echo esc_html($formatted_date); ?>
                            </p>
                            
                            <div class="certificate-signature">
                                <?php if (!empty($settings['certificate_signature_image'])): ?>
                                <img src="<?php echo esc_url($settings['certificate_signature_image']); ?>" 
                                    alt="<?php _e('Signature', 'creator-ai'); ?>" 
                                    class="signature-image">
                                <?php else: ?>
                                <div class="signature-placeholder"><?php _e('Signature', 'creator-ai'); ?></div>
                                <?php endif; ?>
                                
                                <div class="signature-name"><?php echo esc_html(get_bloginfo('name')); ?></div>
                                <div class="signature-title"><?php _e('Course Provider', 'creator-ai'); ?></div>
                            </div>
                        </div>
                        
                        <div class="certificate-footer">
                            <p class="certificate-verification">
                                <?php 
                                printf(
                                    __('Certificate ID: %s • Verify at %s', 'creator-ai'),
                                    esc_html($this->generate_certificate_id($user_id, $course_id)),
                                    esc_html(site_url())
                                ); 
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const downloadBtn = document.getElementById('download-certificate');
                    const certificateContent = document.getElementById('certificate-content');
                    
                    downloadBtn.addEventListener('click', function() {
                        downloadBtn.textContent = '<?php _e('Generating PDF...', 'creator-ai'); ?>';
                        downloadBtn.disabled = true;
                        
                        const opt = {
                            margin: 0,
                            filename: '<?php echo esc_js(sanitize_title($course_data['title'])); ?>-certificate.pdf',
                            image: { type: 'jpeg', quality: 0.98 },
                            html2canvas: { scale: 2, useCORS: true },
                            jsPDF: { unit: 'mm', format: 'a4', orientation: 'landscape' }
                        };
                        
                        html2pdf().set(opt).from(certificateContent).save().then(function() {
                            downloadBtn.textContent = '<?php _e('Download Certificate (PDF)', 'creator-ai'); ?>';
                            downloadBtn.disabled = false;
                        });
                    });
                });
            </script>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Generate a unique certificate ID
     */
    private function generate_certificate_id($user_id, $course_id) {
        $base = substr(md5($user_id . $course_id . AUTH_SALT), 0, 12);
        return strtoupper(substr($base, 0, 4) . '-' . substr($base, 4, 4) . '-' . substr($base, 8, 4));
    }

    /**
     * Process course content to ensure images have proper URLs
     */
    private function process_course_content_images($content, $main_image = '') {
        // Replace any relative image paths with full URLs
        $content = preg_replace_callback(
            '/<img\s+([^>]*?)src=[\'"]([^\'"]+)[\'"]([^>]*?)>/i',
            function($matches) {
                $attributes = $matches[1];
                $src = $matches[2];
                $post_attributes = $matches[3];
                
                // Skip if already a full URL
                if (strpos($src, 'http://') === 0 || strpos($src, 'https://') === 0) {
                    return $matches[0];
                }
                
                // Convert to full URL
                $full_url = $this->get_course_image_url($src);
                
                return "<img {$attributes}src=\"{$full_url}\"{$post_attributes}>";
            },
            $content
        );
        
        // If there's a main image for the section but not in content, add it
        if (!empty($main_image) && strpos($content, $main_image) === false) {
            $img_url = $this->get_course_image_url($main_image);
            $img_html = '<figure class="cai-section-main-image">';
            $img_html .= '<img src="' . esc_url($img_url) . '" alt="' . esc_attr__('Section illustration', 'creator-ai') . '">';
            $img_html .= '<figcaption>' . esc_html__('Visual illustration for this section', 'creator-ai') . '</figcaption>';
            $img_html .= '</figure>';
            
            // Find a good position to insert the image
            $first_para_end = strpos($content, '</p>');
            if ($first_para_end !== false) {
                $content = substr_replace($content, '</p>' . $img_html, $first_para_end, 4);
            } else {
                $content = $img_html . $content;
            }
        }
        
        return $content;
    }

    /**
     * Check if current page is the courses listings page
     */
    private function is_courses_page() {
        $courses_page_id = get_option('cai_courses_page_id', 0);
        
        if (!$courses_page_id) {
            return false;
        }
        
        return is_page($courses_page_id);
    }

    /**
     * Check course access permissions and redirect if needed
     */
    public function check_course_access() {
        if (!is_singular('cai_course')) {
            return;
        }
        
        global $post;
        
        // Get course access settings
        $settings = $this->get_course_appearance_settings();
        $access_type = $settings['course_access'];
        
        // Always allow access if course is public
        if ($access_type === 'public') {
            return;
        }
        
        // Check user role access
        if ($access_type === 'user_role') {
            $required_role = $settings['required_role'];
            $user_id = get_current_user_id();
            
            // Not logged in - redirect to login
            if (!$user_id) {
                wp_redirect(wp_login_url(get_permalink($post->ID)));
                exit;
            }
            
            // Check if user has the required role
            $user = get_userdata($user_id);
            if (!$user || !in_array($required_role, $user->roles)) {
                // User doesn't have required role - show access denied
                wp_die(
                    sprintf(
                        __('You need the "%s" role to access this course.', 'creator-ai'),
                        $required_role
                    ),
                    __('Access Denied', 'creator-ai'),
                    array('response' => 403, 'back_link' => true)
                );
            }
        }
    }

    /**
     * AJAX: Publish course
     */
    public function publish_course() {
        // Verify nonce - modify this line to accept either course_nonce or regular nonce
        if (
            (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'cai_nonce')) &&
            (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'cai_course_nonce'))
        ) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('You do not have permission to publish courses');
            return;
        }
        
        // Get course ID
        $course_id = isset($_POST['course_id']) ? sanitize_text_field($_POST['course_id']) : '';
        
        if (empty($course_id)) {
            wp_send_json_error('No course ID provided');
            return;
        }
        
        // Check if courses page is set
        $courses_page_id = get_option('cai_courses_page_id', 0);
        
        if (!$courses_page_id) {
            wp_send_json_error('Courses page not set. Please configure it in the settings.');
            return;
        }
        
        // Load course data
        $course_data = $this->load_course_file($course_id);
        
        if (!$course_data) {
            wp_send_json_error('Course data not found');
            return;
        }
        
        // Check if course is already published
        $existing_post_id = $this->get_course_post_id($course_id);
        
        if ($existing_post_id) {
            // Update existing post
            $result = $this->update_course_post($existing_post_id, $course_data);
            
            if ($result) {
                wp_send_json_success(array(
                    'message' => 'Course updated successfully',
                    'post_id' => $existing_post_id,
                    'permalink' => get_permalink($existing_post_id)
                ));
            } else {
                wp_send_json_error('Failed to update course');
            }
        } else {
            // Create new course post
            $post_id = $this->create_course_post($course_data);
            
            if ($post_id) {
                // Set post as course
                update_post_meta($post_id, '_cai_course_id', $course_id);
                
                wp_send_json_success(array(
                    'message' => 'Course published successfully',
                    'post_id' => $post_id,
                    'permalink' => get_permalink($post_id)
                ));
            } else {
                wp_send_json_error('Failed to publish course');
            }
        }

        flush_rewrite_rules();
    }
    
    /**
     * Create a new course post
     */
    private function create_course_post($course_data) {
        // Generate clean, SEO-friendly slug
        $slug = $this->generate_seo_friendly_slug($course_data['title']);
        
        // Create post data
        $post_data = array(
            'post_title'    => $course_data['title'],
            'post_content'  => '<!-- wp:shortcode -->[creator_ai_course id="' . $course_data['id'] . '"]<!-- /wp:shortcode -->',
            'post_excerpt'  => $this->get_course_excerpt($course_data),
            'post_status'   => 'publish',
            'post_type'     => 'cai_course',
            'post_name'     => $slug,
        );
        
        // Insert post
        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            error_log('Error creating course post: ' . $post_id->get_error_message());
            return false;
        }
        
        // Set featured image if available
        if (!empty($course_data['coverImage'])) {
            $this->set_course_featured_image($post_id, $course_data['coverImage'], $course_data['title']);
        }
        
        // Set course metadata
        update_post_meta($post_id, '_cai_course_id', $course_data['id']);
        update_post_meta($post_id, '_cai_course_difficulty', $course_data['difficulty']);
        update_post_meta($post_id, '_cai_course_estimated_time', $course_data['estimatedTime']);
        update_post_meta($post_id, '_cai_course_version', $this->version);
        
        // Trigger a rewrite rules flush
        set_transient('cai_flush_rules_needed', true);
        
        return $post_id;
    }
    
    /**
     * Update an existing course post
     */
    private function update_course_post($post_id, $course_data) {
        // Generate clean, SEO-friendly slug
        $slug = $this->generate_seo_friendly_slug($course_data['title']);
        
        // Update post data
        $post_data = array(
            'ID'            => $post_id,
            'post_title'    => $course_data['title'],
            'post_excerpt'  => $this->get_course_excerpt($course_data),
            'post_name'     => $slug,
        );
        
        // Update post
        $result = wp_update_post($post_data);
        
        if (is_wp_error($result)) {
            return false;
        }
        
        // Update featured image if available
        if (!empty($course_data['coverImage'])) {
            $this->set_course_featured_image($post_id, $course_data['coverImage'], $course_data['title']);
        }
        
        // Update course metadata
        update_post_meta($post_id, '_cai_course_difficulty', $course_data['difficulty']);
        update_post_meta($post_id, '_cai_course_estimated_time', $course_data['estimatedTime']);
        update_post_meta($post_id, '_cai_course_version', $this->version);
        
        return true;
    }
    
    /**
     * Set featured image for course post
     */
    private function set_course_featured_image($post_id, $image_source, $title) {
        // Check if using a WP media library image (attachment ID)
        if (is_numeric($image_source)) {
            $attachment_id = intval($image_source);
            
            // Set as featured image
            set_post_thumbnail($post_id, $attachment_id);
            return true;
        }
        
        // Must be a filename or URL - get the full path
        $upload_dir = wp_upload_dir();
        $image_path = '';
        
        // If it's a relative path in our courses directory
        if (strpos($image_source, 'http') !== 0) {
            $image_path = $upload_dir['basedir'] . '/creator-ai-courses/' . $image_source;
        } 
        // If it's a full URL in our uploads directory
        else if (strpos($image_source, $upload_dir['baseurl']) === 0) {
            $image_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $image_source);
        }
        
        // Check if file exists
        if (!empty($image_path) && file_exists($image_path)) {
            // Get file type
            $filetype = wp_check_filetype(basename($image_path), null);
            
            // Prepare attachment data
            $attachment = array(
                'guid'           => $upload_dir['url'] . '/' . basename($image_path),
                'post_mime_type' => $filetype['type'],
                'post_title'     => sanitize_file_name($title . ' - Featured Image'),
                'post_content'   => '',
                'post_status'    => 'inherit'
            );
            
            // Insert attachment
            $attach_id = wp_insert_attachment($attachment, $image_path, $post_id);
            
            if (!is_wp_error($attach_id)) {
                // Generate metadata
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                $attach_data = wp_generate_attachment_metadata($attach_id, $image_path);
                wp_update_attachment_metadata($attach_id, $attach_data);
                
                // Set as featured image
                set_post_thumbnail($post_id, $attach_id);
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get a course post ID from course ID
     */
    private function get_course_post_id($course_id) {
        $args = array(
            'post_type' => 'cai_course',
            'meta_key' => '_cai_course_id',
            'meta_value' => $course_id,
            'posts_per_page' => 1
        );
        
        $query = new WP_Query($args);
        
        if ($query->have_posts()) {
            return $query->posts[0]->ID;
        }
        
        return false;
    }
    
    /**
     * Generate SEO-friendly slug from title
     */
    private function generate_seo_friendly_slug($title) {
        // Convert to lowercase
        $slug = strtolower($title);
        
        // List of common "stop words" to remove from slugs
        $stop_words = array(
            'a', 'an', 'the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'with', 
            'by', 'about', 'as', 'into', 'like', 'through', 'after', 'before', 'between',
            'from', 'up', 'down', 'of', 'off', 'over', 'under', 'again', 'further', 'then',
            'once', 'here', 'there', 'when', 'where', 'why', 'how', 'all', 'any', 'both',
            'each', 'few', 'more', 'most', 'other', 'some', 'such', 'no', 'nor', 'not',
            'only', 'own', 'same', 'so', 'than', 'too', 'very', 'can', 'will', 'just',
            'should', 'now', 'i', 'me', 'my', 'myself', 'we', 'our', 'ours', 'ourselves',
            'you', 'your', 'yours', 'yourself', 'yourselves', 'he', 'him', 'his', 'himself',
            'she', 'her', 'hers', 'herself', 'it', 'its', 'itself', 'they', 'them', 'their',
            'theirs', 'themselves', 'what', 'which', 'who', 'whom', 'this', 'that', 'these',
            'those', 'am', 'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has',
            'had', 'having', 'do', 'does', 'did', 'doing', 'would', 'shall', 'could', 'might',
            'must', 'lets', 'let'
        );
        
        // Split into words
        $words = explode(' ', $slug);
        
        // Filter out stop words
        $words = array_filter($words, function($word) use ($stop_words) {
            return !in_array($word, $stop_words) && strlen($word) > 1;
        });
        
        // Rejoin with hyphens
        $slug = implode('-', $words);
        
        // Replace invalid characters
        $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
        
        // Replace multiple hyphens with single
        $slug = preg_replace('/-+/', '-', $slug);
        
        // Trim hyphens from beginning and end
        $slug = trim($slug, '-');
        
        // Ensure slug isn't empty
        if (empty($slug)) {
            // Fallback to sanitized title
            $slug = sanitize_title($title);
        }
        
        return $slug;
    }
    
    /**
     * Generate a course excerpt from description
     */
    private function get_course_excerpt($course_data) {
        $excerpt = '';
        
        if (!empty($course_data['description'])) {
            // Strip HTML and truncate
            $excerpt = wp_strip_all_tags($course_data['description']);
            $excerpt = wp_trim_words($excerpt, 30, '...');
        }
        
        return $excerpt;
    }
    
    /**
     * Count total sections in a course
     */
    private function count_total_sections($course_data) {
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

    /**
     * AJAX: Get user course progress
     */
    public function get_user_course_progress() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'cai_course_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            wp_send_json_error('User not logged in');
            return;
        }
        
        $course_id = isset($_POST['course_id']) ? sanitize_text_field($_POST['course_id']) : '';
        
        if (empty($course_id)) {
            wp_send_json_error('No course ID provided');
            return;
        }
        
        $progress = $this->get_user_course_progress_data($user_id, $course_id);
        
        wp_send_json_success($progress);
    }
    
    /**
     * AJAX: Mark section as complete
     */
    public function mark_section_complete() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'cai_course_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            wp_send_json_error('User not logged in');
            return;
        }
        
        $course_id = isset($_POST['course_id']) ? sanitize_text_field($_POST['course_id']) : '';
        $section_id = isset($_POST['section_id']) ? sanitize_text_field($_POST['section_id']) : '';
        
        if (empty($course_id) || empty($section_id)) {
            wp_send_json_error('Missing required parameters');
            return;
        }
        
        // Get current progress
        $progress = $this->get_user_course_progress_data($user_id, $course_id);
        
        // Add section to completed sections
        if (!isset($progress['completed_sections'])) {
            $progress['completed_sections'] = array();
        }
        
        if (!in_array($section_id, $progress['completed_sections'])) {
            $progress['completed_sections'][] = $section_id;
            
            // Get total sections
            $course_data = $this->load_course_file($course_id);
            $total_sections = $this->count_total_sections($course_data);
            
            // Check if all sections are completed
            if (count($progress['completed_sections']) >= $total_sections) {
                $progress['completion_date'] = current_time('mysql');
                $progress['completed'] = true;
            }
            
            // Update progress
            $this->update_user_course_progress($user_id, $course_id, $progress);
        }
        
        wp_send_json_success($progress);
    }
    
    /**
     * AJAX: Submit quiz answers
     */
    public function submit_quiz() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'cai_course_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            wp_send_json_error('User not logged in');
            return;
        }
        
        $course_id = isset($_POST['course_id']) ? sanitize_text_field($_POST['course_id']) : '';
        $answers = isset($_POST['answers']) ? $_POST['answers'] : array();
        
        if (empty($course_id) || empty($answers)) {
            wp_send_json_error('Missing required parameters');
            return;
        }
        
        // Load course data
        $course_data = $this->load_course_file($course_id);
        
        if (!$course_data || !isset($course_data['quiz']) || !isset($course_data['quiz']['questions'])) {
            wp_send_json_error('Quiz data not found');
            return;
        }
        
        // Get course settings
        $settings = $this->get_course_appearance_settings();
        $pass_percentage = intval($settings['quiz_pass_percentage']);
        
        // Process answers
        $correct_answers = array();
        $results = array();
        $total_questions = count($course_data['quiz']['questions']);
        $correct_count = 0;
        
        foreach ($course_data['quiz']['questions'] as $index => $question) {
            $question_id = 'q' . $index;
            $user_answer = isset($answers[$question_id]) ? intval($answers[$question_id]) : null;
            $correct_answer = isset($question['correctAnswer']) ? intval($question['correctAnswer']) : 0;
            
            $correct_answers[$question_id] = $correct_answer;
            
            if ($user_answer === $correct_answer) {
                $correct_count++;
                $results[$question_id] = true;
            } else {
                $results[$question_id] = false;
            }
        }
        
        // Calculate score
        $score = $total_questions > 0 ? round(($correct_count / $total_questions) * 100) : 0;
        $passed = $score >= $pass_percentage;
        
        // Get user progress
        $progress = $this->get_user_course_progress_data($user_id, $course_id);
        
        // Update quiz results
        $progress['quiz_results'] = array(
            'score' => $score,
            'correct_count' => $correct_count,
            'total_questions' => $total_questions,
            'passed' => $passed,
            'completed_date' => current_time('mysql')
        );
        
        // Update progress
        $this->update_user_course_progress($user_id, $course_id, $progress);
        
        // Return results
        wp_send_json_success(array(
            'score' => $score,
            'correct_count' => $correct_count,
            'total_questions' => $total_questions,
            'passed' => $passed,
            'results' => $results,
            'correct_answers' => $correct_answers,
            'message' => $passed 
                ? __('Congratulations! You passed the quiz.', 'creator-ai') 
                : __('You did not pass the quiz. Please try again.', 'creator-ai')
        ));
    }
    
    /**
     * AJAX: Generate certificate
     */
    public function generate_certificate() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'cai_course_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            wp_send_json_error('User not logged in');
            return;
        }
        
        $course_id = isset($_POST['course_id']) ? sanitize_text_field($_POST['course_id']) : '';
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        
        if (empty($course_id) || empty($post_id)) {
            wp_send_json_error('Missing required parameters');
            return;
        }
        
        // Get user progress
        $progress = $this->get_user_course_progress_data($user_id, $course_id);
        
        // Get course data
        $course_data = $this->load_course_file($course_id);
        
        if (!$course_data) {
            wp_send_json_error('Course data not found');
            return;
        }
        
        // Check if user has completed the course or passed the quiz
        $course_completed = false;
        $quiz_passed = false;
        
        if (isset($progress['completed']) && $progress['completed']) {
            $course_completed = true;
        }
        
        if (isset($progress['quiz_results']) && isset($progress['quiz_results']['passed']) && $progress['quiz_results']['passed']) {
            $quiz_passed = true;
        }
        
        // Get certificate URL
        $certificate_url = add_query_arg('certificate', 'view', get_permalink($post_id));
        
        wp_send_json_success(array(
            'course_completed' => $course_completed,
            'quiz_passed' => $quiz_passed,
            'certificate_url' => $certificate_url
        ));
    }

    /**
     * Get user progress data for a course
     */
    private function get_user_course_progress_data($user_id, $course_id) {
        if (!$user_id) {
            return array();
        }
        
        $progress_key = 'cai_course_progress_' . $course_id;
        $progress = get_user_meta($user_id, $progress_key, true);
        
        if (empty($progress)) {
            $progress = array(
                'course_id' => $course_id,
                'started_date' => current_time('mysql'),
                'completed_sections' => array(),
                'completed' => false
            );
        }
        
        return $progress;
    }
    
    /**
     * Update user progress data for a course
     */
    private function update_user_course_progress($user_id, $course_id, $progress) {
        if (!$user_id) {
            return false;
        }
        
        $progress_key = 'cai_course_progress_' . $course_id;
        return update_user_meta($user_id, $progress_key, $progress);
    }

    /**
     * Course shortcode callback
     */
    public function course_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => ''
        ), $atts, 'creator_ai_course');
        
        if (empty($atts['id'])) {
            return '<p>' . __('Course ID is required.', 'creator-ai') . '</p>';
        }
        
        $course_data = $this->load_course_file($atts['id']);
        
        if (!$course_data) {
            return '<p>' . __('Course not found.', 'creator-ai') . '</p>';
        }
        
        // Render course
        return $this->render_course_content($course_data, get_the_ID());
    }

    private function get_course_layout_settings() {
        $defaults = array(
            'sidebar_layout' => 'default',
            'disable_featured_image' => false,
            'disable_title' => false,
            'content_width' => 'default'
        );
        
        // Get saved settings
        $saved_settings = get_option('cai_course_layout_settings', array());
        
        // Merge with defaults
        return wp_parse_args($saved_settings, $defaults);
    }


    /**
     * Initialize layout filters for course pages
     */
    private function initialize_layout_filters() {
        // Apply layout filters only for course pages
        add_action('wp', array($this, 'setup_course_layout_filters'));
    }

    /**
     * Set up filters for course layout settings
     */
    public function setup_course_layout_filters() {
        // Only apply on single course pages
        if (!is_singular('cai_course')) {
            return;
        }
        
        // Get layout settings
        $layout_settings = $this->get_course_layout_settings();
        
        // Apply sidebar filter
        if ($layout_settings['sidebar_layout'] !== 'default') {
            add_filter('generate_sidebar_layout', array($this, 'filter_sidebar_layout'));
            add_filter('genesis_site_layout', array($this, 'filter_genesis_layout'));
            add_filter('theme_mod_sidebar_position', array($this, 'filter_sidebar_position'));
            add_filter('body_class', array($this, 'filter_body_class'));
        }
        
        // Disable featured image if needed
        if (!empty($layout_settings['disable_featured_image'])) {
            add_filter('get_post_metadata', array($this, 'disable_featured_image'), 10, 4);
            add_filter('generate_show_featured_image', '__return_false');
            add_filter('generate_featured_image_output', '__return_false');
        }
        
        // Disable title if needed
        if (!empty($layout_settings['disable_title'])) {
            add_filter('generate_show_title', '__return_false');
            add_filter('the_title', array($this, 'maybe_hide_title'), 10, 2);
        }
        
        // Add content width class
        if ($layout_settings['content_width'] !== 'default') {
            add_filter('body_class', array($this, 'add_content_width_class'));
        }
    }

    /**
     * Filter sidebar layout for GeneratePress
     */
    public function filter_sidebar_layout($layout) {
        $settings = $this->get_course_layout_settings();
        
        switch ($settings['sidebar_layout']) {
            case 'no-sidebar':
                return 'no-sidebar';
            case 'left-sidebar':
                return 'left-sidebar';
            case 'right-sidebar':
                return 'right-sidebar';
            default:
                return $layout;
        }
    }

    /**
     * Filter Genesis theme layout
     */
    public function filter_genesis_layout($layout) {
        $settings = $this->get_course_layout_settings();
        
        switch ($settings['sidebar_layout']) {
            case 'no-sidebar':
                return 'full-width-content';
            case 'left-sidebar':
                return 'sidebar-content';
            case 'right-sidebar':
                return 'content-sidebar';
            default:
                return $layout;
        }
    }

    /**
     * Filter sidebar position for other themes
     */
    public function filter_sidebar_position($position) {
        $settings = $this->get_course_layout_settings();
        
        switch ($settings['sidebar_layout']) {
            case 'no-sidebar':
                return 'none';
            case 'left-sidebar':
                return 'left';
            case 'right-sidebar':
                return 'right';
            default:
                return $position;
        }
    }

    /**
     * Filter body classes for layout
     */
    public function filter_body_class($classes) {
        $settings = $this->get_course_layout_settings();
        
        // Add layout class
        switch ($settings['sidebar_layout']) {
            case 'no-sidebar':
                $classes[] = 'cai-no-sidebar';
                // Remove any sidebar classes
                $classes = array_diff($classes, array('has-sidebar', 'has-left-sidebar', 'has-right-sidebar'));
                break;
            case 'left-sidebar':
                $classes[] = 'cai-left-sidebar';
                break;
            case 'right-sidebar':
                $classes[] = 'cai-right-sidebar';
                break;
        }
        
        return $classes;
    }

    /**
     * Add content width class to body
     */
    public function add_content_width_class($classes) {
        $settings = $this->get_course_layout_settings();
        
        if ($settings['content_width'] === 'full-width') {
            $classes[] = 'cai-full-width-content';
        } elseif ($settings['content_width'] === 'contained') {
            $classes[] = 'cai-contained-content';
        }
        
        return $classes;
    }

    /**
     * Hide the title if needed
     */
    public function maybe_hide_title($title, $id = null) {
        // Only affect the main title on course pages
        if (is_singular('cai_course') && in_the_loop() && is_main_query() && get_the_ID() == $id) {
            return '';
        }
        
        return $title;
    }

    /**
     * Disable featured image
     */
    public function disable_featured_image($value, $object_id, $meta_key, $single) {
        // Target the theme's featured image control
        if (is_singular('cai_course') && in_array($meta_key, array('_thumbnail_id', '_genesis_hide_featured_image', '_generatepress_featured_image'))) {
            return '0';
        }
        
        return $value;
    }


}
