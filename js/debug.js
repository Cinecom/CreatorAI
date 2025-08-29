/**
 * Creator AI Debug Panel
 * Handles debug panel interactions and functionality
 */
(function($) {
    // Global variable to track existing debug IDs to avoid duplicates
    window.caiDebugIds = new Set();

    // Toggle the entire debug panel
    $(document).on('click', '.cai-debug-toggle', function() {
        $('.cai-debug-content').slideToggle(300);
        $(this).toggleClass('active');
    });
    
    // Toggle individual debug entries
    $(document).on('click', '.cai-debug-item-header', function() {
        const $item = $(this).closest('.cai-debug-item');
        $item.find('.cai-debug-item-content').slideToggle(200);
        
        // Change plus/minus icon
        const $toggle = $(this).find('.cai-debug-item-toggle');
        if($toggle.text() === '+') {
            $toggle.text('-');
        } else {
            $toggle.text('+');
        }
    });
    
    // Copy to clipboard functionality
    $(document).on('click', '.cai-debug-copy', function() {
        const $pre = $(this).siblings('pre');
        const textToCopy = $pre.text();
        
        // Create temporary element for copying
        const $temp = $('<textarea>');
        $('body').append($temp);
        $temp.val(textToCopy).select();
        document.execCommand('copy');
        $temp.remove();
        
        // Show success message
        $(this).text('Copied!');
        setTimeout(() => {
            $(this).text('Copy to clipboard');
        }, 2000);
    });
    
    // Clear debug data (AJAX)
    $(document).on('click', '.cai-debug-clear', function(e) {
        e.preventDefault();
        
        const $debugPanel = $('.cai-debug-panel');
        const $debugContent = $('.cai-debug-content');
        
        // Show loading state
        $debugContent.html('<div class="cai-debug-loading">Clearing debug data...</div>');
        
        // AJAX request to clear debug data
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cai_clear_debug_data',
                nonce: caiAjax.nonce
            },
            success: function(response) {
                // Update the panel with empty state
                $debugContent.html('<div class="cai-debug-empty">No debug data available yet. Make API requests to see them here.</div>');
                
                // Reset tracked IDs
                window.caiDebugIds = new Set();
            },
            error: function(xhr, status, error) {
                // Show error
                $debugContent.html('<div class="cai-debug-error">Error clearing debug data: ' + error + '</div>');
            }
        });
    });

    // Function to update debug panel with new data
    window.updateDebugPanel = function(debugData) {
        if (!debugData || !Array.isArray(debugData) || debugData.length === 0) {
            return;
        }
        
        const $content = $('.cai-debug-content');
        
        // Remove the empty state message if present
        $content.find('.cai-debug-empty').remove();
        
        // Generate a unique ID for each entry based on its content
        debugData.forEach(function(entry) {
            // Create a unique ID from the entry data
            const entryId = generateEntryId(entry);
            
            // Skip if we've already added this entry
            if (window.caiDebugIds.has(entryId)) {
                return;
            }
            
            // Mark as processed
            window.caiDebugIds.add(entryId);
            
            const timestamp = entry.timestamp ? new Date(entry.timestamp).toLocaleTimeString() : 'Unknown';
            const statusClass = entry.is_error ? 'error' : 'success';
            const apiName = entry.api || 'Unknown API';
            
            // Create HTML for the debug item
            const debugItemHtml = `
                <div class="cai-debug-item ${statusClass}">
                    <div class="cai-debug-item-header">
                        <span class="cai-debug-api">${escapeHtml(apiName)}</span>
                        <span class="cai-debug-time">${escapeHtml(timestamp)}</span>
                        <span class="cai-debug-item-toggle">+</span>
                    </div>
                    <div class="cai-debug-item-content" style="display:none;">
                        <pre>${escapeHtml(JSON.stringify(entry.data || {}, null, 2))}</pre>
                        <button class="cai-debug-copy">Copy to clipboard</button>
                    </div>
                </div>
            `;
            
            // Prepend to the content (newest first)
            $content.prepend(debugItemHtml);
        });
    };
    
    // Helper function to escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Generate a unique ID for a debug entry
    function generateEntryId(entry) {
        const data = JSON.stringify(entry.data || {});
        return entry.api + '-' + entry.timestamp + '-' + data.length;
    }
    
    // Function to manually check for debug updates
    window.checkForDebugUpdates = function() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cai_get_debug_data',
                nonce: caiAjax.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    window.updateDebugPanel(response.data);
                }
            }
        });
    };
    
    // Intercept all jQuery AJAX calls to update debug panel afterward
    $(document).ajaxComplete(function(event, xhr, settings) {
        // If we're on a page with the debug panel, check for updates
        if ($('.cai-debug-panel').length > 0) {
            // Small delay to ensure debug data is saved
            setTimeout(window.checkForDebugUpdates, 250);
        }
    });
    
    // Initialize on document ready
    $(document).ready(function() {
        // Initialize the tracked IDs set
        window.caiDebugIds = new Set();
        
        // Hide debug content by default for cleaner UI
        $('.cai-debug-content').hide();
        
        // Hide all debug item content by default
        $('.cai-debug-item-content').hide();
        
        // Process any initial debug items
        $('.cai-debug-item').each(function() {
            const entryId = generateEntryId({
                api: $(this).find('.cai-debug-api').text(),
                timestamp: $(this).find('.cai-debug-time').text(),
                data: $(this).find('pre').text()
            });
            window.caiDebugIds.add(entryId);
        });
        
        // Check for updates immediately, then every few seconds
        window.checkForDebugUpdates();
        setInterval(window.checkForDebugUpdates, 2000);
    });
    
})(jQuery);