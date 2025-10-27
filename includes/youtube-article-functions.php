<?php
trait Creator_AI_YouTube_Article_Functions {

// FETCH VIDEO PAGE
    public function handle_image_upload() {
        check_ajax_referer('yta_nonce', 'nonce');
        
        if (empty($_FILES['file'])) {
            wp_send_json_error('No file uploaded.');
        }
        
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        $uploaded = wp_handle_upload($_FILES['file'], array('test_form' => false));
        
        if (isset($uploaded['error'])) {
            wp_send_json_error($uploaded['error']);
        }
        
        $filetype = wp_check_filetype($uploaded['file'], null);
        $attachment = array(
            'post_mime_type' => $filetype['type'],
            'post_title' => sanitize_file_name(basename($uploaded['file'])),
            'post_content' => '',
            'post_status' => 'inherit'
        );
        
        $attach_id = wp_insert_attachment($attachment, $uploaded['file']);
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attach_id, $uploaded['file']);
        wp_update_attachment_metadata($attach_id, $attach_data);
        
        $thumb = wp_get_attachment_image_src($attach_id, 'thumbnail');
        wp_send_json_success(array('attachment_id' => $attach_id, 'thumbnail' => $thumb[0]));
    }
    public function fetch_videos() {
        check_ajax_referer('yta_nonce', 'nonce');
        
        $channel_id = get_option('cai_youtube_channel_id');
        if (empty($channel_id)) {
            wp_send_json_error('YouTube Channel ID must be set.');
        }
        
        $token = $this->get_access_token();
        if (!$token) {
            wp_send_json_error("Failed to get OAuth access token.");
        }
        
        $page_token = isset($_POST['pageToken']) ? sanitize_text_field($_POST['pageToken']) : '';
        $url = "https://www.googleapis.com/youtube/v3/search?part=snippet,id&channelId=" . $channel_id . "&order=date&maxResults=10";
        
        if ($page_token) {
            $url .= "&pageToken=" . $page_token;
        }
        
        $response = cai_remote_get($url, array(
            'headers' => array('Authorization' => 'Bearer ' . $token),
            'timeout' => 30
        ));
        
        
        $result = $this->handle_api_error($response, 'YouTube API');
        if ($result['error']) {
            wp_send_json_error($result['message']);
        }
        
        $data = $result['data'];
        
        // Build videos HTML
        $html = '<div class="yta-video-list">';
        
        if (!empty($data['items'])) {
            foreach ($data['items'] as $item) {
                if ($item['id']['kind'] !== 'youtube#video') continue;
                
                $vid = esc_attr($item['id']['videoId']);
                $title = esc_html($item['snippet']['title']);
                $pub = date('F j, Y', strtotime($item['snippet']['publishedAt']));
                
                if (isset($item['snippet']['thumbnails']['maxres'])) {
                    $thumb = esc_url($item['snippet']['thumbnails']['maxres']['url']);
                } else {
                    $thumb = esc_url($item['snippet']['thumbnails']['high']['url']);
                }
                
                // Check if article for this video already exists
                $article_exists = false;
                $post_status = '';
                
                // First check by video ID in post meta
                $posts = get_posts(array(
                    'meta_key' => '_youtube_video_id',
                    'meta_value' => $vid,
                    'post_type' => 'post',
                    'post_status' => array('publish', 'draft', 'pending', 'future'),
                    'numberposts' => 1
                ));
                
                if (!empty($posts)) {
                    $article_exists = true;
                    $post_status = $posts[0]->post_status;
                } else {
                    // Then try to match by title similarity
                    $similar_title_query = new WP_Query(array(
                        'post_type' => 'post',
                        'post_status' => array('publish', 'draft', 'pending', 'future'),
                        's' => $title,
                        'posts_per_page' => 5
                    ));
                    
                    if ($similar_title_query->have_posts()) {
                        while ($similar_title_query->have_posts()) {
                            $similar_title_query->the_post();
                            // Compare title similarity (if 80% similar, consider it a match)
                            $post_title = get_the_title();
                            $similarity = similar_text($post_title, $title, $percent);
                            if ($percent > 80) {
                                $article_exists = true;
                                $post_status = get_post_status();
                                break;
                            }
                        }
                        wp_reset_postdata();
                    }
                }
                
                // Add indicator for published articles
                $status_indicator = '';
                if ($article_exists) {
                    $status_label = ucfirst($post_status);
                    $status_class = 'article-' . $post_status;
                    $status_indicator = "<div class='yta-published-indicator {$status_class}'>{$status_label}</div>";
                }
                
                $html .= "<div class='yta-video" . ($article_exists ? " has-article bg-{$post_status}" : "") . "' data-video-id='{$vid}'>
                            {$status_indicator}
                            <img src='{$thumb}' alt='{$title}' />
                            <div class='video-info'>
                                <strong>{$title}</strong>
                                <span style='color:#999;font-size:0.9em;margin-top:3px;display:block;'>{$pub}</span>
                                <div class='yta-status'></div>
                            </div>
                          </div>";
            }
        }
        
        $html .= '</div>';
        
        $next_page_token = isset($data['nextPageToken']) ? $data['nextPageToken'] : '';
        $prev_page_token = isset($data['prevPageToken']) ? $data['prevPageToken'] : '';
        
        wp_send_json_success(array(
            'html' => $html,
            'nextPageToken' => $next_page_token,
            'prevPageToken' => $prev_page_token
        ));
    }
    public function check_youtube_post_exists() {
        check_ajax_referer('yta_nonce', 'nonce');
        
        if (!isset($_POST['videoId'])) {
            wp_send_json_error('Video ID is required.');
        }
        
        $video_id = sanitize_text_field($_POST['videoId']);
        
        // Query posts to find if this video was processed
        $posts = get_posts(array(
            'meta_key' => '_youtube_video_id',
            'meta_value' => $video_id,
            'post_type' => 'post',
            'post_status' => 'any',
            'numberposts' => 1
        ));
        
        if (!empty($posts)) {
            wp_send_json_success(array('post_id' => $posts[0]->ID));
        } else {
            wp_send_json_error('No post found for this video.');
        }
    }

// BACKGROUND ARTICLE CREATION
    public function create_youtube_article_background() {
        check_ajax_referer('yta_nonce', 'nonce');

        // Validate video ID
        $videoId = isset($_POST['videoId']) ? sanitize_text_field($_POST['videoId']) : '';
        if (empty($videoId)) {
            wp_send_json_error("Video ID is required.");
        }

        // Store a "started" status immediately for polling to detect
        set_transient('yta_result_' . $videoId, array(
            'status' => 'started',
            'timestamp' => time()
        ), 300);

        // Send immediate response - processing will start
        wp_send_json_success(array('status' => 'processing', 'video_id' => $videoId));
    }

