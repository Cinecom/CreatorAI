jQuery(document).ready(function($) {
    // Define plugin directory URL for global access
    window.plugin_dir_url = '/wp-content/plugins/creator-ai/';
    window.site_url = window.location.protocol + '//' + window.location.host;
    
    // Make sure caiAjax is defined globally for all pages
    if (typeof caiAjax === 'undefined') {
        // Use default WordPress ajaxurl variable
        var ajaxUrl = typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php';
        
        console.log('Creating global caiAjax with ajax_url:', ajaxUrl);
        
        window.caiAjax = {
            ajax_url: ajaxUrl,
            nonce: typeof cai_nonce !== 'undefined' ? cai_nonce : ''
        };
    }
    
    // Detect which page we're on
    var isSettingsPage = $('.setting-wrap').length > 0;
    var isYTArticlePage = $('#ytarticle-videos').length > 0;
    var isUpdateArticlePage = $('.update-article-wrap').length > 0;
    var isCourseCreatorPage = $('.course-creator-wrap').length > 0;


    // Always initialize API connections - they're present on all plugin pages
    if (window.CreatorAI && window.CreatorAI.API) {
        window.CreatorAI.API.init();
    } else {
        console.log('CreatorAI.API not available');
    }
    
    // Initialize settings page components
    if (isSettingsPage && window.CreatorAI && window.CreatorAI.Settings) {
        window.CreatorAI.Settings.init();
    }
    
    // Initialize YouTube Article components
    if (isYTArticlePage && window.CreatorAI && window.CreatorAI.YTArticle) {
        window.CreatorAI.YTArticle.init();
    }
    
    // Initialize News Article components
    if (isUpdateArticlePage && window.CreatorAI && window.CreatorAI.updateArticle) {
        window.CreatorAI.updateArticle.init();
    }
    
    // Initialize Course Creator components
    if (isCourseCreatorPage) {
        
        if (window.CreatorAI && window.CreatorAI.CourseCreator) {
            window.CreatorAI.CourseCreator.init();
        } else {
            console.error('CreatorAI.CourseCreator not available!', window.CreatorAI);
        }
    }
});