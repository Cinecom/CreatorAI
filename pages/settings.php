<div class="wrap setting-wrap">
    <svg viewBox="0 0 30 30" class="settings-icon" version="1.1" xml:space="preserve" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" fill="#2271b1"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"><style type="text/css"> .st0{fill:#FD6A7E;} .st1{fill:#17B978;} .st2{fill:#8797EE;} .st3{fill:#41A6F9;} .st4{fill:#37E0FF;} .st5{fill:#2FD9B9;} .st6{fill:#F498BD;} .st7{fill:#FFDF1D;} .st8{fill:#C6C9CC;} </style><path class="st2" d="M26.6,12.9l-2.9-0.3c-0.2-0.7-0.5-1.4-0.8-2l1.8-2.3c0.2-0.2,0.1-0.5,0-0.7l-2.2-2.2c-0.2-0.2-0.5-0.2-0.7,0 l-2.3,1.8c-0.6-0.4-1.3-0.6-2-0.8l-0.3-2.9C17,3.2,16.8,3,16.6,3h-3.1c-0.3,0-0.5,0.2-0.5,0.4l-0.3,2.9c-0.7,0.2-1.4,0.5-2,0.8 L8.3,5.4c-0.2-0.2-0.5-0.1-0.7,0L5.4,7.6c-0.2,0.2-0.2,0.5,0,0.7l1.8,2.3c-0.4,0.6-0.6,1.3-0.8,2l-2.9,0.3C3.2,13,3,13.2,3,13.4v3.1 c0,0.3,0.2,0.5,0.4,0.5l2.9,0.3c0.2,0.7,0.5,1.4,0.8,2l-1.8,2.3c-0.2,0.2-0.1,0.5,0,0.7l2.2,2.2c0.2,0.2,0.5,0.2,0.7,0l2.3-1.8 c0.6,0.4,1.3,0.6,2,0.8l0.3,2.9c0,0.3,0.2,0.4,0.5,0.4h3.1c0.3,0,0.5-0.2,0.5-0.4l0.3-2.9c0.7-0.2,1.4-0.5,2-0.8l2.3,1.8 c0.2,0.2,0.5,0.1,0.7,0l2.2-2.2c0.2-0.2,0.2-0.5,0-0.7l-1.8-2.3c0.4-0.6,0.6-1.3,0.8-2l2.9-0.3c0.3,0,0.4-0.2,0.4-0.5v-3.1 C27,13.2,26.8,13,26.6,12.9z M15,19c-2.2,0-4-1.8-4-4c0-2.2,1.8-4,4-4s4,1.8,4,4C19,17.2,17.2,19,15,19z"></path></g></svg>
    <h1>AI Creator Settings (<?php echo $this->version ?>)</h1>
    
    <div class="cai-tabs-container">
        <nav class="cai-tab-nav">
            <ul>
                <li><a href="#api-settings" class="cai-tab active" data-tab="api-settings">API Settings</a></li>
                <li><a href="#yt-article-settings" class="cai-tab" data-tab="yt-article-settings">YouTube Article Settings</a></li>
                <li><a href="#course-creator-settings" class="cai-tab" data-tab="course-creator-settings">Course Creator Settings</a></li>
            </ul>
        </nav>

        <form method="post" action="options.php" class="settings-form">
            <?php 
            settings_fields('creator_ai_settings_group'); 
            do_settings_sections('creator_ai_settings'); 
            $layout_settings = get_option('cai_course_layout_settings', array());
            $appearance_settings = get_option('cai_course_appearance_settings', array());
            


            ?>

            <!-- API Settings Tab -->
            <div id="api-settings" class="cai-tab-content active">
                <div class="cai-tab-header">
                    <h2>API Configuration</h2>
                    <p>Connect your plugin to OpenAI and Google to enable automatic content generation.</p>
                </div>

                <div class="cai-section box openai-api-settings">
                    <div class="cai-section-header">
                        <h3>OpenAI API Settings</h3>
                        <p>Configure your OpenAI API connection to use AI generation capabilities.</p>
                    </div>
                    <div class="cai-section-content">
                        <table class="form-table">
                            <tr>
                                <th scope="row">OpenAI API Key</th>
                                <td>
                                    <div class="cai-input-group">
                                        <input type="text" name="cai_openai_api_key" value="<?php echo esc_attr(get_option('cai_openai_api_key')); ?>" class="regular-text" />
                                        <?php 
                                            $api_key = get_option('cai_openai_api_key');
                                            if(empty($api_key)){
                                                echo '<button type="button" id="test-openai-connection" class="button" disabled>Test Connection</button>';
                                            } else {
                                                echo '<button type="button" id="test-openai-connection" class="button">Test Connection</button>';
                                            }
                                        ?>
                                    </div>
                                    <p class="description">Sign up at the <a href="https://platform.openai.com/" target="_blank">OpenAI Platform</a> and create a new API key. You can check your <a href="https://platform.openai.com/settings/organization/usage" target="_blank">usage cost here</a>.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Model</th>
                                <td>
                                    <input type="text" name="cai_openai_model" value="<?php echo esc_attr(get_option('cai_openai_model')); ?>" class="regular-text" placeholder="Example: gpt-4o-mini" />
                                    <p class="description">Enter the OpenAI model you want to use.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Tokens</th>
                                <td>
                                    <input type="number" name="cai_openai_tokens" value="<?php echo esc_attr(get_option('cai_openai_tokens')); ?>" class="regular-text" placeholder="Example: 16384" min="100" />
                                    <p class="description">Enter the max number of tokens to use for requests.</p>
                                    <div id="openai-test-response"></div>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="cai-section box google-api-settings">
                    <div class="cai-section-header">
                        <h3>Google API Settings</h3>
                        <p>Make sure to enable the <strong>Cloud Vision</strong> and <strong>YouTube Data</strong> services</p>
                    </div>
                    <div class="cai-section-content">
                        <table class="form-table">
                            <tr>
                                <th scope="row">YouTube Channel ID</th>
                                <td>
                                    <input type="text" name="cai_youtube_channel_id" value="<?php echo esc_attr(get_option('cai_youtube_channel_id')); ?>" class="regular-text" />
                                    <p class="description">Enter the Channel ID of the YouTube channel from which you want to fetch videos.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Google OAuth Client ID</th>
                                <td>
                                    <input type="text" name="cai_google_client_id" value="<?php echo esc_attr(get_option('cai_google_client_id')); ?>" class="regular-text" />
                                    <p class="description">Obtain this from the <a href="https://console.developers.google.com/" target="_blank">Google Developers Console</a>.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Google OAuth Client Secret</th>
                                <td>
                                    <input type="text" name="cai_google_client_secret" value="<?php echo esc_attr(get_option('cai_google_client_secret')); ?>" class="regular-text" />
                                    <p class="description">This is provided alongside your Client ID.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"></th>
                                <td>
                                    <div class="cai-input-group">
                                        <?php 
                                            $client_id     = get_option('cai_google_client_id');
                                            $client_secret = get_option('cai_google_client_secret');
                                            $access_token  = get_option('cai_google_access_token');
                                            if(empty($client_id) || empty($client_secret)){
                                                echo '<p>Please fill in and save your OAuth Client ID and Secret above to enable Google connection.</p>';
                                                echo '<button class="button button-primary" disabled style="opacity:0.5;">Connect with Google</button>';
                                            } else {
                                                if(empty($access_token)){
                                                    $connect_url = admin_url('admin-post.php?action=cai_google_connect');
                                                    echo '<a href="' . esc_url($connect_url) . '" class="button button-primary">Connect with Google</a>';
                                                } else {
                                                    $disconnect_url = admin_url('admin-post.php?action=cai_google_disconnect');
                                                    echo '<a href="' . esc_url($disconnect_url) . '" class="button">Disconnect from Google</a>';
                                                }
                                            }
                                        ?>
                                        <button type="button" id="test-google-connection" class="button" <?php echo (empty($access_token)) ? 'disabled' : ''; ?>>Test Connection</button>
                                    </div>
                                    <div id="google-test-response"></div>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>


                <div class="cai-section box github-update-settings">
                    <div class="cai-section-header">
                        <h3>GitHub Updates</h3>
                        <p>Manage automatic updates from GitHub repository.</p>
                    </div>
                    <div class="cai-section-content">
                        <table class="form-table">
                            <tr>
                                <th scope="row">Update Information</th>
                                <td>
                                    <div class="cai-input-group">
                                        <p><strong>Repository:</strong> Cinecom/CreatorAI</p>
                                        <p><strong>Branch:</strong> main</p>
                                        <p><strong>Current Version:</strong> <?php echo $this->version; ?></p>
                                        <div id="github-update-status" style="margin: 10px 0;"></div>
                                        <button type="button" id="check-github-updates" class="button">Check for Updates</button>
                                        <a href="https://github.com/Cinecom/CreatorAI" target="_blank" class="button button-secondary" style="margin-left: 10px;">View Repository</a>
                                    </div>
                                    <p class="description">The plugin automatically checks for updates daily. Updates will appear in your WordPress admin like standard plugins.</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="cai-section box debug-settings">
                    <div class="cai-section-header">
                        <h3>Debug Settings</h3>
                    </div>
                    <div class="cai-section-content">
                        <table class="form-table">
                            <tr>
                                <th scope="row">Enable Debugging</th>
                                <td>
                                    <div class="cai-input-group">
                                        
                                        <input type="checkbox" id="cai_debugging" name="cai_debug" <?php checked(get_option('cai_debug'), true); ?> class="regular-text" />
                                        <label for="cai_debugging"  class="regular-text">Enable Debugging</label>
                                    </div>
                                    <p class="description">Shows API responses in a textfield for debugging.</p>
                                </td>
                            </tr>
                            
                        </table>
                    </div>
                </div>


            </div>

            <!-- YouTube Article Settings Tab -->
            <div id="yt-article-settings" class="cai-tab-content">
                <div class="cai-tab-header">
                    <h2>YouTube Article Settings</h2>
                    <p>Configure how your YouTube videos are converted into articles.</p>
                </div>

                <div class="cai-section box keywords-settings">
                    <div class="cai-section-header">
                        <h3>Internal Linking Keywords</h3>
                        <p>Enter keywords that should be used for internal linking. These keywords will be matched against your article content to add relevant internal links.</p>
                    </div>
                    <div class="cai-section-content">
                        <table class="form-table">
                            <tr>
                                <th scope="row">Keywords</th>
                                <td>
                                    <div class="keyword-tags-container">
                                        <div class="keyword-tags-input-wrapper">
                                            <input type="text" id="keyword-tags-input" class="regular-text" placeholder="Type a keyword and press Enter or comma" />
                                        </div>
                                        <div class="keyword-tags-display">
                                            <?php 
                                            $keywords = get_option('yta_internal_keywords', array());
                                            if (is_array($keywords) && !empty($keywords)) {
                                                foreach ($keywords as $keyword) {
                                                    echo '<span class="keyword-tag">' . esc_html($keyword) . '<span class="remove-tag">×</span></span>';
                                                }
                                            }
                                            ?>
                                        </div>
                                        <input type="hidden" name="yta_internal_keywords" id="keyword-tags-hidden" value="<?php echo esc_attr(implode(',', (array)get_option('yta_internal_keywords', array()))); ?>" />
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="cai-section box affiliate-settings">
                    <div class="cai-section-header">
                        <h3>Affiliate Links</h3>
                        <p>Enter your affiliate links below. When the article's external links match the domain of one of these links, the AI will use your affiliate URL instead.</p>
                    </div>
                    <div class="cai-section-content">
                        <table class="form-table">
                            <tr>
                                <th scope="row">Add links</th>
                                <td>
                                    <?php 
                                    $affiliate_links = get_option('yta_affiliate_links', array());
                                    if (!is_array($affiliate_links) || empty($affiliate_links)) {
                                        $affiliate_links = array('');
                                    }
                                    foreach ($affiliate_links as $index => $link): ?>
                                        <div class="affiliate-link-input">
                                            <input type="text" name="yta_affiliate_links[]" value="<?php echo esc_attr($link); ?>" class="regular-text" placeholder="https://example.com/your-affiliate-code" />
                                            <?php if ($index === 0): ?>
                                                <button type="button" class="button add-link">+</button>
                                            <?php else: ?>
                                                <button type="button" class="button remove-link">–</button>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="cai-section box blacklist-settings">
                    <div class="cai-section-header">
                        <h3>Blacklist Links</h3>
                        <p>Enter links that should be excluded from article generation. The AI will be instructed not to use or reference these domains.</p>
                    </div>
                    <div class="cai-section-content">
                        <table class="form-table">
                            <tr>
                                <th scope="row">Add blacklisted links</th>
                                <td>
                                    <?php 
                                    $blacklist_links = get_option('yta_blacklist_links', array());
                                    if (!is_array($blacklist_links) || empty($blacklist_links)) {
                                        $blacklist_links = array('');
                                    }
                                    foreach ($blacklist_links as $index => $link): ?>
                                        <div class="affiliate-link-input">
                                            <input type="text" name="yta_blacklist_links[]" value="<?php echo esc_attr($link); ?>" class="regular-text" placeholder="example.com" />
                                            <?php if ($index === 0): ?>
                                                <button type="button" class="button add-link">+</button>
                                            <?php else: ?>
                                                <button type="button" class="button remove-link">–</button>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="cai-section box">
                    <div class="cai-section-header">
                        <h3>Advanced Prompt Settings</h3>
                        <p>Customize the AI prompts used to generate articles and SEO descriptions.</p>
                    </div>
                    <div class="cai-section-content">
                        <table class="form-table">
                            <tr>
                                <th scope="row">Article Prompt</th>
                                <td>
                                    <textarea name="yta_prompt_article_system" rows="4" class="large-text"><?php echo esc_textarea(get_option('yta_prompt_article_system')); ?></textarea>
                                    <p class="description">Instructions for writing the actual article. An introduction of 150-200 words and a total article length of 1500-2500 words is recommended</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Article Description Prompt</th>
                                <td>
                                    <textarea name="yta_prompt_seo_system" rows="3" class="large-text"><?php echo esc_textarea(get_option('yta_prompt_seo_system')); ?></textarea>
                                    <p class="description">Instructions for writing an SEO description of the article based on the AI generated article. It should be between 100-160 characters.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"></th>
                                <td>
                                    <p><button type="button" id="restore-default-prompts" class="button">Restore Default Prompts</button></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

             <!-- Course Creator Settings Tab -->
            <div id="course-creator-settings" class="cai-tab-content">
                <div class="cai-tab-header">
                    <h2>Course Creator Settings</h2>
                    <p>Configure your course display settings and select the main courses page.</p>
                </div>

                <div class="cai-section box">
                    <div class="cai-section-header">
                        <h3>Courses Page</h3>
                        <p>Select the page where all your courses will be displayed. This page will serve as the main courses listing.</p>
                    </div>
                    <div class="cai-section-content">
                        <table class="form-table">
                            <tr>
                                <th scope="row">Select Courses Page</th>
                                <td>
                                    <div class="cai-input-group">
                                        <?php
                                        $current_page_id = get_option('cai_courses_page_id', 0);
                                        $pages_args = array(
                                            'post_type' => 'page',
                                            'post_status' => 'publish',
                                            'posts_per_page' => -1,
                                            'orderby' => 'title',
                                            'order' => 'ASC'
                                        );
                                        $pages = get_posts($pages_args);
                                        ?>
                                        <select name="cai_courses_page_id" id="cai_courses_page_id" class="regular-text">
                                            <option value="0"><?php _e('— Select a page —', 'creator-ai'); ?></option>
                                            <?php foreach ($pages as $page) : ?>
                                                <option value="<?php echo esc_attr($page->ID); ?>" <?php selected($current_page_id, $page->ID); ?>>
                                                    <?php echo esc_html($page->post_title); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        
                                        <a href="<?php echo esc_url(admin_url('post-new.php?post_type=page')); ?>" 
                                           class="button button-secondary" 
                                           target="_blank" 
                                           title="<?php esc_attr_e('Create a new page', 'creator-ai'); ?>">
                                            <?php _e('Create New Page', 'creator-ai'); ?>
                                        </a>
                                    </div>
                                    <p class="description">
                                        <?php _e('Choose a page to display your courses. This page will automatically show all available courses.', 'creator-ai'); ?>
                                    </p>
                                    
                                    <?php if ($current_page_id > 0) : ?>
                                        <?php
                                        $current_page = get_post($current_page_id);
                                        if ($current_page && $current_page->post_status === 'publish') :
                                        ?>
                                            <p class="description">
                                                <strong><?php _e('Currently selected:', 'creator-ai'); ?></strong> 
                                                <a href="<?php echo esc_url(get_permalink($current_page_id)); ?>" target="_blank">
                                                    <?php echo esc_html($current_page->post_title); ?>
                                                </a>
                                                <a href="<?php echo esc_url(get_edit_post_link($current_page_id)); ?>" target="_blank" class="button button-small" style="margin-left: 10px;">
                                                    <?php _e('Edit Page', 'creator-ai'); ?>
                                                </a>
                                            </p>
                                        <?php else : ?>
                                            <p class="description yt-api-error">
                                                <?php _e('The previously selected page no longer exists or is not published. Please select a new page.', 'creator-ai'); ?>
                                            </p>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">Status</th>
                                <td>
                                    <?php
                                    $has_courses_page = $current_page_id > 0 && get_post($current_page_id) && get_post_status($current_page_id) === 'publish';
                                    if ($has_courses_page) :
                                    ?>
                                        <div class="yt-api-success">
                                            <?php _e('Courses page is properly configured', 'creator-ai'); ?>
                                        </div>
                                    <?php else : ?>
                                        <div class="yt-api-error">
                                            <?php _e('No courses page selected or selected page is not valid', 'creator-ai'); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>


                <div class="cai-section box">
                    <div class="cai-section-header">
                        <h3>Course Display Settings</h3>
                        <p>Customize how your courses are displayed and styled on the frontend.</p>
                    </div>
                    <div class="cai-section-content">
                        <table class="form-table">
                            <!-- Theme Styling Toggle -->
                            <tr>
                                <th scope="row">Styling Source</th>
                                <td>
                                    <div class="cai-toggle-wrapper">
                                        <input type="hidden" name="cai_course_appearance_settings[use_theme_styling]" value="0">
                                        <input type="checkbox" id="cai_use_theme_styling" name="cai_course_appearance_settings[use_theme_styling]" value="1" 
                                            <?php checked(isset($appearance_settings['use_theme_styling']) ? $appearance_settings['use_theme_styling'] : false); ?>>
                                        <label for="cai_use_theme_styling" class="cai-toggle-label">
                                            <span class="cai-toggle-slider"></span>
                                            <span class="cai-toggle-text">Use WordPress Theme Styling</span>
                                        </label>
                                    </div>
                                    <p class="description">When enabled, courses will inherit colors, fonts, and button styles from your WordPress theme. When disabled, you can customize all styling options below.</p>
                                </td>
                            </tr>
                            
                            <!-- Color Settings -->
                            <tr class="cai-custom-styling-option">
                                <th scope="row">Color Scheme</th>
                                <td>
                                    <div class="cai-color-settings">
                                        <div class="cai-color-field">
                                            <label for="cai_primary_color">Primary Color</label>
                                            <input type="color" id="cai_primary_color" name="cai_course_appearance_settings[primary_color]" 
                                                value="<?php echo esc_attr(isset($appearance_settings['primary_color']) ? $appearance_settings['primary_color'] : '#3e69dc'); ?>">
                                        </div>
                                        <div class="cai-color-field">
                                            <label for="cai_secondary_color">Secondary Color</label>
                                            <input type="color" id="cai_secondary_color" name="cai_course_appearance_settings[secondary_color]" 
                                                value="<?php echo esc_attr(isset($appearance_settings['secondary_color']) ? $appearance_settings['secondary_color'] : '#2c3e50'); ?>">
                                        </div>
                                        <div class="cai-color-field">
                                            <label for="cai_accent_color">Accent Color</label>
                                            <input type="color" id="cai_accent_color" name="cai_course_appearance_settings[accent_color]" 
                                                value="<?php echo esc_attr(isset($appearance_settings['accent_color']) ? $appearance_settings['accent_color'] : '#e74c3c'); ?>">
                                        </div>
                                    </div>
                                    <div class="cai-color-settings">
                                        <div class="cai-color-field">
                                            <label for="cai_text_color">Text Color</label>
                                            <input type="color" id="cai_text_color" name="cai_course_appearance_settings[text_color]" 
                                                value="<?php echo esc_attr(isset($appearance_settings['text_color']) ? $appearance_settings['text_color'] : '#333333'); ?>">
                                        </div>
                                        <div class="cai-color-field">
                                            <label for="cai_background_color">Background Color</label>
                                            <input type="color" id="cai_background_color" name="cai_course_appearance_settings[background_color]" 
                                                value="<?php echo esc_attr(isset($appearance_settings['background_color']) ? $appearance_settings['background_color'] : '#ffffff'); ?>">
                                        </div>
                                        <div class="cai-color-field">
                                            <label for="cai_sidebar_bg_color">Sidebar Background</label>
                                            <input type="color" id="cai_sidebar_bg_color" name="cai_course_appearance_settings[sidebar_bg_color]" 
                                                value="<?php echo esc_attr(isset($appearance_settings['sidebar_bg_color']) ? $appearance_settings['sidebar_bg_color'] : '#f8f9fa'); ?>">
                                        </div>
                                    </div>
                                    
                                </td>
                            </tr>

                            <!-- Typography Settings -->
                            <tr class="cai-custom-styling-option">
                                <th scope="row">Typography</th>
                                <td>
                                    <div class="cai-form-row">
                                        <div class="cai-form-field">
                                            <label for="cai_heading_font">Heading Font</label>
                                            <select id="cai_heading_font" name="cai_course_appearance_settings[heading_font]">
                                                <option value="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" <?php selected(isset($appearance_settings['heading_font']) ? $appearance_settings['heading_font'] : '', "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif"); ?>>System Default</option>
                                                <option value="'Helvetica Neue', Helvetica, Arial, sans-serif" <?php selected(isset($appearance_settings['heading_font']) ? $appearance_settings['heading_font'] : '', "'Helvetica Neue', Helvetica, Arial, sans-serif"); ?>>Helvetica</option>
                                                <option value="'Georgia', serif" <?php selected(isset($appearance_settings['heading_font']) ? $appearance_settings['heading_font'] : '', "'Georgia', serif"); ?>>Georgia</option>
                                                <option value="'Merriweather', serif" <?php selected(isset($appearance_settings['heading_font']) ? $appearance_settings['heading_font'] : '', "'Merriweather', serif"); ?>>Merriweather</option>
                                                <option value="'Montserrat', sans-serif" <?php selected(isset($appearance_settings['heading_font']) ? $appearance_settings['heading_font'] : '', "'Montserrat', sans-serif"); ?>>Montserrat</option>
                                            </select>
                                        </div>
                                        <div class="cai-form-field">
                                            <label for="cai_body_font">Body Font</label>
                                            <select id="cai_body_font" name="cai_course_appearance_settings[body_font]">
                                                <option value="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" <?php selected(isset($appearance_settings['body_font']) ? $appearance_settings['body_font'] : '', "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif"); ?>>System Default</option>
                                                <option value="'Helvetica Neue', Helvetica, Arial, sans-serif" <?php selected(isset($appearance_settings['body_font']) ? $appearance_settings['body_font'] : '', "'Helvetica Neue', Helvetica, Arial, sans-serif"); ?>>Helvetica</option>
                                                <option value="'Georgia', serif" <?php selected(isset($appearance_settings['body_font']) ? $appearance_settings['body_font'] : '', "'Georgia', serif"); ?>>Georgia</option>
                                                <option value="'Open Sans', sans-serif" <?php selected(isset($appearance_settings['body_font']) ? $appearance_settings['body_font'] : '', "'Open Sans', sans-serif"); ?>>Open Sans</option>
                                                <option value="'Roboto', sans-serif" <?php selected(isset($appearance_settings['body_font']) ? $appearance_settings['body_font'] : '', "'Roboto', sans-serif"); ?>>Roboto</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="cai-form-row">
                                        <div class="cai-form-field">
                                            <label for="cai_base_font_size">Base Font Size (px)</label>
                                            <input type="number" id="cai_base_font_size" name="cai_course_appearance_settings[base_font_size]" 
                                                value="<?php echo esc_attr(isset($appearance_settings['base_font_size']) ? $appearance_settings['base_font_size'] : '16'); ?>" 
                                                min="12" max="24">
                                        </div>
                                        <div class="cai-form-field">
                                            <label for="cai_heading_font_size">Heading Font Size (px)</label>
                                            <input type="number" id="cai_heading_font_size" name="cai_course_appearance_settings[heading_font_size]" 
                                                value="<?php echo esc_attr(isset($appearance_settings['heading_font_size']) ? $appearance_settings['heading_font_size'] : '28'); ?>" 
                                                min="18" max="48">
                                        </div>
                                    </div>
                                </td>
                            </tr>

                            <!-- Layout Settings -->
                            <tr>
                                <th scope="row">Layout</th>
                                <td>
                                    <div class="cai-form-row">
                                        <div class="cai-form-field">
                                            <label for="cai_sidebar_width">Sidebar Width (%)</label>
                                            <input type="number" id="cai_sidebar_width" name="cai_course_appearance_settings[sidebar_width]" 
                                                value="<?php echo esc_attr(isset($appearance_settings['sidebar_width']) ? $appearance_settings['sidebar_width'] : '25'); ?>"
                                                min="15" max="40" step="1">
                                            <p class="description">Sidebar width as percentage of total content area (content area will automatically fill remaining space).</p>
                                        </div>
                                    </div>
                                    
                                    <div class="cai-form-row">
                                        <div class="cai-form-field">
                                            <label for="cai_sidebar_position">Sidebar Position</label>
                                        <select id="cai_sidebar_position" name="cai_course_appearance_settings[sidebar_position]">
                                            <option value="left" <?php selected($appearance_settings['sidebar_position'], "left"); ?>>Left</option>
                                            <option value="right" <?php selected($appearance_settings['sidebar_position'], "right"); ?>>Right</option>
                                        </select>
                                        </div>
                                        <div class="cai-form-field">
                                            <label for="cai_sidebar_behavior">Sidebar Behavior</label>
                                            <select id="cai_sidebar_behavior" name="cai_course_appearance_settings[sidebar_behavior]">
                                                <option value="sticky" <?php selected(isset($appearance_settings['sidebar_behavior']) ? $appearance_settings['sidebar_behavior'] : '', "sticky"); ?>>Sticky (stays visible when scrolling)</option>
                                                <option value="static" <?php selected(isset($appearance_settings['sidebar_behavior']) ? $appearance_settings['sidebar_behavior'] : '', "static"); ?>>Static (scrolls with content)</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="cai-form-row">
                                        <div class="cai-form-field">
                                            <label for="cai_border_radius">Border Radius (px)</label>
                                            <input type="number" id="cai_border_radius" name="cai_course_appearance_settings[border_radius]" 
                                                value="<?php echo esc_attr(isset($appearance_settings['border_radius']) ? $appearance_settings['border_radius'] : '6'); ?>"
                                                min="0" max="20">
                                        </div>
                                    </div>
                                </td>
                            </tr>

                            <!-- Progress Tracking -->
                            <tr>
                                <th scope="row">Progress Tracking</th>
                                <td>
                                    <div class="cai-form-row">
                                        <div class="cai-form-field">
                                            <label for="cai_progress_tracking">Progress Tracking</label>
                                            <select id="cai_progress_tracking" name="cai_course_appearance_settings[progress_tracking]">
                                                <option value="enabled" <?php selected(isset($appearance_settings['progress_tracking']) ? $appearance_settings['progress_tracking'] : '', "enabled"); ?>>Enabled</option>
                                                <option value="disabled" <?php selected(isset($appearance_settings['progress_tracking']) ? $appearance_settings['progress_tracking'] : '', "disabled"); ?>>Disabled</option>
                                            </select>
                                        </div>
                                        <div class="cai-form-field">
                                            <label for="cai_progress_indicator">Progress Indicator</label>
                                            <select id="cai_progress_indicator" name="cai_course_appearance_settings[progress_indicator]">
                                                <option value="circle" <?php selected(isset($appearance_settings['progress_indicator']) ? $appearance_settings['progress_indicator'] : '', "circle"); ?>>Circle</option>
                                                <option value="checkmark" <?php selected(isset($appearance_settings['progress_indicator']) ? $appearance_settings['progress_indicator'] : '', "checkmark"); ?>>Checkmark</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="cai-form-field">
                                        <label>
                                            <input type="checkbox" name="cai_course_appearance_settings[display_progress_bar]" value="1" 
                                            <?php checked(isset($appearance_settings['display_progress_bar']) ? $appearance_settings['display_progress_bar'] : true); ?>>
                                            Display progress bar
                                        </label>
                                    </div>
                                </td>
                            </tr>

                            <!-- Quiz Settings -->
                            <tr>
                                <th scope="row">Quiz Settings</th>
                                <td>
                                    <div class="cai-form-row">
                                        <div class="cai-form-field">
                                            <label for="cai_quiz_pass_percentage">Pass Percentage</label>
                                            <input type="number" id="cai_quiz_pass_percentage" name="cai_course_appearance_settings[quiz_pass_percentage]" 
                                                value="<?php echo esc_attr(isset($appearance_settings['quiz_pass_percentage']) ? $appearance_settings['quiz_pass_percentage'] : '70'); ?>"
                                                min="0" max="100">
                                            <p class="description">Minimum percentage required to pass the quiz</p>
                                        </div>
                                    </div>
                                    
                                    <div class="cai-form-field">
                                        <label>
                                            <input type="checkbox" name="cai_course_appearance_settings[quiz_highlight_correct]" value="1" 
                                            <?php checked(isset($appearance_settings['quiz_highlight_correct']) ? $appearance_settings['quiz_highlight_correct'] : true); ?>>
                                            Highlight correct answers after submission
                                        </label>
                                    </div>
                                    
                                    <div class="cai-form-field">
                                        <label>
                                            <input type="checkbox" name="cai_course_appearance_settings[show_quiz_results]" value="1" 
                                            <?php checked(isset($appearance_settings['show_quiz_results']) ? $appearance_settings['show_quiz_results'] : true); ?>>
                                            Show detailed quiz results
                                        </label>
                                    </div>
                                </td>
                            </tr>

                            <!-- Certificate Settings -->
                            <tr>
                                <th scope="row">Certificate Settings</th>
                                <td>
                                    <div class="cai-form-field">
                                        <label>
                                            <input type="checkbox" name="cai_course_appearance_settings[certificate_enabled]" value="1" 
                                            <?php checked(isset($appearance_settings['certificate_enabled']) ? $appearance_settings['certificate_enabled'] : true); ?>>
                                            Enable certificates
                                        </label>
                                    </div>
                                    
                                    <div class="cai-form-row">
                                        <div class="cai-form-field">
                                            <label for="cai_certificate_layout">Certificate Layout</label>
                                            <select id="cai_certificate_layout" name="cai_course_appearance_settings[certificate_layout]">
                                                <option value="standard" <?php selected(isset($appearance_settings['certificate_layout']) ? $appearance_settings['certificate_layout'] : '', "standard"); ?>>Standard</option>
                                                <option value="modern" <?php selected(isset($appearance_settings['certificate_layout']) ? $appearance_settings['certificate_layout'] : '', "modern"); ?>>Modern</option>
                                                <option value="classic" <?php selected(isset($appearance_settings['certificate_layout']) ? $appearance_settings['certificate_layout'] : '', "classic"); ?>>Classic</option>
                                            </select>
                                        </div>
                                        <div class="cai-form-field">
                                            <label for="cai_certificate_font">Certificate Font</label>
                                            <select id="cai_certificate_font" name="cai_course_appearance_settings[certificate_font]">
                                                <option value="Georgia, serif" <?php selected(isset($appearance_settings['certificate_font']) ? $appearance_settings['certificate_font'] : '', "Georgia, serif"); ?>>Georgia</option>
                                                <option value="'Times New Roman', Times, serif" <?php selected(isset($appearance_settings['certificate_font']) ? $appearance_settings['certificate_font'] : '', "'Times New Roman', Times, serif"); ?>>Times New Roman</option>
                                                <option value="'Montserrat', sans-serif" <?php selected(isset($appearance_settings['certificate_font']) ? $appearance_settings['certificate_font'] : '', "'Montserrat', sans-serif"); ?>>Montserrat</option>
                                                <option value="'Playfair Display', serif" <?php selected(isset($appearance_settings['certificate_font']) ? $appearance_settings['certificate_font'] : '', "'Playfair Display', serif"); ?>>Playfair Display</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="cai-form-row">
                                        <div class="cai-form-field">
                                            <label for="cai_certificate_logo">Certificate Logo</label>
                                            <div class="cai-media-field">
                                                <input type="text" id="cai_certificate_logo" name="cai_course_appearance_settings[certificate_logo]" 
                                                    value="<?php echo esc_attr(isset($appearance_settings['certificate_logo']) ? $appearance_settings['certificate_logo'] : ''); ?>"
                                                    class="regular-text">
                                                <button type="button" class="button cai-media-button" data-target="cai_certificate_logo">Select Image</button>
                                            </div>
                                            <p class="description">Upload your logo to display on certificates</p>
                                        </div>
                                        
                                        <div class="cai-form-field">
                                            <label for="cai_certificate_signature">Signature Image</label>
                                            <div class="cai-media-field">
                                                <input type="text" id="cai_certificate_signature" name="cai_course_appearance_settings[certificate_signature_image]" 
                                                    value="<?php echo esc_attr(isset($appearance_settings['certificate_signature_image']) ? $appearance_settings['certificate_signature_image'] : ''); ?>"
                                                    class="regular-text">
                                                <button type="button" class="button cai-media-button" data-target="cai_certificate_signature">Select Image</button>
                                            </div>
                                            <p class="description">Upload a signature image</p>
                                        </div>
                                    </div>
                                    
                                    <div class="cai-form-row">
                                        <div class="cai-form-field">
                                            <label for="cai_certificate_company">Company/Organization Name</label>
                                            <input type="text" id="cai_certificate_company" name="cai_course_appearance_settings[certificate_company_name]" 
                                                value="<?php echo esc_attr(isset($appearance_settings['certificate_company_name']) ? $appearance_settings['certificate_company_name'] : get_bloginfo('name')); ?>"
                                                class="regular-text">
                                        </div>
                                        <div class="cai-form-field">
                                            <label for="cai_certificate_title_size">Title Size (px)</label>
                                            <input type="number" id="cai_certificate_title_size" name="cai_course_appearance_settings[certificate_title_size]" 
                                                value="<?php echo esc_attr(isset($appearance_settings['certificate_title_size']) ? $appearance_settings['certificate_title_size'] : '32'); ?>"
                                                min="20" max="48">
                                        </div>
                                    </div>
                                    
                                    <div class="cai-form-row">
                                        <div class="cai-form-field">
                                            <label for="cai_certificate_title_color">Title Color</label>
                                            <input type="color" id="cai_certificate_title_color" name="cai_course_appearance_settings[certificate_title_color]" 
                                                value="<?php echo esc_attr(isset($appearance_settings['certificate_title_color']) ? $appearance_settings['certificate_title_color'] : '#3e69dc'); ?>">
                                        </div>
                                        <div class="cai-form-field">
                                            <label for="cai_certificate_border_color">Border Color</label>
                                            <input type="color" id="cai_certificate_border_color" name="cai_course_appearance_settings[certificate_border_color]" 
                                                value="<?php echo esc_attr(isset($appearance_settings['certificate_border_color']) ? $appearance_settings['certificate_border_color'] : '#c0a080'); ?>">
                                        </div>
                                        <div class="cai-form-field">
                                            <label for="cai_certificate_border_width">Border Width (px)</label>
                                            <input type="number" id="cai_certificate_border_width" name="cai_course_appearance_settings[certificate_border_width]" 
                                                value="<?php echo esc_attr(isset($appearance_settings['certificate_border_width']) ? $appearance_settings['certificate_border_width'] : '5'); ?>"
                                                min="0" max="20">
                                        </div>
                                    </div>
                                    
                                    
                                </td>
                            </tr>

                            <!-- Access Control -->
                            <tr>
                                <th scope="row">Access Control</th>
                                <td>
                                    <div class="cai-form-field">
                                        <label for="cai_course_access">Course Access</label>
                                        <select id="cai_course_access" name="cai_course_appearance_settings[course_access]">
                                            <option value="public" <?php selected(isset($appearance_settings['course_access']) ? $appearance_settings['course_access'] : '', "public"); ?>>Public (Anyone can access)</option>
                                            <option value="user_role" <?php selected(isset($appearance_settings['course_access']) ? $appearance_settings['course_access'] : '', "user_role"); ?>>User Role (Requires login with specific role)</option>
                                        </select>
                                    </div>
                                    
                                    <div class="cai-form-field" id="cai-required-role-field" style="<?php echo (isset($appearance_settings['course_access']) && $appearance_settings['course_access'] === 'user_role') ? '' : 'display: none;'; ?>">
                                        <label for="cai_required_role">Required Role</label>
                                        <select id="cai_required_role" name="cai_course_appearance_settings[required_role]">
                                            <?php
                                            $roles = get_editable_roles();
                                            foreach ($roles as $role_key => $role) {
                                                echo '<option value="' . esc_attr($role_key) . '" ' . 
                                                    selected(isset($appearance_settings['required_role']) ? $appearance_settings['required_role'] : 'subscriber', $role_key, false) . 
                                                    '>' . esc_html($role['name']) . '</option>';
                                            }
                                            ?>
                                        </select>
                                        <p class="description">Users must have this role or higher to access courses</p>
                                    </div>
                                </td>
                            </tr>
                        </table>
                        
                        <script>
                            jQuery(document).ready(function($) {
                                // Initialize media uploader
                                $('.cai-media-button').on('click', function() {
                                    var targetInput = $(this).data('target');
                                    var mediaUploader;
                                    
                                    if (mediaUploader) {
                                        mediaUploader.open();
                                        return;
                                    }
                                    
                                    mediaUploader = wp.media({
                                        title: 'Select Image',
                                        button: {
                                            text: 'Use this image'
                                        },
                                        multiple: false
                                    });
                                    
                                    mediaUploader.on('select', function() {
                                        var attachment = mediaUploader.state().get('selection').first().toJSON();
                                        $('#' + targetInput).val(attachment.url);
                                    });
                                    
                                    mediaUploader.open();
                                });
                                
                                // Show/hide required role field based on access control selection
                                $('#cai_course_access').on('change', function() {
                                    if ($(this).val() === 'user_role') {
                                        $('#cai-required-role-field').show();
                                    } else {
                                        $('#cai-required-role-field').hide();
                                    }
                                });
                            });
                        </script>
                    </div>
                </div>

                <div class="cai-section box">
                    <div class="cai-section-header">
                        <h3>WordPress Layout Integration</h3>
                        <p>Control how course pages integrate with WordPress and your theme's default layout elements.</p>
                    </div>
                    <div class="cai-section-content">
                        <table class="form-table">
                            <!-- Sidebar Settings -->
                            <tr>
                                <th scope="row">Course Page Sidebars</th>
                                <td>
                                    <select id="cai_course_sidebar_layout" name="cai_course_layout_settings[sidebar_layout]">
                                        <option value="default" <?php selected(isset($layout_settings['sidebar_layout']) ? $layout_settings['sidebar_layout'] : 'default', "default"); ?>>Theme Default</option>
                                        <option value="no-sidebar" <?php selected(isset($layout_settings['sidebar_layout']) ? $layout_settings['sidebar_layout'] : 'default', "no-sidebar"); ?>>No Sidebar</option>
                                        <option value="left-sidebar" <?php selected(isset($layout_settings['sidebar_layout']) ? $layout_settings['sidebar_layout'] : 'default', "left-sidebar"); ?>>Left Sidebar Only</option>
                                        <option value="right-sidebar" <?php selected(isset($layout_settings['sidebar_layout']) ? $layout_settings['sidebar_layout'] : 'default', "right-sidebar"); ?>>Right Sidebar Only</option>
                                    </select>
                                    <p class="description">Choose the sidebar layout for course pages. This setting applies to all course pages.</p>
                                </td>
                            </tr>
                            
                            <!-- Featured Image Display -->
                            <tr>
                                <th scope="row">Featured Image Display</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="cai_course_layout_settings[disable_featured_image]" value="1" 
                                        <?php checked(isset($layout_settings['disable_featured_image']) ? $layout_settings['disable_featured_image'] : false); ?>>
                                        Disable theme's featured image display on course pages
                                    </label>
                                    <p class="description">When checked, prevents the theme from displaying the featured image at the top of course pages.</p>
                                </td>
                            </tr>
                            
                            <!-- Content Title Display -->
                            <tr>
                                <th scope="row">Page Title Display</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="cai_course_layout_settings[disable_title]" value="1" 
                                        <?php checked(isset($layout_settings['disable_title']) ? $layout_settings['disable_title'] : false); ?>>
                                        Disable theme's page title on course pages
                                    </label>
                                    <p class="description">When checked, prevents the theme from displaying the page title at the top of course pages.</p>
                                </td>
                            </tr>
                            
                            <!-- Content Width (hidden when theme styling is enabled) -->
                            <tr class="cai-custom-styling-option">
                                <th scope="row">Content Width</th>
                                <td>
                                    <select id="cai_course_content_width" name="cai_course_layout_settings[content_width]">
                                        <option value="default" <?php selected(isset($layout_settings['content_width']) ? $layout_settings['content_width'] : 'default', "default"); ?>>Theme Default</option>
                                        <option value="full-width" <?php selected(isset($layout_settings['content_width']) ? $layout_settings['content_width'] : 'default', "full-width"); ?>>Full Width</option>
                                        <option value="contained" <?php selected(isset($layout_settings['content_width']) ? $layout_settings['content_width'] : 'default', "contained"); ?>>Contained</option>
                                    </select>
                                    <p class="description">Control the width of the content area for course pages (only applies when theme styling is disabled).</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

            </div>


            <div class="cai-submit-container">
                <?php submit_button('Save Settings', 'primary', 'submit', true); ?>
            </div>
        </form>
    </div>
</div>