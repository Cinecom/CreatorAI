<?php
/**
 * View for the Plugin Settings Admin Page
 *
 * @package CreatorAI
 */
?>
<div class="wrap setting-wrap">
    <h1>
        <svg viewBox="0 0 30 30" class="settings-icon" version="1.1" xml:space="preserve" xmlns="http://www.w3.org/2000/svg"><path class="st2" d="M26.6,12.9l-2.9-0.3c-0.2-0.7-0.5-1.4-0.8-2l1.8-2.3c0.2-0.2,0.1-0.5,0-0.7l-2.2-2.2c-0.2-0.2-0.5-0.2-0.7,0 l-2.3,1.8c-0.6-0.4-1.3-0.6-2-0.8l-0.3-2.9C17,3.2,16.8,3,16.6,3h-3.1c-0.3,0-0.5,0.2-0.5,0.4l-0.3,2.9c-0.7,0.2-1.4,0.5-2,0.8 L8.3,5.4c-0.2-0.2-0.5-0.1-0.7,0L5.4,7.6c-0.2,0.2-0.2,0.5,0,0.7l1.8,2.3c-0.4,0.6-0.6,1.3-0.8,2l-2.9,0.3C3.2,13,3,13.2,3,13.4v3.1 c0,0.3,0.2,0.5,0.4,0.5l2.9,0.3c0.2,0.7,0.5,1.4,0.8,2l-1.8,2.3c-0.2,0.2-0.1,0.5,0,0.7l2.2,2.2c0.2,0.2,0.5,0.2,0.7,0l2.3-1.8 c0.6,0.4,1.3,0.6,2,0.8l0.3,2.9c0,0.3,0.2,0.4,0.5,0.4h3.1c0.3,0,0.5-0.2,0.5-0.4l0.3-2.9c0.7-0.2,1.4-0.5,2-0.8l2.3,1.8 c0.2,0.2,0.5,0.1,0.7,0l2.2-2.2c0.2-0.2,0.2-0.5,0-0.7l-1.8-2.3c0.4-0.6,0.6-1.3,0.8-2l2.9-0.3c0.3,0,0.4-0.2,0.4-0.5v-3.1 C27,13.2,26.8,13,26.6,12.9z M15,19c-2.2,0-4-1.8-4-4c0-2.2,1.8-4,4-4s4,1.8,4,4C19,17.2,17.2,19,15,19z"></path></svg>
        <?php esc_html_e( 'Creator AI Settings', 'creator-ai' ); ?>
    </h1>

    <div class="cai-tabs-container">
        <!-- Tab navigation will be rendered by JS -->
        <nav class="cai-tab-nav">
            <ul>
                <li><a href="#api-settings" class="cai-tab active" data-tab="api-settings">API Settings</a></li>
                <li><a href="#yt-article-settings" class="cai-tab" data-tab="yt-article-settings">YouTube Article</a></li>
                <li><a href="#course-creator-settings" class="cai-tab" data-tab="course-creator-settings">Course Creator</a></li>
            </ul>
        </nav>

        <form method="post" action="options.php">
            <?php
            settings_fields( 'creator_ai_settings_group' );
            ?>

            <!-- API Settings Tab -->
            <div id="api-settings" class="cai-tab-content active">
                <!-- All settings fields for this tab -->
                <p>API settings form fields will go here.</p>
            </div>

            <!-- YouTube Article Settings Tab -->
            <div id="yt-article-settings" class="cai-tab-content">
                <p>YouTube Article settings form fields will go here.</p>
            </div>

            <!-- Course Creator Settings Tab -->
            <div id="course-creator-settings" class="cai-tab-content">
                <p>Course Creator settings form fields will go here.</p>
            </div>

            <?php submit_button(); ?>
        </form>
    </div>
</div>
