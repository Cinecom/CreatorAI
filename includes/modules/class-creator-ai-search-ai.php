<?php
/**
 * SearchAI Gutenberg Block Module
 *
 * @package CreatorAI
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Creator_AI_Search_AI {

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'init', array( $this, 'register_block' ) );
        add_filter( 'block_categories_all', array( $this, 'add_block_category' ), 10, 2 );
        add_action( 'wp_ajax_cai_search_ai_query', array( $this, 'ajax_handle_query' ) );
        add_action( 'wp_ajax_nopriv_cai_search_ai_query', array( $this, 'ajax_handle_query' ) );
    }

    /**
     * Add a custom block category for Creator AI blocks.
     */
    public function add_block_category( $categories, $post ) {
        return array_merge(
            array(
                array(
                    'slug'  => 'creator-ai',
                    'title' => __( 'Creator AI', 'creator-ai' ),
                    'icon'  => 'superhero',
                ),
            ),
            $categories
        );
    }

    /**
     * Register the SearchAI Gutenberg block.
     */
    public function register_block() {
        if ( ! function_exists( 'register_block_type' ) ) {
            return;
        }

        register_block_type( 'creator-ai/searchai', array(
            'api_version'     => 2,
            'name'            => 'creator-ai/searchai',
            'title'           => __( 'Search AI', 'creator-ai' ),
            'description'     => __( 'Add an AI-powered search assistant to your site.', 'creator-ai' ),
            'category'        => 'creator-ai',
            'icon'            => 'search',
            'editor_script'   => 'creator-ai-admin', // We use the combined admin script.
            'editor_style'    => 'creator-ai-admin',
            'style'           => 'creator-ai-frontend',
            'render_callback' => array( $this, 'render_block' ),
            'attributes'      => array(
                'primaryColor' => array(
                    'type'    => 'string',
                    'default' => '#4a6ee0',
                ),
                'secondaryColor' => array(
                    'type'    => 'string',
                    'default' => '#f8f8f8',
                ),
                 'textColor' => array(
                    'type'    => 'string',
                    'default' => '#333333',
                ),
                'borderRadius' => array(
                    'type' => 'number',
                    'default' => 8
                ),
                'placeholder' => array(
                    'type' => 'string',
                    'default' => 'Ask a question...'
                ),
                'welcomeMessage' => array(
                    'type' => 'string',
                    'default' => 'Hello! How can I help you today?'
                ),
            ),
        ) );
    }

    /**
     * Render the SearchAI block on the frontend.
     *
     * @param array $attributes The block attributes.
     * @return string The HTML to render.
     */
    public function render_block( $attributes ) {
        $unique_id = 'cai-searchai-' . wp_generate_uuid4();
        
        $primary_color   = sanitize_hex_color( $attributes['primaryColor'] );
        $secondary_color = sanitize_hex_color( $attributes['secondaryColor'] );
        $text_color      = sanitize_hex_color( $attributes['textColor'] );
        $border_radius   = intval( $attributes['borderRadius'] );
        $placeholder     = esc_attr( $attributes['placeholder'] );
        $welcome_message = esc_html( $attributes['welcomeMessage'] );

        ob_start();
        ?>
        <style>
            #<?php echo $unique_id; ?> {
                --primary-color: <?php echo $primary_color; ?>;
                --secondary-color: <?php echo $secondary_color; ?>;
                --text-color: <?php echo $text_color; ?>;
                --border-radius: <?php echo $border_radius; ?>px;
            }
        </style>
        <div id="<?php echo $unique_id; ?>" class="searchai-container">
            <div class="searchai-chat-container">
                <div class="searchai-messages">
                    <div class="searchai-message searchai-message-ai">
                         <div class="searchai-avatar">
                            <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/></svg>
                        </div>
                        <div class="searchai-message-content"><?php echo $welcome_message; ?></div>
                    </div>
                </div>
                <div class="searchai-input-container">
                    <form class="searchai-form">
                        <input type="text" class="searchai-input" placeholder="<?php echo $placeholder; ?>">
                        <button type="submit" class="searchai-submit">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * AJAX handler for processing a search query.
     */
    public function ajax_handle_query() {
        check_ajax_referer( 'creator_ai_nonce', 'nonce' );
        
        $query = sanitize_text_field( $_POST['query'] );

        // In a real scenario, this is where you would:
        // 1. Find relevant posts on your site (e.g., using WP_Query).
        // 2. Format that content as context for the AI.
        // 3. Send the query and context to the OpenAI API.
        // 4. Format the AI's response.

        // For this refactor, we'll return a simulated response.
        $simulated_response = "This is a simulated AI response to your query: '" . esc_html( $query ) . "'. In the full plugin, I would search your website's content to provide a relevant answer.";

        wp_send_json_success( array( 'aiResponse' => $simulated_response ) );
    }
}
