(function($) {
    // Configuration
    const CONFIG = {
        ajaxTimeout: 60000 // 60 seconds
    };

    // Create a safe wrapper for AJAX requests that checks for caiAjax
    function safeAjaxRequest(options) {
        // Use WordPress admin-ajax URL if caiAjax is not defined
        if (typeof caiAjax === 'undefined') {
            console.log('caiAjax not defined, attempting to use default WordPress ajax URL');
            
            // Try to use WordPress's ajaxurl global variable
            let ajaxUrl = typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php';
            
            // If we're in the admin area, we can construct the URL
            if (window.location.href.indexOf('/wp-admin/') > -1) {
                ajaxUrl = window.location.href.split('/wp-admin/')[0] + '/wp-admin/admin-ajax.php';
            }
            
            options.url = ajaxUrl;
            
            // Create a nonce using current timestamp if not available
            if (!options.data.nonce) {
                options.data.nonce = 'fallback_nonce_' + new Date().getTime();
                console.log('Using fallback nonce');
            }
        } else {
            // Use caiAjax if it's available
            options.url = caiAjax.ajax_url;
            options.data.nonce = caiAjax.nonce;
        }
        
        // Perform the AJAX request
        return $.ajax(options);
    }

    // API Test Functions
    function testOpenAI() {
        var $responseDiv = $('#openai-test-response');
        
        // Show loading message
        $responseDiv.html('<div class="notice notice-info"><p><span class="spinner" style="visibility:visible;float:none;margin:0 5px 0 0"></span> Testing OpenAI API connection...</p></div>');
        
        safeAjaxRequest({
            type: 'POST',
            data: {
                action: 'cai_test_openai'
            },
            timeout: CONFIG.ajaxTimeout,
            success: function(response) {
                if (response.success) {
                    $responseDiv.html('<div class="yt-api-success">OpenAI API connection successful. Your credentials are working properly.</div>');
                } else {
                    $responseDiv.html('<div class="yt-api-error">OpenAI API Error: ' + response.data + '</div>');
                }
            },
            error: function(xhr, status, error) {
                $responseDiv.html('<div class="yt-api-error">AJAX request failed: ' + (status === 'timeout' ? 'Request timed out' : error) + '</div>');
            }
        });
    }

    function testGoogleAPI() {
        var $responseDiv = $('#google-test-response');
        
        // Show loading message
        $responseDiv.html('<div class="notice notice-info"><p><span class="spinner" style="visibility:visible;float:none;margin:0 5px 0 0"></span> Testing Google API connection...</p></div>');
        
        safeAjaxRequest({
            type: 'POST',
            data: {
                action: 'cai_test_google_api'
            },
            timeout: CONFIG.ajaxTimeout,
            success: function(response) {
                if (response.success) {
                    $responseDiv.html('<div class="yt-api-success">Google API connection successful. Your OAuth credentials are working properly.</div>');
                } else {
                    $responseDiv.html('<div class="yt-api-error">Google API Error: ' + response.data + '</div>');
                }
            },
            error: function(xhr, status, error) {
                $responseDiv.html('<div class="yt-api-error">AJAX request failed: ' + (status === 'timeout' ? 'Request timed out' : error) + '</div>');
            }
        });
    }



    // Function to initialize API connection buttons
    function initAPIConnections() {
        // Direct click event handlers
        $(document).on('click', '#test-openai-connection', function(e) {
            e.preventDefault();
            testOpenAI();
        });
        
        $(document).on('click', '#test-google-connection', function(e) {
            e.preventDefault();
            if (!$(this).prop('disabled')) {
                testGoogleAPI();
            }
        });
        
    }

    // Expose public functions
    window.CreatorAI = window.CreatorAI || {};
    window.CreatorAI.API = {
        init: initAPIConnections,
        testOpenAI: testOpenAI,
        testGoogleAPI: testGoogleAPI
    };

    // Initialize on document ready
    $(document).ready(function() {
        initAPIConnections();
    });

})(jQuery);