<?php

trait Creator_AI_SearchAI_Functions {
    
    /**
     * Initialize the SearchAI block and related functionality
     */
    public function initialize_searchai_block() {
        // Register block category
        global $wp_version;
        if (version_compare($wp_version, '5.8', '>=')) {
            add_filter('block_categories_all', array($this, 'add_searchai_block_category'), 9, 2);
        } else {
            add_filter('block_categories', array($this, 'add_searchai_block_category'), 9, 2);
        }
        
        // Register block and assets
        $this->register_searchai_block();
        $this->register_frontend_assets();
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_block_editor_assets'));
        
        // Register AJAX handlers
        add_action('wp_ajax_searchai_query', array($this, 'handle_searchai_query'));
        add_action('wp_ajax_nopriv_searchai_query', array($this, 'handle_searchai_query'));
        
        // Add hook for content analysis when posts are saved
        add_action('save_post', array($this, 'analyze_post_content'), 10, 2);
    }
    
    /**
     * Register frontend assets
     */
    public function register_frontend_assets() {
        wp_register_script(
            'creator-ai-searchai-frontend',
            plugin_dir_url(dirname(__FILE__)) . 'js/searchai.js',
            array('jquery'),
            isset($this->version) ? $this->version : '1.0',
            true
        );
        
        wp_register_style(
            'creator-ai-searchai-style',
            plugin_dir_url(dirname(__FILE__)) . 'css/searchai-style.css',
            array(),
            isset($this->version) ? $this->version : '1.0'
        );
    }

    /**
     * Register the Search AI block
     */
    public function register_searchai_block() {
        if (!function_exists('register_block_type')) {
            return;
        }
        
        $block_args = array(
            'attributes' => array(
                'primaryColor' => array(
                    'type' => 'string',
                    'default' => '#4a6ee0'
                ),
                'secondaryColor' => array(
                    'type' => 'string',
                    'default' => '#f8f8f8'
                ),
                'textColor' => array(
                    'type' => 'string',
                    'default' => '#333333'
                ),
                'borderRadius' => array(
                    'type' => 'number',
                    'default' => 8
                ),
                'padding' => array(
                    'type' => 'number',
                    'default' => 20
                ),
                'maxHeight' => array(
                    'type' => 'number',
                    'default' => 500
                ),
                'placeholder' => array(
                    'type' => 'string',
                    'default' => 'Ask about video editing, VFX, or motion design...'
                ),
                'welcomeMessage' => array(
                    'type' => 'string',
                    'default' => 'Hi! I\'m your video editing assistant. Ask me anything about Premiere Pro, After Effects, or any creative video techniques.'
                ),
                'avatarImage' => array(
                    'type' => 'object',
                    'default' => array()
                ),
                'className' => array(
                    'type' => 'string'
                )
            ),
            'render_callback' => array($this, 'render_searchai_block')
        );
        
        register_block_type('creator-ai/searchai', $block_args);
    }

    /**
     * Add custom block category
     */
    public function add_searchai_block_category($categories, $post) {
        foreach ($categories as $category) {
            if ($category['slug'] === 'creator-ai') {
                return $categories;
            }
        }
        
        return array_merge(
            array(
                array(
                    'slug' => 'creator-ai',
                    'title' => __('Creator AI', 'creator-ai'),
                    'icon'  => 'admin-generic',
                ),
            ),
            $categories
        );
    }
    
    /**
     * Enqueue block editor assets
     */
    public function enqueue_block_editor_assets() {
        wp_enqueue_script(
            'creator-ai-searchai-editor',
            plugin_dir_url(dirname(__FILE__)) . 'js/searchai.js',
            array('wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'jquery'),
            isset($this->version) ? $this->version : '1.0',
            true
        );
        
        wp_localize_script('creator-ai-searchai-editor', 'searchAiData', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('searchai_nonce')
        ));
        
        wp_enqueue_style(
            'creator-ai-searchai-editor-style',
            plugin_dir_url(dirname(__FILE__)) . 'css/searchai-style.css',
            array('wp-edit-blocks'),
            isset($this->version) ? $this->version : '1.0'
        );
    }
    
