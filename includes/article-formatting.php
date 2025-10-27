<?php
trait Creator_AI_Article_Formatting {

    public function sanitize_keywords_array($input) {
        if (empty($input)) {
            return array();
        }
        
        // If input is already an array, sanitize each element
        if (is_array($input)) {
            return array_map('sanitize_text_field', $input);
        }
        
        // If input is a comma-separated string, explode and sanitize
        $keywords = explode(',', $input);
        $sanitized = array();
        
        foreach ($keywords as $keyword) {
            $keyword = trim($keyword);
            if (!empty($keyword)) {
                $sanitized[] = sanitize_text_field($keyword);
            }
        }
        
        return $sanitized;
    }
    public function sanitize_news_links($input) {
        $sanitized = array();
        
        if (is_array($input)) {
            foreach ($input as $url) {
                $clean_url = esc_url_raw(trim($url));
                if (!empty($clean_url)) {
                    $sanitized[] = $clean_url;
                }
            }
        }
        
        return $sanitized;
    }    


    protected function get_filtered_categories() {
	    $cats = get_categories(array('hide_empty' => false));
	    $filtered_cats = array();
	    foreach ($cats as $c) {
	        if ($c->slug !== 'uncategorized') {
	            $filtered_cats[] = $c;
	        }
	    }
	    return $filtered_cats;
	}

	/**
	 * Get hierarchical category structure for AI processing
	 * Returns array with 'formatted_list' for AI prompt and 'all_categories' for matching
	 */
	protected function get_hierarchical_categories() {
	    try {
	        $all_cats = $this->get_filtered_categories();

	        if (empty($all_cats)) {
	            error_log('Creator AI Debug: get_hierarchical_categories - No categories found');
	            return array(
	                'formatted_list' => array(),
	                'all_categories' => array(),
	                'hierarchy' => array()
	            );
	        }

	        // Separate parent and child categories
	        $parents = array();
	        $children = array();

	        foreach ($all_cats as $cat) {
	            if (!isset($cat->parent) || !isset($cat->term_id) || !isset($cat->name)) {
	                continue; // Skip malformed category objects
	            }

	            if ($cat->parent == 0) {
	                // This is a parent category
	                $parents[$cat->term_id] = array(
	                    'cat' => $cat,
	                    'children' => array()
	                );
	            } else {
	                // This is a child category
	                if (!isset($children[$cat->parent])) {
	                    $children[$cat->parent] = array();
	                }
	                $children[$cat->parent][] = $cat;
	            }
	        }

	        // Attach children to their parents
	        foreach ($children as $parent_id => $child_list) {
	            if (isset($parents[$parent_id])) {
	                $parents[$parent_id]['children'] = $child_list;
	            }
	        }

	        // Build formatted list for AI
	        $formatted_list = array();
	        foreach ($parents as $parent_data) {
	            $parent = $parent_data['cat'];
	            $has_children = !empty($parent_data['children']);

	            if ($has_children) {
	                // Parent with children - show hierarchy
	                $formatted_list[] = $parent->name . " (parent category - must be selected with a sub-category):";
	                foreach ($parent_data['children'] as $child) {
	                    $formatted_list[] = "  └─ " . $parent->name . " > " . $child->name;
	                }
	            } else {
	                // Parent without children - can be selected alone
	                $formatted_list[] = $parent->name;
	            }
	        }

	        error_log('Creator AI Debug: get_hierarchical_categories - Built ' . count($formatted_list) . ' formatted category entries');

	        return array(
	            'formatted_list' => $formatted_list,
	            'all_categories' => $all_cats,
	            'hierarchy' => $parents
	        );
	    } catch (Exception $e) {
	        error_log('Creator AI Debug: get_hierarchical_categories - Error: ' . $e->getMessage());
	        return array(
	            'formatted_list' => array(),
	            'all_categories' => array(),
	            'hierarchy' => array()
	        );
	    }
	}
	protected function generate_image_block($attachment_id, $transcript, $use_generate_blocks = false, $skip_vision = false, $custom_caption = '') {
	    // If we want to skip vision processing, use the provided caption and generate minimal metadata
	    if ($skip_vision) {
	        $alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
	        if (empty($alt)) {
	            $alt = wp_get_attachment_caption($attachment_id) ?: 'Software update image';
	        }
	        $caption = !empty($custom_caption) ? $custom_caption : wp_get_attachment_caption($attachment_id);
	        $filename = sanitize_title($alt);
	    } else {
	        // Generate SEO metadata for the image using vision (original behavior)
	        try {
	            $seo = $this->generate_image_seo_text($attachment_id, $transcript);
	            $alt = isset($seo['alt_text']) ? $seo['alt_text'] : 'Video image';
	            $caption = isset($seo['caption']) ? $seo['caption'] : '';
	            $filename = isset($seo['filename']) ? $seo['filename'] : sanitize_title($alt);
	        } catch (Exception $e) {
	            // Log the error but continue with fallback
	            $alt = 'Video content image';
	            $caption = 'Image from video content';
	            $filename = sanitize_title('video-image-' . $attachment_id);
	        }
	    }
	    
	    // Update image metadata
	    update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt);
	    wp_update_post(array(
	        'ID' => $attachment_id,
	        'post_excerpt' => $caption,
	        'post_title' => $filename
	    ));
	    
