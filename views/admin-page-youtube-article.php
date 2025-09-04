<?php
/**
 * View for the YouTube Article Generator Admin Page
 *
 * @package CreatorAI
 */
?>
<div class="wrap yt-article-wrap">
    <h1>
        <svg class="youtube-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
            <path d="M19.615 3.184c-3.604-.246-11.631-.245-15.23 0-3.897.266-4.356 2.62-4.385 8.816.029 6.185.484 8.549 4.385 8.816 3.6.245 11.626.246 15.23 0 3.897-.266 4.356-2.62 4.385-8.816-.029-6.185-.484-8.549-4.385-8.816zm-10.615 12.816v-8l8 3.993-8 4.007z" fill="#FF0000"/>
        </svg>
        <?php esc_html_e( 'YouTube Article Generator', 'creator-ai' ); ?>
    </h1>

    <p><?php esc_html_e( 'Turn your YouTube videos into SEO-optimized blog posts in minutes.', 'creator-ai' ); ?></p>
    <ol>
        <li><?php esc_html_e( 'Click "Fetch Videos" to load the latest videos from your channel.', 'creator-ai' ); ?></li>
        <li><?php esc_html_e( 'Click on a video to open the image upload modal.', 'creator-ai' ); ?></li>
        <li><?php esc_html_e( 'Upload relevant images, and the AI will generate a complete article with SEO.', 'creator-ai' ); ?></li>
    </ol>

    <button id="ytarticle-fetch" class="button button-primary"><?php esc_html_e( 'Fetch Videos', 'creator-ai' ); ?></button>
    
    <div id="ytarticle-videos" style="margin-top:20px;">
        <!-- Videos will be loaded here via AJAX -->
    </div>

    <div class="pagination-controls" style="margin-top:20px;">
        <button id="ytarticle-prev" class="button" style="display:none;"><?php esc_html_e( 'Previous', 'creator-ai' ); ?></button>
        <button id="ytarticle-next" class="button" style="display:none;"><?php esc_html_e( 'Next', 'creator-ai' ); ?></button>
    </div>

    <?php
    // The debug panel will be displayed here if enabled in settings.
    // This assumes a helper function or method exists to render it.
    if ( get_option( 'cai_debug' ) ) {
        // In a real class structure, you might call a method like:
        // Creator_AI_Utils::display_debug_panel();
        echo '<!-- Debug panel would render here -->';
    }
    ?>
</div>