    /**
     * Render the SearchAI block on the frontend
     */
    public function render_searchai_block($attributes, $content) {
        $attr = wp_parse_args($attributes, array(
            'primaryColor' => '#4a6ee0',
            'secondaryColor' => '#f8f8f8',
            'textColor' => '#333333',
            'borderRadius' => 8,
            'padding' => 20,
            'maxHeight' => 500,
            'placeholder' => 'Ask about video editing, VFX, or motion design...',
            'welcomeMessage' => 'Hi! I\'m your video editing assistant. Ask me anything about Premiere Pro, After Effects, or any creative video techniques.',
            'avatarImage' => array(),
            'className' => ''
        ));
        
        $unique_id = 'searchai-' . uniqid();
        
        wp_enqueue_script('creator-ai-searchai-frontend');
        wp_enqueue_style('creator-ai-searchai-style');
        
        wp_localize_script('creator-ai-searchai-frontend', 'searchAiData', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('searchai_nonce'),
            'instance_id' => $unique_id
        ));
        
        $inline_css = "
            #{$unique_id} {
                --primary-color: {$attr['primaryColor']};
                --secondary-color: {$attr['secondaryColor']};
                --text-color: {$attr['textColor']};
                --border-radius: {$attr['borderRadius']}px;
                --padding: {$attr['padding']}px;
                --max-height: {$attr['maxHeight']}px;
            }
        ";
        
        $avatar_url = !empty($attr['avatarImage']['url']) ? $attr['avatarImage']['url'] : '';
        
        ob_start();
        ?>
        <style><?php echo $inline_css; ?></style>
        <div id="<?php echo esc_attr($unique_id); ?>" class="searchai-container <?php echo esc_attr($attr['className']); ?>" data-avatar="<?php echo esc_attr($avatar_url); ?>">
            <div class="searchai-chat-container">
                <div class="searchai-messages">
                    <?php if (!empty($attr['welcomeMessage'])): ?>
                    <div class="searchai-message searchai-message-ai">
                        <div class="searchai-avatar">
                            <?php if (!empty($avatar_url)): ?>
                                <img src="<?php echo esc_url($avatar_url); ?>" alt="AI Avatar">
                            <?php else: ?>
                                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/>
                                </svg>
                            <?php endif; ?>
                        </div>
                        <div class="searchai-message-content">
                            <?php echo esc_html($attr['welcomeMessage']); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="searchai-input-container">
                    <form class="searchai-form">
                        <input 
                            type="text" 
                            class="searchai-input" 
                            placeholder="<?php echo esc_attr($attr['placeholder']); ?>" 
                            aria-label="Ask a question"
                        >
                        <button type="submit" class="searchai-submit" aria-label="Send question">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Handle AJAX request for Search AI queries
     */
    public function handle_searchai_query() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'searchai_nonce')) {
            wp_send_json_error('Invalid security token');
        }
        
        // Get query from POST
        $query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';
        
        if (empty($query)) {
            wp_send_json_error('No query provided');
        }
        
        // Get conversation history from POST
        $conversation_history = isset($_POST['history']) ? json_decode(stripslashes($_POST['history']), true) : array();
        
        // Validate conversation history
        if (!is_array($conversation_history)) {
            $conversation_history = array();
        }
        
        
        // Step 1: Find relevant content from our website
        $site_content = $this->find_relevant_site_content($query);
        
        // Step 2: If website content is insufficient, search the web
        if (count($site_content) < 2) {
            $web_results = $this->search_web($query);
            // Combine results (web results will appear after site results)
            $merged_content = array_merge($site_content, $web_results);
        } else {
            $merged_content = $site_content;
        }
        
        // Step 3: Generate AI response with conversation history
        $ai_response = $this->generate_response($query, $merged_content, $conversation_history);
        
        // Step 4: Update the conversation history
        $updated_history = $conversation_history;
        $updated_history[] = array('role' => 'user', 'content' => $query);
        
        // Extract just the response text without the sources section
        $response_for_history = $this->extract_response_without_sources($ai_response);
        $updated_history[] = array('role' => 'assistant', 'content' => $response_for_history);
        
        // Return response with updated history
        wp_send_json_success(array(
            'aiResponse' => $ai_response,
            'history' => $updated_history
        ));
    }

    /**
     * Extract just the response part without the sources section
     */
    protected function extract_response_without_sources($response) {
        $sources_pos = strpos($response, "<div class='searchai-relevant-links'>");
        if ($sources_pos !== false) {
            return trim(substr($response, 0, $sources_pos));
        }
        return $response;
    }
    
    /**
     * Find relevant content from the website based on the query
     */
    protected function find_relevant_site_content($query) {
        $results = array();
        
        // First try semantic search using post summaries (if they exist)
        $semantic_results = $this->semantic_search($query);
        if (!empty($semantic_results)) {
            $results = $semantic_results;
        }
        
        // Fall back to standard WP search if semantic search yields no results
        if (empty($results)) {
            $results = $this->standard_wp_search($query);
        }
        
        // If still no results or very few, try a direct database search
        if (count($results) < 2) {
            $db_results = $this->direct_db_search($query);
            
            // Add only non-duplicate results
            foreach ($db_results as $result) {
                $is_duplicate = false;
                foreach ($results as $existing) {
                    if ($existing['url'] === $result['url']) {
                        $is_duplicate = true;
                        break;
                    }
                }
                
                if (!$is_duplicate) {
                    $results[] = $result;
                }
            }
        }
        
        return $results;
    }
    
    /**
     * Semantic search using stored post summaries/topics
     */
    protected function semantic_search($query) {
        $results = array();
        
        // Query posts with post summaries
        $args = array(
            'post_type' => array('post', 'page'),
            'posts_per_page' => 5,
            'post_status' => 'publish',
            'meta_query' => array(
                array(
                    'key' => '_cai_post_summary',
                    'compare' => 'EXISTS',
                )
            )
        );
        
        $query_posts = new WP_Query($args);
        
        if ($query_posts->have_posts()) {
            $query_terms = preg_split('/\s+/', strtolower($query));
            
            while ($query_posts->have_posts()) {
                $query_posts->the_post();
                
                // Get post summary
                $summary = get_post_meta(get_the_ID(), '_cai_post_summary', true);
                $match_score = 0;
                
                // Calculate match score based on term frequency
                foreach ($query_terms as $term) {
                    if (strlen($term) < 3) continue; // Skip very short terms
                    
                    // Check summary
                    $term_count = substr_count(strtolower($summary), $term);
                    $match_score += $term_count * 5;
                    
                    // Check title
                    if (stripos(get_the_title(), $term) !== false) {
                        $match_score += 10;
                    }
                    
                    // Check keywords/topics if they exist
                    $topics = get_post_meta(get_the_ID(), '_cai_post_topics', true);
                    if (!empty($topics) && is_array($topics)) {
                        foreach ($topics as $topic) {
                            if (stripos($topic, $term) !== false) {
                                $match_score += 15;
                                break;
                            }
                        }
                    }
                }
                
                // If there's a reasonable match, add to results
                if ($match_score > 10) {
                    $content = get_the_content();
                    
                    $results[] = array(
                        'title' => get_the_title(),
                        'url' => get_permalink(),
                        'excerpt' => $summary,
                        'date' => get_the_date('F j, Y'),
                        'timestamp' => get_the_date('U'),
                        'content' => wp_strip_all_tags($content),
                        'score' => $match_score
                    );
                }
            }
            
            wp_reset_postdata();
            
            // Sort by match score
            usort($results, function($a, $b) {
                return $b['score'] - $a['score'];
            });
            
            // Get top results
            $results = array_slice($results, 0, 5);
        }
        
        return $results;
    }
    
    /**
     * Standard WordPress search
     */
    protected function standard_wp_search($query) {
        $results = array();
        
        $args = array(
            's' => $query,
            'post_type' => array('post', 'page'),
            'posts_per_page' => 5,
            'post_status' => 'publish'
        );
        
        $search_query = new WP_Query($args);
        
        if ($search_query->have_posts()) {
            while ($search_query->have_posts()) {
                $search_query->the_post();
                
                $content = get_the_content();
                $excerpt = has_excerpt() ? get_the_excerpt() : wp_trim_words($content, 30);
                
                $results[] = array(
                    'title' => get_the_title(),
                    'url' => get_permalink(),
                    'excerpt' => $excerpt,
                    'date' => get_the_date('F j, Y'),
                    'timestamp' => get_the_date('U'),
                    'content' => wp_strip_all_tags($content)
                );
            }
            
            wp_reset_postdata();
        }
        
        return $results;
    }
    
    /**
     * Direct database search for more flexible matching
     */
    protected function direct_db_search($query) {
        global $wpdb;
        $results = array();
        
        // Split query into terms
        $terms = preg_split('/\s+/', $query);
        $search_terms = array();
        
        foreach ($terms as $term) {
            if (strlen($term) > 3) { // Only consider terms longer than 3 characters
                $search_terms[] = $term;
            }
        }
        
        if (empty($search_terms)) {
            return $results;
        }
        
        // Build query conditions
        $conditions = array();
        
        foreach ($search_terms as $term) {
            $like_term = '%' . $wpdb->esc_like($term) . '%';
            $conditions[] = $wpdb->prepare(
                "(post_title LIKE %s OR post_content LIKE %s)",
                $like_term,
                $like_term
            );
        }
        
        $where_clause = '(' . implode(' OR ', $conditions) . ')';
        
        $sql = "
            SELECT ID, post_title, post_content, post_date
            FROM {$wpdb->posts}
            WHERE post_status = 'publish'
            AND post_type IN ('post', 'page')
            AND {$where_clause}
            ORDER BY post_date DESC
            LIMIT 5
        ";
        
        $posts = $wpdb->get_results($sql);
        
        if (!empty($posts)) {
            foreach ($posts as $post) {
                $excerpt = wp_trim_words(wp_strip_all_tags($post->post_content), 30);
                
                $results[] = array(
                    'title' => $post->post_title,
                    'url' => get_permalink($post->ID),
                    'excerpt' => $excerpt,
                    'date' => date('F j, Y', strtotime($post->post_date)),
                    'timestamp' => strtotime($post->post_date),
                    'content' => wp_strip_all_tags($post->post_content)
                );
            }
        }
        
        return $results;
    }
    
    /**
     * Search the web using Google API
     */
    protected function search_web($query) {
        $results = array();
        
        // Get access token using the existing OAuth setup
        $access_token = $this->get_access_token();
        
        if (empty($access_token)) {
            return $results;
        }
        
        // Add the current year to search query for recency
        $search_query = $query . ' ' . date('Y');
        
        // Call Google Custom Search API
        $cx = 'partner-pub-2382315170155951:spkgef-waob'; // Update with your Custom Search Engine ID
        $url = 'https://www.googleapis.com/customsearch/v1?';
        $url .= 'q=' . urlencode($search_query);
        $url .= '&cx=' . urlencode($cx);
        $url .= '&num=5';
        
        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token
            ),
            'timeout' => 15
        );
        
        $response = cai_remote_get($url, $args);
        
        if (is_wp_error($response)) {
            return $results;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (empty($data['items'])) {
            return $results;
        }
        
        // Convert results to our standard format
        foreach ($data['items'] as $item) {
            $results[] = array(
                'title' => $item['title'],
                'url' => $item['link'],
                'excerpt' => isset($item['snippet']) ? $item['snippet'] : '',
                'date' => date('F j, Y'), // Web results often don't have dates
                'timestamp' => time(),
                'content' => isset($item['snippet']) ? $item['snippet'] : '',
                'is_web_result' => true
            );
        }
        
        return $results;
    }
    
    /**
     * Generate AI response with content evaluation
     */
    protected function generate_response($query, $content_items, $conversation_history = array()) {
        // Get API key and model
        $api_key = Creator_AI::get_credential('cai_openai_api_key');
        $model = get_option('cai_openai_model', 'gpt-4o');
        
        if (empty($api_key)) {
            return 'API key not configured. Please set up your OpenAI API key in the Creator AI settings.';
        }
        
        // Create system message
        $system_message = "You are a helpful AI assistant specialized in video editing topics. You excel at providing extremely concise, focused responses about video editing software, techniques, and creative approaches.

        Your primary goal is to be helpful while being as brief as possible. Answer ONLY what was asked, nothing more.

        KEY GUIDELINES:
        1. Be extremely concise - aim for 1-3 sentences maximum unless specifically asked for more detail
        2. Answer ONLY the specific question asked - do not add extra information
        3. Omit pleasantries, introductions, and unnecessary context
        4. If you're unsure what information is needed, provide only the most essential facts
        5. Never explain what you're going to answer - just answer directly
        6. Do not suggest additional information the user didn't ask for
        7. Use simple, direct language - be precise and efficient with words
        8. When asked about recommended resources like youtube channel or learning blog first check with Premiere Basics or After Effects Basics
        9. Avoid talking about Premiere Gal as her tutorials are often times stolen ideas

        For formatting:
        1. Use HTML tags like <p>, <strong>, <em>, <ol><li>, <ul><li>
        2. Links should always use <a href=\"...\" target=\"_blank\">...</a> format
        3. Ensure proper HTML formatting in all responses

        For conversation:
        1. Respond naturally to greetings and casual conversation, but still be brief
        2. Remember previous parts of conversation for context in follow-up questions
        3. Never reference sources or mention where your information comes from

        Remember: Less is more. The perfect response contains only the specific information requested, nothing more.";

        // Build messages array for the API
        $messages = array(
            array(
                'role' => 'system',
                'content' => $system_message
            )
        );
        
        // Add conversation history (limit to last 10 messages to prevent token overflow)
        if (!empty($conversation_history)) {
            $limited_history = array_slice($conversation_history, -10);
            $messages = array_merge($messages, $limited_history);
        }
        
        // Prepare content context
        $content_context = '';
        if (!empty($content_items)) {
            $content_context = "Here is information that might help answer the query:\n\n";
            
            foreach ($content_items as $index => $item) {
                $source_type = !empty($item['is_web_result']) ? "[WEB]" : "[WEBSITE]";
                $content_context .= "SOURCE " . ($index + 1) . ": " . $item['title'] . " " . $source_type . "\n";
                $content_context .= "URL: " . $item['url'] . "\n";
                
                if (!empty($item['content'])) {
                    // Limit content length
                    $content_sample = substr($item['content'], 0, 800);
                    $content_context .= "CONTENT: " . $content_sample . "...\n\n";
                } else {
                    $content_context .= "EXCERPT: " . $item['excerpt'] . "\n\n";
                }
            }
            
            // Add instruction for source evaluation
            $content_context .= "\nPlease respond to the user query, keeping in mind the conversation history. Additionally, evaluate which of the provided sources (if any) are genuinely relevant and would be helpful for the user to read. For each SOURCE, respond with INCLUDE or EXCLUDE in the following format at the end of your answer:

    ###SOURCES###
    SOURCE 1: [INCLUDE/EXCLUDE]
    SOURCE 2: [INCLUDE/EXCLUDE]
    ...

    Only mark sources as INCLUDE if they are highly relevant to the specific query and would provide valuable additional information beyond your response. Otherwise, mark them as EXCLUDE.";
        }
        
        // Add current query with context
        $current_message = $query;
        if (!empty($content_context)) {
            $current_message .= "\n\n" . $content_context;
        }
        
        $messages[] = array(
            'role' => 'user',
            'content' => $current_message
        );
        
        // Prepare the request data
        $data = array(
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.7,
            'max_tokens' => 800
        );
        
        // Call the OpenAI API
        $response = $this->openai_request($data, $api_key);
        
        if ($response === false) {
            return 'Sorry, I encountered an error while processing your message. Please try again later.';
        }
        
        // Extract AI response
        $ai_message = $response['choices'][0]['message']['content'];
        
        // Extract source evaluations and clean up response
        $sources_section = "###SOURCES###";
        $final_response = $ai_message;
        $included_sources = array();
        
        if (strpos($ai_message, $sources_section) !== false) {
            // Split the message to get only the response part
            $parts = explode($sources_section, $ai_message, 2);
            $final_response = trim($parts[0]);
            
            // Parse the sources evaluation
            if (isset($parts[1])) {
                $source_evaluations = explode("\n", trim($parts[1]));
                
                foreach ($source_evaluations as $evaluation) {
                    if (empty(trim($evaluation))) continue;
                    
                    if (preg_match('/SOURCE\s+(\d+):\s+INCLUDE/i', $evaluation, $matches)) {
                        $source_index = (int)$matches[1] - 1;
                        if (isset($content_items[$source_index])) {
                            $included_sources[] = $content_items[$source_index];
                        }
                    }
                }
            }
        }
        
        // Add resource links if there are included sources
        if (!empty($included_sources)) {
            $final_response .= "\n\n<div class='searchai-relevant-links'><strong>Relevant resources:</strong><ul>";
            
            foreach ($included_sources as $source) {
                $title = !empty($source['is_web_result']) ? '🌐 ' . $source['title'] : $source['title'];
                $final_response .= "<li><a href='" . esc_url($source['url']) . "' target='_blank'>" . esc_html($title) . "</a></li>";
            }
            
            $final_response .= "</ul></div>";
        }
        
        return $final_response;
    }
    
    /**
     * Analyze and store content information when a post is saved
     */
    public function analyze_post_content($post_id, $post) {
        // Only process published posts and pages
        if ($post->post_status !== 'publish' || !in_array($post->post_type, array('post', 'page'))) {
            return;
        }
        
        // Get content
        $title = $post->post_title;
        $content = wp_strip_all_tags($post->post_content);
        
        // Skip if content is too short
        if (strlen($content) < 50) {
            return;
        }
        
        // Generate and store a summary
        $summary = $this->generate_content_summary($title, $content);
        if (!empty($summary)) {
            update_post_meta($post_id, '_cai_post_summary', $summary);
        }
        
        // Extract and store topics
        $topics = $this->extract_content_topics($title, $content);
        if (!empty($topics)) {
            update_post_meta($post_id, '_cai_post_topics', $topics);
        }
    }
    
    /**
     * Generate a brief summary of post content
     */
    protected function generate_content_summary($title, $content) {
        // Simple summary generation - extract first paragraph(s)
        $paragraphs = explode("\n\n", $content);
        $summary = '';
        
        // Get first few paragraphs up to about 300 characters
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if (empty($paragraph)) continue;
            
            $summary .= $paragraph . ' ';
            if (strlen($summary) > 300) {
                break;
            }
        }
        
        // Trim to 350 chars max
        $summary = substr($summary, 0, 350);
        
        // Add title at the beginning for better context
        $summary = $title . ': ' . $summary;
        
        return trim($summary);
    }
    
    /**
     * Extract key topics from content
     */
    protected function extract_content_topics($title, $content) {
        $topics = array();
        
        // Common video editing software products
        $products = array(
            'premiere pro', 'after effects', 'photoshop', 'illustrator', 
            'indesign', 'lightroom', 'final cut pro', 'davinci resolve',
            'vegas pro', 'filmora', 'kdenlive', 'shotcut', 'hitfilm'
        );
        
        // Content categories/topics
        $categories = array(
            'tutorial', 'guide', 'review', 'comparison', 'tips', 'tricks',
            'workflow', 'effects', 'transitions', 'editing', 'color grading',
            'audio', 'animation', 'motion graphics', 'vfx', 'compositing',
            'green screen', 'template', 'preset', 'plugin', 'extension',
            'script', 'keyboard shortcut', 'feature', 'update', 'new release'
        );
        
        // Check title and content for products
        $combined_text = strtolower($title . ' ' . $content);
        
        foreach ($products as $product) {
            if (strpos($combined_text, $product) !== false) {
                $topics[] = $product;
            }
        }
        
        // Check for categories/topics
        foreach ($categories as $category) {
            if (strpos($combined_text, $category) !== false) {
                $topics[] = $category;
            }
        }
        
        // Limit to top 10 topics
        return array_slice(array_unique($topics), 0, 10);
    }
    
    /**
     * Clean response text to remove formatting issues
     */
    protected function clean_response_text($text) {
        // Remove markdown links
        $text = preg_replace('/\[(.*?)\]\((.*?)\)/', '$1', $text);
        
        // Remove phrases referencing sources
        $text = preg_replace('/According to (the|source|provided) (source|information|context|content)(s|)[\s,\.:]*/i', '', $text);
        $text = preg_replace('/Based on (the|source|provided) (source|information|context|content)(s|)[\s,\.:]*/i', '', $text);
        $text = preg_replace('/As mentioned in (the|source|provided) (source|information|context|content)(s|)[\s,\.:]*/i', '', $text);
        
        // Remove markdown formatting
        $text = preg_replace('/\*\*(.*?)\*\*/', '$1', $text); // Bold
        $text = preg_replace('/\*(.*?)\*/', '$1', $text);     // Italic
        
        return $text;
    }
}