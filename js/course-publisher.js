/**
 * Course Publisher Frontend Script
 * 
 * Handles client-side functionality for published courses including:
 * - Navigation and scrollspy
 * - Progress tracking
 * - Quiz handling and grading
 * - Certificate generation
 */

(function($) {
    // Track state
    const STATE = {
        courseId: null,
        postId: null,
        activeSection: null,
        userProgress: {},
        quizSubmitted: false,
        quizResults: null,
        certificateGenerating: false,
        sidebarSticky: true,
        updateInProgress: false
    };

    /**
     * Initialize course functionality
     */
    function initCourse() {
        // Get course info from localized data
        if (typeof caiCourse !== 'undefined') {
            STATE.courseId = caiCourse.course_id;
            STATE.postId = caiCourse.post_id;
            STATE.userProgress = caiCourse.user_progress || {};
            
            // Ensure STATE.userProgress has completed_sections array
            if (!STATE.userProgress.completed_sections) {
                STATE.userProgress.completed_sections = [];
            }
            
            // Ensure total_sections is available
            if (!caiCourse.total_sections && caiCourse.course_title) {
                // Count sections from DOM as fallback
                caiCourse.total_sections = $('.cai-section').length;
            }
        } else {
            // Initialize empty state if caiCourse is undefined
            STATE.courseId = null;
            STATE.postId = null;
            STATE.userProgress = {
                completed_sections: []
            };
        }
        
        // Check if theme styling is active and add body class
        if (typeof caiCourse !== 'undefined' && caiCourse.theme_styling_active) {
            $('body').addClass('cai-theme-styling-active');
        }

        // Set up event handlers
        setupEventHandlers();
        
        // Initialize sidebar functionality
        initializeSidebar();
        
        // Initialize navigation
        initializeNavigation();
        
        // Set up progress tracking
        if (typeof caiCourse !== 'undefined') {
            initializeProgressTracking();
        }
        
        // Initialize scrollspy
        initializeScrollSpy();
        
        // Initialize certificate button state
        if ($('.cai-certificate-ready').length > 0) {
            updateCertificateButtonState();
        }
    }

    /**
     * Set up event handlers
     */
    function setupEventHandlers() {
        // Chapter toggles in sidebar
        $('.cai-chapter-header').on('click', function() {
            const $chapter = $(this).closest('.cai-course-chapter');
            $chapter.toggleClass('cai-chapter-expanded');
        });
        
        // Mark section as complete/incomplete - use event delegation
        $(document).on('click', '.cai-mark-complete', function() {
            const sectionId = $(this).data('section-id');
            if ($(this).hasClass('cai-completed')) {
                markSectionIncomplete(sectionId);
            } else {
                markSectionComplete(sectionId);
            }
        });
        
        // Sticky toggle
        $('.cai-sticky-toggle').on('click', function() {
            toggleSidebarSticky();
        });
        
        // Quiz form submission
        $('#cai-quiz-form').on('submit', function(e) {
            e.preventDefault();
            submitQuiz();
        });
        
        // Retry quiz
        $(document).on('click', '.cai-retry-quiz', function() {
            resetQuiz();
        });
        
        // Generate certificate
        $(document).on('click', '.cai-get-certificate', function(e) {
            e.preventDefault();
            if (!$(this).prop('disabled')) {
                checkCertificateEligibility();
            }
        });

        $(document).on('click.main', '.course-action-publish', function() {
            if (!$(this).prop('disabled')) {
                const courseId = $(this).data('course-id');
                // Call your publish function here, for example:
                initPublishButton(courseId);
            }
        });
    }

    /**
     * Initialize course navigation
     */
    function initializeNavigation() {
        // Expand first chapter by default
        $('.cai-course-chapter:first').addClass('cai-chapter-expanded');
        
        // Smooth scroll navigation with improved offset calculation
        $('.cai-section-link, .cai-quiz-link, .cai-certificate-link').on('click', function(e) {
            e.preventDefault();
            
            // Handle certificate generation separately
            if ($(this).attr('data-action') === 'view-certificate') {
                checkCertificateEligibility();
                return;
            }
            
            // Get target from href or data-target attribute
            const target = $(this).attr('href') || $(this).attr('data-target');
            const $target = $(target);
            
            if ($target.length) {
                // Calculate better offset for sticky headers
                const headerOffset = $('.cai-course-header').outerHeight() || 0;
                const scrollOffset = Math.max(headerOffset + 20, 50);
                
                // Smooth scroll to target
                $('html, body').animate({
                    scrollTop: $target.offset().top - scrollOffset
                }, 600);
                
                // Highlight the target section briefly
                $target.addClass('cai-section-highlight');
                setTimeout(() => {
                    $target.removeClass('cai-section-highlight');
                }, 2000);
                
                // On mobile, collapse the navigation
                if ($(window).width() < 992) {
                    $('.cai-course-nav-title').addClass('collapsed');
                    $('.cai-course-chapters').slideUp(300);
                }
            }
        });
        
        // Enhanced mobile navigation toggle
        function setupMobileNavigation() {
            if ($(window).width() < 992) {
                $('.cai-course-chapters').hide();
                $('.cai-course-nav-title').addClass('collapsed').off('click.mobile').on('click.mobile', function() {
                    $(this).toggleClass('expanded').toggleClass('collapsed');
                    $('.cai-course-chapters').slideToggle(300);
                });
            } else {
                $('.cai-course-chapters').show();
                $('.cai-course-nav-title').removeClass('collapsed expanded').off('click.mobile');
            }
        }
        
        // Setup mobile navigation
        setupMobileNavigation();
        
        // Re-setup on window resize
        $(window).on('resize.courseNav', debounce(setupMobileNavigation, 250));
    }

    /**
     * Initialize progress tracking
     */
    function initializeProgressTracking() {
        // Update UI to show completed sections
        if (STATE.userProgress && STATE.userProgress.completed_sections && Array.isArray(STATE.userProgress.completed_sections)) {
            STATE.userProgress.completed_sections.forEach(function(sectionId) {
                markSectionCompleteUI(sectionId);
            });
            
            // Update certificate section if all sections are completed
            updateCertificateUI();
        }
        
        // Update progress indicators
        updateProgressIndicators();
    }

    /**
     * Initialize scrollspy functionality
     */
    function initializeScrollSpy() {
        // Get all sections and chapters
        const $sections = $('.cai-section');
        const $chapters = $('.cai-chapter');
        const $quiz = $('.cai-course-quiz');
        const $certificate = $('.cai-certificate-section');
        
        // Combined elements to track
        const $elements = $sections.add($chapters).add($quiz).add($certificate);
        
        // Set up scrollspy
        $(window).on('scroll', debounce(function() {
            // Current scroll position
            const scrollTop = $(window).scrollTop();
            const windowHeight = $(window).height();
            const scrollMiddle = scrollTop + (windowHeight / 3);
            
            // Find the element that's currently in view
            let currentElement = null;
            
            $elements.each(function() {
                const $element = $(this);
                const elementTop = $element.offset().top;
                const elementHeight = $element.outerHeight();
                
                // Check if this element is in the current viewport
                if (scrollMiddle >= elementTop && scrollMiddle < (elementTop + elementHeight)) {
                    currentElement = $element;
                    return false; // Break the loop
                }
            });
            
            if (currentElement) {
                // Clear all active classes
                $('.cai-section-item').removeClass('cai-section-active');
                
                // Handle different element types
                if (currentElement.hasClass('cai-section')) {
                    // It's a section - highlight in nav and expand chapter
                    const sectionId = currentElement.attr('id');
                    const $navItem = $('.cai-section-link[href="#' + sectionId + '"]').parent();
                    
                    $navItem.addClass('cai-section-active');
                    
                    // Expand parent chapter if not already expanded
                    const $chapter = $navItem.closest('.cai-course-chapter');
                    if (!$chapter.hasClass('cai-chapter-expanded')) {
                        $chapter.addClass('cai-chapter-expanded');
                    }
                    
                    STATE.activeSection = sectionId;
                } else if (currentElement.hasClass('cai-course-quiz')) {
                    // It's the quiz - highlight quiz nav
                    $('.cai-course-quiz-nav').addClass('cai-section-active');
                } else if (currentElement.hasClass('cai-certificate-section')) {
                    // It's the certificate section - highlight certificate nav
                    $('.cai-course-certificate-nav').addClass('cai-section-active');
                }
            }
        }, 100));
        
        // Trigger scroll event once to initialize
        $(window).trigger('scroll');
    }

    /**
     * Mark a section as complete
     */
    function markSectionComplete(sectionId) {
        // Check if user is logged in
        if (!STATE.courseId) {
            showMessagePopup(
                'You must be logged in to track your progress.',
                'Login Required',
                'warning'
            );
            return;
        }
        
        // Send AJAX request to mark section as complete
        $.ajax({
            url: caiCourse.ajax_url,
            type: 'POST',
            data: {
                action: 'cai_mark_section_complete',
                nonce: caiCourse.nonce,
                course_id: STATE.courseId,
                section_id: sectionId
            },
            success: function(response) {
                if (response.success) {
                    // Update UI
                    markSectionCompleteUI(sectionId);
                    
                    // Update STATE
                    STATE.userProgress = response.data;
                    
                    // Update certificate section if needed
                    updateCertificateUI();
                }
            },
            error: function() {
                console.error('Failed to mark section as complete');
            }
        });
    }
    
    /**
     * Mark a section as incomplete
     */
    function markSectionIncomplete(sectionId) {
        // Check if user is logged in
        if (!STATE.courseId) {
            showMessagePopup(
                'You must be logged in to track your progress.',
                'Login Required',
                'warning'
            );
            return;
        }
        
        // Send AJAX request to mark section as incomplete
        $.ajax({
            url: caiCourse.ajax_url,
            type: 'POST',
            data: {
                action: 'cai_mark_section_incomplete',
                nonce: caiCourse.nonce,
                course_id: STATE.courseId,
                section_id: sectionId
            },
            success: function(response) {
                if (response.success) {
                    // Update UI
                    markSectionIncompleteUI(sectionId);
                    
                    // Update STATE
                    STATE.userProgress = response.data;
                    
                    // Update certificate section if needed
                    updateCertificateUI();
                }
            },
            error: function() {
                console.error('Failed to mark section as incomplete');
            }
        });
    }

    /**
     * Update UI to show a section as completed
     */
    function markSectionCompleteUI(sectionId) {
        // Update navigation item
        const $navItem = $(`.cai-section-item[data-section-id="${sectionId}"]`);
        $navItem.addClass('cai-section-completed');
        
        // Update completion button
        const $completeBtn = $(`.cai-mark-complete[data-section-id="${sectionId}"]`);
        $completeBtn.addClass('cai-completed');
        $completeBtn.find('.cai-complete-text').text('Completed');
    }
    
    /**
     * Update UI to show a section as incomplete
     */
    function markSectionIncompleteUI(sectionId) {
        // Update navigation item
        const $navItem = $(`.cai-section-item[data-section-id="${sectionId}"]`);
        $navItem.removeClass('cai-section-completed');
        
        // Update completion button
        const $completeBtn = $(`.cai-mark-complete[data-section-id="${sectionId}"]`);
        $completeBtn.removeClass('cai-completed');
        $completeBtn.find('.cai-complete-text').text('Mark Complete');
    }

    /**
     * Update progress indicators (progress bar, percentage)
     */
    function updateProgressIndicators() {
        if (!STATE.courseId) {
            return;
        }
        
        // Get total sections safely
        let totalSections = 0;
        if (typeof caiCourse !== 'undefined' && caiCourse.total_sections) {
            totalSections = parseInt(caiCourse.total_sections, 10);
        }
        
        if (totalSections === 0) {
            return;
        }
        
        const completedCount = (STATE.userProgress && STATE.userProgress.completed_sections) ? STATE.userProgress.completed_sections.length : 0;
        const percentage = totalSections > 0 ? Math.round((completedCount / totalSections) * 100) : 0;
        
        // Update header progress with animation
        const $progressText = $('.cai-course-progress span').last();
        if ($progressText.length) {
            $progressText.text(percentage + '% Complete');
        }
        
        // Update all progress bars
        $('.cai-progress-bar-fill').each(function() {
            $(this).css('width', percentage + '%');
        });
        
        // Update certificate section progress if it exists
        if ($('.cai-certificate-progress').length) {
            $('.cai-certificate-progress p').text(`Your progress: ${percentage}%. Complete the course to earn your certificate.`);
            
            // Show certificate if 100% complete with celebration
            if (percentage === 100) {
                $('.cai-certificate-progress').fadeOut(400, function() {
                    $('.cai-certificate-ready').fadeIn(600).addClass('cai-celebration');
                    // Remove celebration class after animation
                    setTimeout(() => {
                        $('.cai-certificate-ready').removeClass('cai-celebration');
                    }, 2000);
                });
            }
        }
    }

    /**
     * Update certificate button state based on completion and quiz results
     */
    function updateCertificateButtonState() {
        const completedCount = (STATE.userProgress && STATE.userProgress.completed_sections) ? STATE.userProgress.completed_sections.length : 0;
        const totalSections = (typeof caiCourse !== 'undefined' && caiCourse.total_sections) ? parseInt(caiCourse.total_sections, 10) : 0;
        const courseComplete = totalSections > 0 && completedCount === totalSections;
        
        // Check if quiz exists by looking for quiz form on page
        const hasQuiz = $('#cai-quiz-form').length > 0;
        
        // Check if quiz exists and if user passed it
        let quizPassed = true; // Default to true if no quiz
        if (hasQuiz && STATE.quizResults) {
            quizPassed = STATE.quizResults.passed === true;
        } else if (hasQuiz && !STATE.quizResults) {
            quizPassed = false; // Quiz exists but not taken yet
        }
        
        // Enable certificate button only if course is complete AND (no quiz exists OR quiz is passed)
        const eligible = courseComplete && quizPassed;
        $('.cai-get-certificate').prop('disabled', !eligible);
        
        // Update subtitle message dynamically (only update if subtitle exists)
        if ($('.cai-certificate-subtitle').length > 0) {
            let subtitleMessage = '';
            if (hasQuiz && !quizPassed) {
                if (STATE.quizResults) {
                    // Quiz was taken but failed
                    subtitleMessage = '<i class="fas fa-redo-alt"></i> Almost there! You need to pass the quiz to earn your certificate. Give it another try!';
                } else {
                    // Quiz not taken yet
                    subtitleMessage = '<i class="fas fa-arrow-up"></i> One final step! Complete the quiz above to earn your certificate.';
                }
            } else if (eligible) {
                // Fully eligible
                subtitleMessage = '<i class="fas fa-trophy"></i> Amazing work! You\'re ready to claim your certificate of completion.';
            }
            
            // Only update if the message is different from what's currently shown
            if (subtitleMessage && $('.cai-certificate-subtitle').html() !== subtitleMessage) {
                $('.cai-certificate-subtitle').html(subtitleMessage);
            }
        }
        
        // Update button tooltip and disabled state
        if (!courseComplete) {
            $('.cai-get-certificate').attr('title', 'Complete the course first');
        } else if (!quizPassed) {
            if (STATE.quizResults) {
                $('.cai-get-certificate').attr('title', 'Pass the quiz to earn your certificate');
            } else {
                $('.cai-get-certificate').attr('title', 'Complete the quiz above first');
            }
        } else {
            $('.cai-get-certificate').removeAttr('title');
        }
    }

    /**
     * Update certificate UI based on progress
     */
    function updateCertificateUI() {
        if (!STATE.userProgress || STATE.updateInProgress) {
            return;
        }
        
        STATE.updateInProgress = true;
        
        const completedCount = (STATE.userProgress && STATE.userProgress.completed_sections) ? STATE.userProgress.completed_sections.length : 0;
        const totalSections = (typeof caiCourse !== 'undefined' && caiCourse.total_sections) ? parseInt(caiCourse.total_sections, 10) : 0;
        const percentage = totalSections > 0 ? Math.round((completedCount / totalSections) * 100) : 0;
        
        // Update progress indicators in real-time
        updateAllProgressIndicators(percentage);
        
        // Show certificate section if 100% complete
        if (percentage === 100) {
            $('.cai-certificate-progress').hide();
            $('.cai-certificate-ready').show();
            
            // Update certificate button state and messaging
            updateCertificateButtonState();
        } else {
            // Hide certificate ready section if not 100% complete
            $('.cai-certificate-ready').hide();
            $('.cai-certificate-progress').show();
            
            // Disable certificate button if course not complete
            $('.cai-get-certificate').prop('disabled', true);
        }
        
        STATE.updateInProgress = false;
    }

    /**
     * Update progress indicators throughout the page
     */
    function updateAllProgressIndicators(percentage) {
        // Guard against undefined percentage
        if (typeof percentage !== 'number' || isNaN(percentage)) {
            return;
        }
        
        // Update progress percentage text in header
        $('.cai-course-progress span').text(percentage + '% Complete');
        
        // Update progress bar fill in header
        $('.cai-course-header .cai-progress-bar-fill').css('width', percentage + '%');
        
        // Update progress percentage text in certificate section
        $('.cai-certificate-progress p').html(function(index, html) {
            // Replace existing percentage with new one
            return html.replace(/\d+%/, percentage + '%');
        });
        
        // Update progress bar fill in certificate section
        $('.cai-certificate-section .cai-progress-bar-fill').css('width', percentage + '%');
        
        // Animate progress bar with smooth transition
        const $progressBarFills = $('.cai-progress-bar-fill');
        if ($progressBarFills.length) { // Check if elements exist
            $progressBarFills.each(function() {
                $(this).animate({
                    width: percentage + '%'
                }, 600, 'swing');
            });
        }
    }

    /**
     * Show a styled message popup (replaces browser alerts)
     */
    function showMessagePopup(message, title = null, type = 'info') {
        // Remove existing popup if any
        $('#cai-message-popup-overlay').remove();
        
        // Determine button classes based on theme styling setting
        const useThemeStyling = (typeof caiCourse !== 'undefined' && caiCourse.theme_styling_active);
        const buttonClasses = useThemeStyling 
            ? 'wp-element-button button-primary' 
            : '';
        
        // Set default title and icon based on type
        let popupTitle = title;
        let iconClass = '';
        
        if (!popupTitle) {
            switch(type) {
                case 'error':
                    popupTitle = 'Error';
                    iconClass = '<i class="fas fa-exclamation-triangle" style="color: #dc3545; margin-right: 0.5rem;"></i>';
                    break;
                case 'success':
                    popupTitle = 'Success';
                    iconClass = '<i class="fas fa-check-circle" style="color: #28a745; margin-right: 0.5rem;"></i>';
                    break;
                case 'warning':
                    popupTitle = 'Notice';
                    iconClass = '<i class="fas fa-exclamation-circle" style="color: #ffc107; margin-right: 0.5rem;"></i>';
                    break;
                default:
                    popupTitle = 'Information';
                    iconClass = '<i class="fas fa-info-circle" style="color: #17a2b8; margin-right: 0.5rem;"></i>';
            }
        }
        
        // Create popup HTML
        const popupHtml = `
            <div id="cai-message-popup-overlay">
                <div id="cai-message-popup">
                    <h2>${iconClass}${popupTitle}</h2>
                    <p>${message}</p>
                    <button id="cai-message-ok" class="${buttonClasses}">OK</button>
                </div>
            </div>
        `;

        // Add popup to the body
        $('body').append(popupHtml);

        // Add event listener for OK button
        $('#cai-message-ok').on('click', function() {
            $('#cai-message-popup-overlay').remove();
        });
        
        // Add event listener for overlay click (close popup)
        $('#cai-message-popup-overlay').on('click', function(e) {
            if (e.target === this) {
                $('#cai-message-popup-overlay').remove();
            }
        });
        
        // Add escape key listener
        $(document).on('keydown.messagePopup', function(e) {
            if (e.keyCode === 27) { // Escape key
                $('#cai-message-popup-overlay').remove();
                $(document).off('keydown.messagePopup');
            }
        });
        
        // Focus the OK button for accessibility
        setTimeout(function() {
            $('#cai-message-ok').focus();
        }, 100);
    }

    /**
     * Create certificate ready section dynamically
     */
    function createCertificateReadySection() {
        // Determine button classes based on theme styling setting
        const useThemeStyling = (typeof caiCourse !== 'undefined' && caiCourse.theme_styling_active);
        const buttonClasses = useThemeStyling 
            ? 'cai-view-certificate-btn wp-element-button button btn button-primary' 
            : 'cai-view-certificate-btn';
        
        const certificateReadyHtml = `
            <div class="cai-certificate-ready" style="display: none;">
                <p class="cai-certificate-title">${caiCourse.i18n.course_completed || 'Congratulations! You have completed the course.'}</p>
                <p class="cai-certificate-subtitle" style="color: #666; font-size: 0.9em; margin: 0.5rem 0 1.5rem 0;"></p>
                <button type="button" 
                        class="${buttonClasses} cai-get-certificate"
                        data-action="view-certificate">
                    <i class="fas fa-certificate"></i>
                    ${caiCourse.i18n.get_certificate || 'Get Certificate'}
                </button>
            </div>
        `;
        
        // Insert after the certificate progress section
        $('.cai-certificate-progress').after(certificateReadyHtml);
    }

    /**
     * Submit quiz answers
     */
    function submitQuiz() {
        // Check if quiz already submitted
        if (STATE.quizSubmitted) {
            return;
        }
        
        // Collect answers
        const answers = {};
        let allAnswered = true;
        
        $('.cai-quiz-question').each(function() {
            const questionId = 'q' + $(this).data('question-index');
            const $selected = $(this).find('input[type="radio"]:checked');
            
            if ($selected.length) {
                answers[questionId] = $selected.val();
            } else {
                allAnswered = false;
            }
        });
        
        // Validate that all questions are answered
        if (!allAnswered) {
            showMessagePopup(
                'Please answer all questions before submitting.',
                'Incomplete Quiz',
                'warning'
            );
            return;
        }
        
        // Disable submit button and show loading
        $('.cai-quiz-submit').prop('disabled', true).text('Processing...');
        
        // Send AJAX request to grade quiz
        $.ajax({
            url: caiCourse.ajax_url,
            type: 'POST',
            data: {
                action: 'cai_submit_quiz',
                nonce: caiCourse.nonce,
                course_id: STATE.courseId,
                answers: answers
            },
            success: function(response) {
                // Re-enable submit button
                $('.cai-quiz-submit').prop('disabled', false).text(caiCourse.i18n.submit_quiz);
                
                if (response.success) {
                    // Store results
                    STATE.quizResults = response.data;
                    STATE.quizSubmitted = true;
                    
                    // Show results
                    displayQuizResults(response.data);
                } else {
                    showMessagePopup(
                        'Failed to submit quiz. Please try again.',
                        'Submission Failed',
                        'error'
                    );
                }
            },
            error: function() {
                // Re-enable submit button
                $('.cai-quiz-submit').prop('disabled', false).text(caiCourse.i18n.submit_quiz);
                showMessagePopup(
                    'Error submitting quiz. Please try again.',
                    'Connection Error',
                    'error'
                );
            }
        });
    }

    /**
     * Display quiz results
     */
    function displayQuizResults(results) {
        // Update score
        $('.cai-score-value').text(results.score + '%');
        
        // Update message
        $('.cai-results-message').text(results.message);
        
        // Show question-specific feedback
        $.each(results.results, function(questionId, isCorrect) {
            const questionIndex = questionId.replace('q', '');
            const $question = $(`.cai-quiz-question[data-question-index="${questionIndex}"]`);
            const $feedback = $question.find('.cai-question-feedback');
            
            $feedback.show();
            
            if (isCorrect) {
                $feedback.find('.cai-feedback-correct').show();
                $feedback.find('.cai-feedback-incorrect').hide();
            } else {
                const correctAnswer = results.correct_answers[questionId];
                const $correctOption = $question.find(`input[value="${correctAnswer}"]`).next('label');
                let correctText = '';
                
                // Get correct answer text
                if ($correctOption.length) {
                    correctText = $correctOption.text().trim();
                    
                    if (correctText.length > 30) {
                        correctText = correctText.substring(0, 30) + '...';
                    }
                }
                
                $feedback.find('.cai-feedback-correct').hide();
                $feedback.find('.cai-feedback-incorrect').show();
                $feedback.find('.cai-correct-answer').text('Correct answer: ' + correctText);
            }
        });
        
        // Update certificate button state based on both completion and quiz results
        updateCertificateButtonState();
        
        // Show results section
        $('.cai-quiz-results').slideDown(400);
        
        // Hide submit button
        $('.cai-quiz-actions').hide();
        
        // Scroll to results
        $('html, body').animate({
            scrollTop: $('.cai-quiz-results').offset().top - 50
        }, 600);
    }

    /**
     * Reset quiz to try again
     */
    function resetQuiz() {
        // Reset state
        STATE.quizSubmitted = false;
        STATE.quizResults = null;
        
        // Reset form
        $('#cai-quiz-form')[0].reset();
        
        // Update certificate button state
        updateCertificateButtonState();
        
        // Hide feedback
        $('.cai-question-feedback').hide();
        
        // Hide results
        $('.cai-quiz-results').hide();
        
        // Show submit button
        $('.cai-quiz-actions').show();
        
        // Scroll to top of quiz
        $('html, body').animate({
            scrollTop: $('.cai-course-quiz').offset().top - 50
        }, 600);
    }

    /**
     * Check certificate eligibility before generating
     */
    function checkCertificateEligibility() {
        // If no courseId, redirect to login
        if (!STATE.courseId) {
            window.location.href = window.location.href + '&certificate=view';
            return;
        }
        
        // Send AJAX request to check certificate eligibility
        $.ajax({
            url: caiCourse.ajax_url,
            type: 'POST',
            data: {
                action: 'cai_check_certificate_eligibility',
                nonce: caiCourse.nonce,
                course_id: STATE.courseId,
                post_id: STATE.postId
            },
            success: function(response) {
                if (response.success) {
                    // User is eligible, show name popup
                    generateCertificate();
                } else {
                    showMessagePopup(
                        'You need to complete the course to earn a certificate.',
                        'Course Not Complete',
                        'warning'
                    );
                }
            },
            error: function() {
                showMessagePopup(
                    'Error checking certificate eligibility. Please try again.',
                    'Connection Error',
                    'error'
                );
            }
        });
    }

    /**
     * Ask for the user's name in a popup
     */
    function generateCertificate() {
        // Determine button classes based on theme styling setting
        const useThemeStyling = (typeof caiCourse !== 'undefined' && caiCourse.theme_styling_active);
        const submitButtonClasses = useThemeStyling 
            ? 'wp-element-button button-primary' 
            : '';
        const cancelButtonClasses = useThemeStyling 
            ? 'wp-element-button button-secondary' 
            : '';
        
        // Create popup HTML with appropriate classes
        const popupHtml = `
            <div id="cai-name-popup-overlay">
                <div id="cai-name-popup">
                    <h2>Enter Your Name</h2>
                    <p>Please enter your full name to be displayed on the certificate.</p>
                    <input type="text" id="cai-student-name" placeholder="e.g., John Doe" />
                    <div class="cai-popup-buttons">
                        <button id="cai-cancel-name" class="${cancelButtonClasses}">Cancel</button>
                        <button id="cai-submit-name" class="${submitButtonClasses}">Generate Certificate</button>
                    </div>
                </div>
            </div>
        `;

        // Add popup to the body
        $('body').append(popupHtml);

        // Add event listeners
        $('#cai-submit-name').on('click', function() {
            const studentName = $('#cai-student-name').val();
            if (studentName.trim() === '') {
                showMessagePopup(
                    'Please enter your full name to continue.',
                    'Name Required',
                    'warning'
                );
                return;
            }
            // Close popup
            $('#cai-name-popup-overlay').remove();
            // Proceed with PDF generation
            generateCertificatePdf(studentName);
        });

        $('#cai-cancel-name').on('click', function() {
            $('#cai-name-popup-overlay').remove();
        });
    }


    /**
     * Generate certificate
     */
    function generateCertificatePdf(studentName) {
        // Check if already generating
        if (STATE.certificateGenerating) {
            return;
        }
        
        STATE.certificateGenerating = true;
        
        // Update button
        const $btn = $('.cai-get-certificate');
        $btn.prop('disabled', true).text(caiCourse.i18n.generating_certificate);
        
        // Show loading overlay
        showLoadingOverlay();
        
        // Create form for PDF download
        const form = $('<form>', {
            method: 'POST',
            action: caiCourse.ajax_url,
            style: 'display: none;'
        });
        
        // Add form fields
        form.append($('<input>', { type: 'hidden', name: 'action', value: 'cai_generate_certificate' }));
        form.append($('<input>', { type: 'hidden', name: 'nonce', value: caiCourse.nonce }));
        form.append($('<input>', { type: 'hidden', name: 'course_id', value: STATE.courseId }));
        form.append($('<input>', { type: 'hidden', name: 'post_id', value: STATE.postId }));
        form.append($('<input>', { type: 'hidden', name: 'student_name', value: studentName }));
        
        // Submit form to trigger download
        $('body').append(form);
        form.submit();
        
        // Clean up after a delay
        setTimeout(function() {
            form.remove();
            STATE.certificateGenerating = false;
            hideLoadingOverlay();
            $btn.prop('disabled', false).text(caiCourse.i18n.download_certificate);
        }, 2000);
    }
    
    /**
     * Show loading overlay
     */
    function showLoadingOverlay() {
        const overlay = $(`
            <div id="cai-certificate-loading-overlay" style="
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.8);
                color: white;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-direction: column;
                z-index: 999999;
                font-family: inherit;
            ">
                <div style="text-align: center;">
                    <h2 style="margin-bottom: 15px; color: white;">Generating Certificate...</h2>
                    <p style="margin-bottom: 20px; color: #ccc;">Please wait while your certificate is being prepared.</p>
                    <div style="font-size: 48px; margin: 20px 0; animation: pulse 1.5s infinite;">📜</div>
                    <div style="width: 200px; height: 4px; background: rgba(255,255,255,0.2); border-radius: 2px; overflow: hidden;">
                        <div style="width: 100%; height: 100%; background: #4CAF50; border-radius: 2px; animation: loading 2s infinite;"></div>
                    </div>
                </div>
            </div>
            <style>
                @keyframes pulse {
                    0%, 100% { opacity: 0.6; transform: scale(1); }
                    50% { opacity: 1; transform: scale(1.1); }
                }
                @keyframes loading {
                    0% { transform: translateX(-100%); }
                    100% { transform: translateX(100%); }
                }
            </style>
        `);
        $('body').append(overlay);
    }
    
    /**
     * Hide loading overlay
     */
    function hideLoadingOverlay() {
        $('#cai-certificate-loading-overlay').remove();
    }
    

    /**
     * Publish course button handler for admin
     */
    function initPublishButton() {
        // Remove any existing handlers to prevent duplicates
        $(document).off('click', '.course-action-publish');
        
        // Add the click handler
        $(document).on('click', '.course-action-publish', function(e) {
            e.preventDefault();
            
            // Don't do anything if button is disabled
            if ($(this).prop('disabled')) {
                return;
            }
            
            // Get the course ID from the button's data attribute
            const courseId = $(this).data('course-id');
            
            if (!courseId) {
                showMessagePopup(
                    'No course ID found. Please try again.',
                    'Missing Course Data',
                    'error'
                );
                return;
            }
            
            console.log('Publishing course with ID:', courseId);
            
            // Update button appearance to show it's working
            $(this).prop('disabled', true).html(
                '<span class="spinner is-active" style="margin-right: 5px;"></span>' +
                '<span>Publishing...</span>'
            );
            
            // Send AJAX request to publish course
            $.ajax({
                url: caiAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'cai_publish_course',
                    nonce: caiAjax.nonce,
                    course_id: courseId
                },
                success: function(response) {
                    console.log('Publish response:', response);
                    
                    // Reset button
                    $('.course-action-publish').prop('disabled', false).html(
                        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">' +
                        '<path d="M5 4v2h14V4H5zm0 10h4v6h6v-6h4l-7-7-7 7z"/>' +
                        '</svg>' +
                        '<span>Publish</span>'
                    );
                    
                    if (response.success) {
                        
                        // Add "View" button if not already present
                        const $publishButton = $(`.course-action-publish[data-course-id="${courseId}"]`);
                        const $viewButton = $publishButton.siblings('.course-action-view');
                        
                        if ($viewButton.length === 0) {
                            $publishButton.after(
                                '<a href="' + response.data.permalink + '" ' +
                                'class="course-action-view button" ' +
                                'target="_blank" ' +
                                'title="View Published Course">' +
                                '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">' +
                                '<path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>' +
                                '</svg>' +
                                '<span>View</span>' +
                                '</a>'
                            );
                        } else {
                            // Update existing view button href
                            $viewButton.attr('href', response.data.permalink);
                        }
                    } else {
                        showMessagePopup(
                            'Error publishing course: ' + response.data,
                            'Publishing Failed',
                            'error'
                        );
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', status, error);
                    
                    // Reset button
                    $('.course-action-publish').prop('disabled', false).html(
                        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">' +
                        '<path d="M5 4v2h14V4H5zm0 10h4v6h6v-6h4l-7-7-7 7z"/>' +
                        '</svg>' +
                        '<span>Publish</span>'
                    );
                    
                    showMessagePopup(
                        'Error publishing course. Please try again.',
                        'Connection Error',
                        'error'
                    );
                }
            });
        });
    }

    /**
     * Initialize courses listing page
     */
    function initCoursesPage() {
        // Check if caiCourse is available
        if (typeof caiCourse === 'undefined' || !caiCourse.ajax_url) {
            console.warn('caiCourse not available for courses page');
            return;
        }
        
        // Get published courses
        $.ajax({
            url: caiCourse.ajax_url,
            type: 'POST',
            data: {
                action: 'cai_get_published_courses',
                nonce: caiCourse.nonce
            },
            success: function(response) {
                if (response.success && response.data.courses) {
                    renderCoursesList(response.data.courses);
                }
            }
        });
    }

    /**
     * Render courses list on courses page
     */
    function renderCoursesList(courses) {
        const $container = $('.cai-courses-container');
        
        if (!$container.length) {
            return;
        }
        
        // Guard against undefined courses or non-array
        if (!Array.isArray(courses)) {
            courses = [];
        }
        
        let html = '<div class="cai-courses-list">';
        
        if (courses.length === 0) {
            html += '<p class="cai-no-courses">No courses available yet.</p>';
        } else {
            courses.forEach(function(course) {
                html += `
                <div class="cai-course-card">
                    <div class="cai-course-card-image">
                        <img src="${course.image}" alt="${course.title}">
                    </div>
                    <div class="cai-course-card-content">
                        <h3 class="cai-course-card-title">${course.title}</h3>
                        <div class="cai-course-card-meta">
                            <span class="cai-course-card-difficulty">
                                <i class="fas fa-signal cai-difficulty-icon"></i>
                                <span>${course.difficulty}</span>
                            </span>
                            <span class="cai-course-card-time">
                                <i class="far fa-clock cai-time-icon"></i>
                                <span>${course.time}</span>
                            </span>
                        </div>
                        <div class="cai-course-card-description">
                            ${course.excerpt}
                        </div>
                        <a href="${course.link}" class="cai-course-card-link">
                            Start Course
                        </a>
                    </div>
                </div>`;
            });
        }
        
        html += '</div>';
        
        $container.html(html);
    }

    /**
     * Utility function - Debounce
     */
    function debounce(func, wait) {
        let timeout;
        return function() {
            const context = this, args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(function() {
                func.apply(context, args);
            }, wait);
        };
    }

    /**
     * Initialize sidebar functionality
     */
    function initializeSidebar() {
        const $sidebar = $('.cai-course-sidebar');
        const $toggle = $('.cai-sticky-toggle');
        
        // Set initial state
        if (STATE.sidebarSticky) {
            $sidebar.addClass('cai-sidebar-sticky');
            $toggle.addClass('active');
        } else {
            $sidebar.addClass('cai-sidebar-static');
        }
        
        // Get stored preference
        const stored = localStorage.getItem('cai-sidebar-sticky');
        if (stored !== null) {
            STATE.sidebarSticky = stored === 'true';
            updateSidebarState();
        }
    }
    
    /**
     * Toggle sidebar sticky state
     */
    function toggleSidebarSticky() {
        STATE.sidebarSticky = !STATE.sidebarSticky;
        updateSidebarState();
        
        // Store preference
        localStorage.setItem('cai-sidebar-sticky', STATE.sidebarSticky.toString());
    }
    
    /**
     * Update sidebar state based on sticky preference
     */
    function updateSidebarState() {
        const $sidebar = $('.cai-course-sidebar');
        const $toggle = $('.cai-sticky-toggle');
        
        if (STATE.sidebarSticky) {
            $sidebar.removeClass('cai-sidebar-static').addClass('cai-sidebar-sticky');
            $toggle.addClass('active');
        } else {
            $sidebar.removeClass('cai-sidebar-sticky').addClass('cai-sidebar-static');
            $toggle.removeClass('active');
        }
    }
    
    // Add CSS for enhanced animations
    function addEnhancedStyles() {
        if (!$('#cai-enhanced-styles').length) {
            $('<style id="cai-enhanced-styles">').text(`
                .cai-section-highlight {
                    animation: highlightPulse 2s ease-in-out;
                }
                @keyframes highlightPulse {
                    0%, 100% { background-color: transparent; }
                    50% { background-color: var(--cai-primary-subtle); }
                }
                .cai-celebration {
                    animation: celebrationBounce 1s ease-in-out;
                }
                @keyframes celebrationBounce {
                    0%, 100% { transform: scale(1); }
                    50% { transform: scale(1.05); }
                }
                @media (prefers-reduced-motion: reduce) {
                    .cai-section-highlight,
                    .cai-celebration {
                        animation: none;
                    }
                    .cai-progress-bar-fill {
                        transition: none;
                    }
                }
            `).appendTo('head');
        }
    }

    /**
     * Initialize scroll progress bar
     */
    function initializeScrollProgressBar() {
        const $progressBar = $('.cai-scroll-progress-bar');
        const $progressFill = $('.cai-scroll-progress-fill');
        
        if (!$progressBar.length) {
            return;
        }
        
        // Get the course content area (excluding footer and other non-content elements)
        const $courseContent = $('.cai-course-content-wrapper');
        
        if (!$courseContent.length) {
            return;
        }
        
        // Calculate progress based on course content height
        function updateScrollProgress() {
            const windowHeight = $(window).height();
            const documentHeight = $(document).height();
            const scrollTop = $(window).scrollTop();
            
            // Get course content boundaries
            const contentTop = $courseContent.offset().top;
            const contentHeight = $courseContent.outerHeight();
            const contentBottom = contentTop + contentHeight;
            
            // Calculate progress percentage based on course content only
            let progress = 0;
            
            if (scrollTop > contentTop) {
                const scrolledThroughContent = Math.min(scrollTop + windowHeight - contentTop, contentHeight);
                progress = Math.min((scrolledThroughContent / contentHeight) * 100, 100);
            }
            
            // Update progress bar width
            $progressFill.css('width', progress + '%');
            
            // Hide progress bar when at the very top (before content starts)
            if (scrollTop < contentTop - 100) {
                $progressBar.addClass('cai-hidden');
            } else {
                $progressBar.removeClass('cai-hidden');
            }
        }
        
        // Set up scroll event listener with debouncing
        $(window).on('scroll.progressBar', debounce(updateScrollProgress, 16));
        
        // Initial call
        updateScrollProgress();
    }

    // Initialize the components once document is ready
    $(document).ready(function() {
        try {
            // Add enhanced styles
            addEnhancedStyles();
            
            // Always initialize the publish button on both admin and frontend
            initPublishButton();
            
            // Detect page type and initialize appropriate functionality
            if ($('.cai-course-container').length) {
                // Course page
                initCourse();
                // Initialize scroll progress bar
                initializeScrollProgressBar();
            } else if ($('.cai-courses-container').length) {
                // Courses listing page
                initCoursesPage();
            }
        } catch (error) {
            console.error('Error initializing course publisher:', error);
        }
    });
    
    // Clean up event listeners on page unload
    $(window).on('beforeunload', function() {
        $(window).off('resize.courseNav scroll.scrollSpy scroll.progressBar');
    });

})(jQuery);