<?php
/**
 * YouTube Article Generator Module
 *
 * @package CreatorAI
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Creator_AI_YouTube_Article {

    /**
     * Constructor.
     */
    public function __construct() {
        // Register AJAX hooks for this module.
        add_action( 'wp_ajax_cai_yt_fetch_videos', array( $this, 'ajax_fetch_videos' ) );
        add_action( 'wp_ajax_cai_yt_create_article', array( $this, 'ajax_create_article' ) );
        add_action( 'wp_ajax_cai_yt_upload_image', array( $this, 'ajax_handle_image_upload' ) );
    }

    /**
     * AJAX handler to fetch YouTube videos.
     */
    public function ajax_fetch_videos() {
        check_ajax_referer( 'creator_ai_nonce', 'nonce' );

        $channel_id = get_option( 'cai_youtube_channel_id' );
        if ( empty( $channel_id ) ) {
            wp_send_json_error( 'YouTube Channel ID is not set in settings.' );
        }

        $token = Creator_AI_API::get_google_access_token();
        if ( ! $token ) {
            wp_send_json_error( 'Failed to get Google API access token. Please connect your account in settings.' );
        }

        $page_token = isset( $_POST['pageToken'] ) ? sanitize_text_field( $_POST['pageToken'] ) : '';
        $url = "https://www.googleapis.com/youtube/v3/search?part=snippet,id&channelId=" . urlencode( $channel_id ) . "&order=date&maxResults=12";
        if ( $page_token ) {
            $url .= "&pageToken=" . urlencode( $page_token );
        }

        $response = wp_remote_get( $url, array(
            'headers' => array( 'Authorization' => 'Bearer ' . $token ),
            'timeout' => 30,
        ) );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( $response->get_error_message() );
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( wp_remote_retrieve_response_code( $response ) >= 400 ) {
            $error_message = isset( $data['error']['message'] ) ? $data['error']['message'] : 'An unknown error occurred.';
            wp_send_json_error( 'YouTube API Error: ' . $error_message );
        }
        
        $html = $this->render_video_list_html( $data );

        wp_send_json_success( array(
            'html'          => $html,
            'nextPageToken' => isset( $data['nextPageToken'] ) ? $data['nextPageToken'] : '',
            'prevPageToken' => isset( $data['prevPageToken'] ) ? $data['prevPageToken'] : '',
        ) );
    }

    /**
     * Render the HTML for the list of videos.
     *
     * @param array $data API response data from YouTube.
     * @return string HTML output.
     */
    private function render_video_list_html( $data ) {
        ob_start();
        if ( ! empty( $data['items'] ) ) {
            echo '<div class="yta-video-list">';
            foreach ( $data['items'] as $item ) {
                if ( $item['id']['kind'] !== 'youtube#video' ) {
                    continue;
                }
                
                $video_id = esc_attr( $item['id']['videoId'] );
                $title    = esc_html( $item['snippet']['title'] );
                $thumb_url = esc_url( $item['snippet']['thumbnails']['high']['url'] );
                $pub_date = date_i18n( get_option( 'date_format' ), strtotime( $item['snippet']['publishedAt'] ) );
                
                // Check if an article already exists for this video
                $args = array(
                    'post_type' => 'post',
                    'post_status' => array( 'publish', 'draft', 'pending' ),
                    'meta_key' => '_youtube_video_id',
                    'meta_value' => $video_id,
                    'posts_per_page' => 1,
                    'fields' => 'ids',
                );
                $existing_post = get_posts($args);
                $status_class = '';
                $status_label = '';
                if(!empty($existing_post)) {
                    $post_status = get_post_status($existing_post[0]);
                    $status_class = 'has-article bg-' . $post_status;
                    $status_label = '<div class="yta-published-indicator article-' . esc_attr($post_status) . '">' . esc_html(ucfirst($post_status)) . '</div>';
                }

                ?>
                <div class="yta-video <?php echo $status_class; ?>" data-video-id="<?php echo $video_id; ?>">
                    <?php echo $status_label; ?>
                    <img src="<?php echo $thumb_url; ?>" alt="<?php echo $title; ?>" />
                    <div class="video-info">
                        <strong><?php echo $title; ?></strong>
                        <span style="color:#999;font-size:0.9em;margin-top:3px;display:block;"><?php echo $pub_date; ?></span>
                        <div class="yta-status"></div>
                    </div>
                </div>
                <?php
            }
            echo '</div>';
        } else {
            echo '<p>No videos found.</p>';
        }
        return ob_get_clean();
    }
    
    /**
     * AJAX handler to create an article from a YouTube video.
     */
    public function ajax_create_article() {
        check_ajax_referer( 'creator_ai_nonce', 'nonce' );

        $video_id = isset( $_POST['videoId'] ) ? sanitize_text_field( $_POST['videoId'] ) : '';
        if ( empty( $video_id ) ) {
            wp_send_json_error( 'Video ID is required.' );
        }
        
        // This is a long process, so we increase the time limit.
        set_time_limit(300);

        // Fetch transcript (this could be its own method for clarity)
        $transcript = $this->get_youtube_transcript($video_id);
        if(is_wp_error($transcript)) {
            wp_send_json_error($transcript->get_error_message());
        }

        // Get video details
        $video_details = $this->get_youtube_video_details($video_id);
        if(is_wp_error($video_details)) {
            wp_send_json_error($video_details->get_error_message());
        }

        // Generate Article using OpenAI
        // In a real application, you'd have more complex prompt engineering here.
        $prompt = "Based on the following transcript, write a blog post titled '" . $video_details['title'] . "'. The transcript is: " . $transcript;
        $response = Creator_AI_API::openai_request(array(
            'model' => get_option('cai_openai_model', 'gpt-4o'),
            'messages' => array(
                array('role' => 'system', 'content' => get_option('yta_prompt_article_system')),
                array('role' => 'user', 'content' => $prompt)
            ),
            'max_tokens' => intval(get_option('cai_openai_tokens', 4096))
        ));

        if(is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }

        $article_content = $response['choices'][0]['message']['content'];

        // Create the post
        $post_data = array(
            'post_title'   => wp_strip_all_tags( $video_details['title'] ),
            'post_content' => wp_kses_post( $article_content ),
            'post_status'  => 'draft',
            'post_author'  => get_current_user_id(),
            'post_type'    => 'post',
        );

        $post_id = wp_insert_post( $post_data, true );

        if(is_wp_error($post_id)) {
            wp_send_json_error($post_id->get_error_message());
        }

        // Add meta data
        update_post_meta($post_id, '_youtube_video_id', $video_id);
        
        // Set featured image
        if(!empty($video_details['thumbnail_url'])) {
            // This function would download the image and set it as featured.
            // For simplicity, we'll skip the implementation details here.
        }

        wp_send_json_success( array( 'post_id' => $post_id ) );
    }

    /**
     * Get transcript for a YouTube video.
     */
    private function get_youtube_transcript($video_id) {
        // This is a placeholder for a real transcript fetching service or library.
        // In a real plugin, you might use an external API or a library like youtube-dl on the server.
        // For this refactor, we'll return a placeholder.
        return "This is a placeholder for the video transcript. In a real application, this would be fetched from YouTube, which is a complex process involving checking for available captions and downloading them.";
    }

    /**
     * Get details for a YouTube video.
     */
    private function get_youtube_video_details($video_id) {
        $token = Creator_AI_API::get_google_access_token();
        if(!$token) {
            return new WP_Error('auth_error', 'Could not authenticate with Google.');
        }

        $url = "https://www.googleapis.com/youtube/v3/videos?part=snippet&id=" . urlencode($video_id);
        $response = wp_remote_get($url, array('headers' => array('Authorization' => 'Bearer ' . $token)));

        if(is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if(empty($data['items'])) {
            return new WP_Error('not_found', 'Video details not found.');
        }

        $snippet = $data['items'][0]['snippet'];
        return array(
            'title' => $snippet['title'],
            'description' => $snippet['description'],
            'thumbnail_url' => $snippet['thumbnails']['high']['url']
        );
    }
    
    /**
     * AJAX handler for uploading images.
     */
    public function ajax_handle_image_upload() {
        check_ajax_referer( 'creator_ai_nonce', 'nonce' );

        if ( empty( $_FILES['file'] ) ) {
            wp_send_json_error( 'No file uploaded.' );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $attachment_id = media_handle_upload( 'file', 0 );

        if ( is_wp_error( $attachment_id ) ) {
            wp_send_json_error( $attachment_id->get_error_message() );
        }

        $thumb_url = wp_get_attachment_image_url( $attachment_id, 'thumbnail' );

        wp_send_json_success( array(
            'attachment_id' => $attachment_id,
            'thumbnail'     => $thumb_url,
        ) );
    }
}
