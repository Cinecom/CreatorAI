(function($) {
    function initAPIConnections() {
        $('#test-openai-connection').on('click', function() {
            if (window.CreatorAI && window.CreatorAI.API) {
                window.CreatorAI.API.testOpenAI();
            }
        });
        
        $('#test-google-connection').on('click', function() {
            if ($(this).prop('disabled')) return;
            if (window.CreatorAI && window.CreatorAI.API) {
                window.CreatorAI.API.testGoogleAPI();
            }
        });
        
        $('#test-scrapingbee-connection').on('click', function() {
            if (window.CreatorAI && window.CreatorAI.API) {
                window.CreatorAI.API.testScrapingbee();
            }
        });
    }
    
    // Tab navigation functionality
    function initTabNavigation() {
        $('.cai-tab').on('click', function(e) {
            e.preventDefault();
            
            // Get the target tab
            const tabId = $(this).data('tab');
            
            // Update active tab highlighting
            $('.cai-tab').removeClass('active');
            $(this).addClass('active');
            
            // Show the correct tab content
            $('.cai-tab-content').removeClass('active');
            $('#' + tabId).addClass('active');
            
            // Store the active tab in localStorage for persistence
            localStorage.setItem('cai_active_tab', tabId);
        });
        
        // Restore previously selected tab on page load
        const savedTab = localStorage.getItem('cai_active_tab');
        if (savedTab && $('#' + savedTab).length) {
            $('.cai-tab[data-tab="' + savedTab + '"]').trigger('click');
        }
    }

    // Initialize the keyword tags functionality
    function initKeywordTags() {
        if ($('#keyword-tags-input').length > 0 && $('.keyword-tags-display').length > 0) {
            // Store references to DOM elements
            const $input = $('#keyword-tags-input');
            const $display = $('.keyword-tags-display');
            const $hidden = $('#keyword-tags-hidden');
            
            // Function to add a new tag
            function addKeywordTag(tag) {
                tag = tag.trim();
                if (!tag) return;
                
                // Check if tag already exists
                const currentTags = $hidden.val() ? $hidden.val().split(',') : [];
                if (currentTags.includes(tag)) return;
                
                // Add tag to display
                const $tag = $('<span class="keyword-tag">' + tag + '<span class="remove-tag">×</span></span>');
                $display.append($tag);
                
                // Update hidden input
                currentTags.push(tag);
                $hidden.val(currentTags.join(','));
                
                // Clear input
                $input.val('');
            }
            
            // Add tag on Enter or comma
            $input.off('keydown.keywordtags').on('keydown.keywordtags', function(e) {
                if (e.key === 'Enter' || e.key === ',') {
                    e.preventDefault();
                    const tag = $input.val().replace(/,/g, '');
                    addKeywordTag(tag);
                }
            });
            
            // Add tag on blur
            $input.off('blur.keywordtags').on('blur.keywordtags', function() {
                if ($input.val()) {
                    addKeywordTag($input.val());
                }
            });
            
            // Remove tag on click
            $display.off('click.keywordtags').on('click.keywordtags', '.remove-tag', function() {
                const $tag = $(this).parent();
                const tag = $tag.text().slice(0, -1); // Remove the × character
                
                // Remove from hidden input
                const currentTags = $hidden.val().split(',');
                const index = currentTags.indexOf(tag);
                if (index > -1) {
                    currentTags.splice(index, 1);
                    $hidden.val(currentTags.join(','));
                }
                
                // Remove from display
                $tag.remove();
            });
            
            // Initialize by processing existing tags from hidden field
            if ($hidden.val()) {
                const initialTags = $hidden.val().split(',');
                $display.empty();
                initialTags.forEach(tag => {
                    if (tag.trim()) {
                        $display.append('<span class="keyword-tag">' + tag + '<span class="remove-tag">×</span></span>');
                    }
                });
            }
        }
    }

    // Initialize affiliate link management
    function initAffiliateLinkManagement() {
        $(document).off('click.addlink').on('click.addlink', '.add-link', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Find the container holding all input fields
            var $parent = $(this).closest('td');
            var $lastInput = $parent.find('.affiliate-link-input:last');
            
            // Clone the last input field
            var $newRow = $lastInput.clone();
            
            // Clear value in cloned input
            $newRow.find('input').val('');
            
            // Replace + with - button if it has an add button
            if ($newRow.find('.add-link').length > 0) {
                $newRow.find('.add-link')
                    .removeClass('add-link')
                    .addClass('remove-link')
                    .text('–');
            }
            
            // Insert after the last input
            $lastInput.after($newRow);
        });
        
        $(document).off('click.removelink').on('click.removelink', '.remove-link', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).closest('.affiliate-link-input').remove();
        });
    }

    // Initialize prompt restoration
    function initPromptRestoration() {
        $('#restore-default-prompts').on('click', function() {
            $('textarea[name="yta_prompt_article_system"]').val(caiDefaults.articleSystem);
            $('textarea[name="yta_prompt_seo_system"]').val(caiDefaults.seoSystem);
            $('textarea[name="yta_prompt_anchor"]').val(caiDefaults.anchorText);
            $('textarea[name="yta_prompt_image_seo"]').val(caiDefaults.imageSeo);
        });

        $('#restore-au-prompt').on('click', function() {
            $('textarea[name="au_prompt_article"]').val(caiDefaults.auArticlePrompt);
        });
    }

    function initCourseCreatorSettings() {
        // Handle page selection change
        $('#cai_courses_page_id').on('change', function() {
            const selectedPageId = $(this).val();
            
            // Update status message
            const $statusRow = $(this).closest('table').find('tr:last-child td');
            
            if (selectedPageId === '0') {
                $statusRow.html('<div class="yt-api-error">No courses page selected or selected page is not valid</div>');
            } else {
                $statusRow.html('<div class="yt-api-success">Don\'t forget to save changes to update the courses page</div>');
            }
        });
        
        // Highlight the status when settings are saved
        if (window.location.search.includes('settings-updated=true')) {
            const $statusMessage = $('#course-creator-settings .yt-api-success, #course-creator-settings .yt-api-error');
            if ($statusMessage.length) {
                $statusMessage.css('background-color', '#fff').animate({
                    backgroundColor: $statusMessage.hasClass('yt-api-success') ? '#f0f6e6' : '#fef8f8'
                }, 1500);
            }
        }
    }

    // Handle theme styling toggle
    function initThemeStylingToggle() {
        const $toggle = $('#cai_use_theme_styling');
        const $customOptions = $('.cai-custom-styling-option');
        
        // Function to toggle custom styling options visibility
        function toggleCustomOptions() {
            if ($toggle.is(':checked')) {
                // Theme styling enabled - hide custom options
                $customOptions.addClass('cai-hidden');
                
                // Add a notice about theme styling being active
                if (!$('.cai-theme-styling-notice').length) {
                    const notice = $('<div class="cai-theme-styling-notice show">' +
                        '<strong>Theme Styling Active:</strong> Colors, fonts, and button styles will inherit from your WordPress theme. ' +
                        'Layout settings below are still available for customization.' +
                        '</div>');
                    $('.cai-custom-styling-option').first().before(notice);
                }
            } else {
                // Custom styling enabled - show custom options
                $customOptions.removeClass('cai-hidden');
                $('.cai-theme-styling-notice').remove();
            }
        }
        
        // Initialize on page load
        toggleCustomOptions();
        
        // Handle toggle change
        $toggle.on('change', toggleCustomOptions);
    }


    // Initialize settings page
    function initGitHubUpdater() {
        $('#check-github-updates').on('click', function() {
            var button = $(this);
            var statusDiv = $('#github-update-status');
            
            // Disable button and show loading
            button.prop('disabled', true).text('Checking...');
            statusDiv.html('<p style="color: #666;">Checking for updates...</p>');
            
            // Make AJAX request
            $.post(caiAjax.ajax_url, {
                action: 'cai_force_update_check',
                nonce: caiAjax.nonce
            })
            .done(function(response) {
                if (response.success) {
                    if (response.data.update_available) {
                        statusDiv.html(
                            '<div style="padding: 10px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px;">' +
                            '<p style="margin: 0; color: #856404;"><strong>Update Available!</strong></p>' +
                            '<p style="margin: 5px 0 0 0; color: #856404;">New version: ' + response.data.new_version + ' (current: ' + response.data.current_version + ')</p>' +
                            '<p style="margin: 5px 0 0 0;"><a href="' + caiAjax.admin_url + 'plugins.php" class="button button-primary" style="margin-right: 10px;">Go to Plugins Page</a>' +
                            '<a href="https://github.com/Cinecom/CreatorAI/compare/' + response.data.current_version + '...' + response.data.new_version + '" target="_blank" class="button button-secondary">View Changes</a></p>' +
                            '</div>'
                        );
                    } else {
                        statusDiv.html('<p style="color: #46b450;">✓ You have the latest version!</p>');
                    }
                } else {
                    statusDiv.html('<p style="color: #dc3232;">Error: ' + (response.data || 'Unknown error') + '</p>');
                }
            })
            .fail(function() {
                statusDiv.html('<p style="color: #dc3232;">Error: Failed to check for updates. Please try again.</p>');
            })
            .always(function() {
                // Re-enable button
                button.prop('disabled', false).text('Check for Updates');
            });
        });
    }

    function init() {
        initTabNavigation();
        initKeywordTags();
        initAffiliateLinkManagement();
        initPromptRestoration();
        initAPIConnections(); // Important: Initialize API connections here too
        initCourseCreatorSettings();
        initThemeStylingToggle();
        initGitHubUpdater();
        
        // Add animation for smooth transitions
        setTimeout(function() {
            $('.cai-tabs-container').addClass('loaded');
        }, 100);
    }

    // Expose public functions
    window.CreatorAI = window.CreatorAI || {};
    window.CreatorAI.Settings = {
        init: init
    };

    // Initialize when document is ready
    $(document).ready(function() {
        window.CreatorAI.Settings.init();
    });

})(jQuery);