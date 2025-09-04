(function($) {

    // Configuration
    const CONFIG = {
        ajaxTimeout: 60000, // 60 seconds
        progressPollInterval: 5000, // 5 seconds (increased to reduce server load)
        maxProgressPolls: 60 // 5 minutes max (60 * 5 seconds) - reduced to prevent hosting blocks
    };

    // State management
    const STATE = {
        courseData: {},
        uploadedImages: [],
        currentCourseId: null,
        isEditMode: false,
        progressPolling: null,
        progressPollCount: 0,
        mediaFrame: null,
        imagesMediaFrame: null,
        imageUrlCache: {},
        isChunkProcessing: false // NEW: Add a lock to prevent overlapping AJAX calls
    };

    // Initialize the course creator
    function initCourseCreator() {
        // Reset state
        STATE.isEditMode = false;
        STATE.currentCourseId = null;
        STATE.uploadedImages = [];
        STATE.courseData = {};
        
        // Reset form
        resetForm();
        
        // Fetch existing courses
        fetchCourses();

        // Set up event handlers
        setupEventHandlers();
    }

    // Set up event handlers for the UI
    function setupEventHandlers() {
        // First, unbind any existing main event handlers
        $('#cc-course-form').off('.main');
        $('.cc-new-course-btn').off('.main');
        $('.cc-clear-form-btn').off('.main');
        $('#cc-upload-thumbnail-btn').off('.main');
        $('#cc-upload-images-btn').off('.main');
        $('.cc-refresh-courses-btn').off('.main');
        $(document).off('.main');
        
        // Course form submission
        $('#cc-course-form').on('submit.main', function(e) {
            e.preventDefault();
            if (validateForm()) {
                createOrUpdateCourse();
            }
        });

        // New course button
        $('.cc-new-course-btn').on('click.main', function() {
            resetForm();
        });

        // Clear form button
        $('.cc-clear-form-btn').on('click.main', function() {
            if (confirm('This will clear all form fields. Continue?')) {
                resetForm();
            }
        });

        // Thumbnail upload button
        $('#cc-upload-thumbnail-btn').on('click.main', function() {
            openThumbnailUploader();
        });

        // Images upload button
        $('#cc-upload-images-btn').on('click.main', function() {
            openImagesUploader();
        });

        // Refresh courses button
        $('.cc-refresh-courses-btn').on('click.main', function() {
            fetchCourses();
        });

        // Course actions (view, edit, delete)
        $(document).on('click.main', '.course-action-view', function() {
            const courseId = $(this).data('course-id');
            viewCourse(courseId);
            closeModals();
        });

        $(document).on('click.main', '.course-action-unpublish', function() {
            const courseId = $(this).data('course-id');
            confirmUnpublishCourse(courseId);
        });
        
        $(document).on('click.main', '.course-action-edit', function() {
            const courseId = $(this).data('course-id');
            loadCourseForEdit(courseId);
        });

        $(document).on('click.main', '.course-action-delete', function() {
            const courseId = $(this).data('course-id');
            const courseTitle = $(this).data('course-title');
            confirmDeleteCourse(courseId, courseTitle);
        });

        // Edit course from view modal
        $('.cc-edit-course-btn').on('click.main', function() {
            const courseId = STATE.currentCourseId;
            if (courseId) {
                closeModals();
                loadCourseForEdit(courseId);
            }
        });

        // Save edits button
        $('.cc-save-edits-btn').on('click.main', function() {
            saveEdits();
            closeModals();
        });


        // Confirm delete button
        $('.cc-confirm-delete-btn').on('click.main', function() {
            deleteCourse(STATE.currentCourseId);
        });

        // Modal close buttons
        $('.cc-modal-close, .cc-modal-close-btn').on('click.main', function() {
            closeModals();
        });
        
        // Handle image removal in editor
        $(document).on('click.main', '.cc-remove-image', function() {
            const imageIndex = $(this).data('index');
            removeUploadedImage(imageIndex);
        });
        
        // Add all the chapter and section event handlers with .main namespace
        $(document).on('click.main', '.cc-delete-chapter-btn', function() {
            const chapterId = $(this).data('chapter-id');
            deleteChapter(chapterId);
        });
        
        $(document).on('click.main', '.cc-delete-section-btn', function() {
            const chapterId = $(this).data('chapter-id');
            const sectionId = $(this).data('section-id');
            deleteSection(chapterId, sectionId);
        });
        
        $(document).on('click.main', '.cc-move-chapter-up', function() {
            const chapterId = $(this).data('chapter-id');
            moveChapter(chapterId, 'up');
        });
        
        $(document).on('click.main', '.cc-move-chapter-down', function() {
            const chapterId = $(this).data('chapter-id');
            moveChapter(chapterId, 'down');
        });
        
        $(document).on('click.main', '.cc-move-section-up', function() {
            const chapterId = $(this).data('chapter-id');
            const sectionId = $(this).data('section-id');
            moveSection(chapterId, sectionId, 'up');
        });
        
        $(document).on('click.main', '.cc-move-section-down', function() {
            const chapterId = $(this).data('chapter-id');
            const sectionId = $(this).data('section-id');
            moveSection(chapterId, sectionId, 'down');
        });
        
        // Handle associating images with sections
        $(document).on('change.main', '.cc-section-image-selector', function() {
            const chapterId = $(this).data('chapter-id');
            const sectionId = $(this).data('section-id');
            const imageFilename = $(this).val();
            updateSectionImage(chapterId, sectionId, imageFilename);
        });

    }

    // Validate form before submission
    function validateForm() {
        const requiredFields = [
            'cc-course-title',
            'cc-course-description',
            'cc-target-audience',
            'cc-learning-objectives',
            'cc-main-topics'
        ];
        
        let isValid = true;
        
        // Check each required field
        requiredFields.forEach(fieldId => {
            const $field = $(`#${fieldId}`);
            const value = $field.val().trim();
            
            if (!value) {
                isValid = false;
                $field.addClass('cc-field-error');
                
                // Add error message if it doesn't exist
                if ($field.next('.cc-error-message').length === 0) {
                    $field.after(`<p class="cc-error-message">This field is required.</p>`);
                }
            } else {
                $field.removeClass('cc-field-error');
                $field.next('.cc-error-message').remove();
            }
        });
        
        // At least one image should be uploaded (thumbnail or content image)
        if ($('#cc-thumbnail-id').val() === '' && STATE.uploadedImages.length === 0) {
            isValid = false;
            alert('Please upload at least one image (thumbnail or content image).');
        }
        
        return isValid;
    }

    function initializeEditors() {
        // Find all textarea elements that need to be converted to WYSIWYG
        $('.cc-wp-editor-container textarea').each(function() {
            const editorId = $(this).attr('id');
            
            // Only initialize if the editor is not already initialized
            if (!wp.editor.getDefaultSettings()) {
                return;
            }
            
            // Remove any existing editor instance
            if (typeof tinyMCE !== 'undefined' && tinyMCE.get(editorId)) {
                wp.editor.remove(editorId);
            }
            
            // Initialize the WordPress editor
            wp.editor.initialize(editorId, {
                tinymce: {
                    wpautop: true,
                    plugins: 'lists,paste,tabfocus,wpautoresize,wpeditimage,wpgallery,wplink,wptextpattern',
                    toolbar1: 'formatselect,bold,italic,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,unlink,wp_more,spellchecker,wp_adv',
                    toolbar2: 'strikethrough,hr,forecolor,pastetext,removeformat,charmap,outdent,indent,undo,redo,wp_help',
                    menubar: false,
                    height: 200,
                    browser_spellcheck: true,
                    convert_urls: false,
                    relative_urls: false,
                    remove_script_host: false
                },
                quicktags: true,
                mediaButtons: true
            });
        });
    }

    // Open media uploader for thumbnail
    function openThumbnailUploader() {
        // If we already have a media frame, reuse it
        if (STATE.mediaFrame) {
            STATE.mediaFrame.open();
            return;
        }
        
        // Create a new media frame
        STATE.mediaFrame = wp.media({
            title: 'Select or Upload Course Thumbnail',
            button: {
                text: 'Use this image'
            },
            multiple: false,
            library: {
                type: 'image'
            }
        });
        
        // When an image is selected in the media frame...
        STATE.mediaFrame.on('select', function() {
            // Get media attachment details from the frame state
            const attachment = STATE.mediaFrame.state().get('selection').first().toJSON();
            
            // Set the thumbnail preview
            $('#cc-thumbnail-preview-img').attr('src', attachment.url);
            
            // Store the ID and URL
            $('#cc-thumbnail-id').val(attachment.id);
            $('#cc-thumbnail-url').val(attachment.url);
        });
        
        // Open the media frame
        STATE.mediaFrame.open();
    }

    // Open media uploader for course images
    function openImagesUploader() {
        // If we already have a media frame for images, reuse it
        if (STATE.imagesMediaFrame) {
            STATE.imagesMediaFrame.open();
            return;
        }
        
        // Create a new media frame for multiple images
        STATE.imagesMediaFrame = wp.media({
            title: 'Select or Upload Course Images',
            button: {
                text: 'Add to course'
            },
            multiple: true,
            library: {
                type: 'image'
            }
        });
        
        // When images are selected in the media frame...
        STATE.imagesMediaFrame.on('select', function() {
            // Get media attachment details from the frame state
            const attachments = STATE.imagesMediaFrame.state().get('selection').toJSON();
            
            // Process each selected image
            attachments.forEach(attachment => {
                // Check if image is already in the list
                const exists = STATE.uploadedImages.some(img => img.id === attachment.id);
                
                if (!exists) {
                    // Add to state
                    const imageData = {
                        id: attachment.id,
                        url: attachment.url,
                        filename: attachment.filename,
                        title: attachment.title || attachment.filename,
                        description: attachment.description || ''
                    };
                    
                    STATE.uploadedImages.push(imageData);
                    
                    // Add to UI
                    addImageToPreview(imageData);
                }
            });
            
            // Update hidden input with image data
            updateImagesData();
            
            // Hide "no images" message if we have images
            if (STATE.uploadedImages.length > 0) {
                $('.cc-no-images').hide();
            }
        });
        
        // Open the media frame
        STATE.imagesMediaFrame.open();
    }

    // Add an image to the preview area
    function addImageToPreview(imageData) {
        const index = STATE.uploadedImages.findIndex(img => img.id === imageData.id);
        
        const $imagePreview = $(`
            <div class="cc-image-preview-item" data-image-id="${imageData.id}" data-index="${index}">
                <img src="${imageData.url}" alt="${imageData.title}">
                <div class="cc-image-preview-info">
                    <span class="cc-image-preview-title">${truncateText(imageData.title, 20)}</span>
                    <button type="button" class="cc-remove-image" data-index="${index}">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                            <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                        </svg>
                    </button>
                </div>
            </div>
        `);
        
        // Add to the preview container
        $('#cc-images-preview').append($imagePreview);
    }

    // Remove an uploaded image
    function removeUploadedImage(index) {
        // Remove from state
        if (index >= 0 && index < STATE.uploadedImages.length) {
            STATE.uploadedImages.splice(index, 1);
            
            // Update hidden input with image data
            updateImagesData();
            
            // Refresh the preview
            refreshImagesPreview();
            
            // Show "no images" message if we have no images
            if (STATE.uploadedImages.length === 0) {
                $('.cc-no-images').show();
            }
        }
    }

    // Refresh the images preview based on state
    function refreshImagesPreview() {
        // Clear the preview
        $('#cc-images-preview').empty();
        
        // Re-add all images
        if (STATE.uploadedImages.length > 0) {
            STATE.uploadedImages.forEach((imageData, index) => {
                // Update the index
                imageData.index = index;
                addImageToPreview(imageData);
            });
            $('.cc-no-images').hide();
        } else {
            // Show "no images" message
            $('#cc-images-preview').append('<p class="cc-no-images">No images uploaded yet.</p>');
        }
    }

    // Update the hidden input with image data
    function updateImagesData() {
        $('#cc-images-data').html('');
        
        // Create hidden inputs for each image
        STATE.uploadedImages.forEach((image, index) => {
            $('#cc-images-data').append(`
                <input type="hidden" name="course_images[${index}][id]" value="${image.id}">
                <input type="hidden" name="course_images[${index}][url]" value="${image.url}">
                <input type="hidden" name="course_images[${index}][filename]" value="${image.filename}">
                <input type="hidden" name="course_images[${index}][title]" value="${image.title}">
                <input type="hidden" name="course_images[${index}][description]" value="${image.description}">
            `);
        });
    }

    // Reset the form to its initial state
    function resetForm() {
        // Clear form fields
        $('#cc-course-form')[0].reset();
        
        // Reset hidden fields
        $('#cc-course-id').val('');
        $('#cc-is-edit-mode').val('0');
        $('#cc-thumbnail-id').val('');
        $('#cc-thumbnail-url').val('');
        
        // Reset thumbnail preview
        $('#cc-thumbnail-preview-img').attr('src', plugin_dir_url + 'assets/course-thumbnail-placeholder.jpg');
        
        // Clear uploaded images
        STATE.uploadedImages = [];
        $('#cc-images-preview').html('<p class="cc-no-images">No images uploaded yet.</p>');
        $('#cc-images-data').html('');
        
        // Reset state
        STATE.isEditMode = false;
        STATE.currentCourseId = null;
        
        // Update button text
        $('.cc-create-course-btn').text('Create Course');
        
        // Clear any error messages
        $('.cc-error-message').remove();
        $('.cc-field-error').removeClass('cc-field-error');
    }

    /**
     * Confirm unpublishing a course
     */
    function confirmUnpublishCourse(courseId) {
        if (confirm('Are you sure you want to unpublish this course? The page will be deleted but your course data will remain.')) {
            unpublishCourse(courseId);
        }
    }

    /**
     * Unpublish a course
     */
    function unpublishCourse(courseId) {
        if (!courseId) return;
        
        // Send request to server
        $.ajax({
            url: caiAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'cai_unpublish_course',
                nonce: caiAjax.nonce,
                courseId: courseId
            },
            success: function(response) {
                if (response.success) {
                    // Show success message
                    alert('Course unpublished successfully.');
                    
                    // Refresh courses list
                    fetchCourses();
                } else {
                    // Show error
                    alert('Error unpublishing course: ' + (response.data || 'Unknown error occurred.'));
                }
            },
            error: function(xhr, status, error) {
                // Show error
                alert('Error unpublishing course. Please try again.');
                console.error('Unpublish error:', status, error);
            }
        });
}

    function createOrUpdateCourse() {
        // Show progress container
        $('#cc-progress-container').show();
        updateProgress(5, 'Preparing course data...');
        
        // Scroll to progress container
        $('html, body').animate({
            scrollTop: $('#cc-progress-container').offset().top - 50
        }, 500);
        
        // Prepare form data
        const formData = new FormData($('#cc-course-form')[0]);
        formData.append('action', 'cai_course_create');
        formData.append('nonce', caiAjax.nonce);
        
        // Add uploaded images data
        STATE.uploadedImages.forEach((image, index) => {
            formData.append(`images[${index}][id]`, image.id);
            formData.append(`images[${index}][url]`, image.url);
            formData.append(`images[${index}][filename]`, image.filename);
            formData.append(`images[${index}][title]`, image.title);
            formData.append(`images[${index}][description]`, image.description);
        });
        
        // Send request to server
        $.ajax({
            url: caiAjax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            timeout: CONFIG.ajaxTimeout,
            success: function(response) {
                console.log("Initial course creation response:", response);
                
                if (response.success) {
                    // Store course ID for progress polling
                    STATE.currentCourseId = response.data.course_id;
                    
                    // Update progress
                    updateProgress(response.data.percent || 5, response.data.message || 'Initializing course...');
                    
                    // Continue with processing
                    console.log("Starting chunk processing for course:", STATE.currentCourseId);
                    processCourseChunk(response.data.course_id);
                } else {
                    // Show error message
                    handleError('Failed to create course: ' + (response.data || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                handleError('Error creating course: ' + (status === 'timeout' ? 'Request timed out' : error));
            }
        });
    }

    /**
     * REWRITTEN FUNCTION
     * This function is rewritten to be safer and prevent request floods.
     * 1. It uses a lock (STATE.isChunkProcessing) to ensure only one request runs at a time.
     * 2. The delay between successful requests is increased to 3 seconds to reduce server load.
     */
    function processCourseChunk(courseId) {
        // SAFETY CHECK 1: Use a lock to prevent multiple requests from running simultaneously.
        if (STATE.isChunkProcessing) {
            console.warn("Chunk processing is already in progress. Skipping new request.");
            return;
        }

        // Lock the function to prevent another call.
        STATE.isChunkProcessing = true;
        console.log("Processing chunk for course:", courseId);
        
        if (!courseId) {
            console.error("No course ID provided to processCourseChunk");
            handleError("Missing course ID");
            STATE.isChunkProcessing = false; // Release lock on error
            return;
        }
        
        $.ajax({
            url: caiAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'cai_process_course_chunk',
                nonce: caiAjax.nonce,
                course_id: courseId
            },
            success: function(response) {
                console.log("Chunk processing response:", response);
                
                if (response.success) {
                    const percent = response.data.percent || 0;
                    const message = response.data.message || 'Processing course...';
                    updateProgress(percent, message);
                    
                    if (response.data.complete) {
                        // Course creation is finished, release the lock.
                        onCourseCreationComplete(courseId);
                        STATE.isChunkProcessing = false;
                    } else {
                        // Continue to the next chunk after a safer, longer delay.
                        setTimeout(function() {
                            STATE.isChunkProcessing = false; // Release lock before scheduling next call
                            processCourseChunk(courseId);
                        }, 3000); // SAFETY CHECK 2: Increased delay to 3 seconds.
                    }
                } else {
                    // Handle server-side errors and stop the process.
                    console.error("Error in chunk processing:", response);
                    handleError('Failed to process course: ' + (response.data || 'Unknown error'));
                    STATE.isChunkProcessing = false; // Release lock on failure
                }
            },
            error: function(xhr, status, error) {
                console.error('Process chunk error:', status, error);
                
                // Wait 5 seconds and retry on network/server error.
                setTimeout(function() {
                    STATE.isChunkProcessing = false; // Release lock before retry
                    processCourseChunk(courseId);
                }, 5000);
            }
        });
    }

    // Helper function to get current progress
    function getCourseProgress(courseId, callback) {
        $.ajax({
            url: caiAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'cai_course_progress',
                nonce: caiAjax.nonce,
                course_id: courseId
            },
            success: function(response) {
                if (response.success && response.data) {
                    callback(response.data);
                } else {
                    callback({error: true, message: 'Failed to get progress'});
                }
            },
            error: function() {
                callback({error: true, message: 'AJAX error'});
            }
        });
    }

    // Start polling for progress updates
    function startProgressPolling(courseId) {
        // Reset poll count
        STATE.progressPollCount = 0;
        
        // Clear existing interval if any
        if (STATE.progressPolling) {
            clearInterval(STATE.progressPolling);
        }
        
        // Set up new polling interval
        STATE.progressPolling = setInterval(function() {
            pollCourseProgress(courseId);
            
            // Increment poll count
            STATE.progressPollCount++;
            
            // Stop polling if we've reached the maximum number of polls
            if (STATE.progressPollCount >= CONFIG.maxProgressPolls) {
                stopProgressPolling();
                handleError('Course creation is taking longer than expected. Please check your courses list later.');
            }
        }, CONFIG.progressPollInterval);
    }

    // Stop progress polling
    function stopProgressPolling() {
        if (STATE.progressPolling) {
            clearInterval(STATE.progressPolling);
            STATE.progressPolling = null;
        }
    }

    // Poll for course progress
    function pollCourseProgress(courseId) {
        $.ajax({
            url: caiAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'cai_course_progress',
                nonce: caiAjax.nonce,
                course_id: courseId
            },
            success: function(response) {
                if (response.success) {
                    // Update progress UI
                    if (response.data.percent_complete) {
                        updateProgress(
                            response.data.percent_complete, 
                            response.data.current_task,
                            response.data
                        );
                    }
                    
                    // Check if course creation is complete
                    if (response.data.completed) {
                        stopProgressPolling();
                        onCourseCreationComplete(courseId);
                    }
                } else {
                    console.error('Failed to get progress update:', response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error polling course progress:', status, error);
            }
        });
    }

    // Update progress UI
    function updateProgress(percent, message, data) {
        console.log(`Progress update: ${percent}% - ${message}`);
        
        $('#cc-progress-bar').css('width', percent + '%');
        $('#cc-progress-percentage').text(percent + '%');
        $('#cc-progress-message').text(message);
        
        // Update stage information
        if (data && data.status) {
            let stageText = '';
            
            switch (data.status) {
                case 'analyzing':
                    stageText = 'Analyzing content';
                    break;
                case 'generating_outline':
                    stageText = 'Generating course outline';
                    break;
                case 'expanding_content':
                    stageText = 'Creating detailed content';
                    break;
                case 'finalizing':
                    stageText = 'Finalizing course';
                    break;
                default:
                    stageText = data.status;
            }
            
            $('#cc-progress-stage').text(stageText);
        }
        
        // Update chapter info if available
        if (data && data.current_chapter) {
            const chapterInfo = `Processing: Chapter ${data.current_chapter} of ${data.total_chapters}`;
            const sectionInfo = data.current_section ? `, Section ${data.current_section}` : '';
            $('#cc-progress-chapter-info').html(`<em>${chapterInfo}${sectionInfo}</em>`);
        } else {
            $('#cc-progress-chapter-info').html('');
        }
    }

    // Handle course creation completion
    function onCourseCreationComplete(courseId) {
        updateProgress(100, 'Course created successfully!');
        
        // Add success class to progress bar
        $('#cc-progress-bar').addClass('complete');
        
        // Show completion message
        $('#cc-progress-chapter-info').html(`
            <div class="cc-creation-complete">
                <p>Your course has been created successfully! You can now:</p>
                <div class="cc-creation-actions">
                    <button type="button" class="button button-primary cc-view-created-course" data-course-id="${courseId}">
                        View Course
                    </button>
                    <button type="button" class="button cc-create-another-course">
                        Create Another Course
                    </button>
                </div>
            </div>
        `);
        
        // Add event handlers for the new buttons
        $('.cc-view-created-course').on('click', function() {
            const courseId = $(this).data('course-id');
            $('#cc-progress-container').hide();
            viewCourse(courseId);
        });
        
        $('.cc-create-another-course').on('click', function() {
            $('#cc-progress-container').hide();
            resetForm();
        });
        
        // Reset state
        STATE.isEditMode = false;
        STATE.currentCourseId = null;
        
        // Refresh courses list
        fetchCourses();
    }

    // Handle errors during course creation
    function handleError(message) {
        // Stop progress polling
        stopProgressPolling();
        
        // Show error in progress container
        $('#cc-progress-message').text('Error: ' + message);
        $('#cc-progress-bar').addClass('error');
        
        // Add retry button
        $('#cc-progress-chapter-info').html(`
            <div class="cc-creation-error">
                <p>An error occurred during course creation. Please try again.</p>
                <button type="button" class="button cc-hide-progress">
                    Close
                </button>
            </div>
        `);
        
        // Add event handler for the hide button
        $('.cc-hide-progress').on('click', function() {
            $('#cc-progress-container').hide();
            $('#cc-progress-bar').removeClass('error');
        });
    }

    // Fetch all courses
    function fetchCourses() {
        const $coursesList = $('.cc-courses-list');
        const $loading = $('.cc-courses-list .cc-loading');
        const $noCourses = $('.cc-courses-list .cc-no-courses');
        
        // Show loading indicator
        $loading.show();
        $noCourses.hide();
        
        // Remove existing course items
        $('.cc-course-item').remove();

        // Send request to server
        $.ajax({
            url: caiAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'cai_course_get_all',
                nonce: caiAjax.nonce
            },
            success: function(response) {
                // Hide loading indicator
                $loading.hide();
                
                if (response.success) {
                    // Extract courses and page setting
                    const courses = response.data.courses || response.data;
                    const coursesPageSet = response.data.courses_page_set || false;
                    
                    if (Array.isArray(courses)) {
                        if (courses.length === 0) {
                            // Show no courses message
                            $noCourses.show();
                        } else {
                            // Render courses with page setting
                            courses.forEach(function(course) {
                                renderCourseItem(course, coursesPageSet);
                            });
                        }
                    } else {
                        $noCourses.show().html('<p>Error loading courses. Invalid response format.</p>');
                    }
                } else {
                    // Show error
                    $noCourses.show().html('<p>Error loading courses. Please try again.</p>');
                }
            },
            error: function(xhr, status, error) {
                // Hide loading indicator
                $loading.hide();
                
                // Show error
                $noCourses.show().html('<p>Error loading courses. Please try again.</p>');
                console.error('Fetch courses error:', status, error);
            }
        });
    }

    /**
     * Render a course item in the list
     */
    function renderCourseItem(course, coursesPageSet) {
        const $coursesList = $('.cc-courses-list');
        const dateStr = course.updatedAt ? new Date(course.updatedAt).toLocaleDateString() : 'Unknown date';
        
        // MODIFICATION: The backend now provides the full URL directly.
        // The call to getImageUrl() has been removed to prevent AJAX requests for every course.
        const coverImageUrl = course.coverImage && course.coverImage.length > 0
            ? course.coverImage
            : plugin_dir_url + 'assets/course-thumbnail-placeholder.jpg';
        
        // Check if course is published
        const isPublished = course.is_published || false;
        
        // Apply published class to the entire course item
        const publishedClass = isPublished ? 'cc-course-published' : '';
        
        const $courseElement = $(
            `<div class="cc-course-item ${publishedClass}" data-course-id="${course.id}">
                <div class="cc-course-image">
                    <img src="${coverImageUrl}" alt="${course.title}">
                    ${isPublished ? '<span class="cc-published-indicator article-publish">Published</span>' : ''}
                </div>
                <div class="cc-course-details">
                    <h4 class="cc-course-title">${course.title}</h4>
                    <div class="cc-course-meta">
                        <span class="cc-course-chapters">${course.chapterCount || (course.chapters ? course.chapters.length : 0)} chapters</span>
                        <span class="cc-course-date">${dateStr}</span>
                    </div>
                    <div class="cc-course-description">${truncateText(course.description || 'No description', 80)}</div>
                </div>

                <div class="cc-course-actions">
                    <button class="course-action-edit" data-course-id="${course.id}" data-course-title="${course.title}" title="Edit Course">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                            <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
                        </svg>
                        <span>Edit</span>
                    </button>
                    
                    ${
                        isPublished ? 
                        `<button class="course-action-unpublish" data-course-id="${course.id}" title="Unpublish Course">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                <path d="M7 11v2h10v-2H7zm5-9C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/>
                            </svg>
                            <span>Unpublish</span>
                        </button>` :
                        `<button class="course-action-publish" ${!coursesPageSet ? 'disabled' : ''} data-course-id="${course.id}" title="Publish Course">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                <path fill="green" d="M5 4v2h14V4H5zm0 10h4v6h6v-6h4l-7-7-7 7z"/>
                            </svg>
                            <span>Publish</span>
                        </button>`
                    }
                    
                    ${
                        isPublished ?
                        `<a href="${course.permalink}" class="course-action-view button" target="_blank" title="View Published Course">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                            </svg>
                            <span>View</span>
                        </a>` : ''
                    }
                    
                    <button class="course-action-delete" data-course-id="${course.id}" data-course-title="${course.title}" title="Delete Course">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                            <path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                        </svg>
                        <span>Delete</span>
                    </button>
                </div>
            </div>`
        );

        $coursesList.append($courseElement);
        document.querySelectorAll('.cc-course-item').forEach(item => {
            item.addEventListener('click', function(e) {
                // Don't trigger if clicking on edit/delete buttons
                if (e.target.closest('.cc-course-actions')) {
                    return;
                }
                
                // Get the course ID
                const courseId = this.getAttribute('data-course-id');
                
                // View the course (reuse your existing view course function)
                viewCourse(courseId);
            });
        });
    }

    /**
     * View a course
     * @param {string} courseId - The ID of the course to view
     */
    function viewCourse(courseId) {
        // Store the current course ID
        STATE.currentCourseId = courseId;
        
        // Show view modal
        $('#course-view-modal').show();
        $('.cc-view-loading').show();
        $('#cc-view-content').empty();
        
        // Send request to server
        $.ajax({
            url: caiAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'cai_course_get_single',
                nonce: caiAjax.nonce,
                courseId: courseId
            },
            success: function(response) {
                // Hide loading indicator
                $('.cc-view-loading').hide();
                
                if (response.success) {
                    // Store course data
                    STATE.courseData = response.data;
                    
                    // Render course view
                    renderCourseView(response.data);
                } else {
                    // Show error
                    $('#cc-view-content').html('<p>Error loading course. It may have been deleted.</p>');
                }
            },
            error: function(xhr, status, error) {
                // Hide loading indicator
                $('.cc-view-loading').hide();
                
                // Show error
                $('#cc-view-content').html('<p>Error loading course. Please try again.</p>');
                console.error('View course error:', status, error);
            }
        });
    }

    /**
     * Render course view with proper image paths
     * @param {Object} course - The course data to render
     */
    function renderCourseView(course) {
        if (!course) {
            console.error('Cannot render course view: course data is undefined');
            $('#cc-view-content').html('<p>Error: Course data not available</p>');
            return;
        }
        
        const $viewContent = $('#cc-view-content');
        
        // Determine image URL
        const coverImageUrl = course.coverImage 
            ? getImageUrl(course.coverImage)
            : plugin_dir_url + 'assets/course-thumbnail-placeholder.jpg';
        
        // Basic course information
        let viewHtml = `
            <div class="cc-course-view">
                <div class="cc-course-view-header">
                    <div class="cc-course-view-cover">
                        <img src="${coverImageUrl}" alt="${course.title}">
                    </div>
                    <h2>${course.title}</h2>
                    <div class="cc-course-view-meta">
                        ${course.estimatedTime ? `<span class="cc-course-view-time">Estimated time: ${course.estimatedTime}</span>` : ''}
                        ${course.targetAudience ? `<span class="cc-course-view-audience">For: ${course.targetAudience}</span>` : ''}
                        ${course.difficulty ? `<span class="cc-course-view-difficulty">Level: ${course.difficulty}</span>` : ''}
                    </div>
                    <div class="cc-course-view-description">${course.description}</div>
                </div>`;
        
        // Course chapters
        if (course.chapters && course.chapters.length > 0) {
            viewHtml += `<div class="cc-course-view-chapters">
                <h3>Course Content</h3>`;
            
            course.chapters.forEach((chapter, index) => {
                viewHtml += `
                    <div class="cc-course-view-chapter">
                        <h4>${chapter.title}</h4>
                        <div class="cc-course-view-chapter-intro">${chapter.introduction}</div>
                        
                        <div class="cc-course-view-sections">`;
                
                if (chapter.sections && chapter.sections.length > 0) {
                    chapter.sections.forEach((section, sectionIndex) => {
                        // Process content to ensure image URLs are correct
                        let sectionContent = section.content;
                        
                        // Replace image src with full URL if needed
                        if (section.image) {
                            const imageUrl = getImageUrl(section.image);
                            sectionContent = sectionContent.replace(
                                new RegExp('src="' + section.image + '"', 'g'), 
                                'src="' + imageUrl + '"'
                            );
                        }
                        
                        viewHtml += `
                            <div class="cc-course-view-section">
                                <h5>${section.title}</h5>
                                <div class="cc-course-view-section-content">${sectionContent}</div>
                            </div>`;
                    });
                }
                
                viewHtml += `</div></div>`;
            });
            
            viewHtml += `</div>`;
        }
        
        // Quiz section
        if (course.quiz) {
            viewHtml += `
                <div class="cc-course-view-quiz">
                    <h3>Final Quiz</h3>
                    <p>${course.quiz.description || 'Test your knowledge with this quiz.'}</p>
                    <div class="cc-course-view-quiz-questions">`;
            
            if (course.quiz.questions && course.quiz.questions.length > 0) {
                course.quiz.questions.forEach((question, index) => {
                    viewHtml += `
                        <div class="cc-course-view-quiz-question">
                            <h5>Question ${index + 1}: ${question.question}</h5>
                            <div class="cc-course-view-quiz-options ${question.type === 'image-choice' || (question.image_options && question.image_options.length) ? 'cc-course-view-quiz-image-options' : ''}">`;
                    
                    if (question.type === 'multiple-choice' && question.options) {
                        // Check if this question has image options
                        const hasImageOptions = question.image_options && question.image_options.length;
                        
                        question.options.forEach((option, optIndex) => {
                            const isCorrect = optIndex === question.correctAnswer;
                            
                            if (hasImageOptions && question.image_options[optIndex]) {
                                const imageUrl = getImageUrl(question.image_options[optIndex]);
                                viewHtml += `
                                    <div class="cc-course-view-quiz-option cc-course-view-quiz-image-option ${isCorrect ? 'cc-correct-answer' : ''}">
                                        <div class="cc-option-image">
                                            <img src="${imageUrl}" alt="${option}">
                                        </div>
                                        <div class="cc-option-text">
                                            ${option} ${isCorrect ? '<span class="cc-correct-indicator">(Correct Answer)</span>' : ''}
                                        </div>
                                    </div>`;
                            } else {
                                viewHtml += `
                                    <div class="cc-course-view-quiz-option ${isCorrect ? 'cc-correct-answer' : ''}">
                                        ${option} ${isCorrect ? '<span class="cc-correct-indicator">(Correct Answer)</span>' : ''}
                                    </div>`;
                            }
                        });
                    } else if (question.type === 'true-false') {
                        const correctAnswer = question.correctAnswer === 0 ? 'True' : 'False';
                        viewHtml += `
                            <div class="cc-course-view-quiz-option ${question.correctAnswer === 0 ? 'cc-correct-answer' : ''}">
                                True ${question.correctAnswer === 0 ? '<span class="cc-correct-indicator">(Correct Answer)</span>' : ''}
                            </div>
                            <div class="cc-course-view-quiz-option ${question.correctAnswer === 1 ? 'cc-correct-answer' : ''}">
                                False ${question.correctAnswer === 1 ? '<span class="cc-correct-indicator">(Correct Answer)</span>' : ''}
                            </div>`;
                    } else if (question.type === 'image-choice' && question.image_options) {
                        // Display image options for image-choice questions
                        question.image_options.forEach((imageFilename, optIndex) => {
                            const isCorrect = optIndex === question.correctAnswer;
                            const imageUrl = getImageUrl(imageFilename);
                            
                            viewHtml += `
                                <div class="cc-course-view-quiz-option cc-course-view-quiz-image-option ${isCorrect ? 'cc-correct-answer' : ''}">
                                    <div class="cc-option-image">
                                        <img src="${imageUrl}" alt="Option ${optIndex + 1}">
                                    </div>
                                    <div class="cc-option-text">
                                        Option ${optIndex + 1} ${isCorrect ? '<span class="cc-correct-indicator">(Correct Answer)</span>' : ''}
                                    </div>
                                </div>`;
                        });
                    }
                    
                    viewHtml += `</div></div>`;
                });
            } else {
                viewHtml += `<p>No quiz questions available.</p>`;
            }
            
            viewHtml += `</div></div>`;
        }
        
        viewHtml += `</div>`;
        
        // Add to view content
        $viewContent.html(viewHtml);
    }




    // Load a course for editing
    function loadCourseForEdit(courseId) {
        // Set edit mode
        STATE.isEditMode = true;
        STATE.currentCourseId = courseId;
        
        // Show editor modal
        $('#course-editor-modal').show();
        $('.cc-editor-loading').show();
        $('#cc-editor-content').empty();
        
        // Send request to server
        $.ajax({
            url: caiAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'cai_course_get_single',
                nonce: caiAjax.nonce,
                courseId: courseId
            },
            success: function(response) {
                // Hide loading indicator
                $('.cc-editor-loading').hide();
                
                if (response.success) {
                    // Store course data
                    STATE.courseData = response.data;
                    
                    // Load course images
                    loadCourseImages(response.data);
                    
                    // Render course editor
                    renderCourseEditor(response.data);
                } else {
                    // Show error
                    $('#cc-editor-content').html('<p>Error loading course. It may have been deleted.</p>');
                }
            },
            error: function(xhr, status, error) {
                // Hide loading indicator
                $('.cc-editor-loading').hide();
                
                // Show error
                $('#cc-editor-content').html('<p>Error loading course. Please try again.</p>');
                console.error('Load course for edit error:', status, error);
            }
        });
    }

    // Load course images for editing
    function loadCourseImages(course) {
        // Reset uploaded images
        STATE.uploadedImages = [];
        
        // Get all images used in the course
        const usedImages = [];
        
        // Add cover image if exists
        if (course.coverImage) {
            usedImages.push(course.coverImage);
        }
        
        // Add section images
        if (course.chapters) {
            course.chapters.forEach(chapter => {
                if (chapter.sections) {
                    chapter.sections.forEach(section => {
                        if (section.image) {
                            usedImages.push(section.image);
                        }
                    });
                }
            });
        }
        
        // Now fetch metadata for these images and add to state
        // This is a simplified version - in a real implementation,
        // you would fetch the actual metadata from WordPress
        usedImages.forEach((filename, index) => {
            const imageData = {
                id: 'image-' + index, // Placeholder ID
                url: getImageUrl(filename),
                filename: filename,
                title: filename,
                description: 'Image used in course'
            };
            
            STATE.uploadedImages.push(imageData);
        });
    }

    // Render course editor
    function renderCourseEditor(course_data) {
        const $editorContent = $('#cc-editor-content');
        
        // Start building the editor HTML
        let editorHtml = `
            <form id="cc-editor-form" class="cc-editor-form">
                <input type="hidden" name="course_id" value="${course_data.id}">
                
                <div class="cc-editor-section">
                    <h4>Basic Information</h4>
                    
                    <div class="cc-editor-field">
                        <label for="editor-course-title">Course Title</label>
                        <input type="text" id="editor-course-title" name="title" value="${course_data.title}" required>
                    </div>
                    
                    <div class="cc-editor-field">
                        <label for="editor-course-description">Course Description</label>
                        <textarea id="editor-course-description" name="description" rows="3" required>${course_data.description}</textarea>
                    </div>
                    
                    <div class="cc-editor-row">
                        <div class="cc-editor-field">
                            <label for="editor-target-audience">Target Audience</label>
                            <input type="text" id="editor-target-audience" name="targetAudience" value="${course_data.targetAudience || ''}">
                        </div>
                        
                        <div class="cc-editor-field">
                            <label for="editor-difficulty">Difficulty Level</label>
                            <select id="editor-difficulty" name="difficulty">
                                <option value="beginner" ${course_data.difficulty === 'beginner' ? 'selected' : ''}>Beginner</option>
                                <option value="intermediate" ${course_data.difficulty === 'intermediate' ? 'selected' : ''}>Intermediate</option>
                                <option value="advanced" ${course_data.difficulty === 'advanced' ? 'selected' : ''}>Advanced</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="cc-editor-section">
                    <h4>Course Thumbnail</h4>
                    
                    <div class="cc-editor-field">
                        <div class="cc-editor-thumbnail-wrapper">
                            <div class="cc-editor-thumbnail-preview">
                                <img id="cc-thumbnail-image" src="${getImageUrl(course_data.coverImage)}" alt="${course_data.title}">
                            </div>
                            <div class="cc-editor-thumbnail-actions">
                                <button type="button" class="button button-secondary" id="cc-replace-thumbnail-btn">
                                    Replace Thumbnail
                                </button>
                                <p class="description">Recommended size: 800x450 pixels</p>
                            </div>
                            <input type="hidden" id="cc-thumbnail-filename" name="coverImage" value="${course_data.coverImage || ''}">
                        </div>
                    </div>
                </div>
                
                <div class="cc-editor-section">
                    <div class="cc-editor-header">
                        <h4>Course Content</h4>
                        <button type="button" class="button cc-add-chapter-btn">Add Chapter</button>
                    </div>
                    
                    <div id="cc-editor-chapters" class="cc-editor-chapters">`;
        
        // Add chapters
        if (course_data.chapters && course_data.chapters.length > 0) {
            course_data.chapters.forEach((chapter, chapterIndex) => {
                editorHtml += generateChapterEditor(chapter, chapterIndex);
            });
        } else {
            editorHtml += `<p class="cc-editor-no-chapters">No chapters available. Click "Add Chapter" to create one.</p>`;
        }
        
        editorHtml += `
                    </div>
                </div>
                
                <div class="cc-editor-section">
                    <h4>Quiz</h4>
                    
                    <div class="cc-editor-field">
                        <label for="editor-quiz-description">Quiz Description</label>
                        <textarea id="editor-quiz-description" name="quiz[description]" rows="2">${course_data.quiz ? course_data.quiz.description : 'Test your knowledge with this quiz.'}</textarea>
                    </div>
                    
                    <div class="cc-editor-quiz-questions">
                        <h5>Questions</h5>
                        <div id="cc-editor-questions">`;
        
        // Add quiz questions
        if (course_data.quiz && course_data.quiz.questions && course_data.quiz.questions.length > 0) {
            course_data.quiz.questions.forEach((question, questionIndex) => {
                editorHtml += generateQuestionEditor(question, questionIndex);
            });
        } else {
            editorHtml += `<p class="cc-editor-no-questions">No questions available. Click "Add Question" to create one.</p>`;
        }
        
        editorHtml += `
                        </div>
                        <button type="button" class="button cc-add-question-btn">Add Question</button>
                    </div>
                </div>
            </form>`;
        
        // Add to editor content
        $editorContent.html(editorHtml);
        
        // Initialize WYSIWYG editors after a small delay to ensure DOM is ready
        setTimeout(function() {
            initializeEditors();
        }, 100);
        
        // Set up additional event handlers for the editor
        setupEditorEventHandlers();
    }

    // Generate HTML for chapter editor
    function generateChapterEditor(chapter, index) {
        // Preprocess the content to replace image filenames with full URLs
        let processedIntroduction = chapter.introduction;
        if (processedIntroduction && typeof processedIntroduction === 'string') {
            // Find all img tags and replace their src if needed
            processedIntroduction = processedIntroduction.replace(/<img\s+([^>]*?)src=(["'])((?:(?!\2).)*?)\2([^>]*?)>/gi, function(match, prefix, quote, src, suffix) {
                // If src is not already a full URL
                if (!src.startsWith('http://') && !src.startsWith('https://') && !src.startsWith('/')) {
                    // Convert to full URL
                    const fullUrl = getImageUrl(src);
                    return `<img ${prefix}src=${quote}${fullUrl}${quote}${suffix}>`;
                }
                return match;
            });
        }
        
        let html = `
            <div class="cc-editor-chapter" data-chapter-id="${index}">
                <div class="cc-editor-chapter-header">
                    <div class="cc-editor-chapter-title">
                        <button type="button" class="cc-toggle-chapter" data-chapter-id="${index}" title="Toggle Chapter">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="cc-toggle-icon">
                                <path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z"/>
                            </svg>
                        </button>
                        <h5>Chapter ${index + 1}: ${chapter.title}</h5>
                    </div>
                    <div class="cc-editor-chapter-actions">
                        <button type="button" class="cc-move-chapter-up" data-chapter-id="${index}" title="Move Up">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                <path d="M7.41 15.41L12 10.83l4.59 4.58L18 14l-6-6-6 6z"/>
                            </svg>
                        </button>
                        <button type="button" class="cc-move-chapter-down" data-chapter-id="${index}" title="Move Down">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                <path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z"/>
                            </svg>
                        </button>
                        <button type="button" class="cc-delete-chapter-btn" data-chapter-id="${index}" title="Delete Chapter">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                <path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <div class="cc-editor-chapter-content">
                    <div class="cc-editor-field">
                        <label for="chapter-title-${index}">Chapter Title</label>
                        <input type="text" id="chapter-title-${index}" name="chapters[${index}][title]" value="${chapter.title}" required>
                    </div>
                    
                    <div class="cc-editor-field">
                        <label for="chapter-intro-${index}">Introduction</label>
                        <div class="cc-wp-editor-container">
                            <textarea id="chapter-intro-${index}" name="chapters[${index}][introduction]" rows="3">${processedIntroduction}</textarea>
                        </div>
                    </div>
                    
                    <div class="cc-editor-sections">
                        <h6>Sections</h6>
                        <div class="cc-editor-sections-list">`;
        
        // Add sections
        if (chapter.sections && chapter.sections.length > 0) {
            chapter.sections.forEach((section, sectionIndex) => {
                html += generateSectionEditor(section, index, sectionIndex);
            });
        } else {
            html += `<p class="cc-editor-no-sections">No sections available. Click "Add Section" to create one.</p>`;
        }
        
        html += `
                        </div>
                        <button type="button" class="button cc-add-section-btn" data-chapter-id="${index}">Add Section</button>
                    </div>
                </div>
            </div>`;
        
        return html;
    }

    // Generate HTML for section editor
    function generateSectionEditor(section, chapterIndex, sectionIndex) {
        // Preprocess the content to replace image filenames with full URLs
        let processedContent = section.content;
        if (processedContent && typeof processedContent === 'string') {
            // Find all img tags and replace their src if needed
            processedContent = processedContent.replace(/<img\s+([^>]*?)src=(["'])((?:(?!\2).)*?)\2([^>]*?)>/gi, function(match, prefix, quote, src, suffix) {
                // If src is not already a full URL
                if (!src.startsWith('http://') && !src.startsWith('https://') && !src.startsWith('/')) {
                    // Convert to full URL
                    const fullUrl = getImageUrl(src);
                    return `<img ${prefix}src=${quote}${fullUrl}${quote}${suffix}>`;
                }
                return match;
            });
        }
        
        return `
            <div class="cc-editor-section-item" data-chapter-id="${chapterIndex}" data-section-id="${sectionIndex}">
                <div class="cc-editor-section-header">
                    <div class="cc-editor-section-title">
                        <button type="button" class="cc-toggle-section" data-chapter-id="${chapterIndex}" data-section-id="${sectionIndex}" title="Toggle Section">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="cc-toggle-icon">
                                <path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z"/>
                            </svg>
                        </button>
                        <h6>Section ${sectionIndex + 1}: ${section.title}</h6>
                    </div>
                    <div class="cc-editor-section-actions">
                        <button type="button" class="cc-move-section-up" data-chapter-id="${chapterIndex}" data-section-id="${sectionIndex}" title="Move Up">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                <path d="M7.41 15.41L12 10.83l4.59 4.58L18 14l-6-6-6 6z"/>
                            </svg>
                        </button>
                        <button type="button" class="cc-move-section-down" data-chapter-id="${chapterIndex}" data-section-id="${sectionIndex}" title="Move Down">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                <path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z"/>
                            </svg>
                        </button>
                        <button type="button" class="cc-delete-section-btn" data-chapter-id="${chapterIndex}" data-section-id="${sectionIndex}" title="Delete Section">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                <path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <div class="cc-editor-section-content">
                    <div class="cc-editor-field">
                        <label for="section-title-${chapterIndex}-${sectionIndex}">Section Title</label>
                        <input type="text" id="section-title-${chapterIndex}-${sectionIndex}" 
                               name="chapters[${chapterIndex}][sections][${sectionIndex}][title]" 
                               value="${section.title}" required>
                    </div>
                    
                    <div class="cc-editor-field">
                        <label for="section-content-${chapterIndex}-${sectionIndex}">Content</label>
                        <div class="cc-wp-editor-container">
                            <textarea id="section-content-${chapterIndex}-${sectionIndex}" 
                                      name="chapters[${chapterIndex}][sections][${sectionIndex}][content]" 
                                      rows="5">${processedContent}</textarea>
                        </div>
                    </div>
                </div>
            </div>`;
    }

    // Generate HTML for quiz question editor
    function generateQuestionEditor(question, index) {
        let html = `
            <div class="cc-editor-question" data-question-id="${index}">
                <div class="cc-editor-question-header">
                    <div class="cc-editor-question-title">
                        <h6>Question ${index + 1}</h6>
                    </div>
                    <div class="cc-editor-question-actions">
                        <button type="button" class="cc-delete-question-btn" data-question-id="${index}" title="Delete Question">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                <path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <div class="cc-editor-question-content">
                    <div class="cc-editor-field">
                        <label for="question-text-${index}">Question Text</label>
                        <textarea id="question-text-${index}" name="quiz[questions][${index}][question]" rows="2">${question.question}</textarea>
                    </div>
                    
                    <div class="cc-editor-field">
                        <label for="question-type-${index}">Question Type</label>
                        <select id="question-type-${index}" name="quiz[questions][${index}][type]" class="cc-question-type-selector" data-question-id="${index}" data-previous-type="${question.type}">
                            <option value="multiple-choice" ${question.type === 'multiple-choice' ? 'selected' : ''}>Multiple Choice</option>
                            <option value="true-false" ${question.type === 'true-false' ? 'selected' : ''}>True/False</option>
                            <option value="image-choice" ${question.type === 'image-choice' ? 'selected' : ''}>Image Choice</option>
                        </select>
                    </div>`;
        
        if (question.type === 'multiple-choice') {
            html += `
                <div class="cc-editor-question-options">
                    <label>Options</label>`;
            
            if (question.options && question.options.length > 0) {
                question.options.forEach((option, optionIndex) => {
                    // Check if there's an image for this option
                    const hasImageOption = question.image_options && question.image_options[optionIndex];
                    const imageUrl = hasImageOption ? getImageUrl(question.image_options[optionIndex]) : '';
                    
                    html += `
                        <div class="cc-editor-option">
                            <input type="text" name="quiz[questions][${index}][options][${optionIndex}]" value="${option}" class="cc-option-text">
                            
                            <div class="cc-editor-option-image">
                                ${hasImageOption ? 
                                    `<div class="cc-option-image-preview">
                                        <img src="${imageUrl}" alt="Option image">
                                    </div>` : 
                                    ''
                                }
                                <button type="button" class="button cc-option-image-btn" data-question-id="${index}" data-option-id="${optionIndex}">
                                    ${hasImageOption ? 'Change Image' : 'Add Image'}
                                </button>
                                ${hasImageOption ? 
                                    `<button type="button" class="cc-option-image-remove-btn" data-question-id="${index}" data-option-id="${optionIndex}">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                            <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                                        </svg>
                                    </button>` : 
                                    ''
                                }
                                <input type="hidden" name="quiz[questions][${index}][image_options][${optionIndex}]" value="${hasImageOption ? question.image_options[optionIndex] : ''}">
                            </div>
                            
                            <label class="cc-correct-option">
                                <input type="radio" name="quiz[questions][${index}][correctAnswer]" value="${optionIndex}" ${question.correctAnswer === optionIndex ? 'checked' : ''}>
                                Correct
                            </label>
                            ${optionIndex > 1 ? 
                                `<button type="button" class="cc-remove-option-btn" data-question-id="${index}" data-option-id="${optionIndex}">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                        <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                                    </svg>
                                </button>` : 
                                ''
                            }
                        </div>`;
                });
            }
            
            html += `
                    <button type="button" class="button cc-add-option-btn" data-question-id="${index}">Add Option</button>
                </div>`;
        } else if (question.type === 'true-false') {
            html += `
                <div class="cc-editor-question-tf">
                    <label>Correct Answer</label>
                    <div class="cc-editor-tf-options">
                        <label>
                            <input type="radio" name="quiz[questions][${index}][correctAnswer]" value="0" ${question.correctAnswer === 0 ? 'checked' : ''}>
                            True
                        </label>
                        <label>
                            <input type="radio" name="quiz[questions][${index}][correctAnswer]" value="1" ${question.correctAnswer === 1 ? 'checked' : ''}>
                            False
                        </label>
                    </div>
                </div>`;
        } else if (question.type === 'image-choice') {
            html += `
                <div class="cc-editor-question-image-options">
                    <label>Image Options</label>
                    <div class="cc-editor-image-options-list">`;
            
            if (question.image_options && question.image_options.length > 0) {
                question.image_options.forEach((imageFilename, optionIndex) => {
                    const imageUrl = getImageUrl(imageFilename);
                    
                    html += `
                        <div class="cc-editor-image-option" data-option-id="${optionIndex}">
                            <div class="cc-image-option-preview">
                                <img src="${imageUrl}" alt="Option ${optionIndex + 1}">
                            </div>
                            <div class="cc-image-option-controls">
                                <label class="cc-correct-option">
                                    <input type="radio" name="quiz[questions][${index}][correctAnswer]" value="${optionIndex}" ${question.correctAnswer === optionIndex ? 'checked' : ''}>
                                    Correct
                                </label>
                                <div class="cc-image-option-actions">
                                    ${optionIndex > 0 ? 
                                        `<button type="button" class="cc-move-image-option-left" data-question-id="${index}" data-option-id="${optionIndex}" title="Move Left">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                                <path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/>
                                            </svg>
                                        </button>` : ''}
                                    <button type="button" class="cc-move-image-option-right" data-question-id="${index}" data-option-id="${optionIndex}" title="Move Right">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                            <path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/>
                                        </svg>
                                    </button>
                                    <button type="button" class="cc-remove-image-option-btn" data-question-id="${index}" data-option-id="${optionIndex}" title="Remove Image">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                            <path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <input type="hidden" name="quiz[questions][${index}][image_options][${optionIndex}]" value="${imageFilename}">
                        </div>`;
                });
            }
            
            html += `
                    </div>
                    <button type="button" class="button cc-add-image-option-btn" data-question-id="${index}">Add Image Option</button>
                </div>`;
        }
        
        html += `
                </div>
            </div>`;
        
            setTimeout(() => {
            const $question = $(`.cc-editor-question[data-question-id="${index}"]`);
            if ($question.length) {
                const savedOptions = {
                    'multiple-choice': [],
                    'true-false': { correctAnswer: question.correctAnswer },
                    'image-choice': []
                };
                
                // Set up saved options based on current question data
                if (question.type === 'multiple-choice' && question.options) {
                    savedOptions['multiple-choice'] = question.options.map((option, optIndex) => ({
                        text: option,
                        isCorrect: question.correctAnswer === optIndex,
                        image: question.image_options ? question.image_options[optIndex] : ''
                    }));
                } else if (question.type === 'image-choice' && question.image_options) {
                    savedOptions['image-choice'] = question.image_options.map((image, optIndex) => ({
                        image: image,
                        isCorrect: question.correctAnswer === optIndex
                    }));
                }
                
                $question.data('savedOptions', savedOptions);
            }
        }, 0);
        
        return html;
    }

    // Set up event handlers specific to the editor
    function setupEditorEventHandlers() {
        // First, unbind any existing editor event handlers
        $(document).off('.editor');
        
        // Now bind the event handlers with a namespace
        
        // Replace thumbnail button
        $(document).on('click.editor', '#cc-replace-thumbnail-btn', function() {
            openThumbnailReplacer();
        });

        // Add new chapter
        $(document).on('click.editor', '.cc-add-chapter-btn', function() {
            addNewChapter();
        });

        // Add new section
        $(document).on('click.editor', '.cc-add-section-btn', function() {
            const chapterId = $(this).data('chapter-id');
            addNewSection(chapterId);
        });

        // Add new question
        $(document).on('click.editor', '.cc-add-question-btn', function() {
            addNewQuestion();
        });

        // Add new option to multiple-choice question
        $(document).on('click.editor', '.cc-add-option-btn', function() {
            const questionId = $(this).data('question-id');
            addNewOption(questionId);
        });

        // Remove option from multiple-choice question
        $(document).on('click.editor', '.cc-remove-option-btn', function() {
            const questionId = $(this).data('question-id');
            const optionId = $(this).data('option-id');
            removeOption(questionId, optionId);
        });

        // Question type change
        $(document).on('change.editor', '.cc-question-type-selector', function() {
            const questionId = $(this).data('question-id');
            const newType = $(this).val();
            changeQuestionType(questionId, newType);
        });

        // Delete question
        $(document).on('click.editor', '.cc-delete-question-btn', function() {
            const questionId = $(this).data('question-id');
            deleteQuestion(questionId);
        });

        // Add image to multiple-choice option
        $(document).on('click.editor', '.cc-option-image-btn', function() {
            const questionId = $(this).data('question-id');
            const optionId = $(this).data('option-id');
            openOptionImageUploader(questionId, optionId);
        });
        
        // Remove image from multiple-choice option
        $(document).on('click.editor', '.cc-option-image-remove-btn', function() {
            const questionId = $(this).data('question-id');
            const optionId = $(this).data('option-id');
            removeOptionImage(questionId, optionId);
        });
        
        // Add new image option to image-choice question
        $(document).on('click.editor', '.cc-add-image-option-btn', function() {
            const questionId = $(this).data('question-id');
            openNewImageOptionUploader(questionId);
        });
        
        // Remove image option from image-choice question
        $(document).on('click.editor', '.cc-remove-image-option-btn', function() {
            const questionId = $(this).data('question-id');
            const optionId = $(this).data('option-id');
            removeImageOption(questionId, optionId);
        });
        
        // Move image option left
        $(document).on('click.editor', '.cc-move-image-option-left', function() {
            moveImageOptionLeft($(this));
        });

        // Move image option right
        $(document).on('click.editor', '.cc-move-image-option-right', function() {
            moveImageOptionRight($(this));
        });
        
        // Toggle chapter visibility
        $(document).on('click.editor', '.cc-toggle-chapter', function() {
            const chapterId = $(this).data('chapter-id');
            const $chapter = $(`.cc-editor-chapter[data-chapter-id="${chapterId}"]`);
            const $content = $chapter.find('.cc-editor-chapter-content');
            const $icon = $(this).find('.cc-toggle-icon');
            
            $content.slideToggle(200);
            $chapter.toggleClass('collapsed');
            
            // Rotate icon
            if ($chapter.hasClass('collapsed')) {
                $icon.css('transform', 'rotate(-90deg)');
            } else {
                $icon.css('transform', 'rotate(0deg)');
            }
        });
        
        // Toggle section visibility
        $(document).on('click.editor', '.cc-toggle-section', function() {
            const chapterId = $(this).data('chapter-id');
            const sectionId = $(this).data('section-id');
            const $section = $(`.cc-editor-section-item[data-chapter-id="${chapterId}"][data-section-id="${sectionId}"]`);
            const $content = $section.find('.cc-editor-section-content');
            const $icon = $(this).find('.cc-toggle-icon');
            
            $content.slideToggle(200);
            $section.toggleClass('collapsed');
            
            // Rotate icon
            if ($section.hasClass('collapsed')) {
                $icon.css('transform', 'rotate(-90deg)');
            } else {
                $icon.css('transform', 'rotate(0deg)');
            }
        });
    }

    // Add a new chapter to the editor
    function addNewChapter() {
        // Get current chapter count
        const chapterCount = $('.cc-editor-chapter').length;
        
        // Create empty chapter data
        const newChapter = {
            title: `Chapter ${chapterCount + 1}`,
            introduction: '',
            sections: []
        };
        
        // Generate HTML
        const chapterHtml = generateChapterEditor(newChapter, chapterCount);
        
        // Add to UI
        $('.cc-editor-no-chapters').remove();
        $('#cc-editor-chapters').append(chapterHtml);
        
        // Initialize editors for the new chapter
        setTimeout(function() {
            initializeEditors();
        }, 100);
    }

    // Open media uploader for thumbnail replacement
    function openThumbnailReplacer() {
        // Create a media frame if we don't have one
        if (!STATE.thumbnailReplacerFrame) {
            STATE.thumbnailReplacerFrame = wp.media({
                title: 'Select or Upload Course Thumbnail',
                button: {
                    text: 'Use this image'
                },
                multiple: false,
                library: {
                    type: 'image'
                }
            });
            
            // When an image is selected
            STATE.thumbnailReplacerFrame.on('select', function() {
                const attachment = STATE.thumbnailReplacerFrame.state().get('selection').first().toJSON();
                
                // Update the thumbnail preview
                $('#cc-thumbnail-image').attr('src', attachment.url);
                
                // For media library uploads, store the attachment ID instead of trying to use custom paths
                if (attachment.id) {
                    // This is a media library attachment - store the attachment ID
                    $('#cc-thumbnail-filename').val(attachment.id);
                } else {
                    // External image - store full URL
                    $('#cc-thumbnail-filename').val(attachment.url);
                }
            });
        }
        
        // Open the media frame
        STATE.thumbnailReplacerFrame.open();
    }

    // Add a new section to a chapter
    function addNewSection(chapterId) {
        // Get current section count for this chapter
        const $chapter = $(`.cc-editor-chapter[data-chapter-id="${chapterId}"]`);
        const sectionCount = $chapter.find('.cc-editor-section-item').length;
        
        // Create empty section data
        const newSection = {
            title: `Section ${sectionCount + 1}`,
            content: '',
            image: ''
        };
        
        // Generate HTML
        const sectionHtml = generateSectionEditor(newSection, chapterId, sectionCount);
        
        // Add to UI
        $chapter.find('.cc-editor-no-sections').remove();
        $chapter.find('.cc-editor-sections-list').append(sectionHtml);
        
        // Initialize editors for the new section
        setTimeout(function() {
            initializeEditors();
        }, 100);
    }

    // Add a new question to the quiz
    function addNewQuestion() {
        // Get current question count
        const questionCount = $('.cc-editor-question').length;
        
        // Create empty question data
        const newQuestion = {
            question: 'New question?',
            type: 'multiple-choice',
            options: ['Option 1', 'Option 2', 'Option 3', 'Option 4'],
            correctAnswer: 0
        };
        
        // Generate HTML
        const questionHtml = generateQuestionEditor(newQuestion, questionCount);
        
        // Add to UI
        $('.cc-editor-no-questions').remove();
        $('#cc-editor-questions').append(questionHtml);
    }

    // Add a new option to a multiple-choice question
    function addNewOption(questionId) {
        const $question = $(`.cc-editor-question[data-question-id="${questionId}"]`);
        const optionCount = $question.find('.cc-editor-option').length;
        
        // Create new option HTML with empty value and placeholder
        const optionHtml = `
            <div class="cc-editor-option">
                <input type="text" name="quiz[questions][${questionId}][options][${optionCount}]" value="" placeholder="Enter option text">
                <label class="cc-correct-option">
                    <input type="radio" name="quiz[questions][${questionId}][correctAnswer]" value="${optionCount}">
                    Correct
                </label>
                <button type="button" class="cc-remove-option-btn" data-question-id="${questionId}" data-option-id="${optionCount}">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                    </svg>
                </button>
            </div>`;
        
        // Add to UI
        $question.find('.cc-editor-question-options').find('.cc-add-option-btn').before(optionHtml);
    }

    // Remove an option from a multiple-choice question
    function removeOption(questionId, optionId) {
        const $question = $(`.cc-editor-question[data-question-id="${questionId}"]`);
        const $options = $question.find('.cc-editor-option');
        
        // Need at least 2 options
        if ($options.length <= 2) {
            alert('A multiple-choice question must have at least 2 options.');
            return;
        }
        
        // Check if removing the correct answer
        const $optionToRemove = $options.eq(optionId);
        const isCorrect = $optionToRemove.find('input[type="radio"]').prop('checked');
        
        // Remove the option
        $optionToRemove.remove();
        
        // If removed the correct answer, set the first option as correct
        if (isCorrect) {
            $question.find('input[type="radio"]').first().prop('checked', true);
        }
        
        // Renumber the options
        $question.find('.cc-editor-option').each(function(newIndex) {
            const $option = $(this);
            $option.find('input[type="text"]').attr('name', `quiz[questions][${questionId}][options][${newIndex}]`);
            $option.find('input[type="radio"]').attr('value', newIndex);
            $option.find('.cc-remove-option-btn').attr('data-option-id', newIndex);
        });
    }

    // Change question type
    function changeQuestionType(questionId, newType) {
        const $question = $(`.cc-editor-question[data-question-id="${questionId}"]`);
        const $content = $question.find('.cc-editor-question-content');
        const $typeSelect = $question.find('.cc-question-type-selector');
        const oldType = $typeSelect.data('previousType') || $typeSelect.find('option:not(:selected)').val();
        
        // Initialize saved options if they don't exist
        let savedOptions = $question.data('savedOptions') || {
            'multiple-choice': [],
            'true-false': { correctAnswer: 0 },
            'image-choice': []
        };
        
        // Save current options before changing
        if ($content.find('.cc-editor-question-options').length) {
            // Save multiple choice options
            const mcOptions = [];
            $content.find('.cc-editor-option').each(function() {
                const text = $(this).find('input[type="text"]').val();
                const isCorrect = $(this).find('input[type="radio"]').prop('checked');
                const image = $(this).find('input[type="hidden"]').val();
                mcOptions.push({ text, isCorrect, image });
            });
            if (mcOptions.length > 0) {
                savedOptions['multiple-choice'] = mcOptions;
            }
        } else if ($content.find('.cc-editor-question-tf').length) {
            // Save true/false answer
            const correctAnswer = $content.find('input[type="radio"]:checked').val();
            savedOptions['true-false'] = { correctAnswer: correctAnswer ? parseInt(correctAnswer) : 0 };
        } else if ($content.find('.cc-editor-question-image-options').length) {
            // Save image choice options
            const imageOptions = [];
            $content.find('.cc-editor-image-option').each(function() {
                const image = $(this).find('input[type="hidden"]').val();
                const isCorrect = $(this).find('input[type="radio"]').prop('checked');
                imageOptions.push({ image, isCorrect });
            });
            if (imageOptions.length > 0) {
                savedOptions['image-choice'] = imageOptions;
            }
        }
        
        // Store the updated saved options
        $question.data('savedOptions', savedOptions);
        
        // Save the current type as previous type for next change
        $typeSelect.data('previousType', newType);
        
        // Remove existing options/tf section
        $content.find('.cc-editor-question-options, .cc-editor-question-tf, .cc-editor-question-image-options').remove();
        
        // Add appropriate input based on type
        if (newType === 'multiple-choice') {
            const savedMCOptions = savedOptions['multiple-choice'] || [];
            
            let optionsHtml = `
                <div class="cc-editor-question-options">
                    <label>Options</label>`;
            
            if (savedMCOptions.length > 0) {
                savedMCOptions.forEach((option, index) => {
                    optionsHtml += `
                        <div class="cc-editor-option">
                            <input type="text" name="quiz[questions][${questionId}][options][${index}]" value="${option.text || ''}" class="cc-option-text">
                            <div class="cc-editor-option-image">
                                ${option.image ? 
                                    `<div class="cc-option-image-preview">
                                        <img src="${getImageUrl(option.image)}" alt="Option image">
                                    </div>` : ''}
                                <button type="button" class="button cc-option-image-btn" data-question-id="${questionId}" data-option-id="${index}">
                                    ${option.image ? 'Change Image' : 'Add Image'}
                                </button>
                                ${option.image ? 
                                    `<button type="button" class="cc-option-image-remove-btn" data-question-id="${questionId}" data-option-id="${index}">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                            <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                                        </svg>
                                    </button>` : ''}
                                <input type="hidden" name="quiz[questions][${questionId}][image_options][${index}]" value="${option.image || ''}">
                            </div>
                            <label class="cc-correct-option">
                                <input type="radio" name="quiz[questions][${questionId}][correctAnswer]" value="${index}" ${option.isCorrect ? 'checked' : ''}>
                                Correct
                            </label>
                            ${index > 1 ? 
                                `<button type="button" class="cc-remove-option-btn" data-question-id="${questionId}" data-option-id="${index}">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                        <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                                    </svg>
                                </button>` : ''}
                        </div>`;
                });
            } else {
                // Default options if none saved
                for (let i = 0; i < 2; i++) {
                    optionsHtml += `
                        <div class="cc-editor-option">
                            <input type="text" name="quiz[questions][${questionId}][options][${i}]" value="" placeholder="Option ${i + 1}" class="cc-option-text">
                            <div class="cc-editor-option-image">
                                <button type="button" class="button cc-option-image-btn" data-question-id="${questionId}" data-option-id="${i}">
                                    Add Image
                                </button>
                                <input type="hidden" name="quiz[questions][${questionId}][image_options][${i}]" value="">
                            </div>
                            <label class="cc-correct-option">
                                <input type="radio" name="quiz[questions][${questionId}][correctAnswer]" value="${i}" ${i === 0 ? 'checked' : ''}>
                                Correct
                            </label>
                        </div>`;
                }
            }
            
            optionsHtml += `
                    <button type="button" class="button cc-add-option-btn" data-question-id="${questionId}">Add Option</button>
                </div>`;
            
            $content.append(optionsHtml);
            
        } else if (newType === 'true-false') {
            const savedTFData = savedOptions['true-false'] || { correctAnswer: 0 };
            const correctAnswer = savedTFData.correctAnswer;
            
            $content.append(`
                <div class="cc-editor-question-tf">
                    <label>Correct Answer</label>
                    <div class="cc-editor-tf-options">
                        <label>
                            <input type="radio" name="quiz[questions][${questionId}][correctAnswer]" value="0" ${correctAnswer === 0 ? 'checked' : ''}>
                            True
                        </label>
                        <label>
                            <input type="radio" name="quiz[questions][${questionId}][correctAnswer]" value="1" ${correctAnswer === 1 ? 'checked' : ''}>
                            False
                        </label>
                    </div>
                </div>`);
        } else if (newType === 'image-choice') {
            const savedImageOptions = savedOptions['image-choice'] || [];
            
            let imageChoiceHtml = `
                <div class="cc-editor-question-image-options">
                    <label>Image Options</label>
                    <div class="cc-editor-image-options-list">`;
            
            if (savedImageOptions.length > 0) {
                savedImageOptions.forEach((option, index) => {
                    imageChoiceHtml += createImageOptionHtml(questionId, index, option.image, option.isCorrect);
                });
            } else {
                imageChoiceHtml += `<p class="cc-no-image-options">No image options added yet. Click "Add Image Option" to begin.</p>`;
            }
            
            imageChoiceHtml += `
                    </div>
                    <button type="button" class="button cc-add-image-option-btn" data-question-id="${questionId}">Add Image Option</button>
                </div>`;
            
            $content.append(imageChoiceHtml);
            
            // If we added options, reindex to ensure buttons are correct
            if (savedImageOptions.length > 0) {
                reindexImageOptions(questionId);
            }
        }
    }

    // Helper function to create image option HTML
    function createImageOptionHtml(questionId, optionIndex, imageFilename, isCorrect) {
        const imageUrl = getImageUrl(imageFilename);
        
        return `
            <div class="cc-editor-image-option" data-option-id="${optionIndex}">
                <div class="cc-image-option-preview">
                    <img src="${imageUrl}" alt="Option ${optionIndex + 1}">
                </div>
                <div class="cc-image-option-controls">
                    <label class="cc-correct-option">
                        <input type="radio" name="quiz[questions][${questionId}][correctAnswer]" value="${optionIndex}" ${isCorrect ? 'checked' : ''}>
                        Correct
                    </label>
                    <div class="cc-image-option-actions">
                        ${optionIndex > 0 ? 
                            `<button type="button" class="cc-move-image-option-left" data-question-id="${questionId}" data-option-id="${optionIndex}" title="Move Left">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                    <path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/>
                                </svg>
                            </button>` : ''}
                        <button type="button" class="cc-move-image-option-right" data-question-id="${questionId}" data-option-id="${optionIndex}" title="Move Right">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                <path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/>
                            </svg>
                        </button>
                        <button type="button" class="cc-remove-image-option-btn" data-question-id="${questionId}" data-option-id="${optionIndex}" title="Remove Image">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                <path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <input type="hidden" name="quiz[questions][${questionId}][image_options][${optionIndex}]" value="${imageFilename}">
            </div>`;
    }

    // Move image option left
    function moveImageOptionLeft($button) {
        const $currentOption = $button.closest('.cc-editor-image-option');
        const $prevOption = $currentOption.prev('.cc-editor-image-option');
        
        if ($prevOption.length > 0) {
            const questionId = $button.data('question-id');
            $currentOption.insertBefore($prevOption);
            reindexImageOptions(questionId);
        }
    }

    // Move image option right
    function moveImageOptionRight($button) {
        const $currentOption = $button.closest('.cc-editor-image-option');
        const $nextOption = $currentOption.next('.cc-editor-image-option');
        
        if ($nextOption.length > 0) {
            const questionId = $button.data('question-id');
            $currentOption.insertAfter($nextOption);
            reindexImageOptions(questionId);
        }
    }

    // Reindex image options after reordering
    function reindexImageOptions(questionId) {
        const $question = $(`.cc-editor-question[data-question-id="${questionId}"]`);
        const $options = $question.find('.cc-editor-image-option');
        const totalOptions = $options.length;
        
        $options.each(function(newIndex) {
            const $option = $(this);
            // Update the option's data-option-id
            $option.attr('data-option-id', newIndex);
            
            // Find all elements within this option that need updating
            const $radioInput = $option.find('input[type="radio"]');
            const $hiddenInput = $option.find('input[type="hidden"]');
            const $leftButton = $option.find('.cc-move-image-option-left');
            const $rightButton = $option.find('.cc-move-image-option-right');
            const $removeButton = $option.find('.cc-remove-image-option-btn');
            
            // Update radio input
            $radioInput.attr('value', newIndex);
            
            // Update hidden input
            $hiddenInput.attr('name', `quiz[questions][${questionId}][image_options][${newIndex}]`);
            
            // Update button data attributes
            if ($leftButton.length) {
                $leftButton.attr('data-option-id', newIndex);
                $leftButton.attr('data-question-id', questionId);
            }
            
            if ($rightButton.length) {
                $rightButton.attr('data-option-id', newIndex);
                $rightButton.attr('data-question-id', questionId);
            }
            
            if ($removeButton.length) {
                $removeButton.attr('data-option-id', newIndex);
                $removeButton.attr('data-question-id', questionId);
            }
            
            // Handle Move Left button visibility
            if (newIndex === 0) {
                // First item - remove left button if it exists
                $leftButton.remove();
            } else {
                // Not first item - ensure left button exists
                if ($leftButton.length === 0) {
                    const leftButtonHtml = `
                        <button type="button" class="cc-move-image-option-left" data-question-id="${questionId}" data-option-id="${newIndex}" title="Move Left">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                <path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/>
                            </svg>
                        </button>`;
                    
                    // Insert left button at the beginning of the actions div
                    const $actionsDiv = $option.find('.cc-image-option-actions');
                    $actionsDiv.prepend(leftButtonHtml);
                }
            }
            
            // Handle Move Right button visibility
            if (newIndex === totalOptions - 1) {
                // Last item - remove right button if it exists
                $rightButton.remove();
            } else {
                // Not last item - ensure right button exists
                if ($rightButton.length === 0) {
                    const rightButtonHtml = `
                        <button type="button" class="cc-move-image-option-right" data-question-id="${questionId}" data-option-id="${newIndex}" title="Move Right">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                <path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/>
                            </svg>
                        </button>`;
                    
                    // Insert right button before the remove button
                    const $removeBtn = $option.find('.cc-remove-image-option-btn');
                    if ($removeBtn.length > 0) {
                        $removeBtn.before(rightButtonHtml);
                    } else {
                        $option.find('.cc-image-option-actions').append(rightButtonHtml);
                    }
                }
            }
        });
    }


    // Remove an image option from image-choice question
    function removeImageOption(questionId, optionId) {
        const $question = $(`.cc-editor-question[data-question-id="${questionId}"]`);
        const $options = $question.find('.cc-editor-image-option');
        
        // Need at least 1 option for image choice questions
        if ($options.length <= 1) {
            alert('An image choice question must have at least 1 option.');
            return;
        }
        
        // Check if removing the correct answer
        const $optionToRemove = $options.filter(`[data-option-id="${optionId}"]`);
        const isCorrect = $optionToRemove.find('input[type="radio"]').prop('checked');
        
        // Remove the option
        $optionToRemove.remove();
        
        // If removed the correct answer, set the first option as correct
        if (isCorrect) {
            $question.find('.cc-editor-image-option').first().find('input[type="radio"]').prop('checked', true);
        }
        
        // Reindex the remaining options
        reindexImageOptions(questionId);
        
        // If no options left, show the "no options" message
        if ($question.find('.cc-editor-image-option').length === 0) {
            $question.find('.cc-editor-image-options-list').html('<p class="cc-no-image-options">No image options added yet. Click "Add Image Option" to begin.</p>');
        }
    }

    // Open image uploader for a multiple-choice option
    function openOptionImageUploader(questionId, optionId) {
        // Create a media frame if we don't have one
        if (!STATE.option_image_frame) {
            STATE.option_image_frame = wp.media({
                title: 'Select Image for Option',
                button: {
                    text: 'Use this image'
                },
                multiple: false,
                library: {
                    type: 'image'
                }
            });
        }
        
        // Store the question and option ID being edited
        STATE.current_question_id = questionId;
        STATE.current_option_id = optionId;
        
        // When an image is selected
        STATE.option_image_frame.off('select').on('select', function() {
            const attachment = STATE.option_image_frame.state().get('selection').first().toJSON();
            
            // Get the question and option elements
            const $question = $(`.cc-editor-question[data-question-id="${STATE.current_question_id}"]`);
            const $option = $question.find(`.cc-editor-option:eq(${STATE.current_option_id})`);
            const $imageContainer = $option.find('.cc-editor-option-image');
            
            // Remove existing preview if any
            $imageContainer.find('.cc-option-image-preview').remove();
            
            // Add image preview
            $imageContainer.prepend(`
                <div class="cc-option-image-preview">
                    <img src="${attachment.url}" alt="Option image">
                </div>
            `);
            
            // Update the button text
            $imageContainer.find('.cc-option-image-btn').text('Change Image');
            
            // Add remove button if it doesn't exist
            if ($imageContainer.find('.cc-option-image-remove-btn').length === 0) {
                $imageContainer.append(`
                    <button type="button" class="cc-option-image-remove-btn" data-question-id="${STATE.current_question_id}" data-option-id="${STATE.current_option_id}">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                            <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                        </svg>
                    </button>
                `);
            }
            
            // Store the filename in the hidden input
            $imageContainer.find('input[type="hidden"]').val(attachment.filename);
        });
        
        // Open the media frame
        STATE.option_image_frame.open();
    }

    // Remove Image
    function removeOptionImage(questionId, optionId) {
        // Get the question and option elements
        const $question = $(`.cc-editor-question[data-question-id="${questionId}"]`);
        const $option = $question.find(`.cc-editor-option:eq(${optionId})`);
        const $imageContainer = $option.find('.cc-editor-option-image');
        
        // Remove image preview
        $imageContainer.find('.cc-option-image-preview').remove();
        
        // Update the button text
        $imageContainer.find('.cc-option-image-btn').text('Add Image');
        
        // Remove the remove button
        $imageContainer.find('.cc-option-image-remove-btn').remove();
        
        // Clear the hidden input
        $imageContainer.find('input[type="hidden"]').val('');
    }

    function openNewImageOptionUploader(questionId) {
        // Create a media frame if we don't have one
        if (!STATE.image_option_frame) {
            STATE.image_option_frame = wp.media({
                title: 'Select Image for Quiz Option',
                button: {
                    text: 'Use this image'
                },
                multiple: false,
                library: {
                    type: 'image'
                }
            });
        }
        
        // Store the question ID being edited
        STATE.current_question_id = questionId;
        
        // When an image is selected
        STATE.image_option_frame.off('select').on('select', function() {
            const attachment = STATE.image_option_frame.state().get('selection').first().toJSON();
            
            // Get the question element
            const $question = $(`.cc-editor-question[data-question-id="${STATE.current_question_id}"]`);
            const $optionsList = $question.find('.cc-editor-image-options-list');
            
            // Remove the "no options" message if it exists
            $optionsList.find('.cc-no-image-options').remove();
            
            // Get the new option index
            const optionIndex = $optionsList.children('.cc-editor-image-option').length;
            
            // Create image option HTML with proper move buttons and no "Change Image" button
            const imageOptionHtml = createImageOptionHtml(STATE.current_question_id, optionIndex, attachment.filename, false);
            
            // Add to UI
            $optionsList.append(imageOptionHtml);
            
            // Reindex to ensure all buttons are properly set
            reindexImageOptions(STATE.current_question_id);
        });
        
        // Open the media frame
        STATE.image_option_frame.open();
    }

    // Delete a question
    function deleteQuestion(questionId) {
        if (confirm('Are you sure you want to delete this question?')) {
            // Remove the question from the UI
            $(`.cc-editor-question[data-question-id="${questionId}"]`).remove();
            
            // Renumber the questions
            $('.cc-editor-question').each(function(newIndex) {
                const $question = $(this);
                $question.attr('data-question-id', newIndex);
                $question.find('.cc-editor-question-title h6').text(`Question ${newIndex + 1}`);
                
                // Update input names and data attributes
                $question.find('textarea[id^="question-text-"]').attr({
                    'id': `question-text-${newIndex}`,
                    'name': `quiz[questions][${newIndex}][question]`
                });
                
                $question.find('select[id^="question-type-"]').attr({
                    'id': `question-type-${newIndex}`,
                    'name': `quiz[questions][${newIndex}][type]`,
                    'data-question-id': newIndex
                });
                
                // Update options or tf inputs
                if ($question.find('.cc-editor-question-options').length) {
                    $question.find('.cc-editor-option').each(function(optIndex) {
                        $(this).find('input[type="text"]').attr('name', `quiz[questions][${newIndex}][options][${optIndex}]`);
                        $(this).find('input[type="radio"]').attr('name', `quiz[questions][${newIndex}][correctAnswer]`);
                    });
                    
                    $question.find('.cc-add-option-btn').attr('data-question-id', newIndex);
                    $question.find('.cc-remove-option-btn').attr('data-question-id', newIndex);
                } else if ($question.find('.cc-editor-question-tf').length) {
                    $question.find('input[type="radio"]').attr('name', `quiz[questions][${newIndex}][correctAnswer]`);
                }
                
                $question.find('.cc-delete-question-btn').attr('data-question-id', newIndex);
            });
            
            // Show "no questions" message if no questions left
            if ($('.cc-editor-question').length === 0) {
                $('#cc-editor-questions').html('<p class="cc-editor-no-questions">No questions available. Click "Add Question" to create one.</p>');
            }
        }
    }

    // Delete a chapter
    function deleteChapter(chapterId) {
        if (confirm('Are you sure you want to delete this chapter and all its sections?')) {
            // Remove the chapter from the UI
            $(`.cc-editor-chapter[data-chapter-id="${chapterId}"]`).remove();
            
            // Renumber the chapters
            $('.cc-editor-chapter').each(function(newIndex) {
                const $chapter = $(this);
                $chapter.attr('data-chapter-id', newIndex);
                $chapter.find('.cc-editor-chapter-title h5').text(`Chapter ${newIndex + 1}`);
                
                // Update input names and data attributes
                $chapter.find('input[id^="chapter-title-"]').attr({
                    'id': `chapter-title-${newIndex}`,
                    'name': `chapters[${newIndex}][title]`
                });
                
                $chapter.find('textarea[id^="chapter-intro-"]').attr({
                    'id': `chapter-intro-${newIndex}`,
                    'name': `chapters[${newIndex}][introduction]`
                });
                
                // Update chapter action buttons
                $chapter.find('.cc-move-chapter-up, .cc-move-chapter-down, .cc-delete-chapter-btn')
                    .attr('data-chapter-id', newIndex);
                
                // Update add section button
                $chapter.find('.cc-add-section-btn').attr('data-chapter-id', newIndex);
                
                // Update sections
                $chapter.find('.cc-editor-section-item').each(function(sectionIndex) {
                    const $section = $(this);
                    $section.attr({
                        'data-chapter-id': newIndex,
                        'data-section-id': sectionIndex
                    });
                    
                    $section.find('.cc-editor-section-title h6').text(`Section ${sectionIndex + 1}`);
                    
                    // Update section input names and data attributes
                    $section.find('input[id^="section-title-"]').attr({
                        'id': `section-title-${newIndex}-${sectionIndex}`,
                        'name': `chapters[${newIndex}][sections][${sectionIndex}][title]`
                    });
                    
                    $section.find('select[id^="section-image-"]').attr({
                        'id': `section-image-${newIndex}-${sectionIndex}`,
                        'name': `chapters[${newIndex}][sections][${sectionIndex}][image]`,
                        'data-chapter-id': newIndex,
                        'data-section-id': sectionIndex
                    });
                    
                    $section.find('textarea[id^="section-content-"]').attr({
                        'id': `section-content-${newIndex}-${sectionIndex}`,
                        'name': `chapters[${newIndex}][sections][${sectionIndex}][content]`
                    });
                    
                    // Update section action buttons
                    $section.find('.cc-move-section-up, .cc-move-section-down, .cc-delete-section-btn').attr({
                        'data-chapter-id': newIndex,
                        'data-section-id': sectionIndex
                    });
                });
            });
            
            // Show "no chapters" message if no chapters left
            if ($('.cc-editor-chapter').length === 0) {
                $('#cc-editor-chapters').html('<p class="cc-editor-no-chapters">No chapters available. Click "Add Chapter" to create one.</p>');
            }
        }
    }

    // Delete a section
    function deleteSection(chapterId, sectionId) {
        if (confirm('Are you sure you want to delete this section?')) {
            // Remove the section from the UI
            $(`.cc-editor-section-item[data-chapter-id="${chapterId}"][data-section-id="${sectionId}"]`).remove();
            
            // Renumber the sections in this chapter
            $(`.cc-editor-chapter[data-chapter-id="${chapterId}"] .cc-editor-section-item`).each(function(newIndex) {
                const $section = $(this);
                $section.attr('data-section-id', newIndex);
                $section.find('.cc-editor-section-title h6').text(`Section ${newIndex + 1}`);
                
                // Update input names and data attributes
                $section.find('input[id^="section-title-"]').attr({
                    'id': `section-title-${chapterId}-${newIndex}`,
                    'name': `chapters[${chapterId}][sections][${newIndex}][title]`
                });
                
                $section.find('select[id^="section-image-"]').attr({
                    'id': `section-image-${chapterId}-${newIndex}`,
                    'name': `chapters[${chapterId}][sections][${newIndex}][image]`,
                    'data-chapter-id': chapterId,
                    'data-section-id': newIndex
                });
                
                $section.find('textarea[id^="section-content-"]').attr({
                    'id': `section-content-${chapterId}-${newIndex}`,
                    'name': `chapters[${chapterId}][sections][${newIndex}][content]`
                });
                
                // Update section action buttons
                $section.find('.cc-move-section-up, .cc-move-section-down, .cc-delete-section-btn').attr({
                    'data-chapter-id': chapterId,
                    'data-section-id': newIndex
                });
            });
            
            // Show "no sections" message if no sections left
            if ($(`.cc-editor-chapter[data-chapter-id="${chapterId}"] .cc-editor-section-item`).length === 0) {
                $(`.cc-editor-chapter[data-chapter-id="${chapterId}"] .cc-editor-sections-list`)
                    .html('<p class="cc-editor-no-sections">No sections available. Click "Add Section" to create one.</p>');
            }
        }
    }

    // Move a chapter up or down
    function moveChapter(chapterId, direction) {
        const $chapter = $(`.cc-editor-chapter[data-chapter-id="${chapterId}"]`);
        const $chapters = $('.cc-editor-chapter');
        const index = parseInt(chapterId);
        
        if (direction === 'up' && index > 0) {
            // Swap with previous chapter
            $chapter.insertBefore($chapters.eq(index - 1));
        } else if (direction === 'down' && index < $chapters.length - 1) {
            // Swap with next chapter
            $chapter.insertAfter($chapters.eq(index + 1));
        } else {
            return; // Cannot move
        }
        
        // Renumber all chapters
        $('.cc-editor-chapter').each(function(newIndex) {
            const $chapter = $(this);
            $chapter.attr('data-chapter-id', newIndex);
            
            // Update the chapter number while preserving the title
            const $titleElement = $chapter.find('.cc-editor-chapter-title h5');
            const currentTitle = $titleElement.text();
            const titleText = currentTitle.replace(/^Chapter \d+: /, ''); // Remove old chapter number
            $titleElement.text(`Chapter ${newIndex + 1}: ${titleText}`);
            
            // Update input names and data attributes
            $chapter.find('input[id^="chapter-title-"]').attr({
                'id': `chapter-title-${newIndex}`,
                'name': `chapters[${newIndex}][title]`
            });
            
            $chapter.find('textarea[id^="chapter-intro-"]').attr({
                'id': `chapter-intro-${newIndex}`,
                'name': `chapters[${newIndex}][introduction]`
            });
            
            // Update chapter action buttons
            $chapter.find('.cc-toggle-chapter').attr('data-chapter-id', newIndex);
            $chapter.find('.cc-move-chapter-up, .cc-move-chapter-down, .cc-delete-chapter-btn')
                .attr('data-chapter-id', newIndex);
            
            // Update add section button
            $chapter.find('.cc-add-section-btn').attr('data-chapter-id', newIndex);
            
            // Update sections
            $chapter.find('.cc-editor-section-item').each(function(sectionIndex) {
                const $section = $(this);
                $section.attr({
                    'data-chapter-id': newIndex,
                    'data-section-id': sectionIndex
                });
                
                // Update the section number while preserving the title
                const $sectionTitleElement = $section.find('.cc-editor-section-title h6');
                const currentSectionTitle = $sectionTitleElement.text();
                const sectionTitleText = currentSectionTitle.replace(/^Section \d+: /, ''); // Remove old section number
                $sectionTitleElement.text(`Section ${sectionIndex + 1}: ${sectionTitleText}`);
                
                // Update section input names and data attributes
                $section.find('input[id^="section-title-"]').attr({
                    'id': `section-title-${newIndex}-${sectionIndex}`,
                    'name': `chapters[${newIndex}][sections][${sectionIndex}][title]`
                });
                
                $section.find('select[id^="section-image-"]').attr({
                    'id': `section-image-${newIndex}-${sectionIndex}`,
                    'name': `chapters[${newIndex}][sections][${sectionIndex}][image]`,
                    'data-chapter-id': newIndex,
                    'data-section-id': sectionIndex
                });
                
                $section.find('textarea[id^="section-content-"]').attr({
                    'id': `section-content-${newIndex}-${sectionIndex}`,
                    'name': `chapters[${newIndex}][sections][${sectionIndex}][content]`
                });
                
                // Update section action buttons
                $section.find('.cc-toggle-section').attr({
                    'data-chapter-id': newIndex,
                    'data-section-id': sectionIndex
                });
                $section.find('.cc-move-section-up, .cc-move-section-down, .cc-delete-section-btn').attr({
                    'data-chapter-id': newIndex,
                    'data-section-id': sectionIndex
                });
            });
        });
    }

    // Move a section up or down
    function moveSection(chapterId, sectionId, direction) {
        const $section = $(`.cc-editor-section-item[data-chapter-id="${chapterId}"][data-section-id="${sectionId}"]`);
        const $sections = $(`.cc-editor-chapter[data-chapter-id="${chapterId}"] .cc-editor-section-item`);
        const index = parseInt(sectionId);
        
        if (direction === 'up' && index > 0) {
            // Swap with previous section
            $section.insertBefore($sections.eq(index - 1));
        } else if (direction === 'down' && index < $sections.length - 1) {
            // Swap with next section
            $section.insertAfter($sections.eq(index + 1));
        } else {
            return; // Cannot move
        }
        
        // Renumber all sections in this chapter
        $(`.cc-editor-chapter[data-chapter-id="${chapterId}"] .cc-editor-section-item`).each(function(newIndex) {
            const $section = $(this);
            $section.attr('data-section-id', newIndex);
            
            // Update the section number while preserving the title
            const $titleElement = $section.find('.cc-editor-section-title h6');
            const currentTitle = $titleElement.text();
            const titleText = currentTitle.replace(/^Section \d+: /, ''); // Remove old section number
            $titleElement.text(`Section ${newIndex + 1}: ${titleText}`);
            
            // Update input names and data attributes
            $section.find('input[id^="section-title-"]').attr({
                'id': `section-title-${chapterId}-${newIndex}`,
                'name': `chapters[${chapterId}][sections][${newIndex}][title]`
            });
            
            $section.find('select[id^="section-image-"]').attr({
                'id': `section-image-${chapterId}-${newIndex}`,
                'name': `chapters[${chapterId}][sections][${newIndex}][image]`,
                'data-chapter-id': chapterId,
                'data-section-id': newIndex
            });
            
            $section.find('textarea[id^="section-content-"]').attr({
                'id': `section-content-${chapterId}-${newIndex}`,
                'name': `chapters[${chapterId}][sections][${newIndex}][content]`
            });
            
            // Update section action buttons
            $section.find('.cc-toggle-section').attr({
                'data-chapter-id': chapterId,
                'data-section-id': newIndex
            });
            $section.find('.cc-move-section-up, .cc-move-section-down, .cc-delete-section-btn').attr({
                'data-chapter-id': chapterId,
                'data-section-id': newIndex
            });
        });
    }
    // Update the image for a section
    function updateSectionImage(chapterId, sectionId, imageFilename) {
        const $section = $(`.cc-editor-section-item[data-chapter-id="${chapterId}"][data-section-id="${sectionId}"]`);
        const $imageField = $section.find('.cc-editor-field-image');
        
        // Remove existing preview if any
        $imageField.find('.cc-section-image-preview').remove();
        
        // Add preview if image selected
        if (imageFilename) {
            const imageUrl = getImageUrl(imageFilename);
            const $preview = $(`
                <div class="cc-section-image-preview">
                    <img src="${imageUrl}" alt="Section image">
                </div>
            `);
            
            $imageField.append($preview);
        }
    }

    // Save edited course
    function saveEdits() {
        // Update all WYSIWYG editors content to their textareas before saving
        $('.cc-wp-editor-container textarea').each(function() {
            const editorId = $(this).attr('id');
            if (typeof tinyMCE !== 'undefined' && tinyMCE.get(editorId)) {
                // Save the content back to the textarea
                tinyMCE.get(editorId).save();
                
                // Process the content to convert full URLs back to filenames
                let content = $(this).val();
                if (content && typeof content === 'string') {
                    // Convert full URLs back to filenames
                    const baseUrl = getImageUrl('').replace(/\/$/, ''); // Get base URL without trailing slash
                    content = content.replace(new RegExp(`<img\\s+([^>]*?)src=(["'])${baseUrl}/([^"']+)(["'])([^>]*?)>`, 'gi'), 
                        function(match, prefix, quote1, filename, quote2, suffix) {
                            return `<img ${prefix}src=${quote1}${filename}${quote2}${suffix}>`;
                        }
                    );
                    
                    $(this).val(content);
                }
            }
        });
        
        // Show saving indicator
        $('.cc-editor-status').html('<span class="spinner is-active"></span> Saving changes...');
        
        // Serialize form data
        const formData = new FormData($('#cc-editor-form')[0]);
        formData.append('action', 'cai_course_update');
        formData.append('nonce', caiAjax.nonce);
        
        // Add course ID
        formData.append('course_id', STATE.currentCourseId);
        
        // Send request to server
        $.ajax({
            url: caiAjax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    // Show success message
                    $('.cc-editor-status').html('<span class="cc-success">Changes saved successfully!</span>');
                    
                    // Reset status after delay
                    setTimeout(function() {
                        $('.cc-editor-status').html('');
                    }, 3000);
                    
                    // Refresh courses list
                    fetchCourses();
                } else {
                    // Show error message
                    $('.cc-editor-status').html('<span class="cc-error">Error: ' + (response.data || 'Failed to save changes.') + '</span>');
                }
            },
            error: function(xhr, status, error) {
                // Show error message
                $('.cc-editor-status').html('<span class="cc-error">Error: Failed to save changes. Please try again.</span>');
                console.error('Save edits error:', status, error);
            }
        });
    }

    // Confirm course deletion
    function confirmDeleteCourse(courseId, courseTitle) {
        STATE.currentCourseId = courseId;
        $('.confirm-delete-message').html(`Are you sure you want to delete the course <strong>"${courseTitle}"</strong>? This action cannot be undone.`);
        $('#confirm-delete-modal').show();
    }

    // Delete a course
    function deleteCourse(courseId) {
        if (!courseId) return;
        
        // Close the modal
        closeModals();
        
        // Send request to server
        $.ajax({
            url: caiAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'cai_course_delete',
                nonce: caiAjax.nonce,
                courseId: courseId
            },
            success: function(response) {
                if (response.success) {
                
                    // Refresh courses list
                    fetchCourses();
                } else {
                    // Show error
                    alert('Error deleting course: ' + (response.data || 'Unknown error occurred.'));
                }
            },
            error: function(xhr, status, error) {
                // Show error
                console.error('Delete error:', status, error);
            }
        });
    }

    // Close all modals
    function closeModals() {
        $('.cc-modal').hide();
    }

    // Get full URL for a course image
    function getImageUrl(imageInput, size = 'full') {
        if (!imageInput) {
            return plugin_dir_url + 'assets/course-thumbnail-placeholder.jpg';
        }
        
        // If it's already a full URL, return it
        if (imageInput.startsWith('http://') || imageInput.startsWith('https://') || imageInput.startsWith('/')) {
            return imageInput;
        }
        
        // For attachment IDs or filenames, we need to resolve through the server
        // Check if we have a cached result first
        const cacheKey = 'image_url_' + imageInput;
        if (STATE.imageUrlCache && STATE.imageUrlCache[cacheKey]) {
            return STATE.imageUrlCache[cacheKey];
        }
        
        // If it's a numeric attachment ID, try WordPress media first
        if (/^\d+$/.test(imageInput) && wp && wp.media && wp.media.attachment) {
            const attachment = wp.media.attachment(imageInput);
            if (attachment.get('url')) {
                const url = attachment.get('url');
                // Cache the result
                if (!STATE.imageUrlCache) STATE.imageUrlCache = {};
                STATE.imageUrlCache[cacheKey] = url;
                return url;
            }
        }
        
        // Make synchronous AJAX call to get URL from server
        // Note: This should ideally be async, but for simplicity using sync
        let resolvedUrl = plugin_dir_url + 'assets/course-thumbnail-placeholder.jpg';
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cai_get_image_url',
                image_input: imageInput,
                nonce: caiAjax.nonce
            },
            async: false, // Synchronous call
            success: function(response) {
                if (response.success && response.data.url) {
                    resolvedUrl = response.data.url;
                    // Cache the result
                    if (!STATE.imageUrlCache) STATE.imageUrlCache = {};
                    STATE.imageUrlCache[cacheKey] = resolvedUrl;
                }
            },
            error: function() {
                console.log('Failed to resolve image URL for: ' + imageInput);
            }
        });
        
        return resolvedUrl;
    }

    // Utility function to truncate text
    function truncateText(text, maxLength) {
        if (!text) return '';
        if (text.length <= maxLength) return text;
        return text.substring(0, maxLength) + '...';
    }

    // Expose public functions
    window.CreatorAI = window.CreatorAI || {};
    window.CreatorAI.CourseCreator = {
        init: initCourseCreator,
        fetchCourses: fetchCourses,
        viewCourse: viewCourse,
        resetForm: resetForm
    };

})(jQuery);