	    $img_url = wp_get_attachment_url($attachment_id);
	    
	    if ($use_generate_blocks) {
	        // GenerateBlocks format
	        $uniqueImageId = 'img_' . $attachment_id . '_' . substr(md5(uniqid(mt_rand(), true)), 0, 8);
	        $uniqueCaptionId = 'cap_' . $attachment_id . '_' . substr(md5(uniqid(mt_rand(), true)), 0, 8);
	                
	        return '<!-- wp:generateblocks/image {"uniqueId":"' . $uniqueImageId . '","sizeSlug":"full","blockVersion":2} -->
	        <figure class="gb-block-image gb-block-image-' . $uniqueImageId . '"><img class="gb-image gb-image-' . $uniqueImageId . '" src="' . esc_url($img_url) . '" alt="' . esc_attr($alt) . '" title="' . esc_attr($alt) . '"/><!-- wp:generateblocks/headline {"uniqueId":"' . $uniqueCaptionId . '","element":"figcaption","blockVersion":3,"isCaption":true} -->
	        <figcaption class="gb-headline gb-headline-' . $uniqueCaptionId . ' gb-headline-text">' . esc_html($caption) . '</figcaption>
	        <!-- /wp:generateblocks/headline --></figure>
	        <!-- /wp:generateblocks/image -->' . "\n";
	    } else {
	        // Standard WordPress format
	        return '<!-- wp:image {"id":' . intval($attachment_id) . ',"sizeSlug":"large","align":"center", "className":"wp-image-' . intval($attachment_id) . '"} -->
	        <figure class="wp-block-image aligncenter size-large wp-image-' . intval($attachment_id) . '"><img src="' . esc_url($img_url) . '" alt="' . esc_attr($alt) . '" class="wp-image-' . intval($attachment_id) . '" title="' . esc_attr($alt) . '"/><figcaption class="wp-element-caption">' . esc_html($caption) . '</figcaption></figure>
	        <!-- /wp:image -->' . "\n";
	    }
	}
	
	protected function fix_html_markup($html) {
	    // Use a more robust approach for HTML parsing
	    libxml_use_internal_errors(true);
	    $doc = new DOMDocument();

	    // Preserve GenerateBlocks comments by replacing them temporarily
	    $pattern = '/<!-- wp:generateblocks\/([^>]+) -->/';
	    $html = preg_replace_callback($pattern, function($matches) {
	        return '<!-- GENERATEBLOCK_START:' . base64_encode($matches[0]) . ' -->';
	    }, $html);

	    $pattern = '/<!-- \/wp:generateblocks\/([^>]+) -->/';
	    $html = preg_replace_callback($pattern, function($matches) {
	        return '<!-- GENERATEBLOCK_END:' . base64_encode($matches[0]) . ' -->';
	    }, $html);

	    // Add a wrapper to make parsing more reliable
	    $wrapped_html = '<!DOCTYPE html><html><body>' . $html . '</body></html>';

	    // Load HTML with options to prevent tag structure changes
	    $doc->loadHTML($wrapped_html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

	    // Get just the body content
	    $body = $doc->getElementsByTagName('body')->item(0);
	    $fixed = '';
	    foreach ($body->childNodes as $node) {
	        $fixed .= $doc->saveHTML($node);
	    }

	    // Clean up libxml
	    libxml_clear_errors();
	    libxml_use_internal_errors(false);

	    // Restore GenerateBlocks comments
	    $pattern = '/<!-- GENERATEBLOCK_START:([^>]+) -->/';
	    $fixed = preg_replace_callback($pattern, function($matches) {
	        return base64_decode($matches[1]);
	    }, $fixed);

	    $pattern = '/<!-- GENERATEBLOCK_END:([^>]+) -->/';
	    $fixed = preg_replace_callback($pattern, function($matches) {
	        return base64_decode($matches[1]);
	    }, $fixed);

	    // Fix broken GenerateBlocks closing tags (common issue)
	    $fixed = preg_replace('/<\/p>\s*<\/a>/i', '</a></p>', $fixed);
	           
	    return $fixed;
	}
	protected function process_heading($element, $use_generate_blocks = false) {
	    // Extract heading level and text
	    preg_match('/<h([2-3])[^>]*>(.*?)<\/h\1>/is', $element, $matches);
	    if (empty($matches)) {
	        return '';
	    }
	    
	    $level = intval($matches[1]);
	    $heading_text = trim(strip_tags($matches[2]));
	    
	    if ($use_generate_blocks) {
	        // Generate a consistent uniqueId based on content
	        $uniqueId = 'heading_' . $level . '_' . substr(md5($heading_text . uniqid(mt_rand(), true)), 0, 8);
	     
	        return "<!-- wp:generateblocks/headline {\"uniqueId\":\"{$uniqueId}\",\"element\":\"h{$level}\",\"blockVersion\":3} -->\n" .
	               "<h{$level} class=\"gb-headline gb-headline-{$uniqueId} gb-headline-text\">{$heading_text}</h{$level}>\n" .
	               "<!-- /wp:generateblocks/headline -->\n";
	    } else {
	        // Standard WordPress format
	        return "<!-- wp:heading {\"level\": $level} -->\n" .
	               "<h{$level}>{$heading_text}</h{$level}>\n" .
	               "<!-- /wp:heading -->\n";
	    }
	}
	protected function process_paragraph($element, $use_generate_blocks = false) {

	    // Extract paragraph content WITHOUT stripping HTML tags
	    preg_match('/<p[^>]*>(.*?)<\/p>/is', $element, $matches);
	    $para_content = isset($matches[1]) ? $matches[1] : '';
	    
	    // If no match found, just clean the element
	    if (empty($para_content)) {
	        $para_content = strip_tags($element);
	    }
	    
	    // Skip if empty
	    if (empty(trim($para_content))) {
	        return '';
	    }
	    
	    // Check if para_content has proper ending tags
	    if (substr_count($para_content, '<a') > substr_count($para_content, '</a>')) {
	        $para_content = preg_replace('/<a([^>]+)>([^<]*)/i', '<a$1>$2</a>', $para_content);
	    }
	    
	    // Check if this is a long paragraph that should be split
	    if (strlen($para_content) > 300) { // About 50 words
	        return $this->split_paragraph_into_blocks($para_content, $use_generate_blocks);
	    }
	    
	    // Process as a single paragraph
	    if ($use_generate_blocks) {
	        // Generate a truly unique ID for this paragraph
	        $uniqueId = 'para_' . substr(md5($para_content . uniqid(mt_rand(), true)), 0, 8);
	      
	        // Important: Make sure the opening and closing tags are properly balanced
	        return "<!-- wp:generateblocks/text {\"uniqueId\":\"{$uniqueId}\",\"tagName\":\"p\"} -->\n" . 
	               "<p class=\"gb-text\">" . $para_content . "</p>\n" . 
	               "<!-- /wp:generateblocks/text -->\n";
	    } else {
	        return "<!-- wp:paragraph -->\n" .
	               "<p>" . $para_content . "</p>\n" .
	               "<!-- /wp:paragraph -->\n";
	    }
	}
	protected function split_paragraph_into_blocks($content, $use_generate_blocks = false) {
	    // Split the content into sentences
	    $sentences = preg_split('/(\.|\!|\?)(\s)/', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
	    
	    // Recombine delimiters with their sentences
	    $complete_sentences = [];
	    for ($i = 0; $i < count($sentences); $i += 3) {
	        if (isset($sentences[$i+1]) && isset($sentences[$i+2])) {
	            $complete_sentences[] = $sentences[$i] . $sentences[$i+1] . $sentences[$i+2];
	        } elseif (isset($sentences[$i])) {
	            $complete_sentences[] = $sentences[$i];
	        }
	    }
	    
	    // Group sentences into paragraphs of 2-3 sentences each
	    $paragraph_chunks = array_chunk($complete_sentences, mt_rand(2, 3));
	    
	    // Build blocks for each chunk
	    $blocks = '';
	    foreach ($paragraph_chunks as $chunk) {
	        $chunk_content = implode('', $chunk);
	        
	        // Skip empty chunks
	        if (empty(trim($chunk_content))) {
	            continue;
	        }
	        
	        if ($use_generate_blocks) {
	            $uniqueId = substr(md5($chunk_content), 0, 8) . substr(uniqid(), 0, 8);
	            $blocks .= "<!-- wp:generateblocks/text {\"uniqueId\":\"{$uniqueId}\",\"tagName\":\"p\"} -->\n" . 
	                      "<p class=\"gb-text\">" . $chunk_content . "</p>\n" . 
	                      "<!-- /wp:generateblocks/text -->\n";
	        } else {
	            $blocks .= "<!-- wp:paragraph -->\n" .
	                      "<p>" . $chunk_content . "</p>\n" .
	                      "<!-- /wp:paragraph -->\n";
	        }
	    }
	    
	    return $blocks;
	}
	protected function improve_paragraph_structure($article_text) {
	    // Use a single function for comprehensive paragraph structure improvement
	    return $this->ensure_multiple_paragraphs_after_headings($this->break_long_paragraphs($article_text));
	}

	protected function ensure_multiple_paragraphs_after_headings($article_text) {
        // Use DOMDocument for reliable HTML parsing
        $dom = $this->create_dom_document($article_text);
        $xpath = new DOMXPath($dom);
        $headings = $xpath->query('//h2|//h3');
        
        // Track if we modified anything
        $modified = false;
        
        foreach ($headings as $heading) {
            // Check what follows the heading
            $nextParagraphs = array();
            $node = $heading->nextSibling;
            
            // Skip text nodes until we find an element
            while ($node && $node->nodeType == XML_TEXT_NODE) {
                $node = $node->nextSibling;
            }
            
            // Count paragraphs after this heading
            $paragraphCount = 0;
            $lastParagraph = null;
            
            while ($node && $paragraphCount < 3) {
                if ($node->nodeName == 'p') {
                    $paragraphCount++;
                    $lastParagraph = $node;
                    $nextParagraphs[] = $node;
                } else if ($node->nodeName == 'h2' || $node->nodeName == 'h3') {
                    // Stop if we hit another heading
                    break;
                }
                $node = $node->nextSibling;
            }
            
            // If we have fewer than 2 paragraphs, add filler paragraphs
            if ($paragraphCount < 2 && $lastParagraph) {
                $modified = true;
                $parent = $lastParagraph->parentNode;
                
                // Create filler paragraphs from our centralized array
                $filler_index = 0;
                
                // Add as many fillers as needed to reach 2 paragraphs total
                for ($i = $paragraphCount; $i < 2; $i++) {
                    $fillerText = $this->filler_paragraphs[$filler_index++];
                    $filler = $dom->createElement('p', $fillerText);
                    if ($lastParagraph->nextSibling) {
                        $parent->insertBefore($filler, $lastParagraph->nextSibling);
                    } else {
                        $parent->appendChild($filler);
                    }
                    $lastParagraph = $filler;
                }
            }
            // If there are no paragraphs after the heading, add two
            else if ($paragraphCount == 0) {
                $modified = true;
                $parent = $heading->parentNode;
                
                // Create filler paragraphs from our centralized array
                $fillers = array_slice($this->filler_paragraphs, 2, 2);
                $lastNode = $heading;
                
                // Add two fillers after the heading
                foreach ($fillers as $fillerText) {
                    $filler = $dom->createElement('p', $fillerText);
                    if ($lastNode->nextSibling) {
                        $parent->insertBefore($filler, $lastNode->nextSibling);
                    } else {
                        $parent->appendChild($filler);
                    }
                    $lastNode = $filler;
                }
            }
        }
        
        if ($modified) {
            // Get the modified HTML
            return $this->clean_dom_output($dom);
        }
        
        return $article_text;
    }
    protected function break_long_paragraphs($article_text) {
        // Use DOMDocument for reliable HTML parsing
        $dom = $this->create_dom_document($article_text);
        
        $modified = false;
        $xpath = new DOMXPath($dom);
        $paragraphs = $xpath->query('//p');
        
        // Process each paragraph
        foreach ($paragraphs as $paragraph) {
            $content = $paragraph->textContent;
            
            // If paragraph has more than 600 characters (approximately 100 words)
            if (strlen($content) > 600) {
                $sentences = preg_split('/(?<=[.!?])\s+/', $content, -1, PREG_SPLIT_NO_EMPTY);
                
                // If we have 4+ sentences, we can break it up
                if (count($sentences) >= 4) {
                    $modified = true;
                    
                    // Create approximately equal chunks of sentences
                    $chunks = array_chunk($sentences, ceil(count($sentences) / 2));
                    
                    // Create new paragraph elements
                    $new_paragraphs = array();
                    foreach ($chunks as $chunk) {
                        $new_p = $dom->createElement('p');
                        $new_p->textContent = implode(' ', $chunk);
                        $new_paragraphs[] = $new_p;
                    }
                    
                    // Replace original paragraph with new paragraphs
                    $parent = $paragraph->parentNode;
                    $reference = $paragraph->nextSibling;
                    $parent->removeChild($paragraph);
                    
                    foreach ($new_paragraphs as $new_p) {
                        if ($reference) {
                            $parent->insertBefore($new_p, $reference);
                        } else {
                            $parent->appendChild($new_p);
                        }
                    }
                }
            }
        }
        
        if ($modified) {
            return $this->clean_dom_output($dom);
        }
        
        return $article_text;
    }
	protected function process_list($element, $use_generate_blocks = false) {
	    // Clean the list content
	    $list_html = trim($element);
	    
	    if ($use_generate_blocks) {
	        // Outer unique ID for the list element
	        $outerId = substr(uniqid(), 0, 8);
	        
	        // Use regex to extract all <li> contents
	        preg_match_all('/<li[^>]*>(.*?)<\/li>/is', $list_html, $matches);
	        $li_items = $matches[1];
	        $list_output = "";
	        
	        foreach ($li_items as $index => $li_text) {
	            $li_text = trim($li_text);  // Don't strip tags here
	            $innerId = 'list_item_' . $outerId . '_' . $index . '_' . substr(md5(uniqid(mt_rand(), true)), 0, 8);
	            $list_output .= "<!-- wp:generateblocks/text {\"uniqueId\":\"{$innerId}\",\"tagName\":\"li\"} -->\n";
	            $list_output .= "<li class=\"gb-text\">" . $li_text . "</li>\n";  // Preserve HTML inside list items
	            $list_output .= "<!-- /wp:generateblocks/text -->\n";
	        }
	        
	        $final = "<!-- wp:generateblocks/element {\"uniqueId\":\"{$outerId}\",\"tagName\":\"ul\"} -->\n";
	        $final .= "<ul>\n" . $list_output . "</ul>\n";
	        $final .= "<!-- /wp:generateblocks/element -->\n";
	        return $final;
	    } else {
	        // Standard WordPress list block
	        return "<!-- wp:list -->\n" . $list_html . "\n<!-- /wp:list -->\n";
	    }
	}

	// LINKS
    protected function add_internal_links($article_text) {
        // Get user-defined keywords
        $user_keywords = get_option('yta_internal_keywords', array());
        
        // If no keywords defined, return the original text
        if (empty($user_keywords) || !is_array($user_keywords) || count($user_keywords) == 0) {
            return $article_text;
        }
        
        // Filter out empty keywords
        $user_keywords = array_filter($user_keywords, function($keyword) {
            return !empty(trim($keyword));
        });
        
        if (empty($user_keywords)) {
            return $article_text;
        }
        
        // Get posts for internal linking
        $posts = get_posts(array(
            'numberposts' => 15, 
            'post_type' => 'post',
            'post_status' => 'publish',
            'orderby' => 'rand'
        ));
        
        if (empty($posts)) {
            return $article_text;
        }
        
        // Use DOMDocument for parsing
        $dom = $this->create_dom_document($article_text);
        $xpath = new DOMXPath($dom);
        $paragraphs = $xpath->query('//p');
        
        // Sort keywords by length (longest first) to prioritize specific phrases
        usort($user_keywords, function($a, $b) {
            return strlen($b) - strlen($a);
        });
        
        // Track which keywords we've linked
        $linked_keywords = array();
        $added_links = 0;
        $max_links = 2;
        
        // Process each paragraph
        foreach ($paragraphs as $paragraph) {
            // Skip if we've added enough links
            if ($added_links >= $max_links) {
                break;
            }
            
            // Skip paragraphs that already have links
            $existing_links = $xpath->query('.//a', $paragraph);
            if ($existing_links->length > 0) {
                continue;
            }
            
            $paragraph_html = $dom->saveHTML($paragraph);
            $paragraph_text = $paragraph->textContent;
            
            // Try each keyword
            foreach ($user_keywords as $keyword) {
                // Skip if already linked
                if (in_array($keyword, $linked_keywords)) {
                    continue;
                }
                
                // Check for an exact case-insensitive match with word boundaries
                if (preg_match('/\b(' . preg_quote($keyword, '/') . ')\b/i', $paragraph_text)) {
                    // Find a relevant post by title match
                    $matching_post = null;
                    foreach ($posts as $post) {
                        // Choose the first post we haven't linked to yet
                        if (!in_array($post->ID, $linked_keywords)) {
                            $matching_post = $post;
                            break;
                        }
                    }
                    
                    if (!$matching_post) {
                        continue; // No appropriate post found
                    }
                    
                    // Create replacement with the link including a title attribute
                    $pattern = '/\b(' . preg_quote($keyword, '/') . ')\b/i';
                    $replacement = '<a href="' . esc_url(get_permalink($matching_post->ID)) . '" title="' . esc_attr($matching_post->post_title) . '">$1</a>';
                    $modified_html = preg_replace($pattern, $replacement, $paragraph_html, 1);
                    
                    // Replace the paragraph with the new version
                    $fragment = $dom->createDocumentFragment();
                    $fragment->appendXML($modified_html);
                    $paragraph->parentNode->replaceChild($fragment, $paragraph);
                    
                    // Track what we've done
                    $linked_keywords[] = $keyword;
                    $linked_keywords[] = $matching_post->ID; // Track used posts
                    $added_links++;
                    break;
                }
            }
        }
        
        // Get the modified HTML
        return $this->clean_dom_output($dom);
    }
    protected function replace_with_affiliate_links($article) {
        $affiliate_links = get_option('yta_affiliate_links', array());
        if (empty($affiliate_links)) {
            return $article;
        }
        
        // Prepare affiliate domains for faster lookup
        $affiliate_domains = [];
        foreach ($affiliate_links as $affiliate_url) {
            $domain = parse_url($affiliate_url, PHP_URL_HOST);
            if (!empty($domain)) {
                // Remove 'www.' if present for consistent matching
                $domain = preg_replace('/^www\./i', '', $domain);
                $affiliate_domains[$domain] = $affiliate_url;
            }
        }
        
        if (empty($affiliate_domains)) {
            return $article;
        }
        
        // Use DOMDocument for more reliable HTML parsing
        $dom = $this->create_dom_document($article, false);
        $links = $dom->getElementsByTagName('a');
        $modified = false;
        
        // Iterate through all links
        foreach ($links as $link) {
            if ($link->hasAttribute('href')) {
                $url = $link->getAttribute('href');
                
                // Only process external links
                if (strpos($url, 'http') === 0) {
                    $domain = parse_url($url, PHP_URL_HOST);
                    if (!empty($domain)) {
                        // Remove 'www.' for consistent matching
                        $domain = preg_replace('/^www\./i', '', $domain);
                        
                        // Check if this domain matches any affiliate domain
                        if (isset($affiliate_domains[$domain])) {
                            $link->setAttribute('href', $affiliate_domains[$domain]);
                            $link->setAttribute('target', '_blank');
                            $link->setAttribute('rel', 'noopener noreferrer');
                            $modified = true;
                        }
                    }
                }
            }
        }
        
        if ($modified) {
            // Get the modified HTML
            return $this->clean_dom_output($dom);
        }
        
        return $article;
    }
    protected function remove_blacklisted_links($article_text) {
        $blacklist_links = get_option('yta_blacklist_links', array());
        if (empty($blacklist_links) || !is_array($blacklist_links)) {
            return $article_text;
        }
        
        // Filter out empty entries
        $blacklist_links = array_filter($blacklist_links, function($link) {
            return !empty(trim($link));
        });
        
        if (empty($blacklist_links)) {
            return $article_text;
        }
        
        // Extract domains from blacklist links
        $blacklisted_domains = array();
        foreach ($blacklist_links as $link) {
            $domain = parse_url(trim($link), PHP_URL_HOST);
            if (empty($domain)) {
                // If parse_url couldn't extract a host, maybe the user entered just a domain
                $domain = trim($link);
            }
            // Remove www. prefix for consistent matching
            $domain = preg_replace('/^www\./i', '', $domain);
            if (!empty($domain)) {
                $blacklisted_domains[] = preg_quote($domain, '/');
            }
        }
        
        if (empty($blacklisted_domains)) {
            return $article_text;
        }
        
        // Use DOMDocument for more reliable HTML parsing
        $dom = $this->create_dom_document($article_text, false);
        $links = $dom->getElementsByTagName('a');
        $modified = false;
        $remove_list = array();
        
        // First pass - identify links to remove
        foreach ($links as $link) {
            if ($link->hasAttribute('href')) {
                $url = $link->getAttribute('href');
                
                // Only process external links
                if (strpos($url, 'http') === 0) {
                    $domain = parse_url($url, PHP_URL_HOST);
                    if (!empty($domain)) {
                        // Remove www. for consistent matching
                        $domain = preg_replace('/^www\./i', '', $domain);
                        
                        // Check against blacklisted domains
                        foreach ($blacklisted_domains as $blacklisted) {
                            if (preg_match('/' . $blacklisted . '/i', $domain)) {
                                $remove_list[] = $link;
                                $modified = true;
                                break;
                            }
                        }
                    }
                }
            }
        }
        
        // Second pass - remove blacklisted links
        foreach ($remove_list as $link) {
            // Replace the link with its text content
            $text_node = $dom->createTextNode($link->textContent);
            $link->parentNode->replaceChild($text_node, $link);
        }
        
        if ($modified) {
            return $this->clean_dom_output($dom);
        }
        
        return $article_text;
    }
    protected function add_external_links($article_text) {

        // Wrap the article in a temporary container.
        $wrapper = '<div id="article_wrapper">' . $article_text . '</div>';
        libxml_use_internal_errors(true);
        $doc = new DOMDocument('1.0', 'UTF-8');

        // Encode HTML entities to preserve UTF-8 characters through DOMDocument processing
        $wrapper = mb_convert_encoding($wrapper, 'HTML-ENTITIES', 'UTF-8');

        // Use proper encoding to avoid character issues.
        $doc->loadHTML('<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' . $wrapper . '</body></html>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($doc);
        
        // STEP 1: Process all external <a> tags.
        // Find all <a> elements with href starting with "http" within our wrapper.
        $linkNodes = $xpath->query('//*[@id="article_wrapper"]//a[starts-with(@href, "http")]');
        $uniqueDomains = array(); // key: normalized domain, value: first occurrence node
        
        
        foreach ($linkNodes as $link) {
            $href = $link->getAttribute('href');
            $domain = parse_url($href, PHP_URL_HOST);
            if ($domain) {
                // Normalize by removing any leading "www."
                $normDomain = preg_replace('/^www\./i', '', $domain);
                
                if (!isset($uniqueDomains[$normDomain])) {
                    // First occurrence: keep this link.
                    $uniqueDomains[$normDomain] = $link;
                } else {
                    // Duplicate: replace this <a> tag with its plain text.
                    $text = $link->textContent;
                    $textNode = $doc->createTextNode($text);
                    $link->parentNode->replaceChild($textNode, $link);
                }
            }
        }
        
        // STEP 2: If there are more than 3 unique external links, remove extras.
        $domainsInOrder = array_keys($uniqueDomains);
        
        if (count($domainsInOrder) > 3) {
            // Keep only the first 3 domains (by document order).
            $domainsToKeep = array_slice($domainsInOrder, 0, 3);
            foreach ($uniqueDomains as $normDomain => $node) {
                if (!in_array($normDomain, $domainsToKeep)) {
                    // Replace the link with its text.
                    $text = $node->textContent;
                    $textNode = $doc->createTextNode($text);
                    $node->parentNode->replaceChild($textNode, $node);
                    // Remove from our list.
                    unset($uniqueDomains[$normDomain]);
                }
            }
        }
        
        // STEP 3: If fewer than 3 unique external links, add affiliate links.
        $currentCount = count($uniqueDomains);
        
        if ($currentCount < 3) {
            // Get the plain text of the article for matching (strip HTML tags)
            $wrapperNode = $doc->getElementById('article_wrapper');
            $plainText = strip_tags($doc->saveHTML($wrapperNode));
            
            // Retrieve affiliate links from the CORRECT option name
            $affiliate_links = get_option('yta_affiliate_links', array());
                        
            // Find paragraphs to potentially insert links into
            $paragraphs = $xpath->query('//*[@id="article_wrapper"]/p');
            $paragraphsArray = array();
            foreach ($paragraphs as $p) {
                // Skip paragraphs that already have links
                if ($xpath->query('.//a', $p)->length == 0) {
                    $paragraphsArray[] = $p;
                }
            }
            
            // Only proceed if we have paragraphs to insert into
            if (!empty($paragraphsArray) && !empty($affiliate_links)) {
                // Calculate positions for natural link distribution
                $positions = array();
                if (count($paragraphsArray) >= 3) {
                    // Divide the article into sections
                    $positions[] = (int)(count($paragraphsArray) * 0.2); // Beginning section
                    $positions[] = (int)(count($paragraphsArray) * 0.5); // Middle section
                    $positions[] = (int)(count($paragraphsArray) * 0.8); // End section
                } else {
                    // If fewer paragraphs, just use what we have
                    $positions = range(0, count($paragraphsArray) - 1);
                }
                                
                $positionIndex = 0;
                foreach ($affiliate_links as $affiliate_url) {
                    if ($currentCount >= 3) break;
                    
                    $affDomain = parse_url($affiliate_url, PHP_URL_HOST);
                    if (!$affDomain) continue;
                    
                    $normAffDomain = preg_replace('/^www\./i', '', $affDomain);
                    
                    // Skip if this domain is already used
                    if (isset($uniqueDomains[$normAffDomain])) continue;
                    
                    // Extract domain name without extension for matching
                    $baseDomain = preg_replace('/\.[^.]+$/', '', $normAffDomain);

                    // Make the pattern case-insensitive and use word boundaries
                    $pattern = '/\b' . preg_quote($baseDomain, '/') . '\b/i';

                    // First check if the keyword exists anywhere in the text
                    if (preg_match($pattern, $plainText)) {
                        // Choose an appropriate paragraph
                        if (isset($positions[$positionIndex]) && isset($paragraphsArray[$positions[$positionIndex]])) {
                            $paragraph = $paragraphsArray[$positions[$positionIndex]];
                            $positionIndex++;
                            
                            // Get text from the paragraph
                            $paragraphText = $paragraph->textContent;
                            
                            // Check if the keyword is in this specific paragraph
                            if (preg_match($pattern, $paragraphText)) {
                                
                                // Create anchor text (the actual word with the case preserved)
                                $anchor = '';
                                if (preg_match('/\b(' . preg_quote($baseDomain, '/') . ')\b/i', $paragraphText, $matches)) {
                                    $anchor = $matches[1]; // This preserves the case from the original text
                                } else {
                                    $anchor = $baseDomain;
                                }
                                
                                // Use DOM instead of regex for more reliable replacements
                                $textContent = $paragraph->textContent;
                                $newHtml = preg_replace('/\b' . preg_quote($anchor, '/') . '\b/i', 
                                    '<a href="' . htmlspecialchars($affiliate_url) . '" target="_blank" rel="noopener noreferrer">' . $anchor . '</a>', 
                                    $textContent, 1);
                                
                                // Clear and update the paragraph content
                                while ($paragraph->firstChild) {
                                    $paragraph->removeChild($paragraph->firstChild);
                                }

                                $tempDoc = new DOMDocument('1.0', 'UTF-8');
                                $newHtml_encoded = mb_convert_encoding($newHtml, 'HTML-ENTITIES', 'UTF-8');
                                @$tempDoc->loadHTML('<!DOCTYPE html><html><body><div>' . $newHtml_encoded . '</div></body></html>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
                                $newContent = $tempDoc->getElementsByTagName('div')->item(0);
                                
                                if ($newContent && $newContent->childNodes->length > 0) {
                                    foreach ($newContent->childNodes as $child) {
                                        $importedNode = $doc->importNode($child, true);
                                        $paragraph->appendChild($importedNode);
                                    }
                                    $uniqueDomains[$normAffDomain] = true;
                                    $currentCount++;
                                }
                            }
                        }
                    }
                }
            }
        }
        
    
        // Return the inner HTML of our temporary container.
        $wrapperNode = $doc->getElementById('article_wrapper');
        $innerHTML = '';
        foreach ($wrapperNode->childNodes as $child) {
            $innerHTML .= $doc->saveHTML($child);
        }

        // Decode HTML entities back to UTF-8
        $innerHTML = html_entity_decode($innerHTML, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return $innerHTML;
    }
    protected function process_all_links($article_text) {
	    // Process external links first (limit to 3 unique domains)
	    $article_text = $this->add_external_links($article_text);
	    
	    // Then process internal links (add links to relevant keywords)
	    $article_text = $this->add_internal_links($article_text);
	    
	    // Apply affiliate links where applicable
	    $article_text = $this->replace_with_affiliate_links($article_text);
	    
	    // Finally, remove any blacklisted links
	    $article_text = $this->remove_blacklisted_links($article_text);
	    
	    return $article_text;
	}
	protected function generate_slug($title) {
        $stopwords = array(
            'a', 'about', 'above', 'after', 'again', 'against', 'all', 'am', 'an', 'and',
            'any', 'are', 'aren\'t', 'as', 'at', 'be', 'because', 'been', 'before', 'being',
            'below', 'between', 'both', 'but', 'by', 'can', 'can\'t', 'cannot', 'could', 'couldn\'t',
            'did', 'didn\'t', 'do', 'does', 'doesn\'t', 'doing', 'don\'t', 'down', 'during', 'each',
            'few', 'for', 'from', 'further', 'had', 'hadn\'t', 'has', 'hasn\'t', 'have', 'haven\'t',
            'having', 'he', 'he\'d', 'he\'ll', 'he\'s', 'her', 'here', 'here\'s', 'hers', 'herself',
            'him', 'himself', 'his', 'how', 'how\'s', 'i', 'i\'d', 'i\'ll', 'i\'m', 'i\'ve', 'if',
            'in', 'into', 'is', 'isn\'t', 'it', 'it\'s', 'its', 'itself', 'let\'s', 'me', 'more',
            'most', 'mustn\'t', 'my', 'myself', 'no', 'nor', 'not', 'of', 'off', 'on', 'once',
            'only', 'or', 'other', 'ought', 'our', 'ours', 'ourselves', 'out', 'over', 'own',
            'same', 'shan\'t', 'she', 'she\'d', 'she\'ll', 'she\'s', 'should', 'shouldn\'t', 'so',
            'some', 'such', 'than', 'that', 'that\'s', 'the', 'their', 'theirs', 'them', 'themselves',
            'then', 'there', 'there\'s', 'these', 'they', 'they\'d', 'they\'ll', 'they\'re', 'they\'ve',
            'this', 'those', 'through', 'to', 'too', 'under', 'until', 'up', 'very', 'was', 'wasn\'t',
            'we', 'we\'d', 'we\'ll', 'we\'re', 'we\'ve', 'were', 'weren\'t', 'what', 'what\'s',
            'when', 'when\'s', 'where', 'where\'s', 'which', 'while', 'who', 'who\'s', 'whom', 'why',
            'why\'s', 'with', 'won\'t', 'would', 'wouldn\'t', 'you', 'you\'d', 'you\'ll', 'you\'re',
            'you\'ve', 'your', 'yours', 'yourself', 'yourselves'
        );
        
        $words = explode(' ', strtolower($title));
        $filtered = array();
        
        foreach ($words as $word) {
            if (!in_array($word, $stopwords)) {
                $filtered[] = $word;
            }
        }
        
        $slug = implode('-', $filtered);
        $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
        return $slug;
    }


    // UTILITY
    protected function create_dom_document($html_content, $include_wrapper = true) {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');

        // Encode HTML entities to preserve UTF-8 characters through DOMDocument processing
        $html_content = mb_convert_encoding($html_content, 'HTML-ENTITIES', 'UTF-8');

        if ($include_wrapper) {
            $dom->loadHTML('<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' . $html_content . '</body></html>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        } else {
            $dom->loadHTML('<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' . $html_content . '</body></html>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        }

        return $dom;
    }
    protected function clean_dom_output($dom, $element = null) {
        if ($element === null) {
            $output = $dom->saveHTML($dom->documentElement);
        } else {
            $output = $dom->saveHTML($element);
        }

        // Decode HTML entities back to UTF-8
        $output = html_entity_decode($output, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Clean up XML wrapper
        $output = preg_replace('/<\?xml encoding="UTF-8">\s*/', '', $output);
        $output = preg_replace('/<\/?div>/', '', $output);

        libxml_clear_errors();

        return $output;
    }
    protected function validate_api_credentials() {
	    $token = $this->get_access_token();
	    if (!$token) {
	        throw new Exception("Failed to authenticate with YouTube.");
	    }
	    
	    $key = Creator_AI::get_credential('cai_openai_api_key');
	    if (empty($key)) {
	        throw new Exception("OpenAI API key is required.");
	    }
	    
	    return array('token' => $token, 'key' => $key);
	}
	protected function log_error($message, $data = array()) {
	    $log_entry = array(
	        'timestamp' => current_time('mysql'),
	        'message' => $message,
	        'data' => $data
	    );
	    
	    $logs = get_option('creator_ai_error_logs', array());
	    array_unshift($logs, $log_entry); // Add to beginning
	    
	    // Keep only last 50 logs
	    if (count($logs) > 50) {
	        $logs = array_slice($logs, 0, 50);
	    }
	    
	    update_option('creator_ai_error_logs', $logs);
	    
	    // Also log to WordPress error log
	    error_log('Creator AI Error: ' . $message);
	    if (!empty($data)) {
	        error_log('Creator AI Error Data: ' . json_encode($data));
	    }
	}
	protected function log_json_response($data, $label = 'JSON') {
	    if (is_array($data) || is_object($data)) {
	        // Convert to JSON and format it for readability
	        $json = json_encode($data, JSON_PRETTY_PRINT);
	        
	        // Break into smaller chunks for logging (PHP error logs can truncate long lines)
	        $chunks = str_split($json, 4000);
	        
	        error_log("===== BEGIN {$label} DUMP =====");
	        $chunk_count = count($chunks);
	        
	        foreach ($chunks as $index => $chunk) {
	            error_log("[PART " . ($index + 1) . "/{$chunk_count}] $chunk");
	        }
	        
	        error_log("===== END {$label} DUMP =====");
	        
	        return true;
	    } else {
	        error_log("{$label} DUMP FAILED: Not an array or object");
	        return false;
	    }
	}

}