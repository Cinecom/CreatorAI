(function($) {
    // Configuration
    const CONFIG = {
        maxImageUploads: 5,
        progressPollInterval: 2000,     // 2 seconds
        progressTimeoutLimit: 600000,   // 10 minutes
        ajaxTimeout: 60000              // 60 seconds
    };

    // State management
    const STATE = {
        uploadedImages: [],
        currentVideoId: '',
        processInProgress: false,
        progressLog: [],
        resetProcess: function() {
            this.processInProgress = false;
            this.uploadedImages = [];
            this.currentVideoId = '';
            $('.yta-video').removeClass('disabled');
            window.removeEventListener('beforeunload', beforeUnloadHandler);
        }
    };

    // Warning when leaving during process
    function beforeUnloadHandler(e) {
        e.preventDefault();
        e.returnValue = "An article creation process is in progress. Please do not close the window.";
    }

    // Initialize image upload modal
    function initUploadModal() {
        if ($('#yt-image-upload-modal').length === 0) {
            $('body').append(
                '<div id="yt-image-upload-modal" class="yt-image-upload-modal">' +
                    '<div class="yt-modal-content">' +
                        '<button type="button" class="yt-close-button" id="yt-close-button" style="position:absolute;top:15px;right:15px;">&times;</button>' +
                        '<div class="yt-modal-header">Upload Images (Max ' + CONFIG.maxImageUploads + ')</div>' +
                        '<div class="yt-drag-drop-area" id="yt-drag-drop-area">' +
                            '<span class="dashicons dashicons-upload"></span>' +
                            '<p>Drag and drop images here or click to select</p>' +
                        '</div>' +
                        '<input type="file" id="yt-file-input" accept="image/*" multiple style="display:none;" />' +
                        '<div class="yt-upload-grid" id="yt-upload-grid"></div>' +
                        '<div class="yt-modal-buttons">' +
                            '<button type="button" class="yt-skip-button" id="yt-skip-button">Skip</button>' +
                            '<button type="button" class="yt-proceed-button" id="yt-proceed-button" disabled>Proceed</button>' +
                        '</div>' +
                    '</div>' +
                '</div>'
            );
        }
    }

    // Set up modal event handlers
    function initUploadModalEvents() {
        // Modal Buttons
        $('#yt-close-button').on('click', function() {
            $('#yt-image-upload-modal').fadeOut();
            STATE.resetProcess();
        });
        
        $('#yt-skip-button').on('click', function() {
            $('#yt-image-upload-modal').fadeOut();
            createArticle(STATE.currentVideoId, []);
        });
        
        $('#yt-proceed-button').on('click', function() {
            $('#yt-image-upload-modal').fadeOut();
            createArticle(STATE.currentVideoId, STATE.uploadedImages);
        });
        
        // File upload area
        $('#yt-drag-drop-area').on('click', function() {
            $('#yt-file-input').click();
        });
        
        $('#yt-file-input').on('change', function(e) {
            handleFiles(e.target.files);
        });
        
        $('#yt-drag-drop-area')
            .on('dragover', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).addClass('hover');
            })
            .on('dragleave', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('hover');
            })
            .on('drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('hover');
                
                // Get the original dataTransfer files
                const files = e.originalEvent.dataTransfer.files;
                
                // Use a timeout to ensure we're not processing the same files twice
                // due to event bubbling or multiple event triggers
                if (!$(this).data('processing')) {
                    $(this).data('processing', true);
                    setTimeout(() => {
                        handleFiles(files);
                        $(this).data('processing', false);
                    }, 100);
                }
            });
    }

    // Video selection handling
    function initVideoSelection() {
        $(document).on('click', '.yta-video', function(e) {
                
            e.preventDefault();
            e.stopPropagation();
            
            if (STATE.processInProgress) return;
            
            STATE.processInProgress = true;
            STATE.currentVideoId = $(this).data('video-id');
            STATE.uploadedImages = [];
            STATE.progressLog = [];
            
            $('.yta-video').addClass('disabled');
            window.addEventListener('beforeunload', beforeUnloadHandler);
            
            $('#yt-upload-grid').empty();
            $('#yt-proceed-button').attr('disabled', true);
            $('#yt-image-upload-modal').fadeIn();
        });

        // Video Fetch Buttons
        $('#ytarticle-fetch').on('click', function(e) {
            e.preventDefault();
            fetchVideos('');
        });
        
        $('#ytarticle-next').on('click', function(e) {
            e.preventDefault();
            fetchVideos($(this).data('token'));
        });
        
        $('#ytarticle-prev').on('click', function(e) {
            e.preventDefault();
            fetchVideos($(this).data('token'));
        });
    }

    /**
     * Handle image file uploads
     */
    function handleFiles(files) {
        if (STATE.uploadedImages.length >= CONFIG.maxImageUploads) {
            alert("Maximum of " + CONFIG.maxImageUploads + " images allowed.");
            return;
        }
        
        var filesArr = Array.from(files);
        filesArr.forEach(function(file) {
            if (STATE.uploadedImages.length < CONFIG.maxImageUploads) {
                uploadImage(file);
            }
        });
    }
    
    /**
     * Upload a single image file to WordPress
     */
    function uploadImage(file) {
        if ($('#spinner-container').length === 0) {
            $('#yt-drag-drop-area').append('<div id="spinner-container" style="margin-top:10px;"></div>');
        }
        
        var $spinner = $('<span class="yt-spinner uploading-indicator"></span>');
        $('#spinner-container').append($spinner);
        
        var formData = new FormData();
        formData.append('action', 'yta_upload_image');
        formData.append('nonce', ytaAjax.nonce);
        formData.append('file', file);
        
        $.ajax({
            url: ytaAjax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            timeout: CONFIG.ajaxTimeout,
            success: function(response) {
                $spinner.remove();
                
                if (response.success) {
                    var thumbUrl = response.data.thumbnail;
                    var attachmentId = response.data.attachment_id;
                    
                    STATE.uploadedImages.push(attachmentId);
                    $('#yt-upload-grid').append('<img src="' + thumbUrl + '" class="yt-upload-thumbnail" alt="Uploaded thumbnail" />');
                    
                    if (STATE.uploadedImages.length > 0) {
                        $('#yt-proceed-button').removeAttr('disabled');
                    }
                } else {
                    alert("Image upload error: " + response.data);
                }
            },
            error: function(xhr, status, error) {
                $spinner.remove();
                alert("Upload error: " + (status === 'timeout' ? 'Request timed out' : error));
            }
        });
    }
    
    /**
     * Fetch videos from YouTube API
     */
    function fetchVideos(pageToken) {
        $('#ytarticle-videos').html('<div class="cai-loading"><span class="yt-spinner"></span> Loading videos...</div>');
        
        $.ajax({
            url: ytaAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'yta_fetch_videos',
                nonce: ytaAjax.nonce,
                pageToken: pageToken
            },
            timeout: CONFIG.ajaxTimeout,
            success: function(response) {
                if (response.success) {
                    $('#ytarticle-videos').html(response.data.html);
                    
                    // Update navigation buttons
                    if (response.data.nextPageToken) {
                        $('#ytarticle-next').show().data('token', response.data.nextPageToken);
                    } else {
                        $('#ytarticle-next').hide();
                    }
                    
                    if (response.data.prevPageToken) {
                        $('#ytarticle-prev').show().data('token', response.data.prevPageToken);
                    } else {
                        $('#ytarticle-prev').hide();
                    }
                    
                    // Handle debug data - direct integration
                    if (response.data.debug_data && typeof window.updateDebugPanel === 'function') {
                        window.updateDebugPanel(response.data.debug_data);
                    }
                } else {
                    $('#ytarticle-videos').html('<div class="yt-api-error">' + response.data + '</div>');
                }
            },
            error: function(xhr, status, error) {
                $('#ytarticle-videos').html('<div class="yt-api-error">AJAX Error: ' + (status === 'timeout' ? 'Request timed out' : error) + '</div>');
            }
        });
    }
    
    /**
     * Create article with simplified progress tracking
     */
    function createArticle(videoId, imageIds) {
        var $videoElement = $('.yta-video[data-video-id="' + videoId + '"]');
        var $status = $videoElement.find('.yta-status');
        
        // Prevent multiple submissions
        if ($videoElement.hasClass('processing')) {
            return;
        }
        $videoElement.addClass('processing');
        
        // Clear any previous messages and progress containers
        $status.html('<span class="yt-spinner"></span> <span class="status-message">Initializing article creation...</span>');
        $videoElement.find('.yt-progress-container').remove(); // Remove any existing progress containers
        
        // Create a progress bar (only one)
        var $progressContainer = $('<div class="yt-progress-container"><div class="yt-progress-bar"></div></div>');
        $status.after($progressContainer);
        
        function updateProgressUI(message, percent) {
            // Update status message
            $status.html('<span class="yt-spinner"></span> <span class="status-message">' + message + '</span>');
            
            // Update progress bar
            $progressContainer.find('.yt-progress-bar').css('width', percent + '%');
        }
        
        // Initial status
        updateProgressUI('Starting article creation...', 0);
        
        // Make the request with a completion callback
        $.ajax({
            url: ytaAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'yta_create_article',
                nonce: ytaAjax.nonce,
                videoId: videoId,
                uploaded_images: imageIds
            },
            timeout: 300000, // 5 minutes
            success: function(response) {
                // Remove processing class
                $videoElement.removeClass('processing');
                
                if (response.success) {
                    // Handle debug data - direct integration
                    if (response.data.debug_data && typeof window.updateDebugPanel === 'function') {
                        window.updateDebugPanel(response.data.debug_data);
                    }
                    
                    // Success - show completion message
                    $status.html('<span class="status-message">Article created! Redirecting...</span>');
                    $progressContainer.find('.yt-progress-bar').css('width', '100%');
                    
                    // Remove warning before redirect
                    window.removeEventListener('beforeunload', beforeUnloadHandler);
                    
                    // Redirect to edit page after a short delay
                    setTimeout(function() {
                        window.location.href = 'post.php?post=' + response.data.post_id + '&action=edit';
                    }, 1500);
                } else {
                    STATE.resetProcess();
                    $status.html('<span style="color: #d63638; font-weight: bold;">✕ ' + response.data + '</span>');
                }
            },
            error: function(xhr, status, error) {
                // Remove processing class
                $videoElement.removeClass('processing');
                
                STATE.resetProcess();
                $status.html('<span style="color: #d63638; font-weight: bold;">✕ Connection error: ' + 
                    (status === 'timeout' ? 'Request timed out' : error) + '</span>');
                
                // Even if request fails, check if post was created (but only once)
                if (!$videoElement.hasClass('checked-creation')) {
                    $videoElement.addClass('checked-creation');
                    checkIfPostCreated(videoId);
                }
            }
        });
        
        // Set up a simple passive progress indicator
        var progress = 0;
        var stages = [
            { percent: 10, message: "Authenticating with YouTube API..." },
            { percent: 20, message: "Retrieving video transcript..." },
            { percent: 30, message: "Generating article content with AI..." },
            { percent: 45, message: "Adding internal links to related content..." },
            { percent: 60, message: "Processing images..." },
            { percent: 75, message: "Building article with WordPress blocks..." },
            { percent: 85, message: "Creating WordPress post..." },
            { percent: 95, message: "Setting SEO description..." }
        ];
        
        var progressTimer = setInterval(function() {
            if (progress >= 95 || !$videoElement.hasClass('processing')) {
                clearInterval(progressTimer);
                return;
            }
            
            // Find the next stage to display
            var nextStage = null;
            for (var i = 0; i < stages.length; i++) {
                if (progress < stages[i].percent) {
                    nextStage = stages[i];
                    break;
                }
            }
            
            if (nextStage) {
                progress = nextStage.percent;
                updateProgressUI(nextStage.message, progress);
            }
        }, 5000); // Update every 5 seconds
    }

    /**
     * Update status display with appropriate styling
     */
    function updateStatus($element, message, type) {
        var timestamp = new Date().toLocaleTimeString();
        var formattedMessage = '';
        
        switch(type) {
            case 'process':
                formattedMessage = '<span class="yt-spinner"></span> <span class="status-message">' + message + '</span>';
                break;
            case 'success':
                formattedMessage = '<span style="color: #00a32a; font-weight: bold;">✓ ' + message + '</span>';
                break;
            case 'error':
                formattedMessage = '<span style="color: #d63638; font-weight: bold;">✕ ' + message + '</span>';
                break;
            default:
                formattedMessage = message;
        }
        
        // Add to progress log
        STATE.progressLog.push({
            time: timestamp,
            message: message,
            type: type
        });
        
        // Update UI
        $element.html(formattedMessage);
    }

    /**
     * Check if post was created despite communication issues
     */
    function checkIfPostCreated(videoId) {
        var $videoElement = $('.yta-video[data-video-id="' + videoId + '"]');
        var $status = $videoElement.find('.ytarticle-status');
        
        updateStatus($status, 'Checking if article was created...', 'process');
        
        $.ajax({
            url: ytaAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'yta_check_post_exists',
                nonce: ytaAjax.nonce,
                videoId: videoId
            },
            timeout: CONFIG.ajaxTimeout,
            success: function(response) {
                if (response.success && response.data.post_id) {
                    updateStatus($status, 'Article successfully created!', 'success');
                    
                    // Remove warning before redirect
                    window.removeEventListener('beforeunload', beforeUnloadHandler);
                    
                    setTimeout(function() {
                        window.location.href = 'post.php?post=' + response.data.post_id + '&action=edit';
                    }, 1500);
                } else {
                    STATE.resetProcess();
                    updateStatus($status, 'Process timed out. Please try again.', 'error');
                }
            },
            error: function() {
                STATE.resetProcess();
                updateStatus($status, 'Connection failed. Please check posts manually.', 'error');
            }
        });
    }

    // Intercept the fetchVideos response to update debug panel
    var originalFetchVideos = fetchVideos;
    fetchVideos = function(pageToken) {
        originalFetchVideos(pageToken);
    };

    // Intercept createArticle for debug panel updates
    var originalCreateArticle = createArticle;
    createArticle = function(videoId, imageIds) {
        originalCreateArticle(videoId, imageIds);
    };

    // Initialize YouTube Article functionality
    function init() {
        initUploadModal();
        initUploadModalEvents();
        initVideoSelection();
    }

    // Expose public functions
    window.CreatorAI = window.CreatorAI || {};
    window.CreatorAI.YTArticle = {
        init: init
    };

    // Initialize when document is ready
    $(document).ready(function() {
        window.CreatorAI.YTArticle.init();
    });


})(jQuery);