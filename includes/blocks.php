<?php
/**
 * Creator AI - Blocks Registration
 * 
 * Handles registration of Gutenberg blocks for the Creator AI plugin
 */

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

class Creator_AI_Blocks {
    
    /**
     * Initialize the blocks registration
     */
    public static function init() {
        // Register hooks
        add_action('init', array(__CLASS__, 'register_blocks'));
        add_filter('block_categories_all', array(__CLASS__, 'register_block_category'), 10, 2);
        
        // Register scripts and styles
        add_action('enqueue_block_editor_assets', array(__CLASS__, 'enqueue_block_editor_assets'));
    }
    
    /**
     * Register the Creator AI block category
     */
    public static function register_block_category($categories, $editor_context) {
        return array_merge(
            $categories,
            array(
                array(
                    'slug' => 'creator-ai',
                    'title' => __('Creator AI', 'creator-ai'),
                    'icon'  => 'admin-generic',
                ),
            )
        );
    }
    
    /**
     * Register all blocks
     */
    public static function register_blocks() {
        // Check if Gutenberg is active
        if (!function_exists('register_block_type')) {
            return;
        }
        
        // Register the SearchAI block
        register_block_type(plugin_dir_path(dirname(__FILE__)) . 'blocks/searchai', array(
            'render_callback' => array(__CLASS__, 'render_searchai_block'),
        ));
    }
    
    /**
     * Enqueue block editor assets
     */
    public static function enqueue_block_editor_assets() {
        // Get plugin version
        $plugin_data = get_plugin_data(plugin_dir_path(dirname(__FILE__)) . 'creator-ai.php');
        $version = $plugin_data['Version'];
        
        // Enqueue the searchai.js script for the editor
        wp_enqueue_script(
            'creator-ai-searchai-editor',
            plugin_dir_url(dirname(__FILE__)) . 'js/searchai.js',
            array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'jquery'),
            $version,
            true
        );
        
        // Enqueue styles
        wp_enqueue_style(
            'creator-ai-searchai-editor-style',
            plugin_dir_url(dirname(__FILE__)) . 'css/searchai-style.css',
            array('wp-edit-blocks'),
            $version
        );
    }
    
    /**
     * Render the SearchAI block on the frontend
     */
    public static function render_searchai_block($attributes, $content) {
        // Prepare attributes with defaults
        $attr = wp_parse_args($attributes, array(
            'primaryColor' => '#4a6ee0',
            'secondaryColor' => '#f8f8f8',
            'textColor' => '#333333',
            'borderRadius' => 8,
            'padding' => 20,
            'maxHeight' => 500,
            'placeholder' => 'Ask about video editing, VFX, or motion design...',
            'welcomeMessage' => 'Hi! I\'m your video editing assistant. Ask me anything about Premiere Pro, After Effects, or any creative video techniques.',
            'showBranding' => true,
            'className' => ''
        ));
        
        // Generate unique ID for this instance
        $unique_id = 'searchai-' . uniqid();
        
        // Generate inline CSS for customization
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
        
        // Enqueue front-end script
        wp_enqueue_script(
            'creator-ai-searchai-frontend',
            plugin_dir_url(dirname(__FILE__)) . 'js/searchai.js',
            array('jquery'),
            '',
            true
        );
        
        // Localize the script
        wp_localize_script('creator-ai-searchai-frontend', 'searchAiData', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('searchai_nonce'),
            'instance_id' => $unique_id
        ));
        
        // Enqueue the styles
        wp_enqueue_style(
            'creator-ai-searchai-style',
            plugin_dir_url(dirname(__FILE__)) . 'css/searchai-style.css'
        );
        
        // Render the block
        ob_start();
        ?>
        <style><?php echo $inline_css; ?></style>
        <div id="<?php echo esc_attr($unique_id); ?>" class="searchai-container <?php echo esc_attr($attr['className']); ?>">
            <div class="searchai-chat-container">
                <div class="searchai-messages">
                    <?php if (!empty($attr['welcomeMessage'])): ?>
                    <div class="searchai-message searchai-message-ai">
                        <div class="searchai-avatar">
                            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/>
                            </svg>
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
            <?php if ($attr['showBranding']): ?>
            <div class="searchai-branding">
                <span>Powered by Creator AI</span>
            </div>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
}

// Initialize the blocks
Creator_AI_Blocks::init();
