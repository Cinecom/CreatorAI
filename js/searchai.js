/**
 * Search AI Block
 * 
 * Provides AI-powered search and chat functionality for WordPress sites.
 */

(function($) {
    'use strict';
    
    // Check if we're in the block editor (Gutenberg) environment
    const isBlockEditor = typeof wp !== 'undefined' && wp.blocks && wp.blockEditor;
    

    /**
     * Frontend functionality for Search AI with simple conversation history
     */
    function initFrontendSearchAI() {
        $(document).ready(function() {
            $('.searchai-container').each(function() {
                const $container = $(this);
                const $form = $container.find('.searchai-form');
                const $input = $container.find('.searchai-input');
                const $messages = $container.find('.searchai-messages');
                
                // Initialize chat history
                let chatHistory = [];
                
                // Handle form submission
                $form.on('submit', function(e) {
                    e.preventDefault();
                    
                    const query = $input.val().trim();
                    if (query === '') return;
                    
                    // Add user message to chat
                    addMessage('user', query);
                    
                    // Clear input
                    $input.val('');
                    
                    // Add loading message
                    const $loadingMessage = addMessage('ai', '<div class="searchai-loading"><span></span><span></span><span></span></div>');
                    
                    // Send AJAX request to WordPress
                    $.ajax({
                        url: searchAiData.ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'searchai_query',
                            nonce: searchAiData.nonce,
                            query: query,
                            history: JSON.stringify(chatHistory)
                        },
                        success: function(response) {
                            // Remove loading animation
                            $loadingMessage.remove();
                            
                            if (response.success) {
                                // Update chat history
                                if (response.data.history) {
                                    chatHistory = response.data.history;
                                }
                                
                                // Add AI response to chat
                                addMessage('ai', response.data.aiResponse);
                                
                                // Scroll to bottom of chat
                                scrollToBottom();
                            } else {
                                // Handle error
                                addMessage('ai', 'Sorry, I encountered an error. Please try again.');
                            }
                        },
                        error: function() {
                            // Remove loading animation
                            $loadingMessage.remove();
                            
                            // Add error message
                            addMessage('ai', 'Sorry, there was a problem connecting to the server. Please try again later.');
                        }
                    });
                });
                
                // Helper function to add message to chat
                function addMessage(sender, content) {
                    const $message = $('<div class="searchai-message"></div>');
                    $message.addClass(sender === 'user' ? 'searchai-message-user' : 'searchai-message-ai');
                    
                    // Add avatar
                    let avatarContent = '';
                    if (sender === 'user') {
                        avatarContent = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>';
                    } else {
                        // Check if custom avatar exists
                        const customAvatar = $container.data('avatar');
                        if (customAvatar) {
                            avatarContent = `<img src="${customAvatar}" alt="AI Avatar" />`;
                        } else {
                            avatarContent = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/></svg>';
                        }
                    }
                    
                    const $avatar = $('<div class="searchai-avatar"></div>').html(avatarContent);
                    $message.append($avatar);
                    
                    // Add content
                    const $content = $('<div class="searchai-message-content"></div>').html(content);
                    $message.append($content);
                    
                    // Add message to chat
                    $messages.append($message);
                    
                    // Scroll to bottom of chat
                    scrollToBottom();
                    
                    return $message;
                }
                
                // Helper function to scroll to bottom of chat
                function scrollToBottom() {
                    $messages.scrollTop($messages[0].scrollHeight);
                }
            });
        });
    }
    
    // Register Gutenberg block - only run in the editor
    if (isBlockEditor) {
        const { registerBlockType } = wp.blocks;
        const { InspectorControls, MediaUpload, MediaUploadCheck } = wp.blockEditor || wp.editor;
        const { PanelBody, TextControl, RangeControl, ColorPicker, Button } = wp.components;
        const { Fragment } = wp.element;
        const { createElement } = wp.element;
        
        console.log('Registering Creator AI Search block');
        
        // Register the block
        registerBlockType('creator-ai/searchai', {
            title: 'Search AI',
            icon: 'search',
            category: 'creator-ai', // This line is crucial for category assignment
            description: 'Add an AI-powered search assistant that helps users find content on your site.',
            
            attributes: {
                primaryColor: {
                    type: 'string',
                    default: '#4a6ee0'
                },
                secondaryColor: {
                    type: 'string',
                    default: '#f8f8f8'
                },
                textColor: {
                    type: 'string',
                    default: '#333333'
                },
                borderRadius: {
                    type: 'number',
                    default: 8
                },
                padding: {
                    type: 'number',
                    default: 20
                },
                maxHeight: {
                    type: 'number',
                    default: 500
                },
                placeholder: {
                    type: 'string',
                    default: 'Ask about video editing, VFX, or motion design...'
                },
                welcomeMessage: {
                    type: 'string',
                    default: 'Hi! I\'m your video editing assistant. Ask me anything about Premiere Pro, After Effects, or any creative video techniques.'
                },
                avatarImage: {
                    type: 'object',
                    default: {}
                }
            },
            
            edit: function(props) {
                const { attributes, setAttributes } = props;
                
                // Inline styles for preview
                const blockStyle = {
                    '--primary-color': attributes.primaryColor,
                    '--secondary-color': attributes.secondaryColor,
                    '--text-color': attributes.textColor,
                    '--border-radius': attributes.borderRadius + 'px',
                    '--padding': attributes.padding + 'px',
                    '--max-height': attributes.maxHeight + 'px'
                };
                
                // Create block controls
                const controls = createElement(
                    InspectorControls,
                    null,
                    createElement(
                        PanelBody,
                        { title: 'Appearance Settings', initialOpen: true },
                        createElement(
                            'div',
                            { className: 'searchai-color-picker-label' },
                            'Primary Color'
                        ),
                        createElement(ColorPicker, {
                            color: attributes.primaryColor,
                            onChangeComplete: function(color) {
                                setAttributes({ primaryColor: color.hex });
                            },
                            disableAlpha: true
                        }),
                        
                        createElement(
                            'div',
                            { className: 'searchai-color-picker-label' },
                            'Secondary Color'
                        ),
                        createElement(ColorPicker, {
                            color: attributes.secondaryColor,
                            onChangeComplete: function(color) {
                                setAttributes({ secondaryColor: color.hex });
                            },
                            disableAlpha: true
                        }),
                        
                        createElement(
                            'div',
                            { className: 'searchai-color-picker-label' },
                            'Text Color'
                        ),
                        createElement(ColorPicker, {
                            color: attributes.textColor,
                            onChangeComplete: function(color) {
                                setAttributes({ textColor: color.hex });
                            },
                            disableAlpha: true
                        }),
                        
                        createElement(RangeControl, {
                            label: 'Border Radius',
                            value: attributes.borderRadius,
                            onChange: function(value) {
                                setAttributes({ borderRadius: value });
                            },
                            min: 0,
                            max: 30
                        }),
                        
                        createElement(RangeControl, {
                            label: 'Padding',
                            value: attributes.padding,
                            onChange: function(value) {
                                setAttributes({ padding: value });
                            },
                            min: 0,
                            max: 50
                        }),
                        
                        createElement(RangeControl, {
                            label: 'Max Height',
                            value: attributes.maxHeight,
                            onChange: function(value) {
                                setAttributes({ maxHeight: value });
                            },
                            min: 200,
                            max: 1000,
                            step: 50
                        }),
                        
                        createElement(
                            'div',
                            { className: 'searchai-color-picker-label' },
                            'AI Avatar Image'
                        ),
                        createElement(
                            MediaUploadCheck,
                            null,
                            createElement(MediaUpload, {
                                onSelect: function(media) {
                                    setAttributes({ avatarImage: { id: media.id, url: media.url } });
                                },
                                type: 'image',
                                value: attributes.avatarImage.id,
                                render: function(obj) {
                                    return createElement(
                                        'div',
                                        null,
                                        attributes.avatarImage.url ? 
                                            createElement(
                                                'div',
                                                { style: { marginBottom: '10px' } },
                                                createElement('img', { 
                                                    src: attributes.avatarImage.url,
                                                    style: { 
                                                        maxWidth: '100px',
                                                        maxHeight: '100px',
                                                        borderRadius: '50%',
                                                        objectFit: 'cover'
                                                    }
                                                })
                                            ) : null,
                                        createElement(
                                            Button,
                                            {
                                                onClick: obj.open,
                                                isPrimary: true
                                            },
                                            attributes.avatarImage.url ? 'Change Avatar' : 'Upload Avatar'
                                        ),
                                        attributes.avatarImage.url ? 
                                            createElement(
                                                Button,
                                                {
                                                    onClick: function() {
                                                        setAttributes({ avatarImage: {} });
                                                    },
                                                    isSecondary: true,
                                                    style: { marginLeft: '8px' }
                                                },
                                                'Remove'
                                            ) : null
                                    );
                                }
                            })
                        )
                    ),
                    
                    createElement(
                        PanelBody,
                        { title: 'Content Settings', initialOpen: true },
                        createElement(TextControl, {
                            label: 'Input Placeholder',
                            value: attributes.placeholder,
                            onChange: function(value) {
                                setAttributes({ placeholder: value });
                            }
                        }),
                        
                        createElement(TextControl, {
                            label: 'Welcome Message',
                            value: attributes.welcomeMessage,
                            onChange: function(value) {
                                setAttributes({ welcomeMessage: value });
                            }
                        })
                    )
                );
                
                // Get AI avatar content
                const aiAvatarContent = attributes.avatarImage.url ? 
                    createElement('img', { 
                        src: attributes.avatarImage.url,
                        style: { width: '100%', height: '100%', objectFit: 'cover', borderRadius: '50%' }
                    }) :
                    createElement(
                        'svg',
                        { viewBox: '0 0 24 24', xmlns: 'http://www.w3.org/2000/svg' },
                        createElement('path', { d: 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z' })
                    );
                
                // Create block preview
                return createElement(
                    Fragment,
                    null,
                    controls,
                    createElement(
                        'div',
                        { className: 'searchai-container searchai-editor', style: blockStyle },
                        createElement(
                            'div',
                            { className: 'searchai-chat-container' },
                            createElement(
                                'div',
                                { className: 'searchai-messages' },
                                attributes.welcomeMessage && createElement(
                                    'div',
                                    { className: 'searchai-message searchai-message-ai' },
                                    createElement(
                                        'div',
                                        { className: 'searchai-avatar' },
                                        aiAvatarContent
                                    ),
                                    createElement(
                                        'div',
                                        { className: 'searchai-message-content' },
                                        attributes.welcomeMessage
                                    )
                                ),
                                
                                createElement(
                                    'div',
                                    { className: 'searchai-message searchai-message-user' },
                                    createElement(
                                        'div',
                                        { className: 'searchai-avatar' },
                                        createElement(
                                            'svg',
                                            { viewBox: '0 0 24 24', xmlns: 'http://www.w3.org/2000/svg' },
                                            createElement('path', { d: 'M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z' })
                                        )
                                    ),
                                    createElement(
                                        'div',
                                        { className: 'searchai-message-content' },
                                        'Sample user question about video editing'
                                    )
                                ),
                                
                                createElement(
                                    'div',
                                    { className: 'searchai-message searchai-message-ai' },
                                    createElement(
                                        'div',
                                        { className: 'searchai-avatar' },
                                        aiAvatarContent
                                    ),
                                    createElement(
                                        'div',
                                        { className: 'searchai-message-content' },
                                        'This is a sample response about video editing techniques.',
                                        createElement(
                                            'div',
                                            { className: 'searchai-relevant-links' },
                                            createElement(
                                                'strong',
                                                null,
                                                'Relevant resources:'
                                            ),
                                            createElement(
                                                'ul',
                                                null,
                                                createElement(
                                                    'li',
                                                    null,
                                                    createElement(
                                                        'a',
                                                        { href: '#' },
                                                        'Sample Article Title'
                                                    )
                                                ),
                                                createElement(
                                                    'li',
                                                    null,
                                                    createElement(
                                                        'a',
                                                        { href: '#' },
                                                        'Another Related Tutorial'
                                                    )
                                                )
                                            )
                                        )
                                    )
                                )
                            ),
                            
                            createElement(
                                'div',
                                { className: 'searchai-input-container' },
                                createElement(
                                    'form',
                                    { className: 'searchai-form' },
                                    createElement('input', {
                                        type: 'text',
                                        className: 'searchai-input',
                                        placeholder: attributes.placeholder,
                                        disabled: true
                                    }),
                                    createElement(
                                        'button',
                                        { type: 'button', className: 'searchai-submit', disabled: true },
                                        createElement(
                                            'svg',
                                            { xmlns: 'http://www.w3.org/2000/svg', viewBox: '0 0 24 24' },
                                            createElement('path', { d: 'M2.01 21L23 12 2.01 3 2 10l15 2-15 2z' })
                                        )
                                    )
                                )
                            )
                        )
                    )
                );
            },
            
            save: function() {
                // Use PHP render_callback instead
                return null;
            }
        });
    }
    
    // Initialize when document is ready
    $(document).ready(function() {
        // Only run frontend code if we're not in the block editor
        if (!isBlockEditor) {
            initFrontendSearchAI();
        }
    });
    
})(jQuery);