// ARTICLE CREATION
    public function create_youtube_article() {
        error_log('=== CREATOR AI DEBUG START === ' . date('Y-m-d H:i:s'));
        
        check_ajax_referer('yta_nonce', 'nonce');
        error_log('Creator AI Debug: Nonce validated at ' . date('Y-m-d H:i:s'));
        
        // Validate video ID
        $videoId = isset($_POST['videoId']) ? sanitize_text_field($_POST['videoId']) : '';
        if (empty($videoId)) {
            error_log('Creator AI Debug: ERROR - No video ID provided');
            wp_send_json_error("Video ID is required.");
        }
        error_log('Creator AI Debug: Video ID validated: ' . $videoId . ' at ' . date('Y-m-d H:i:s'));
        
        try {
            // 1. Validate prerequisites
            error_log('Creator AI Debug: STEP 1 - Starting API validation at ' . date('Y-m-d H:i:s'));
            $this->validate_api_credentials();
            error_log('Creator AI Debug: STEP 1 - API validation completed at ' . date('Y-m-d H:i:s'));
            
            // 2. Fetch video data (details and transcript)
            error_log('Creator AI Debug: STEP 2 - Starting video data fetch at ' . date('Y-m-d H:i:s'));
            $video_data = $this->fetch_video_data($videoId);
            error_log('Creator AI Debug: STEP 2 - Video data fetched at ' . date('Y-m-d H:i:s'));
            
            // 3. Generate article content
            error_log('Creator AI Debug: STEP 3 - Starting article generation at ' . date('Y-m-d H:i:s'));
            $article_data = $this->generate_article_content(
                $video_data['title'], 
                $video_data['transcript'],
                $this->get_filtered_categories()
            );
            error_log('Creator AI Debug: STEP 3 - Article generation completed at ' . date('Y-m-d H:i:s'));
            
            // 4. Process article content
            error_log('Creator AI Debug: STEP 4 - Starting article processing at ' . date('Y-m-d H:i:s'));
            $processed_content = $this->process_article_content(
                $article_data['article'],
                $videoId,
                $video_data['transcript'],
                $video_data['title']
            );
            error_log('Creator AI Debug: STEP 4 - Article processing completed at ' . date('Y-m-d H:i:s'));
            
            // 5. Create WordPress post
            error_log('Creator AI Debug: STEP 5 - Starting post creation at ' . date('Y-m-d H:i:s'));
            $post_id = $this->create_complete_post(
                $video_data['title'],
                $processed_content,
                $video_data['pubdate'],
                $video_data['thumbnail_url'],
                $videoId,
                $article_data
            );
            error_log('Creator AI Debug: STEP 5 - Post creation completed, Post ID: ' . $post_id . ' at ' . date('Y-m-d H:i:s'));
            
            $response_data = array('post_id' => $post_id);
            
            
            // 6. Store success status for polling
            error_log('Creator AI Debug: STEP 6 - Storing success status at ' . date('Y-m-d H:i:s'));

            // Store the result in a transient for polling
            set_transient('yta_result_' . $videoId, array(
                'post_id' => $post_id,
                'status' => 'completed',
                'timestamp' => time()
            ), 300); // 5 minutes

            error_log('Creator AI Debug: Success status stored at ' . date('Y-m-d H:i:s'));

            // Return the post_id instead of sending JSON response (will be handled by caller)
            return $post_id;

            
        } catch (Exception $e) {
            error_log('Creator AI Debug: EXCEPTION caught: ' . $e->getMessage() . ' at ' . date('Y-m-d H:i:s'));

            // Store error status for polling
            set_transient('yta_result_' . $videoId, array(
                'error' => $e->getMessage(),
                'status' => 'failed',
                'timestamp' => time()
            ), 300);

            // Let the caller handle the response
            throw $e;
        }
    }
    protected function fetch_video_data($videoId) {
        $token = $this->get_access_token();
        
        // Get video details
        $video = $this->get_video_details($videoId, $token);
        if (!$video) {
            throw new Exception('Error fetching video details.');
        }
        
        // Get transcript
        $transcript = $this->get_youtube_transcript($videoId);
        if (empty($transcript)) {
            throw new Exception('Transcript not available.');
        }
        
        return array(
            'title' => sanitize_text_field($video['snippet']['title']),
            'pubdate' => $video['snippet']['publishedAt'],
            'thumbnail_url' => isset($video['snippet']['thumbnails']['maxres']) 
                ? esc_url_raw($video['snippet']['thumbnails']['maxres']['url']) 
                : esc_url_raw($video['snippet']['thumbnails']['high']['url']),
            'transcript' => $transcript
        );
    }
    protected function generate_article_content($title, $transcript, $categories) {
        $combined_data = $this->generate_combined_content($title, $transcript, $categories);
        if (empty($combined_data) || empty($combined_data['article'])) {
            throw new Exception('Failed to generate article content.');
        }
        
        return $combined_data;
    }
    protected function process_article_content($article, $videoId, $transcript, $video_title = '') {
        // FIRST: Replace ALL UTF-8 special characters before ANY DOMDocument processing
        // This prevents multiple layers of encoding corruption
        $article = str_replace(
            array("\xE2\x80\x9C", "\xE2\x80\x9D", "\xE2\x80\x98", "\xE2\x80\x99", "\xE2\x80\x93", "\xE2\x80\x94"),
            array('"', '"', "'", "'", '-', '-'),
            $article
        );

        error_log('Creator AI Debug: process_article_content - Starting link processing at ' . date('Y-m-d H:i:s'));
        // 1. Process all links in one step
        $article = $this->process_all_links($article);
        error_log('Creator AI Debug: process_article_content - Link processing completed at ' . date('Y-m-d H:i:s'));
        
        error_log('Creator AI Debug: process_article_content - Starting paragraph structure improvement at ' . date('Y-m-d H:i:s'));
        // 2. Improve paragraph structure
        $article = $this->improve_paragraph_structure($article);
        error_log('Creator AI Debug: process_article_content - Paragraph structure improvement completed at ' . date('Y-m-d H:i:s'));
        
        error_log('Creator AI Debug: process_article_content - Starting paragraph split at ' . date('Y-m-d H:i:s'));
        // 3. Split into paragraphs
        $paragraphs = preg_split("/\r\n\r\n|\n\n|\r\r|<\/p>\s*<p>/", $article);
        error_log('Creator AI Debug: process_article_content - Paragraph split completed, found ' . count($paragraphs) . ' paragraphs at ' . date('Y-m-d H:i:s'));
        
        error_log('Creator AI Debug: process_article_content - Processing uploaded images at ' . date('Y-m-d H:i:s'));
        // 4. Process uploaded images
        $uploaded_images = isset($_POST['uploaded_images']) ? $_POST['uploaded_images'] : array();
        $uploaded_images = is_array($uploaded_images) ? array_unique($uploaded_images) : array();
        error_log('Creator AI Debug: process_article_content - Found ' . count($uploaded_images) . ' uploaded images at ' . date('Y-m-d H:i:s'));
        
        error_log('Creator AI Debug: process_article_content - Starting content formatting at ' . date('Y-m-d H:i:s'));
        // 5. Build article content with proper formatting
        $is_generate_blocks = is_plugin_active("generateblocks/plugin.php");
        
        $final_content = $this->format_youtube_content(
            $paragraphs,
            $videoId,
            $uploaded_images,
            $transcript,
            $is_generate_blocks,
            $video_title
        );
        error_log('Creator AI Debug: process_article_content - Content formatting completed at ' . date('Y-m-d H:i:s'));
        
        error_log('Creator AI Debug: process_article_content - Starting HTML markup fix at ' . date('Y-m-d H:i:s'));
        // 6. Fix HTML markup
        $result = $this->fix_html_markup($final_content);
        error_log('Creator AI Debug: process_article_content - HTML markup fix completed at ' . date('Y-m-d H:i:s'));
        return $result;
    }
    protected function create_complete_post($title, $content, $pubdate, $thumbnail_url, $videoId, $article_data) {
        error_log('Creator AI Debug: create_complete_post - Starting featured image setting at ' . date('Y-m-d H:i:s'));
        // 1. Set featured image
        $thumb_id = $this->set_featured_image($thumbnail_url, $title);
        if (!$thumb_id) {
            error_log('Creator AI Debug: create_complete_post - ERROR: Failed to set featured image at ' . date('Y-m-d H:i:s'));
            throw new Exception('Error setting featured image.');
        }
        error_log('Creator AI Debug: create_complete_post - Featured image set, ID: ' . $thumb_id . ' at ' . date('Y-m-d H:i:s'));
        
        error_log('Creator AI Debug: create_complete_post - Starting WordPress post creation at ' . date('Y-m-d H:i:s'));
        // 2. Create post
        $post_id = $this->create_wordpress_post($title, $content, $pubdate, $thumb_id);
        if (!$post_id) {
            error_log('Creator AI Debug: create_complete_post - ERROR: Failed to create WordPress post at ' . date('Y-m-d H:i:s'));
            throw new Exception('Error creating post.');
        }
        error_log('Creator AI Debug: create_complete_post - WordPress post created, ID: ' . $post_id . ' at ' . date('Y-m-d H:i:s'));
        
        error_log('Creator AI Debug: create_complete_post - Storing YouTube video ID meta at ' . date('Y-m-d H:i:s'));
        // 3. Store YouTube Video ID in post meta
        update_post_meta($post_id, '_youtube_video_id', $videoId);
        error_log('Creator AI Debug: create_complete_post - YouTube video ID meta stored at ' . date('Y-m-d H:i:s'));
        
        error_log('Creator AI Debug: create_complete_post - Starting post metadata processing at ' . date('Y-m-d H:i:s'));
        // 4. Process post metadata
        $this->process_youtube_post_metadata($post_id, $title, $videoId, $content, $article_data);
        error_log('Creator AI Debug: create_complete_post - Post metadata processing completed at ' . date('Y-m-d H:i:s'));
        
        return $post_id;
    }
    protected function format_youtube_content($paragraphs, $videoId, $uploaded_images, $transcript, $use_generate_blocks = false, $video_title = '') {
        $final_content = "";

        // Build raw HTML content and store it for debugging.
        $raw_content = implode("\n\n", $paragraphs);
        update_option('yta_raw_content', $raw_content);

        // Replace smart quotes and special characters BEFORE DOMDocument processing
        $raw_content = str_replace(
            array("\xE2\x80\x9C", "\xE2\x80\x9D", "\xE2\x80\x98", "\xE2\x80\x99", "\xE2\x80\x93", "\xE2\x80\x94"),
            array('"', '"', "'", "'", '-', '-'),
            $raw_content
        );

        // Parse HTML content into DOMDocument.
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $doc->loadHTML('<!DOCTYPE html><html><body>' . $raw_content . '</body></html>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        // Extract top-level HTML elements.
        $body = $doc->getElementsByTagName('body')->item(0);
        $elements = [];
        foreach ($body->childNodes as $node) {
            if ($node->nodeType === XML_ELEMENT_NODE) {
                $elements[] = $doc->saveHTML($node);
            }
        }

        // Calculate image insertion interval.
        $total_elements = count($elements);
        $total_images = count($uploaded_images);
        $img_interval = min(5, max(3, floor($total_elements / ($total_images + 1))));
        $img_index = 0;

        $video_inserted = false;
        $last_was_heading = false;
        $paragraphs_after_heading = 0;
        $heading_number = 1; // For numbering H2 headings only

        // Loop through all elements.
        for ($i = 0; $i < $total_elements; $i++) {
            $element = $elements[$i];
            // Skip empty elements.
            if (empty(trim(strip_tags($element)))) {
                continue;
            }

            // Check if this is the first heading and the video hasn't been added yet.
            if (!$video_inserted && (strpos($element, '<h1') === 0 || strpos($element, '<h2') === 0 || strpos($element, '<h3') === 0)) {
                $final_content .= $this->generate_youtube_embed_block($videoId);
                $video_inserted = true;
            }

            // Process heading elements.
            if (strpos($element, '<h1') === 0 || strpos($element, '<h2') === 0 || strpos($element, '<h3') === 0) {
                preg_match('/<h([1-3])[^>]*>(.*?)<\/h\1>/is', $element, $matches);
                if (empty($matches)) {
                    continue;
                }
                $level = intval($matches[1]);
                $heading_text = trim($matches[2]);

                // For H2 headings, add numbering if not already numbered and not "Conclusion".
                if ($level == 2 && !preg_match('/^\s*\d+\.\s+/', $heading_text) && $heading_text !== 'Conclusion') {
                    $heading_text = $heading_number . '. ' . $heading_text;
                    $heading_number++;
                    $element = "<h{$level}>{$heading_text}</h{$level}>";
                }

                // Add filler paragraph if previous element was a heading and had no following paragraph.
                if ($last_was_heading && $paragraphs_after_heading === 0) {
                    $filler = "<p>" . $this->filler_paragraphs[0] . "</p>";
                    $final_content .= $this->process_paragraph($filler, $use_generate_blocks);
                }

                $final_content .= $this->process_heading($element, $use_generate_blocks);
                $last_was_heading = true;
                $paragraphs_after_heading = 0;

                // Check if next element is a long paragraph that might need splitting.
                if ($i + 1 < $total_elements && strpos($elements[$i + 1], '<p') === 0) {
                    preg_match('/<p[^>]*>(.*?)<\/p>/is', $elements[$i + 1], $matches);
                    if (isset($matches[1]) && strlen($matches[1]) > 300) {
                        $elements[$i + 1] = '<p data-split-into-multiple="true">' . $matches[1] . '</p>';
                    }
                }
            }
            // Process paragraph elements.
            elseif (strpos($element, '<p') === 0) {
                if (strpos($element, 'data-split-into-multiple="true"') !== false) {
                    preg_match('/<p[^>]*>(.*?)<\/p>/is', $element, $matches);
                    if (isset($matches[1])) {
                        $content_to_split = $matches[1];
                        $min_paragraphs = $last_was_heading ? 3 : 2;
                        $sentences = preg_split('/([.!?])(\s+|$)/', $content_to_split, -1, PREG_SPLIT_DELIM_CAPTURE);
                        $sentence_groups = [];
                        $current_group = '';

                        for ($s = 0; $s < count($sentences); $s += 3) {
                            if (isset($sentences[$s])) {
                                $sentence = $sentences[$s];
                                $delimiter = isset($sentences[$s+1]) ? $sentences[$s+1] : '';
                                $space = isset($sentences[$s+2]) ? $sentences[$s+2] : ' ';
                                $current_group .= $sentence . $delimiter . $space;
                                if (($s + 3) % (mt_rand(6, 9)) === 0 || $s + 3 >= count($sentences)) {
                                    if (trim($current_group)) {
                                        $sentence_groups[] = trim($current_group);
                                        $current_group = '';
                                    }
                                }
                            }
                        }
                        if (trim($current_group)) {
                            $sentence_groups[] = trim($current_group);
                        }
                        if (count($sentence_groups) < $min_paragraphs) {
                            $longest_idx = 0;
                            $max_length = 0;
                            foreach ($sentence_groups as $idx => $group) {
                                if (strlen($group) > $max_length) {
                                    $max_length = strlen($group);
                                    $longest_idx = $idx;
                                }
                            }
                            if ($max_length > 200) {
                                $long_paragraph = $sentence_groups[$longest_idx];
                                $split_sentences = preg_split('/([.!?])(\s+|$)/', $long_paragraph, -1, PREG_SPLIT_DELIM_CAPTURE);
                                $midpoint = floor(count($split_sentences) / 2);
                                $first_half = '';
                                $second_half = '';
                                for ($s = 0; $s < count($split_sentences); $s += 3) {
                                    $sentence = $split_sentences[$s] ?? '';
                                    $delimiter = $split_sentences[$s+1] ?? '';
                                    $space = $split_sentences[$s+2] ?? ' ';
                                    if ($s < $midpoint) {
                                        $first_half .= $sentence . $delimiter . $space;
                                    } else {
                                        $second_half .= $sentence . $delimiter . $space;
                                    }
                                }
                                array_splice($sentence_groups, $longest_idx, 1, [trim($first_half), trim($second_half)]);
                            }
                        }
                        foreach ($sentence_groups as $group) {
                            if (!empty(trim($group))) {
                                $final_content .= $this->process_paragraph('<p>' . $group . '</p>', $use_generate_blocks);
                                if ($last_was_heading) {
                                    $paragraphs_after_heading++;
                                }
                            }
                        }
                    }
                } else {
                    $final_content .= $this->process_paragraph($element, $use_generate_blocks);
                    if ($last_was_heading) {
                        $paragraphs_after_heading++;
                    }
                }
                $last_was_heading = false;
            }
            // Process unordered lists.
            elseif (strpos($element, '<ul') === 0) {
                $final_content .= $this->process_list($element, $use_generate_blocks);
                if ($last_was_heading) {
                    $paragraphs_after_heading++;
                }
                $last_was_heading = false;
            }
            // For any other element, treat it as a paragraph.
            else {
                $final_content .= $this->process_paragraph('<p>' . strip_tags($element) . '</p>', $use_generate_blocks);
                if ($last_was_heading) {
                    $paragraphs_after_heading++;
                }
                $last_was_heading = false;
            }

            // After a heading with only one paragraph, add filler paragraphs if necessary.
            if ($last_was_heading && $paragraphs_after_heading == 1) {
                if ($i + 1 >= $total_elements || (strpos($elements[$i+1], '<p') !== 0)) {
                    $filler_paragraphs = array_slice($this->filler_paragraphs, 1, 2);
                    foreach ($filler_paragraphs as $filler) {
                        $final_content .= $this->process_paragraph("<p>{$filler}</p>", $use_generate_blocks);
                        $paragraphs_after_heading++;
                    }
                }
            }

            // Insert image block after every $img_interval elements.
            if ((($i + 1) % $img_interval == 0) && ($img_index < $total_images)) {
                try {
                    $final_content .= $this->generate_image_block($uploaded_images[$img_index], $transcript, $use_generate_blocks, false, '', $video_title);
                } catch (Exception $e) {
                    $img_url = wp_get_attachment_url($uploaded_images[$img_index]);
                    if ($img_url) {
                        if ($use_generate_blocks) {
                            $uniqueId = substr(uniqid(), 0, 8);
                            $final_content .= "<!-- wp:generateblocks/image {\"uniqueId\":\"" . $uniqueId . "\",\"sizeSlug\":\"full\",\"blockVersion\":2} -->" .
                            "<figure class=\"gb-block-image gb-block-image-" . $uniqueId . "\"><img class=\"gb-image gb-image-" . $uniqueId .
                            "\" src=\"" . esc_url($img_url) . "\" alt=\"Video image\"/></figure>" .
                            "<!-- /wp:generateblocks/image -->\n";
                        } else {
                            $final_content .= "<!-- wp:image {\"align\":\"center\"} -->\n" . 
                            "<figure class=\"wp-block-image aligncenter\"><img src=\"" . esc_url($img_url) . "\" alt=\"Video image\"/></figure>" .
                            "\n<!-- /wp:image -->\n";
                        }
                    }
                }
                $img_index++;
            }
        }

        // If no heading was found, prepend the video at the beginning.
        if (!$video_inserted) {
            $final_content = $this->generate_youtube_embed_block($videoId) . $final_content;
        }

        // Append any remaining images.
        while ($img_index < $total_images) {
            try {
                $final_content .= $this->generate_image_block($uploaded_images[$img_index], $transcript, $use_generate_blocks, false, '', $video_title);
            } catch (Exception $e) {
                $img_url = wp_get_attachment_url($uploaded_images[$img_index]);
                if ($img_url) {
                    if ($use_generate_blocks) {
                        $uniqueId = substr(uniqid(), 0, 8);
                        $final_content .= "<!-- wp:generateblocks/image {\"uniqueId\":\"" . $uniqueId . "\",\"sizeSlug\":\"full\",\"blockVersion\":2} -->" .
                            "<figure class=\"gb-block-image gb-block-image-" . $uniqueId . "\"><img class=\"gb-image gb-image-" . $uniqueId .
                            "\" src=\"" . esc_url($img_url) . "\" alt=\"Video image\"/></figure>" .
                            "<!-- /wp:generateblocks/image -->\n";
                    } else {
                        $final_content .= "<!-- wp:image {\"align\":\"center\"} -->\n" . 
                            "<figure class=\"wp-block-image aligncenter\"><img src=\"" . esc_url($img_url) . "\" alt=\"Video image\"/></figure>" .
                            "\n<!-- /wp:image -->\n";
                    }
                }
            }
            $img_index++;
        }

        return $final_content;
    }
    protected function get_video_details($videoId, $token) {
        $url = "https://www.googleapis.com/youtube/v3/videos?part=snippet,contentDetails,statistics&id={$videoId}";
        $response = cai_remote_get($url, array('headers' => array('Authorization' => 'Bearer ' . $token)));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (empty($data['items'][0])) {
            return false;
        }
        
        return $data['items'][0];
    }
    protected function set_featured_image($thumbnail_url, $title) {
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        
        $tmp = download_url($thumbnail_url);
        if (is_wp_error($tmp)) {
            return false;
        }
        
        $file_array = array();
        $file_array['name'] = sanitize_title($title) . '-thumbnail.jpg';
        $file_array['tmp_name'] = $tmp;
        
        $thumb_id = media_handle_sideload($file_array, 0);
        if (is_wp_error($thumb_id)) {
            @unlink($file_array['tmp_name']);
            return false;
        }
        
        update_post_meta($thumb_id, '_wp_attachment_image_alt', 'Thumbnail for ' . $title);
        return $thumb_id;
    }
    protected function create_wordpress_post($title, $content, $pubdate, $thumb_id) {
        // Convert YouTube's ISO 8601 date to WordPress format
        // YouTube returns dates in UTC format like "2024-08-15T14:30:00Z"
        $wordpress_date = date('Y-m-d H:i:s', strtotime($pubdate));
        
        $post_data = array(
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => 'draft',
            'post_date' => $wordpress_date,
            'post_date_gmt' => gmdate('Y-m-d H:i:s', strtotime($pubdate)), // Store UTC time as well
            'post_name' => $this->generate_slug($title)
        );
        
        $post_id = wp_insert_post($post_data);
        if (is_wp_error($post_id)) {
            return false;
        }
        
        set_post_thumbnail($post_id, $thumb_id);
        return $post_id;
    }
    protected function process_html_element($element, $use_generate_blocks = false) {
        if (strpos($element, '<h2') === 0 || strpos($element, '<h3') === 0) {
            return $this->process_heading($element, $use_generate_blocks);
        } else if (strpos($element, '<p') === 0) {
            return $this->process_paragraph($element, $use_generate_blocks);
        } else if (strpos($element, '<ul') === 0) {
            return $this->process_list($element, $use_generate_blocks);
        } else {
            // Unknown element type, treat as paragraph
            return $this->process_paragraph('<p>' . strip_tags($element) . '</p>', $use_generate_blocks);
        }
    }
    protected function get_youtube_transcript($videoId) {
        $token = $this->get_access_token();
        if (!$token) {
            return '';
        }
        
        // Step 1: Fetch available captions list
        $url = "https://www.googleapis.com/youtube/v3/captions?part=snippet&videoId=" . $videoId;
        $args = array(
            'headers' => array('Authorization' => 'Bearer ' . $token),
            'timeout' => 30
        );
        
        $response = wp_remote_get($url, $args);
        
        // Check for errors in the response
        if (is_wp_error($response)) {
            return '';
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        
        // Check for quota exceeded error
        if ($status_code == 403 && !empty($data['error']['errors'])) {
            foreach ($data['error']['errors'] as $error) {
                if (isset($error['reason']) && $error['reason'] === 'quotaExceeded') {
                    throw new Exception("YouTube API quota exceeded. Please try again tomorrow when your quota resets.");
                }
            }
        }
        
        if (empty($data['items'])) {
            return '';
        }
        
        // Step 2: Identify the caption ID to download
        $caption_id = null;
        
        // Prefer ASR (auto-generated) captions first
        foreach ($data['items'] as $item) {
            if (isset($item['snippet']['trackKind']) && $item['snippet']['trackKind'] == 'ASR') {
                $caption_id = $item['id'];
                break;
            }
        }
        
        // If no ASR captions, take the first available caption
        if (!$caption_id && !empty($data['items'])) {
            $caption_id = $data['items'][0]['id'];
        }
        
        if (!$caption_id) {
            return '';
        }
        
        // Step 3: Download the transcript
        $download_url = "https://www.googleapis.com/youtube/v3/captions/" . $caption_id . "?tfmt=srt&alt=media";
        
        $resp2 = wp_remote_get($download_url, $args);
        
        // Check for errors when downloading the caption
        if (is_wp_error($resp2)) {
            return '';
        }
        
        $status_code = wp_remote_retrieve_response_code($resp2);
        if ($status_code != 200) {
            return '';
        }
        
        // Step 4: Process the SRT format into plain text
        $srt = wp_remote_retrieve_body($resp2);
        
        // Parse the SRT format into plain text
        $lines = explode("\n", $srt);
        $text_lines = array();
        
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            if (preg_match('/^\d+$/', $line)) continue;  // Skip line numbers
            if (preg_match('/^\d{2}:\d{2}:\d{2},\d{3} -->/', $line)) continue; // Skip timestamps
            
            $text_lines[] = $line;
        }
        
        return implode(" ", $text_lines);
    }

// PARAGRAPHS / HEADINGS / MEDIA
    protected function generate_combined_content($title, $transcript, $categories) {
        $key = Creator_AI::get_credential('cai_openai_api_key');
        if (empty($key)) {
            return array();
        }
        
        // Check API settings
        if (!isset($this->api_settings) || !is_array($this->api_settings)) {
            return array();
        }
        
        if (!isset($this->api_settings['openai_model']) || !isset($this->api_settings['max_tokens'])) {
            return array();
        }
        
        // Get the system prompts
        $articlePrompt = get_option('yta_prompt_article_system', $this->default_prompts['yta_prompt_article_system']);
        $seoPrompt = get_option('yta_prompt_seo_system', $this->default_prompts['yta_prompt_seo_system']);
        
        // Add blacklist links to the prompt if any exist
        $blacklist_links = get_option('yta_blacklist_links', array());
        if (!empty($blacklist_links) && is_array($blacklist_links)) {
            $filtered_blacklist = array_filter($blacklist_links, function($link) {
                return !empty(trim($link));
            });
            
            if (!empty($filtered_blacklist)) {
                $blacklist_domains = array();
                foreach ($filtered_blacklist as $link) {
                    $domain = parse_url($link, PHP_URL_HOST);
                    if ($domain) {
                        $blacklist_domains[] = $domain;
                    }
                }
                
                if (!empty($blacklist_domains)) {
                    $blacklist_text = "\n\nIMPORTANT: Do NOT include links or references to the following domains:\n";
                    $blacklist_text .= "- " . implode("\n- ", array_unique($blacklist_domains));
                    $articlePrompt .= $blacklist_text;
                }
            }
        }
        
        // Get hierarchical category structure for the AI
        $category_data = $this->get_hierarchical_categories();
        $category_list = isset($category_data['formatted_list']) ? $category_data['formatted_list'] : array();

        error_log('Creator AI Debug: generate_combined_content - Category list has ' . count($category_list) . ' items');

        // Build a combined system prompt for all tasks
        $combined_system_prompt = "You are an expert content writer specializing in SEO. Complete the following tasks:\n\n";
        $combined_system_prompt .= "TASK 1: Write an article according to these instructions:\n{$articlePrompt}\n\n";
        $combined_system_prompt .= "TASK 2: Generate 3-5 SEO-friendly tags for the article (comma-separated).\n\n";
        $combined_system_prompt .= "TASK 3: Generate an SEO meta description (between 100-160 characters):\n{$seoPrompt}\n\n";

        // Add category selection instructions if we have categories
        if (!empty($category_list)) {
            $combined_system_prompt .= "TASK 4: Select the most appropriate category from this list:\n\n";
            $combined_system_prompt .= implode("\n", $category_list) . "\n\n";
            $combined_system_prompt .= "RULES: If selecting a sub-category (shown with '>'), use the exact format shown (e.g., 'Parent > Sub-category'). Otherwise, use just the category name.\n\n";
        }

        $combined_system_prompt .= "Format your response as a JSON object with these keys:\n";
        $combined_system_prompt .= "- article: The complete HTML article content\n";
        $combined_system_prompt .= "- tags: Comma-separated list of tags\n";
        $combined_system_prompt .= "- meta_description: The SEO description\n";
        if (!empty($category_list)) {
            $combined_system_prompt .= "- category: The selected category name\n";
        }

        error_log('Creator AI Debug: generate_combined_content - Prompt built, length: ' . strlen($combined_system_prompt));
        
        // Determine appropriate token limit based on model
        $model = $this->api_settings['openai_model'];
        $token_limit = $this->api_settings['max_tokens'];
        
        // For reasoning models (gpt-5 series), significantly increase token limit
        if (strpos(strtolower($model), 'gpt-5') !== false) {
            $token_limit = max(16000, $token_limit * 4); // At least 16k tokens for reasoning models
        }
        
        // Build request data
        $data = array(
            "model" => $model,
            "messages" => array(
                array(
                    "role" => "system", 
                    "content" => $combined_system_prompt 
                ),
                array(
                    "role" => "user", 
                    "content" => "Title: " . $title . "\n\nTranscript:\n" . $transcript
                )
            ),
            "temperature" => mt_rand(650, 750) / 1000,
            "max_tokens" => $token_limit,
            "user" => 'session_' . time() . '_' . mt_rand(1, 10000)
        );
        
        try {
            $result = Creator_AI::openai_request($data, $key);
            
            if (!$result) {
                return array();
            }
            
            if (!isset($result['choices']) || !is_array($result['choices']) || empty($result['choices'])) {
                return array();
            }
            
            if (!isset($result['choices'][0]['message']) || !isset($result['choices'][0]['message']['content'])) {
                return array();
            }
            
            $response = trim($result['choices'][0]['message']['content']);
            
            // Check for empty content (common with reasoning models hitting token limits)
            if (empty($response)) {
                return array();
            }
            
            // Save the raw AI response for debugging
            update_option('yta_last_raw_response', $response);
            
            // Extract JSON from the response
            preg_match('/```json\s*(.*?)\s*```/s', $response, $json_matches);
            if (!empty($json_matches[1])) {
                $json = trim($json_matches[1]);
            } else {
                // Try to find JSON without the code blocks
                preg_match('/\{.*\}/s', $response, $json_matches);
                if (!empty($json_matches[0])) {
                    $json = $json_matches[0];
                } else {
                    // Fallback to using the entire response
                    $json = $response;
                }
            }
            
            $data = json_decode($json, true);

            error_log('Creator AI Debug: generate_combined_content - JSON decode result: ' . (is_array($data) ? 'SUCCESS' : 'FAILED'));
            if (is_array($data)) {
                error_log('Creator AI Debug: generate_combined_content - Data keys: ' . implode(', ', array_keys($data)));
            }

            if (!is_array($data) || !isset($data['article'])) {
                error_log('Creator AI Debug: generate_combined_content - JSON parsing failed, using fallback');
                // If JSON parsing failed, try to extract just the article content and return it
                return array(
                    'article' => $response,
                    'tags' => '',
                    'meta_description' => '',
                    'category' => ''
                );
            }

            // Process the article content
            $article = trim($data['article']);

            error_log('Creator AI Debug: generate_combined_content - Article length: ' . strlen($article));
            error_log('Creator AI Debug: generate_combined_content - Tags: ' . (isset($data['tags']) ? $data['tags'] : 'NOT SET'));
            error_log('Creator AI Debug: generate_combined_content - Meta desc: ' . (isset($data['meta_description']) ? $data['meta_description'] : 'NOT SET'));
            error_log('Creator AI Debug: generate_combined_content - Category: ' . (isset($data['category']) ? $data['category'] : 'NOT SET'));

            // Replace smart quotes and special characters with standard equivalents
            $article = str_replace(
                array("\xE2\x80\x9C", "\xE2\x80\x9D", "\xE2\x80\x98", "\xE2\x80\x99", "\xE2\x80\x93", "\xE2\x80\x94"),
                array('"', '"', "'", "'", '-', '-'),
                $article
            );
            
            // Convert Markdown-style formatting to HTML (if needed)
            $article = preg_replace('/\*\*(.*?)\*\*/s', '<strong>$1</strong>', $article); // Bold
            $article = preg_replace('/\*(.*?)\*/s', '<em>$1</em>', $article); // Italic
            $article = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/s', '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>', $article); // Links
            $article = preg_replace('/^#### (.*?)$/m', '<h4>$1</h4>', $article); // h4
            $article = preg_replace('/^### (.*?)$/m', '<h3>$1</h3>', $article); // h3
            $article = preg_replace('/^## (.*?)$/m', '<h2>$1</h2>', $article); // h2
            
            // Replace external links with affiliate links where applicable
            if (method_exists($this, 'replace_with_affiliate_links')) {
                $article = $this->replace_with_affiliate_links($article);
            }
            
            // Create return data
            $return_data = array(
                'article' => $article,
                'tags' => isset($data['tags']) ? $data['tags'] : '',
                'meta_description' => isset($data['meta_description']) ? $data['meta_description'] : '',
                'category' => isset($data['category']) ? $data['category'] : ''
            );
            
            return $return_data;
        } 
        catch (Exception $e) {
            return array();
        }
    }
    protected function process_youtube_post_metadata($post_id, $title, $videoId, $content, $combined_data) {
        error_log('Creator AI Debug: process_youtube_post_metadata - Starting tag processing at ' . date('Y-m-d H:i:s'));
        // Set tags if available
        if (!empty($combined_data['tags'])) {
            $tags = explode(',', $combined_data['tags']);
            $clean_tags = array();
            foreach ($tags as $tag) {
                $clean_tags[] = str_replace(array('"', '"', '"'), '', trim($tag));
            }
            if (!empty($clean_tags)) {
                wp_set_post_tags($post_id, $clean_tags);
                error_log('Creator AI Debug: process_youtube_post_metadata - Tags set: ' . implode(', ', $clean_tags) . ' at ' . date('Y-m-d H:i:s'));
            }
        }
        error_log('Creator AI Debug: process_youtube_post_metadata - Tag processing completed at ' . date('Y-m-d H:i:s'));
        
        error_log('Creator AI Debug: process_youtube_post_metadata - Starting category processing at ' . date('Y-m-d H:i:s'));
        // Set category if available
        if (!empty($combined_data['category']) && !isset($_POST['skipCategory'])) {
            error_log('Creator AI Debug: process_youtube_post_metadata - Getting hierarchical categories at ' . date('Y-m-d H:i:s'));
            $category_data = $this->get_hierarchical_categories();
            $all_categories = $category_data['all_categories'];
            $hierarchy = $category_data['hierarchy'];

            if (!empty($all_categories)) {
                $cat_selection = trim($combined_data['category']);
                error_log('Creator AI Debug: process_youtube_post_metadata - AI suggested category: ' . $cat_selection . ' at ' . date('Y-m-d H:i:s'));

                // Store the AI's suggestion for debugging
                update_post_meta($post_id, '_category_ai_suggestion', $cat_selection);

                $category_ids = array();

                // Check if this is a hierarchical selection (contains ">")
                if (strpos($cat_selection, '>') !== false) {
                    // Parse hierarchical selection: "Parent > Sub-category"
                    $parts = array_map('trim', explode('>', $cat_selection));
                    $parent_name = isset($parts[0]) ? $parts[0] : '';
                    $child_name = isset($parts[1]) ? $parts[1] : '';

                    error_log('Creator AI Debug: Hierarchical selection - Parent: ' . $parent_name . ', Child: ' . $child_name);

                    // Find parent category
                    $parent_id = null;
                    foreach ($all_categories as $cat) {
                        if ($cat->parent == 0 && strcasecmp($cat->name, $parent_name) === 0) {
                            $parent_id = $cat->term_id;
                            break;
                        }
                    }

                    // Find child category
                    $child_id = null;
                    if ($parent_id) {
                        foreach ($all_categories as $cat) {
                            if ($cat->parent == $parent_id && strcasecmp($cat->name, $child_name) === 0) {
                                $child_id = $cat->term_id;
                                break;
                            }
                        }
                    }

                    // If both found, select both parent and child
                    if ($parent_id && $child_id) {
                        $category_ids = array($parent_id, $child_id);
                        error_log('Creator AI Debug: Found parent ID: ' . $parent_id . ' and child ID: ' . $child_id);
                    } else {
                        error_log('Creator AI Debug: Failed to find parent or child category');
                    }
                } else {
                    // Single category selection (no hierarchy indicator)
                    // This should only match categories without children
                    $matched = false;

                    // Try exact match first
                    foreach ($all_categories as $cat) {
                        if (strcasecmp($cat->name, $cat_selection) === 0) {
                            // Check if this category has children
                            $has_children = false;
                            if ($cat->parent == 0 && isset($hierarchy[$cat->term_id])) {
                                $has_children = !empty($hierarchy[$cat->term_id]['children']);
                            }

                            if (!$has_children) {
                                // This is a valid standalone category
                                $category_ids = array($cat->term_id);
                                $matched = true;
                                error_log('Creator AI Debug: Matched standalone category: ' . $cat->name . ' (ID: ' . $cat->term_id . ')');
                                break;
                            } else {
                                error_log('Creator AI Debug: Category "' . $cat->name . '" has children and cannot be selected alone');
                            }
                        }
                    }

                    // If no exact match, try partial matching
                    if (!$matched) {
                        foreach ($all_categories as $cat) {
                            if (stripos($cat->name, $cat_selection) !== false || stripos($cat_selection, $cat->name) !== false) {
                                // Check if this category has children
                                $has_children = false;
                                if ($cat->parent == 0 && isset($hierarchy[$cat->term_id])) {
                                    $has_children = !empty($hierarchy[$cat->term_id]['children']);
                                }

                                if (!$has_children) {
                                    $category_ids = array($cat->term_id);
                                    $matched = true;
                                    error_log('Creator AI Debug: Partial match found for standalone category: ' . $cat->name);
                                    break;
                                }
                            }
                        }
                    }
                }

                // Fallback: if no categories matched, use first available standalone category
                if (empty($category_ids)) {
                    // First try to find a standalone category (without children)
                    foreach ($hierarchy as $parent_id => $parent_data) {
                        if (empty($parent_data['children'])) {
                            // This parent has no children, can be used as fallback
                            $category_ids = array($parent_id);
                            update_post_meta($post_id, '_category_fallback', 'Used first available standalone: ' . $parent_data['cat']->name);
                            error_log('Creator AI Debug: Using fallback standalone category: ' . $parent_data['cat']->name);
                            break;
                        }
                    }

                    // If still no match and all categories have children, use first parent + first child
                    if (empty($category_ids)) {
                        foreach ($hierarchy as $parent_id => $parent_data) {
                            if (!empty($parent_data['children'])) {
                                $first_child = $parent_data['children'][0];
                                $category_ids = array($parent_id, $first_child->term_id);
                                update_post_meta($post_id, '_category_fallback', 'Used first parent+child: ' . $parent_data['cat']->name . ' > ' . $first_child->name);
                                error_log('Creator AI Debug: Using fallback parent+child: ' . $parent_data['cat']->name . ' > ' . $first_child->name);
                                break;
                            }
                        }
                    }
                }

                // Assign categories if IDs found
                if (!empty($category_ids)) {
                    error_log('Creator AI Debug: process_youtube_post_metadata - Setting category IDs: ' . implode(', ', $category_ids) . ' at ' . date('Y-m-d H:i:s'));
                    wp_set_post_categories($post_id, $category_ids);
                    error_log('Creator AI Debug: process_youtube_post_metadata - Categories set at ' . date('Y-m-d H:i:s'));
                } else {
                    error_log('Creator AI Debug: No valid categories found to assign');
                }
            }
        }
        error_log('Creator AI Debug: process_youtube_post_metadata - Category processing completed at ' . date('Y-m-d H:i:s'));
        
        error_log('Creator AI Debug: process_youtube_post_metadata - Starting SEO description processing at ' . date('Y-m-d H:i:s'));
        // Set SEO description if available
        if (!empty($combined_data['meta_description'])) {
            $desc = $combined_data['meta_description'];
            
            // Validate the length of the description
            if (strlen($desc) > 160) {
                $desc = substr($desc, 0, 160);
            }
            
            error_log('Creator AI Debug: process_youtube_post_metadata - Updating post excerpt at ' . date('Y-m-d H:i:s'));
            wp_update_post(array(
                'ID' => $post_id,
                'post_excerpt' => $desc
            ));
            error_log('Creator AI Debug: process_youtube_post_metadata - Post excerpt updated at ' . date('Y-m-d H:i:s'));
            
            // SEO plugins compatibility
            if (function_exists('is_plugin_active') && is_plugin_active('autodescription/autodescription.php')) {
                error_log('Creator AI Debug: process_youtube_post_metadata - Setting autodescription meta at ' . date('Y-m-d H:i:s'));
                update_post_meta($post_id, '_autodescription_description', $desc);
                error_log('Creator AI Debug: process_youtube_post_metadata - Autodescription meta set at ' . date('Y-m-d H:i:s'));
            }
        }
        error_log('Creator AI Debug: process_youtube_post_metadata - SEO description processing completed at ' . date('Y-m-d H:i:s'));
    }
    protected function generate_youtube_embed_block($videoId) {
        return '<!-- wp:embed {"url":"https://www.youtube.com/watch?v=' . $videoId . '","type":"video","providerNameSlug":"youtube","responsive":true,"className":"wp-embed-aspect-16-9 wp-has-aspect-ratio"} -->
        <figure class="wp-block-embed is-type-video is-provider-youtube wp-block-embed-youtube wp-embed-aspect-16-9 wp-has-aspect-ratio"><div class="wp-block-embed__wrapper">
        https://www.youtube.com/watch?v=' . $videoId . '
        </div></figure>
        <!-- /wp:embed -->' . "\n";
    }


// SEO AND METADATA


    protected function generate_image_seo_text($attachment_id, $transcript, $video_title = '') {
        // Start with fallback values in case of timeout
        $fallback = array(
            'alt_text' => 'Video content image',
            'caption' => 'Image from video content',
            'filename' => sanitize_title('video-image-' . $attachment_id)
        );

        $key = Creator_AI::get_credential('cai_openai_api_key');
        if (empty($key)) {
            return $fallback;
        }

        // Get the local file path instead of URL
        $image_path = get_attached_file($attachment_id);
        if (!$image_path || !file_exists($image_path)) {
            return $fallback;
        }

        try {
            // Read and encode image to base64 (same method as course creator)
            $image_data = file_get_contents($image_path);
            if ($image_data === false) {
                return $fallback;
            }
            
            $base64_image = base64_encode($image_data);
            
            // Detect image type for proper data URL
            $image_info = getimagesize($image_path);
            $mime_type = $image_info['mime'] ?? 'image/jpeg';
            
            // Use OpenAI Vision API with base64 encoded image
            $data = array(
                "model" => "gpt-4o",
                "messages" => array(
                    array(
                        "role" => "user",
                        "content" => array(
                            array(
                                "type" => "text", 
                                "text" => "You are analyzing an image for an article" . (!empty($video_title) ? " titled: \"" . $video_title . "\"" : "") . ".\n\nArticle context (transcript excerpt):\n" . substr($transcript, 0, 2000) . "\n\nAnalyze the image and describe SPECIFICALLY what you see in it. Create SEO-friendly metadata:\n\n1. 'alt_text': 3-5 words describing the SPECIFIC visual elements in the image (not generic)\n2. 'caption': One sentence describing EXACTLY what is visible in the image and how it relates to the article topic. Be concrete and specific about colors, objects, people, actions, text, or interface elements you can see.\n3. 'filename': SEO-friendly filename based on the specific content\n\nReturn JSON format only. Avoid generic phrases like 'video content image' or 'related to topic' - instead describe the actual visible elements."
                            ),
                            array(
                                "type" => "image_url",
                                "image_url" => array(
                                    "url" => "data:" . $mime_type . ";base64," . $base64_image,
                                    "detail" => "low"
                                )
                            )
                        )
                    )
                ),
                "temperature" => 0.3,
                "max_tokens" => 200,
                "user" => 'session_' . time() . '_' . mt_rand(1, 10000)
            );

            $result = $this->openai_request($data, $key, 15); // 15 second timeout
            
            if (!$result || empty($result['choices'][0]['message']['content'])) {
                return $fallback;
            }
            
            $response = trim($result['choices'][0]['message']['content']);
            
            // Extract JSON from the response
            preg_match('/```json\s*(.*?)\s*```/s', $response, $json_matches);
            if (!empty($json_matches[1])) {
                $json = trim($json_matches[1]);
            } else {
                // Try to find JSON without code blocks
                preg_match('/\{.*\}/s', $response, $json_matches);
                if (!empty($json_matches[0])) {
                    $json = $json_matches[0];
                } else {
                    return $fallback;
                }
            }
            
            $decoded = json_decode($json, true);
            
            if (
                is_array($decoded) &&
                isset($decoded['alt_text'], $decoded['caption'], $decoded['filename']) &&
                !empty(trim($decoded['alt_text'])) &&
                !empty(trim($decoded['caption'])) &&
                !empty(trim($decoded['filename']))
            ) {
                // Sanitize the filename
                $decoded['filename'] = sanitize_title($decoded['filename']);
                return $decoded;
            }
            
            return $fallback;
            
        } catch (Exception $e) {
            return $fallback;
        }
    }

}