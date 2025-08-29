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
        certificateGenerating: false
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
        }

        // Set up event handlers
        setupEventHandlers();
        
        // Initialize navigation
        initializeNavigation();
        
        // Set up progress tracking if user is logged in
        if (typeof caiCourse !== 'undefined' && caiCourse.user_progress) {
            initializeProgressTracking();
        }
        
        // Initialize scrollspy
        initializeScrollSpy();
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
        
        // Mark section as complete
        $('.cai-mark-complete').on('click', function() {
            if (!$(this).hasClass('cai-completed')) {
                markSectionComplete($(this).data('section-id'));
            }
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
        $(document).on('click', '.cai-get-certificate', function() {
            if (!$(this).prop('disabled')) {
                generateCertificate();
            }
        });
        
        // View certificate
        $(document).on('click', '[data-action="view-certificate"]', function(e) {
            e.preventDefault();
            // First check if user has completed course
            checkCertificateEligibility();
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
        
        // Smooth scroll navigation
        $('.cai-section-link, .cai-quiz-link, .cai-certificate-link').on('click', function(e) {
            // Only handle normal navigation, not certificate generation
            if ($(this).attr('data-action') !== 'view-certificate') {
                e.preventDefault();
                const target = $(this).attr('href');
                
                // Smooth scroll to target
                $('html, body').animate({
                    scrollTop: $(target).offset().top - 30
                }, 600);
                
                // On mobile, collapse the navigation
                if ($(window).width() < 992) {
                    $('.cai-course-nav-title').addClass('collapsed');
                    $('.cai-course-chapters').slideUp(300);
                }
            }
        });
        
        // Mobile navigation toggle
        if ($(window).width() < 992) {
            $('.cai-course-chapters').hide();
            $('.cai-course-nav-title').addClass('collapsed').on('click', function() {
                $(this).toggleClass('expanded').toggleClass('collapsed');
                $('.cai-course-chapters').slideToggle(300);
            });
        }
    }

    /**
     * Initialize progress tracking
     */
    function initializeProgressTracking() {
        // Update UI to show completed sections
        if (STATE.userProgress && STATE.userProgress.completed_sections) {
            STATE.userProgress.completed_sections.forEach(function(sectionId) {
                markSectionCompleteUI(sectionId);
            });
            
            // Update certificate section if all sections are completed
            updateCertificateUI();
        }
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
            alert('You must be logged in to track your progress.');
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
     * Update UI to show a section as completed
     */
    function markSectionCompleteUI(sectionId) {
        // Update navigation item
        const $navItem = $(`.cai-section-item[data-section-id="${sectionId}"]`);
        $navItem.addClass('cai-section-completed');
        
        // Update completion button
        const $completeBtn = $(`.cai-mark-complete[data-section-id="${sectionId}"]`);
        $completeBtn.addClass('cai-completed');
        $completeBtn.find('.cai-complete-text').text(caiCourse.i18n.marked_complete);
        $completeBtn.prop('disabled', true);
        
        // Update progress indicators
        updateProgressIndicators();
    }

    /**
     * Update progress indicators (progress bar, percentage)
     */
    function updateProgressIndicators() {
        if (!STATE.courseId || !caiCourse.total_sections) {
            return;
        }
        
        const completedCount = STATE.userProgress.completed_sections ? STATE.userProgress.completed_sections.length : 0;
        const totalSections = parseInt(caiCourse.total_sections, 10);
        const percentage = totalSections > 0 ? Math.round((completedCount / totalSections) * 100) : 0;
        
        // Update header progress
        $('.cai-course-progress-text').text(percentage + '% ' + 'Complete');
        $('.cai-progress-bar-fill').css('width', percentage + '%');
        
        // Update certificate section progress if it exists
        if ($('.cai-certificate-progress').length) {
            $('.cai-certificate-progress p').text(`Your progress: ${percentage}%. Complete the course to earn your certificate.`);
            $('.cai-certificate-progress .cai-progress-bar-fill').css('width', percentage + '%');
            
            // Show certificate if 100% complete
            if (percentage === 100) {
                $('.cai-certificate-progress').hide();
                $('.cai-certificate-ready').show();
            }
        }
    }

    /**
     * Update certificate UI based on progress
     */
    function updateCertificateUI() {
        if (!STATE.userProgress) {
            return;
        }
        
        const completedCount = STATE.userProgress.completed_sections ? STATE.userProgress.completed_sections.length : 0;
        const totalSections = parseInt(caiCourse.total_sections, 10);
        const percentage = totalSections > 0 ? Math.round((completedCount / totalSections) * 100) : 0;
        
        // Show certificate section if 100% complete
        if (percentage === 100) {
            $('.cai-certificate-progress').hide();
            $('.cai-certificate-ready').show();
            
            // Enable "Get Certificate" button in quiz results if it exists
            $('.cai-get-certificate').prop('disabled', false);
        }
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
            alert('Please answer all questions before submitting.');
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
                    alert('Failed to submit quiz. Please try again.');
                }
            },
            error: function() {
                // Re-enable submit button
                $('.cai-quiz-submit').prop('disabled', false).text(caiCourse.i18n.submit_quiz);
                alert('Error submitting quiz. Please try again.');
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
        
        // Enable/disable certificate button
        $('.cai-get-certificate').prop('disabled', !results.passed);
        
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
     * Check certificate eligibility
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
                action: 'cai_generate_certificate',
                nonce: caiCourse.nonce,
                course_id: STATE.courseId,
                post_id: STATE.postId
            },
            success: function(response) {
                if (response.success) {
                    // Redirect to certificate view
                    window.location.href = response.data.certificate_url;
                } else {
                    alert('You need to complete the course to earn a certificate.');
                }
            },
            error: function() {
                alert('Error checking certificate eligibility. Please try again.');
            }
        });
    }

    /**
     * Generate certificate
     */
    function generateCertificate() {
        // Check if already generating
        if (STATE.certificateGenerating) {
            return;
        }
        
        STATE.certificateGenerating = true;
        
        // Update button
        const $btn = $('.cai-get-certificate');
        $btn.prop('disabled', true).text(caiCourse.i18n.generating_certificate);
        
        // Check certificate eligibility
        $.ajax({
            url: caiCourse.ajax_url,
            type: 'POST',
            data: {
                action: 'cai_generate_certificate',
                nonce: caiCourse.nonce,
                course_id: STATE.courseId,
                post_id: STATE.postId
            },
            success: function(response) {
                STATE.certificateGenerating = false;
                
                if (response.success) {
                    // Redirect to certificate view
                    window.location.href = response.data.certificate_url;
                } else {
                    $btn.prop('disabled', false).text(caiCourse.i18n.download_certificate);
                    alert('You need to complete the course to earn a certificate.');
                }
            },
            error: function() {
                STATE.certificateGenerating = false;
                $btn.prop('disabled', false).text(caiCourse.i18n.download_certificate);
                alert('Error generating certificate. Please try again.');
            }
        });
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
                alert('No course ID found. Please try again.');
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
                        alert('Error publishing course: ' + response.data);
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
                    
                    alert('Error publishing course. Please try again.');
                }
            });
        });
    }

    /**
     * Initialize courses listing page
     */
    function initCoursesPage() {
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
                                <span class="cai-difficulty-icon">📊</span>
                                ${course.difficulty}
                            </span>
                            <span class="cai-course-card-time">
                                <span class="cai-time-icon">⏱</span>
                                ${course.time}
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

    // Initialize the components once document is ready
    $(document).ready(function() {
        // Always initialize the publish button on both admin and frontend
        initPublishButton();
        
        // Detect page type and initialize appropriate functionality
        if ($('.cai-course-container').length) {
            // Course page
            initCourse();
        } else if ($('.cai-courses-container').length) {
            // Courses listing page
            initCoursesPage();
        }
    });

})(jQuery);
