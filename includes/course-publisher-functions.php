<?php

// Include PDF generator
require_once plugin_dir_path(__FILE__) . 'certificate-pdf-generator.php';

trait Creator_AI_Course_Publisher_Functions {


    
    /**
     * Initialize course publisher functionality
     */
    public function initialize_course_publisher() {


        // 1) Register the custom post type with a dynamic slug based on your ‘Courses” page
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
                'show_in_menu'       => false,
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
        add_action( 'wp_ajax_cai_mark_section_incomplete', array( $this, 'mark_section_incomplete' ) );
        add_action( 'wp_ajax_cai_submit_quiz',           array( $this, 'submit_quiz' ) );
        add_action( 'wp_ajax_cai_generate_certificate',  array( $this, 'generate_certificate' ) );
        add_action( 'wp_ajax_cai_check_certificate_eligibility',  array( $this, 'check_certificate_eligibility' ) );
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
            
            // Enqueue Font Awesome 7
            wp_enqueue_style(
                'font-awesome',
                'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',
                array(),
                '6.5.1'
            );
            
            // Enqueue styles
            wp_enqueue_style(
                'cai-course-publisher-style',
                plugin_dir_url(dirname(__FILE__)) . 'css/course-publisher-style.css',
                array('font-awesome'),
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
            
            // Get appearance settings to check theme styling status
            $appearance_settings = $this->get_course_appearance_settings();
            $use_theme_styling = isset($appearance_settings['use_theme_styling']) && $appearance_settings['use_theme_styling'];
            
            // Localize script with data
            $localize_data = array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('cai_course_nonce'),
                'theme_styling_active' => $use_theme_styling,
                'i18n' => array(
                    'mark_complete' => __('Mark Complete', 'creator-ai'),
                    'marked_complete' => __('Completed', 'creator-ai'),
                    'submit_quiz' => __('Submit Answers', 'creator-ai'),
                    'try_again' => __('Try Again', 'creator-ai'),
                    'certificate_title' => __('Certificate of Completion', 'creator-ai'),
                    'generating_certificate' => __('Generating your certificate...', 'creator-ai'),
                    'download_certificate' => __('Download Certificate', 'creator-ai'),
                    'course_completed' => __('Congratulations! You have completed the course.', 'creator-ai'),
                    'get_certificate' => __('Get Certificate', 'creator-ai')
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
                        
                        // Ensure user_progress always has required structure
                        if (!is_array($user_progress)) {
                            $user_progress = array();
                        }
                        if (!isset($user_progress['completed_sections']) || !is_array($user_progress['completed_sections'])) {
                            $user_progress['completed_sections'] = array();
                        }
                        
                        $localize_data['course_id'] = $course_id;
                        $localize_data['post_id'] = $post->ID;
                        $localize_data['user_progress'] = $user_progress;
                        $localize_data['course_title'] = isset($course_data['title']) ? $course_data['title'] : '';
                        $localize_data['total_sections'] = $this->count_total_sections($course_data);
                    } else {
                        // Add empty defaults if course data fails to load
                        $localize_data['course_id'] = $course_id;
                        $localize_data['post_id'] = $post->ID;
                        $localize_data['user_progress'] = array('completed_sections' => array());
                        $localize_data['course_title'] = '';
                        $localize_data['total_sections'] = 0;
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
        $use_theme_styling = isset($settings['use_theme_styling']) && $settings['use_theme_styling'];
        
        $css = ":root {";
        
        if (!$use_theme_styling) {
            // Colors (only when not using theme styling)
            $css .= "--cai-primary-color: " . esc_attr($settings['primary_color']) . ";";
            $css .= "--cai-secondary-color: " . esc_attr($settings['secondary_color']) . ";";
            $css .= "--cai-text-color: " . esc_attr($settings['text_color']) . ";";
            $css .= "--cai-background-color: " . esc_attr($settings['background_color']) . ";";
            $css .= "--cai-sidebar-bg-color: " . esc_attr($settings['sidebar_bg_color']) . ";";
            $css .= "--cai-accent-color: " . esc_attr($settings['accent_color']) . ";";
            
            // Typography (only when not using theme styling)
            $css .= "--cai-heading-font: " . esc_attr($settings['heading_font']) . ";";
            $css .= "--cai-body-font: " . esc_attr($settings['body_font']) . ";";
            $css .= "--cai-base-font-size: " . esc_attr($settings['base_font_size']) . "px;";
            $css .= "--cai-heading-font-size: " . esc_attr($settings['heading_font_size']) . "px;";
            $css .= "--cai-subheading-font-size: " . esc_attr($settings['subheading_font_size']) . "px;";
        } else {
            // When using theme styling, set CSS variables to inherit from theme
            $css .= "--cai-primary-color: var(--wp--preset--color--primary, var(--wp--custom--color--primary, inherit));";
            $css .= "--cai-secondary-color: var(--wp--preset--color--secondary, var(--wp--custom--color--secondary, inherit));";
            $css .= "--cai-text-color: var(--wp--preset--color--foreground, var(--wp--custom--typography--text-color, inherit));";
            $css .= "--cai-background-color: var(--wp--preset--color--background, var(--wp--custom--color--background, inherit));";
            $css .= "--cai-sidebar-bg-color: var(--wp--preset--color--tertiary, var(--wp--custom--color--light-gray, #f8f9fa));";
            $css .= "--cai-accent-color: var(--wp--preset--color--accent, var(--wp--custom--color--accent, inherit));";
            
            // Typography from theme
            $css .= "--cai-heading-font: var(--wp--preset--font-family--heading, var(--wp--custom--typography--headings--font-family, inherit));";
            $css .= "--cai-body-font: var(--wp--preset--font-family--body, var(--wp--custom--typography--body--font-family, inherit));";
            $css .= "--cai-base-font-size: var(--wp--preset--font-size--medium, var(--wp--custom--typography--body--font-size, 16px));";
            $css .= "--cai-heading-font-size: var(--wp--preset--font-size--large, var(--wp--custom--typography--headings--h1--font-size, 28px));";
            $css .= "--cai-subheading-font-size: var(--wp--preset--font-size--medium-large, var(--wp--custom--typography--headings--h2--font-size, 20px));";
            
            // Global typography overrides for WordPress theme styling
            $css .= "
            /* Remove font-family from all text elements to let WordPress theme handle it */
            .cai-course-container *:not(.fa):not(.fas):not(.fab):not(.far):not(.fal):not(.fad) {
                font-family: inherit !important;
            }
            
            /* Let WordPress handle heading typography */
            .cai-course-container h1,
            .cai-course-container h2,
            .cai-course-container h3,
            .cai-course-container h4,
            .cai-course-container h5,
            .cai-course-container h6 {
                font-family: inherit !important;
                font-size: inherit !important;
            }
            
            /* Let WordPress handle body typography */
            .cai-course-container,
            .cai-course-container p {
                font-family: inherit !important;
                font-size: inherit !important;
            }";
        }
        
        // Spacing and Layout (always applied regardless of theme styling)
        $css .= "--cai-content-padding: " . esc_attr($settings['content_padding']) . "px;";
        $css .= "--cai-section-spacing: " . esc_attr($settings['section_spacing']) . "px;";
        $css .= "--cai-border-radius: " . esc_attr($settings['border_radius']) . "px;";
        $css .= "--cai-sidebar-width: " . esc_attr($settings['sidebar_width']) . "%;";
        
        $css .= "}";
        
        // Certificate styles (conditional based on theme styling)
        if (!$use_theme_styling) {
            // Custom certificate styling
            $css .= ".cai-certificate {";
            if (!empty($settings['certificate_background'])) {
                $css .= "background-image: url(" . esc_url($settings['certificate_background']) . ");";
            } else {
                $css .= "background: linear-gradient(135deg, #f5f5f5 0%, #e8e8e8 100%);";
            }
            $css .= "border: " . esc_attr($settings['certificate_border_width']) . "px solid " . esc_attr($settings['certificate_border_color']) . ";";
            $css .= "}";
            
            $css .= ".cai-certificate-title {";
            $css .= "color: " . esc_attr($settings['certificate_title_color']) . ";";
            $css .= "font-family: " . esc_attr($settings['certificate_font']) . ";";
            $css .= "font-size: " . esc_attr($settings['certificate_title_size']) . "px;";
            $css .= "}";
        } else {
            // Theme-based certificate styling
            $css .= ".cai-certificate {";
            if (!empty($settings['certificate_background'])) {
                $css .= "background-image: url(" . esc_url($settings['certificate_background']) . ");";
            } else {
                $css .= "background: linear-gradient(135deg, #f5f5f5 0%, #e8e8e8 100%);";
            }
            $css .= "border: " . esc_attr($settings['certificate_border_width']) . "px solid var(--wp--preset--color--primary, #ccc);";
            $css .= "}";
            
            $css .= ".cai-certificate-title {";
            $css .= "color: var(--wp--preset--color--primary, var(--wp--custom--color--primary, inherit));";
            $css .= "font-family: var(--wp--preset--font-family--heading, var(--wp--custom--typography--headings--font-family, inherit));";
            $css .= "font-size: var(--wp--preset--font-size--x-large, " . esc_attr($settings['certificate_title_size']) . "px);";
            $css .= "}";
        }
        
        // Button styling when using theme styling  
        if ($use_theme_styling) {
            $css .= "
            /* Mark theme styling as active */
            body.cai-course-page {
                --cai-theme-styling-active: 1;
            }
            
            /* For WordPress theme buttons, only apply essential layout */
            .cai-course-content .wp-element-button.cai-mark-complete,
            .cai-course-content .wp-element-button.cai-quiz-submit,
            .cai-course-content .wp-element-button.cai-retry-quiz,
            .cai-course-content .wp-element-button.cai-get-certificate,
            .cai-course-content .wp-element-button.cai-view-certificate-btn,
            .cai-course-content .wp-element-button.cai-quiz-link,
            .cai-course-content .wp-element-button.cai-certificate-link,
            .cai-course-content .wp-element-button.cai-sticky-toggle {
                /* Only essential layout - let WordPress handle everything else */
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                gap: 0.5rem !important;
                text-decoration: none !important;
                cursor: pointer !important;
            }
            
            /* Full width for navigation buttons */
            .cai-course-content .wp-element-button.cai-quiz-link,
            .cai-course-content .wp-element-button.cai-certificate-link,
            .cai-course-content .wp-element-button.cai-sticky-toggle {
                width: 100% !important;
            }
            
            /* Compact styling for sticky toggle button */
            .cai-course-content .wp-element-button.cai-sticky-toggle {
                padding: 0.5rem 0.75rem !important;
                font-size: 0.875rem !important;
            }";
            
            $css .= "
            /* Theme Link Styling */
            .cai-course-content a:not(.cai-section-link):not(.cai-chapter-toggle):not(.cai-quiz-link):not(.cai-certificate-link) {
                color: revert !important;
                text-decoration: revert !important;
            }
            
            .cai-course-content a:not(.cai-section-link):not(.cai-chapter-toggle):not(.cai-quiz-link):not(.cai-certificate-link):hover {
                color: revert !important;
                text-decoration: revert !important;
            }
            
            /* Global Typography Override for WordPress Theme Integration */
            .cai-course-container *:not(.fa):not(.fas):not(.fab):not(.far):not(.fal):not(.fad):not(.cai-icon) {
                font-family: inherit !important;
            }
            
            /* Let WordPress handle heading typography completely */
            .cai-course-container h1,
            .cai-course-container h2,
            .cai-course-container h3,
            .cai-course-container h4,
            .cai-course-container h5,
            .cai-course-container h6 {
                font-family: inherit !important;
                font-size: revert !important;
                font-weight: inherit !important;
                line-height: inherit !important;
                color: inherit !important;
            }
            
            /* Let WordPress handle paragraph and text typography */
            .cai-course-container p,
            .cai-course-container span,
            .cai-course-container div:not([class*='cai-']),
            .cai-course-container li {
                font-family: inherit !important;
                font-size: inherit !important;
                line-height: inherit !important;
                color: inherit !important;
            }";
        }
        
        return $css;
    }
    
    /**
     * Get course appearance settings with defaults
     */
    private function get_course_appearance_settings() {
                $defaults = array(
            // Styling Source
            'use_theme_styling' => false,
            
            // Colors
            'primary_color' => '#b91c1c',
            'secondary_color' => '#2c3e50',
            'text_color' => '#333333',
            'background_color' => '#ffffff',
            'sidebar_bg_color' => '#f8f9fa',
            'accent_color' => '#15803d',
            'hover_color' => '#991b1b',
            'success_color' => '#15803d',
            'warning_color' => '#f59e0b',
            
            // Typography
            'heading_font' => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif',
            'body_font' => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif',
            'base_font_size' => 16,
            'heading_font_size' => 28,
            'subheading_font_size' => 20,
            
            // Layout
            'content_padding' => 20,
            'section_spacing' => 30,
            'border_radius' => 6,
            'sidebar_width' => 25,
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
            'certificate_background' => '',
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
            $student_name = isset($_GET['student_name']) ? sanitize_text_field($_GET['student_name']) : '';
            echo $this->generate_certificate_html($course_id, $post->ID, $student_name);
            exit;
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
        <!-- Course Header - Outside Container -->
        <header class="cai-course-header" data-course-id="<?php echo esc_attr($course_data['id']); ?>">
                <div class="cai-course-header-content">
                    <!-- Course Thumbnail -->
                    <div class="cai-course-thumbnail">
                        <?php if (!empty($course_data['coverImage'])): ?>
                            <img src="<?php echo esc_url($this->get_course_publisher_image_url($course_data['coverImage'])); ?>" 
                                 alt="<?php echo esc_attr($course_data['title']); ?>">
                        <?php else: ?>
                            <div class="cai-course-thumbnail-placeholder">
                                <i class="fas fa-graduation-cap"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Main Header Content -->
                    <div class="cai-course-header-main">
                        <!-- Title and Description -->
                        <div class="cai-course-header-top">
                            <h1 class="cai-course-title"><?php echo esc_html($course_data['title']); ?></h1>
                            <div class="cai-course-description">
                                <?php echo wp_kses_post($course_data['description']); ?>
                            </div>
                            
                            <!-- Meta Information - Better positioned -->
                            <div class="cai-course-meta">
                                <?php if (!empty($course_data['estimatedTime'])): ?>
                                <div class="cai-course-time">
                                    <i class="far fa-clock cai-course-time-icon"></i>
                                    <span><?php echo esc_html($course_data['estimatedTime']); ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($course_data['difficulty'])): ?>
                                <div class="cai-course-difficulty">
                                    <i class="fas fa-signal cai-course-difficulty-icon"></i>
                                    <span><?php echo esc_html(ucfirst($course_data['difficulty'])); ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($course_settings['progress_tracking'] === 'enabled' && $user_id): ?>
                                <div class="cai-course-progress">
                                    <div class="cai-course-header-text">
                                        <i class="fas fa-chart-line cai-course-progress-icon"></i>
                                        <span>
                                            <?php 
                                            $completed = isset($user_progress['completed_sections']) ? count($user_progress['completed_sections']) : 0;
                                            $total = $this->count_total_sections($course_data);
                                            $percentage = $total > 0 ? round(($completed / $total) * 100) : 0;
                                            echo sprintf(__('%d%% Complete', 'creator-ai'), $percentage); 
                                            ?>
                                        </span>
                                    </div>
                                    <?php if ($course_settings['display_progress_bar']): ?>
                                    <div class="cai-progress-bar">
                                        <div class="cai-progress-bar-fill" style="width: <?php echo esc_attr($percentage); ?>%"></div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Course Details - Integrated in Header -->
                <?php if (!empty($course_data['targetAudience']) || !empty($course_data['learningObjectives'])): ?>
                <div class="cai-course-details">
                    <?php if (!empty($course_data['targetAudience'])): ?>
                    <div class="cai-course-audience">
                        <h3><i class="fas fa-users"></i> <?php _e('Who This Course is For', 'creator-ai'); ?></h3>
                        <p><?php echo esc_html($course_data['targetAudience']); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($course_data['learningObjectives'])): ?>
                    <div class="cai-course-objectives">
                        <h3><i class="fas fa-bullseye"></i> <?php _e('Learning Objectives', 'creator-ai'); ?></h3>
                        <div class="cai-course-objectives-content">
                            <?php echo wp_kses_post($course_data['learningObjectives']); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
        </header>
        
        <div class="cai-course-container">
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
                                        $section_id = "section-" . $chapter_index . "-" . $section_index;
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
                                <?php 
                                $quiz_classes = 'cai-quiz-link';
                                if ($course_settings['use_theme_styling']) {
                                    $quiz_classes .= ' wp-element-button button btn button-primary';
                                }
                                ?>
                                <button type="button" class="<?php echo esc_attr($quiz_classes); ?>" data-target="#course-quiz">
                                    <i class="fas fa-question-circle cai-quiz-icon"></i>
                                    <span><?php _e('Final Quiz', 'creator-ai'); ?></span>
                                </button>
                            </li>
                            <?php endif; ?>
                        </ul>
                        
                        <!-- Sticky Toggle Button -->
                        <div class="cai-sidebar-controls">
                            <?php 
                            $sticky_classes = 'cai-sticky-toggle';
                            if ($course_settings['use_theme_styling']) {
                                $sticky_classes .= ' wp-element-button button btn button-secondary';
                            }
                            ?>
                            <button class="<?php echo esc_attr($sticky_classes); ?>" title="Toggle sticky navigation">
                                <i class="fas fa-thumbtack cai-sticky-icon"></i>
                                <span class="cai-sticky-text">Sticky</span>
                            </button>
                        </div>
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
                                $section_id = "section-" . $chapter_index . "-" . $section_index;
                                $is_completed = isset($user_progress['completed_sections']) && 
                                                in_array($section_id, $user_progress['completed_sections']);
                            ?>
                            <div class="cai-section" id="<?php echo esc_attr($section_id); ?>">
                                <div class="cai-section-header">
                                    <h3 class="cai-section-heading"><?php echo esc_html($section['title']); ?></h3>
                                    
                                    <?php if ($course_settings['progress_tracking'] === 'enabled' && $user_id): ?>
                                    <div class="cai-section-completion">
                                        <?php 
                                        $complete_classes = 'cai-mark-complete';
                                        if ($is_completed) $complete_classes .= ' cai-completed';
                                        if ($course_settings['use_theme_styling']) {
                                            $complete_classes .= ' wp-element-button button btn button-secondary';
                                        }
                                        ?>
                                        <button class="<?php echo esc_attr($complete_classes); ?>" 
                                                data-section-id="<?php echo esc_attr($section_id); ?>">
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
                                                        $img_url = $this->get_course_publisher_image_url($question['image_options'][$option_index]);
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
                                                $img_url = $this->get_course_publisher_image_url($image_option);
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
                                                <span class="cai-feedback-icon"></span>
                                                <span class="cai-feedback-text"><?php _e('Correct!', 'creator-ai'); ?></span>
                                            </div>
                                            <div class="cai-feedback-incorrect">
                                                <span class="cai-feedback-icon"></span>
                                                <span class="cai-feedback-text"><?php _e('Incorrect.', 'creator-ai'); ?></span>
                                                <span class="cai-correct-answer"></span>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div class="cai-quiz-actions">
                                    <?php 
                                    $submit_classes = 'cai-quiz-submit';
                                    if ($course_settings['use_theme_styling']) {
                                        $submit_classes .= ' wp-element-button button btn button-primary';
                                    }
                                    ?>
                                    <button type="submit" class="<?php echo esc_attr($submit_classes); ?>">
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
                                        
                                        <?php if ($course_settings['certificate_enabled']): ?>
                                        <?php 
                                        $cert_btn_classes = 'cai-get-certificate';
                                        if ($course_settings['use_theme_styling']) {
                                            $cert_btn_classes .= ' wp-element-button button btn button-primary';
                                        }
                                        ?>
                                        <button type="button" class="<?php echo esc_attr($cert_btn_classes); ?>" disabled>
                                            <?php _e('Get Certificate', 'creator-ai'); ?>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($course_settings['certificate_enabled']): ?>
                        <div class="cai-certificate-section" id="course-certificate">
                            <h2 class="cai-certificate-heading"><?php _e('Certificate of Completion', 'creator-ai'); ?></h2>
                            
                            <div class="cai-certificate-info">
                                <p><?php _e('Complete the course to earn your certificate.', 'creator-ai'); ?></p>
                                
                                <?php 
                                $completed = isset($user_progress['completed_sections']) ? count($user_progress['completed_sections']) : 0;
                                $total = $this->count_total_sections($course_data);
                                $percentage = $total > 0 ? round(($completed / $total) * 100) : 0;
                                
                                // Check if quiz exists and if user passed it
                                $has_quiz = isset($course_data['quiz']) && !empty($course_data['quiz']['questions']);
                                $quiz_passed = false;
                                if (isset($user_progress['quiz_results']) && isset($user_progress['quiz_results']['passed'])) {
                                    $quiz_passed = $user_progress['quiz_results']['passed'];
                                }
                                
                                if ($percentage == 100):
                                ?>
                                <div class="cai-certificate-ready">
                                    <p class="cai-certificate-title"><?php _e('Congratulations! You have completed the course.', 'creator-ai'); ?></p>
                                    
                                    <?php if ($has_quiz && !$quiz_passed): ?>
                                        <?php if (isset($user_progress['quiz_results'])): ?>
                                            <p class="cai-certificate-subtitle" style="color: #666; font-size: 0.9em; margin: 0.5rem 0 1.5rem 0;">
                                                <i class="fas fa-redo-alt"></i> <?php _e('Almost there! You need to pass the quiz to earn your certificate. Give it another try!', 'creator-ai'); ?>
                                            </p>
                                        <?php else: ?>
                                            <p class="cai-certificate-subtitle" style="color: #666; font-size: 0.9em; margin: 0.5rem 0 1.5rem 0;">
                                                <i class="fas fa-arrow-up"></i> <?php _e('One final step! Complete the quiz above to earn your certificate.', 'creator-ai'); ?>
                                            </p>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <p class="cai-certificate-subtitle" style="color: #666; font-size: 0.9em; margin: 0.5rem 0 1.5rem 0;">
                                            <i class="fas fa-trophy"></i> <?php _e('Amazing work! You\'re ready to claim your certificate of completion.', 'creator-ai'); ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <?php 
                                    $view_cert_classes = 'cai-view-certificate-btn';
                                    if ($course_settings['use_theme_styling']) {
                                        $view_cert_classes .= ' wp-element-button button btn button-primary';
                                    }
                                    
                                    // Determine if button should be enabled
                                    $button_enabled = !$has_quiz || $quiz_passed;
                                    $button_title = '';
                                    if (!$button_enabled) {
                                        if (isset($user_progress['quiz_results'])) {
                                            $button_title = 'Pass the quiz to earn your certificate';
                                        } else {
                                            $button_title = 'Complete the quiz above first';
                                        }
                                    }
                                    ?>
                                    <button type="button"
                                            class="<?php echo esc_attr($view_cert_classes); ?> cai-get-certificate"
                                            <?php if (!$button_enabled): ?>disabled<?php endif; ?>
                                            <?php if ($button_title): ?>title="<?php echo esc_attr($button_title); ?>"<?php endif; ?>
                                            >
                                        <i class="fas fa-certificate"></i>
                                        <?php _e('Get Certificate', 'creator-ai'); ?>
                                    </button>
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
        
        <!-- Scroll Progress Bar -->
        <div class="cai-scroll-progress-bar">
            <div class="cai-scroll-progress-fill"></div>
        </div>
        
        <?php
        return ob_get_clean();
    }
    
    /**
     * Generate certificate HTML
     */
    private function generate_certificate_html($course_id, $post_id, $student_name = '') {
        // Load course data
        $course_data = $this->load_course_file($course_id);
        
        if (!$course_data) {
            return '<div class="cai-course-error">' . __('Course data not found.', 'creator-ai') . '</div>';
        }

        // Get user data
        $user_id = get_current_user_id();

        if (empty($student_name)) {
            if ($user_id) {
                $user = get_userdata($user_id);
                $username = $user ? $user->display_name : __('Student', 'creator-ai');
            } else {
                $username = __('Student', 'creator-ai');
            }
        } else {
            $username = $student_name;
        }
        
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
                    position: relative;
                }
                
                .loading-overlay {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background-color: rgba(26, 26, 26, 0.95);
                    color: #fff;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    z-index: 9999;
                    font-size: 18px;
                }
                
                .loading-message {
                    text-align: center;
                }
                
                .cai-certificate-container {
                    width: 297mm;
                    height: 210mm;
                    margin: 0;
                    text-align: center;
                    position: relative;
                    page-break-inside: avoid;
                    overflow: hidden;
                }
                
                .certificate {
                    width: 100%;
                    height: 100%;
                    max-height: 100%;
                    background-color: white;
                    border: <?php echo esc_attr($settings['certificate_border_width']); ?>px solid <?php echo esc_attr($settings['certificate_border_color']); ?>;
                    padding: 30px;
                    box-sizing: border-box;
                    position: relative;
                    <?php if (!empty($settings['certificate_background'])): ?>
                    background-image: url('<?php echo esc_url($settings['certificate_background']); ?>');
                    <?php else: ?>
                    background: linear-gradient(135deg, #f5f5f5 0%, #e8e8e8 100%);
                    <?php endif; ?>
                    background-size: cover;
                    background-position: center;
                    page-break-inside: avoid;
                    overflow: hidden;
                }
                
                .certificate-inner {
                    padding: 20px;
                    border: 1px solid #d0d0d0;
                    background-color: rgba(255, 255, 255, 0.9);
                    height: calc(100% - 40px);
                    display: flex;
                    flex-direction: column;
                    justify-content: space-between;
                    text-align: center;
                }
                
                .certificate-header {
                    margin-bottom: 20px;
                    flex-shrink: 0;
                }
                
                .certificate-body {
                    flex-grow: 1;
                    display: flex;
                    flex-direction: column;
                    justify-content: center;
                }
                
                .certificate-footer {
                    margin-top: 20px;
                    flex-shrink: 0;
                }
                
                /* Ensure no page breaks */
                .no-break, 
                .cai-certificate-container,
                .certificate,
                .certificate-inner {
                    page-break-inside: avoid !important;
                    break-inside: avoid !important;
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

            </style>
        </head>
        <body>
            <!-- Loading overlay -->
            <div class="loading-overlay" id="loading-overlay">
                <div class="loading-message">
                    <h2><?php _e('Generating Certificate...', 'creator-ai'); ?></h2>
                    <p><?php _e('Please wait while your certificate is being prepared.', 'creator-ai'); ?></p>
                    <div style="margin: 20px 0; font-size: 24px;">📜</div>
                </div>
            </div>
            
            <!-- Certificate content (visible but covered by overlay) -->
            <div class="cai-certificate-container" id="certificate-content">
                <div class="certificate">
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
            
            <script>
                // Certificate content is now ready for PDF generation
                // PDF generation is handled by the calling page via iframe
                document.addEventListener('DOMContentLoaded', function() {
                    // Hide loading overlay since content is ready
                    const loadingOverlay = document.getElementById('loading-overlay');
                    if (loadingOverlay) {
                        loadingOverlay.style.display = 'none';
                    }
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
                $full_url = $this->get_course_publisher_image_url($src);
                
                return "<img {$attributes}src=\"{$full_url}\"{$post_attributes}>";
            },
            $content
        );
        
        // If there's a main image for the section but not in content, add it
        if (!empty($main_image) && strpos($content, $main_image) === false) {
            $img_url = $this->get_course_publisher_image_url($main_image);
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
     * Get a course image URL with proper handling for attachment IDs and legacy paths
     */
    private function get_course_publisher_image_url($image_input, $size = 'full') {
        if (empty($image_input)) {
            return plugin_dir_url(dirname(__FILE__)) . 'assets/course-thumbnail-placeholder.jpg';
        }
        
        // If it's already a full URL, return it
        if (filter_var($image_input, FILTER_VALIDATE_URL)) {
            return $image_input;
        }
        
        // Check if this is an attachment ID (numeric)
        if (is_numeric($image_input)) {
            $attachment_url = wp_get_attachment_url($image_input);
            if ($attachment_url) {
                return $attachment_url;
            }
        }
        
        // Try to find attachment by filename (legacy support)
        if (!filter_var($image_input, FILTER_VALIDATE_URL) && !is_numeric($image_input)) {
            $attachment_id = $this->get_attachment_id_by_filename_publisher($image_input);
            if ($attachment_id) {
                $attachment_url = wp_get_attachment_url($attachment_id);
                if ($attachment_url) {
                    return $attachment_url;
                }
            }
        }
        
        // No legacy directory support - images should be in media library
        
        // Fallback: return placeholder
        return plugin_dir_url(dirname(__FILE__)) . 'assets/course-thumbnail-placeholder.jpg';
    }

    /**
     * Helper function to get attachment ID by filename
     */
    private function get_attachment_id_by_filename_publisher($filename) {
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
        
        // Only handle full URLs in the uploads directory - no custom course directory
        if (strpos($image_source, $upload_dir['baseurl']) === 0) {
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
     * Mark a section as incomplete
     */
    public function mark_section_incomplete() {
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
        
        // Remove section from completed sections
        if (isset($progress['completed_sections'])) {
            $progress['completed_sections'] = array_diff($progress['completed_sections'], array($section_id));
            $progress['completed_sections'] = array_values($progress['completed_sections']); // Re-index array
            
            // If section was removed, course is no longer completed
            if (isset($progress['completed']) && $progress['completed']) {
                $progress['completed'] = false;
                unset($progress['completion_date']);
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
    /**
     * Check if a user has completed the course
     */
    private function is_course_completed($user_id, $course_id) {
        if (!$user_id) {
            return false;
        }

        // Get user progress
        $user_progress = $this->get_user_course_progress_data($user_id, $course_id);
        
        // Get course data
        $course_data = $this->load_course_file($course_id);
        if (!$course_data) {
            return false;
        }

        // Calculate completion percentage
        $completed = isset($user_progress['completed_sections']) ? count($user_progress['completed_sections']) : 0;
        $total = $this->count_total_sections($course_data);
        $percentage = $total > 0 ? round(($completed / $total) * 100) : 0;
        
        return $percentage == 100;
    }

    public function check_certificate_eligibility() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'cai_course_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }

        $course_id = isset($_POST['course_id']) ? sanitize_text_field($_POST['course_id']) : '';
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $user_id = get_current_user_id();

        if (empty($course_id) || empty($post_id)) {
            wp_send_json_error('Missing required parameters');
            return;
        }

        // Check if user has completed the course
        if (!$user_id) {
            wp_send_json_error('Must be logged in to get certificate');
            return;
        }

        // Get course completion status
        $is_completed = $this->is_course_completed($user_id, $course_id);
        
        // Get user progress to check quiz results
        $user_progress = $this->get_user_course_progress_data($user_id, $course_id);
        $quiz_passed = false;
        
        // Check if there's quiz data and if user passed
        if (isset($user_progress['quiz_results']) && isset($user_progress['quiz_results']['passed'])) {
            $quiz_passed = $user_progress['quiz_results']['passed'];
        }
        
        // Load course data to check if quiz exists
        $course_data = $this->load_course_file($course_id);
        $has_quiz = isset($course_data['quiz']) && !empty($course_data['quiz']['questions']);
        
        // If course has a quiz, user must pass it. If no quiz, only completion is required.
        if ($has_quiz) {
            if ($is_completed && $quiz_passed) {
                wp_send_json_success(array('eligible' => true));
            } else {
                $error_message = '';
                if (!$is_completed && !$quiz_passed) {
                    $error_message = 'You need to complete the course and pass the quiz to earn a certificate.';
                } else if (!$is_completed) {
                    $error_message = 'You need to complete the course to earn a certificate.';
                } else if (!$quiz_passed) {
                    $error_message = 'You need to pass the quiz to earn a certificate.';
                }
                wp_send_json_error($error_message);
            }
        } else {
            // No quiz, only check completion
            if ($is_completed) {
                wp_send_json_success(array('eligible' => true));
            } else {
                wp_send_json_error('You need to complete the course to earn a certificate.');
            }
        }
    }

    public function generate_certificate() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'cai_course_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }

        $course_id = isset($_POST['course_id']) ? sanitize_text_field($_POST['course_id']) : '';
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $student_name = isset($_POST['student_name']) ? sanitize_text_field($_POST['student_name']) : '';

        if (empty($course_id) || empty($post_id) || empty($student_name)) {
            wp_send_json_error('Missing required parameters');
            return;
        }

        // Generate PDF directly using server-side generator
        try {
            $pdf_generator = new CAI_Certificate_PDF_Generator();
            $pdf_generator->generate_certificate($course_id, $post_id, $student_name);
            // This will not return as the PDF generator handles the response
        } catch (Exception $e) {
            wp_send_json_error('Certificate generation failed: ' . $e->getMessage());
        }
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