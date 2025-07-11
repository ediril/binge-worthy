// JavaScript for Binge-Worthy platform

/**
 * Open a video in a new tab
 * @param {string} videoUrl - The URL of the video to open
 */
function openVideo(videoUrl) {
    if (videoUrl) {
        window.open(videoUrl, '_blank');
    }
}

/**
 * Initialize the application
 */
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips if Bootstrap is loaded
    if (typeof bootstrap !== 'undefined') {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
    
    // Initialize thumbnail error handling
    initializeThumbnailErrorHandling();
    
    // Add smooth scrolling for video rows
    const videoRows = document.querySelectorAll('.video-row');
    videoRows.forEach(row => {
        row.addEventListener('wheel', function(e) {
            // Only apply horizontal scrolling if the user is hovering over the video row
            // and there's horizontal overflow to scroll
            if (e.deltaY !== 0 && this.scrollWidth > this.clientWidth) {
                // Only prevent default if we're actually over the video row area
                const rect = this.getBoundingClientRect();
                if (e.clientX >= rect.left && e.clientX <= rect.right &&
                    e.clientY >= rect.top && e.clientY <= rect.bottom) {
                    e.preventDefault();
                    this.scrollLeft += e.deltaY;
                }
            }
        });
    });
    
    // Add click event listeners to video cards
    const videoCards = document.querySelectorAll('.video-card');
    videoCards.forEach(card => {
        card.addEventListener('click', function(e) {
            // Check if this is a video card with an onclick handler
            const img = this.querySelector('.video-thumbnail img');
            if (img && img.onclick) {
                img.onclick(e);
                return;
            }
            
            // Check if this is a channel card with an <a> tag
            const channelLink = this.querySelector('.video-thumbnail a');
            if (channelLink && channelLink.href) {
                window.open(channelLink.href, '_blank');
                return;
            }
        });
    });
    
    // Auto-dismiss alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });
    
    // Search form enhancement
    const searchForm = document.querySelector('form[action="search.php"]');
    if (searchForm) {
        const searchInput = searchForm.querySelector('input[name="q"]');
        if (searchInput) {
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    searchForm.submit();
                }
            });
        }
    }
});

/**
 * Utility function to format video duration
 * @param {number} seconds - Duration in seconds
 * @returns {string} Formatted duration string
 */
function formatDuration(seconds) {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const remainingSeconds = seconds % 60;
    
    if (hours > 0) {
        return `${hours}:${minutes.toString().padStart(2, '0')}:${remainingSeconds.toString().padStart(2, '0')}`;
    } else {
        return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
    }
}

/**
 * Utility function to truncate text
 * @param {string} text - Text to truncate
 * @param {number} maxLength - Maximum length
 * @returns {string} Truncated text
 */
function truncateText(text, maxLength) {
    if (text.length <= maxLength) {
        return text;
    }
    return text.substring(0, maxLength) + '...';
}

/**
 * Initialize thumbnail error handling
 */
function initializeThumbnailErrorHandling() {
    const thumbnailImages = document.querySelectorAll('.video-thumbnail img');
    
    thumbnailImages.forEach(img => {
        // Add loading placeholder
        img.addEventListener('load', function() {
            this.style.opacity = '1';
        });
        
        // Handle error loading
        img.addEventListener('error', function() {
            handleThumbnailError(this);
        });
        
        // Set initial opacity for loading effect
        img.style.opacity = '0.5';
        img.style.transition = 'opacity 0.3s ease';
    });
}

/**
 * Handle thumbnail loading errors with fallback options
 * @param {HTMLImageElement} img - The image element that failed to load
 */
function handleThumbnailError(img) {
    const originalSrc = img.src;
    const videoId = extractVideoIdFromThumbnail(originalSrc);
    
    if (videoId) {
        // Try different thumbnail resolutions in order
        const fallbackOptions = [
            `https://img.youtube.com/vi/${videoId}/hqdefault.jpg`,
            `https://img.youtube.com/vi/${videoId}/mqdefault.jpg`,
            `https://img.youtube.com/vi/${videoId}/default.jpg`
        ];
        
        // Try the next available option
        for (let i = 0; i < fallbackOptions.length; i++) {
            if (originalSrc !== fallbackOptions[i]) {
                img.src = fallbackOptions[i];
                return;
            }
        }
    }
    
    // If all thumbnails fail, create a placeholder
    createThumbnailPlaceholder(img);
}

/**
 * Extract video ID from thumbnail URL
 * @param {string} thumbnailUrl - The thumbnail URL
 * @returns {string|null} The video ID or null
 */
function extractVideoIdFromThumbnail(thumbnailUrl) {
    const match = thumbnailUrl.match(/\/vi\/([a-zA-Z0-9_-]{11})\//);
    return match ? match[1] : null;
}

/**
 * Create a placeholder for failed thumbnails
 * @param {HTMLImageElement} img - The image element to replace
 */
function createThumbnailPlaceholder(img) {
    const placeholder = document.createElement('div');
    placeholder.className = 'thumbnail-placeholder';
    placeholder.style.cssText = `
        width: 100%;
        height: 180px;
        background: linear-gradient(135deg, #404040 0%, #2d2d2d 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 4px;
        color: #888;
        font-size: 2rem;
        font-weight: bold;
    `;
    placeholder.innerHTML = '🎬';
    
    // Replace the image with the placeholder
    img.parentNode.replaceChild(placeholder, img);
} 