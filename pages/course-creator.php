<?php
// Exit if accessed directly
if (!defined('ABSPATH')) exit;

// Get OpenAI API key status
$api_key = get_option('cai_openai_api_key');
$has_api_key = !empty($api_key);
?>

<div class="wrap course-creator-wrap">
    
    <?php if (!$has_api_key): ?>
        <div class="notice notice-error">
            <p><?php echo esc_html__('OpenAI API key is required for Course Creator. Please configure it in the settings.', 'creator-ai'); ?></p>
            <p><a href="<?php echo esc_url(admin_url('admin.php?page=creator-ai-settings')); ?>" class="button button-primary"><?php echo esc_html__('Go to Settings', 'creator-ai'); ?></a></p>
        </div>
    <?php else: ?>
        <!-- Main Container -->
        <div class="cc-container">
            <!-- Top Header -->
            <div class="cc-header-box">
                <div class="cc-intro">
                    <svg class="cc-icon" fill="#0db56c" viewBox="0 0 14 14" role="img" focusable="false" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"><path d="m 12.499079,12.25525 c 0.0968,0 0.188377,-0.0436 0.249339,-0.11884 0.06096,-0.0752 0.08473,-0.17385 0.06473,-0.26853 l -0.202146,-0.95662 c 0.115125,-0.11137 0.187491,-0.26686 0.187491,-0.43975 0,-0.182 -0.08106,-0.34343 -0.206876,-0.45558 l 0,-3.32202 -0.810333,0.50146 0,2.82056 c -0.125815,0.11215 -0.2069,0.27358 -0.2069,0.45558 0,0.17291 0.07239,0.32841 0.187515,0.43975 l -0.20217,0.95662 c -0.02,0.0947 0.0038,0.19335 0.06473,0.26853 0.06096,0.0752 0.152539,0.11884 0.249339,0.11884 l 0.625281,0 z M 12.773741,4.75539 7.5021019,1.49209 C 7.3477151,1.39699 7.1736728,1.34925 6.9996305,1.34925 c -0.1740423,0 -0.3482077,0.0477 -0.5016586,0.14284 l -5.271713,3.2633 C 1.0854931,4.84249 0.99999905,4.99633 0.99999905,5.1619 c 0,0.1656 0.085494,0.31949 0.22625985,0.40673 l 5.2716883,3.26333 c 0.153451,0.0952 0.3276163,0.14284 0.5016586,0.14284 0.1740423,0 0.3481092,-0.0477 0.5024714,-0.14284 L 12.773741,5.56863 c 0.140766,-0.0872 0.22626,-0.24113 0.22626,-0.40673 0,-0.16557 -0.08549,-0.31946 -0.22626,-0.40651 z M 6.9996059,9.78508 c -0.3283798,0 -0.6488777,-0.0912 -0.928242,-0.26411 l -3.0750017,-1.90368 0,3.27796 c 0,0.97016 1.7931578,1.7555 4.0032436,1.7555 2.2108742,0 4.0038842,-0.78536 4.0038842,-1.7555 l 0,-3.27796 -3.0748786,1.90368 C 7.6492472,9.69388 7.3279857,9.78508 6.9996059,9.78508 Z"></path></g></svg>
                    <h1><?php echo esc_html__('Course Creator', 'creator-ai'); ?></h1>
                    <p><?php echo esc_html__('Create professional educational courses with the help of AI. Fill in the course details, upload relevant images, and let our AI generate comprehensive educational content.', 'creator-ai'); ?></p>
                </div>
            </div>

            <!-- Bottom Section: Courses -->
            <div class="cc-courses-section">
                <div class="cc-courses-header">
                    <h3><?php echo esc_html__('Your Courses', 'creator-ai'); ?></h3>
                    <div class="cc-courses-actions">
                        <button class="button cc-refresh-courses-btn">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                <path d="M17.65 6.35C16.2 4.9 14.21 4 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08c-.82 2.33-3.04 4-5.65 4-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/>
                            </svg>
                            <span><?php echo esc_html__('Refresh', 'creator-ai'); ?></span>
                        </button>
                    </div>
                </div>
                
                <div class="cc-courses-list">
                    <div class="cc-loading">
                        <span class="spinner is-active"></span>
                        <p><?php echo esc_html__('Loading courses...', 'creator-ai'); ?></p>
                    </div>
                    <div class="cc-no-courses" style="display: none;">
                        <p><?php echo esc_html__('No courses found. Create your first course using the form above.', 'creator-ai'); ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Middle Section with Course Form and Help -->
            <div class="cc-middle-section">
                <!--  Course Form -->
                <div class="cc-form-box">
                    <div class="cc-form-header">
                        <h3>Create a New Course</h3>
                    </div>
                    
                    <form id="cc-course-form" class="cc-form">
                        <input type="hidden" id="cc-course-id" name="course_id" value="">
                        <input type="hidden" id="cc-is-edit-mode" name="is_edit_mode" value="0">
                        
                        <div class="cc-form-section">
                            <h4><?php echo esc_html__('Basic Information', 'creator-ai'); ?></h4>
                            
                            <div class="cc-form-field">
                                <label for="cc-course-title"><?php echo esc_html__('Course Title', 'creator-ai'); ?> <span class="required">*</span></label>
                                <input type="text" id="cc-course-title" name="course_title" placeholder="<?php echo esc_attr__('Enter a descriptive title for your course...', 'creator-ai'); ?>" required>
                            </div>
                            
                            <div class="cc-form-field">
                                <label for="cc-course-description"><?php echo esc_html__('Course Description', 'creator-ai'); ?> <span class="required">*</span></label>
                                <textarea id="cc-course-description" name="course_description" rows="3" placeholder="<?php echo esc_attr__('Provide a detailed description of your course...', 'creator-ai'); ?>" required></textarea>
                            </div>
                            
                            <div class="cc-form-row">
                                <div class="cc-form-field">
                                    <label for="cc-target-audience"><?php echo esc_html__('Target Audience', 'creator-ai'); ?> <span class="required">*</span></label>
                                    <input type="text" id="cc-target-audience" name="target_audience" placeholder="<?php echo esc_attr__('Who is this course for?', 'creator-ai'); ?>" required>
                                </div>
                                
                                <div class="cc-form-field">
                                    <label for="cc-difficulty-level"><?php echo esc_html__('Difficulty Level', 'creator-ai'); ?></label>
                                    <select id="cc-difficulty-level" name="difficulty_level">
                                        <option value="beginner"><?php echo esc_html__('Beginner - No prior knowledge required', 'creator-ai'); ?></option>
                                        <option value="intermediate"><?php echo esc_html__('Intermediate - Some basic knowledge expected', 'creator-ai'); ?></option>
                                        <option value="advanced"><?php echo esc_html__('Advanced - For experienced learners', 'creator-ai'); ?></option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="cc-form-section">
                            <h4><?php echo esc_html__('Course Goals', 'creator-ai'); ?></h4>
                            
                            <div class="cc-form-field">
                                <label for="cc-learning-objectives"><?php echo esc_html__('Learning Objectives', 'creator-ai'); ?> <span class="required">*</span></label>
                                <textarea id="cc-learning-objectives" name="learning_objectives" rows="3" placeholder="<?php echo esc_attr__('What will students learn from this course? List the main objectives...', 'creator-ai'); ?>" required></textarea>
                            </div>
                            
                            <div class="cc-form-field">
                                <label for="cc-prerequisites"><?php echo esc_html__('Prerequisites', 'creator-ai'); ?></label>
                                <input type="text" id="cc-prerequisites" name="prerequisites" placeholder="<?php echo esc_attr__('Any required knowledge or skills? Leave blank if none.', 'creator-ai'); ?>">
                            </div>
                        </div>
                        
                        <div class="cc-form-section">
                            <h4><?php echo esc_html__('Course Structure', 'creator-ai'); ?></h4>
                            
                            <div class="cc-form-field">
                                <label for="cc-main-topics"><?php echo esc_html__('Main Topics', 'creator-ai'); ?> <span class="required">*</span></label>
                                <textarea id="cc-main-topics" name="main_topics" rows="4" placeholder="<?php echo esc_attr__('List the main topics your course will cover, one per line...', 'creator-ai'); ?>" required></textarea>
                                <p class="cc-field-help"><?php echo esc_html__('These topics will be used to generate course chapters. The more detailed, the better.', 'creator-ai'); ?></p>
                            </div>
                            
                            <div class="cc-form-field">
                                <label for="cc-additional-notes"><?php echo esc_html__('Additional Notes', 'creator-ai'); ?></label>
                                <textarea id="cc-additional-notes" name="additional_notes" rows="3" placeholder="<?php echo esc_attr__('Any additional information that might help in generating your course...', 'creator-ai'); ?>"></textarea>
                            </div>
                        </div>
                        
                        <div class="cc-form-section">
                            <h4><?php echo esc_html__('Course Media', 'creator-ai'); ?></h4>
                            
                            <div class="cc-form-field">
                                <label><?php echo esc_html__('Course Thumbnail', 'creator-ai'); ?></label>
                                <div class="cc-thumbnail-upload">
                                    <div id="cc-thumbnail-preview" class="cc-thumbnail-preview">
                                        <img src="<?php echo esc_url(plugin_dir_url(dirname(__FILE__)) . 'assets/course-thumbnail-placeholder.jpg'); ?>" alt="Thumbnail preview" id="cc-thumbnail-preview-img">
                                    </div>
                                    <div class="cc-thumbnail-controls">
                                        <button type="button" class="button button-secondary" id="cc-upload-thumbnail-btn">
                                            <?php echo esc_html__('Select Thumbnail', 'creator-ai'); ?>
                                        </button>
                                        <p class="cc-field-help"><?php echo esc_html__('Recommended size: 800x450 pixels', 'creator-ai'); ?></p>
                                    </div>
                                    <input type="hidden" id="cc-thumbnail-id" name="thumbnail_id" value="">
                                    <input type="hidden" id="cc-thumbnail-url" name="thumbnail_url" value="">
                                </div>
                            </div>
                            
                            <div class="cc-form-field">
                                <label><?php echo esc_html__('Course Images', 'creator-ai'); ?></label>
                                <div class="cc-images-upload">
                                    <div id="cc-images-preview" class="cc-images-preview">
                                        <p class="cc-no-images"><?php echo esc_html__('No images uploaded yet.', 'creator-ai'); ?></p>
                                    </div>
                                    <div class="cc-images-controls">
                                        <button type="button" class="button button-secondary" id="cc-upload-images-btn">
                                            <?php echo esc_html__('Upload Images', 'creator-ai'); ?>
                                        </button>
                                        <p class="cc-field-help"><?php echo esc_html__('Upload images that will be used in the course content. The AI will analyze these images and integrate them into relevant sections.', 'creator-ai'); ?></p>
                                    </div>
                                    <div id="cc-images-data" class="cc-images-data"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="cc-form-actions">
                            <button type="button" class="button button-secondary cc-clear-form-btn"><?php echo esc_html__('Clear Form', 'creator-ai'); ?></button>
                            <button type="submit" class="button button-primary cc-create-course-btn"><?php echo esc_html__('Create Course', 'creator-ai'); ?></button>
                        </div>
                    </form>
                </div>
                
            
            <!-- Progress Indicator (hidden by default) -->
            <div id="cc-progress-container" class="cc-progress-container" style="display: none;">
                <div class="cc-progress-header">
                    <h3><?php echo esc_html__('Creating Your Course', 'creator-ai'); ?></h3>
                </div>
                <div class="cc-progress-content">
                    <div class="cc-progress-status">
                        <span id="cc-progress-message"><?php echo esc_html__('Analyzing course information...', 'creator-ai'); ?></span>
                    </div>
                    <div class="cc-progress-bar-container">
                        <div id="cc-progress-bar" class="cc-progress-bar" style="width: 0%;"></div>
                    </div>
                    <div class="cc-progress-details">
                        <span id="cc-progress-percentage">0%</span>
                        <span id="cc-progress-stage"><?php echo esc_html__('Starting...', 'creator-ai'); ?></span>
                    </div>
                    <div id="cc-progress-chapter-info" class="cc-progress-chapter-info"></div>
                    <div class="cc-progress-note">
                        <p><?php echo esc_html__('This process may take several minutes depending on the course complexity. Please don\'t close this page.', 'creator-ai'); ?></p>
                    </div>
                </div>
            </div>
            

        </div>
        
        <!-- Course View Modal -->
        <div class="cc-modal" id="course-view-modal" style="display: none;">
            <div class="cc-modal-content">
                <div class="cc-modal-header">
                    <h3><?php echo esc_html__('Course Details', 'creator-ai'); ?></h3>
                    <button class="cc-modal-close">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                            <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                        </svg>
                    </button>
                </div>
                <div class="cc-modal-body">
                    <div class="cc-view-loading">
                        <span class="spinner is-active"></span>
                        <p><?php echo esc_html__('Loading course...', 'creator-ai'); ?></p>
                    </div>
                    <div id="cc-view-content" class="cc-view-content"></div>
                </div>
                <div class="cc-modal-footer">
                    <button class="button cc-modal-close-btn"><?php echo esc_html__('Close', 'creator-ai'); ?></button>
                    <button class="button button-primary cc-edit-course-btn"><?php echo esc_html__('Edit Course', 'creator-ai'); ?></button>
                </div>
            </div>
        </div>
        
        <!-- Course Editor Modal -->
        <div class="cc-modal" id="course-editor-modal" style="display: none;">
            <div class="cc-modal-content cc-editor-modal-content">
                <div class="cc-modal-header">
                    <h3><?php echo esc_html__('Course Editor', 'creator-ai'); ?></h3>
                    <div class="cc-editor-actions">
                        <button class="button button-primary cc-save-edits-btn"><?php echo esc_html__('Save Changes', 'creator-ai'); ?></button>
                        <button class="cc-modal-close">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="cc-modal-body">
                    <div class="cc-editor-loading">
                        <span class="spinner is-active"></span>
                        <p><?php echo esc_html__('Loading editor...', 'creator-ai'); ?></p>
                    </div>
                    <div id="cc-editor-content" class="cc-editor-content"></div>
                </div>
                <div class="cc-modal-footer">
                    <div class="cc-editor-status"></div>
                    <div class="cc-editor-buttons">
                        <button class="button cc-modal-close-btn"><?php echo esc_html__('Cancel', 'creator-ai'); ?></button>
                        <button class="button button-primary cc-save-edits-btn"><?php echo esc_html__('Save Changes', 'creator-ai'); ?></button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Confirm Delete Modal -->
        <div class="cc-modal" id="confirm-delete-modal" style="display: none;">
            <div class="cc-modal-content cc-confirm-modal-content">
                <div class="cc-modal-header">
                    <h3><?php echo esc_html__('Confirm Delete', 'creator-ai'); ?></h3>
                    <button class="cc-modal-close">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                            <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                        </svg>
                    </button>
                </div>
                <div class="cc-modal-body">
                    <p class="confirm-delete-message"><?php echo esc_html__('Are you sure you want to delete this course? This action cannot be undone.', 'creator-ai'); ?></p>
                </div>
                <div class="cc-modal-footer">
                    <button class="button cc-modal-close-btn"><?php echo esc_html__('Cancel', 'creator-ai'); ?></button>
                    <button class="button button-danger cc-confirm-delete-btn"><?php echo esc_html__('Delete Course', 'creator-ai'); ?></button>
                </div>
            </div>
        </div>
        
        <!-- Debug Panel (if debug is enabled) -->
        <?php if (get_option('cai_debug', false)): ?>
            <?php $this->display_debug_panel(); ?>
        <?php endif; ?>
    <?php endif; ?>
</div>