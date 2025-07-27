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
            // Only apply horizontal scrolling if:
            // 1. There's horizontal overflow to scroll
            // 2. User is holding Shift key (common convention for horizontal scroll)
            // OR the row is at the scroll boundaries and user is trying to scroll further
            if (e.deltaY !== 0 && this.scrollWidth > this.clientWidth) {
                // Check if user is holding Shift for intentional horizontal scroll
                if (e.shiftKey) {
                    e.preventDefault();
                    this.scrollLeft += e.deltaY;
                    return;
                }
                
                // Check if we're at scroll boundaries and trying to scroll further
                const atLeftBoundary = this.scrollLeft <= 0 && e.deltaY < 0;
                const atRightBoundary = this.scrollLeft >= (this.scrollWidth - this.clientWidth) && e.deltaY > 0;
                
                // Only prevent default vertical scroll if we can actually scroll horizontally
                if (!atLeftBoundary && !atRightBoundary) {
                    // Only convert to horizontal scroll if the mouse is directly over the scrollable content
                    const rect = this.getBoundingClientRect();
                    if (e.clientX >= rect.left && e.clientX <= rect.right &&
                        e.clientY >= rect.top && e.clientY <= rect.bottom) {
                        // Allow some vertical scrolling, only convert excess to horizontal
                        if (Math.abs(e.deltaY) > 50) {
                            e.preventDefault();
                            this.scrollLeft += e.deltaY * 0.5;
                        }
                    }
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
    
    // Suggestion form handling
    const suggestionForm = document.getElementById('suggestionForm');
    if (suggestionForm) {
        suggestionForm.addEventListener('submit', handleSuggestionSubmit);
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
        
        // If image is already loaded, set opacity to 1
        if (img.complete && img.naturalHeight !== 0) {
            img.style.opacity = '1';
        }
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

/**
 * Handle suggestion form submission
 * @param {Event} e - Form submission event
 */
function handleSuggestionSubmit(e) {
    e.preventDefault();
    
    const form = e.target;
    const linkInput = document.getElementById('suggestionLink');
    const submitButton = document.getElementById('submitSuggestion');
    const submitText = submitButton.querySelector('.submit-text');
    const submitSpinner = submitButton.querySelector('.submit-spinner');
    
    const link = linkInput.value.trim();
    
    if (!link) {
        showSuggestionToast('Please enter a YouTube URL', 'error');
        return;
    }
    
    // Basic client-side validation
    if (!isValidYouTubeURL(link)) {
        showSuggestionToast('Please enter a valid YouTube channel or video URL', 'error');
        return;
    }
    
    // Show loading state
    submitButton.disabled = true;
    submitText.classList.add('d-none');
    submitSpinner.classList.remove('d-none');
    
    // Submit to backend
    fetch('/suggestions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ link: link })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuggestionToast(data.message, 'success');
            form.reset();
        } else {
            showSuggestionToast(data.error, 'error');
        }
    })
    .catch(error => {
        showSuggestionToast('Network error. Please try again.', 'error');
    })
    .finally(() => {
        // Reset button state
        submitButton.disabled = false;
        submitText.classList.remove('d-none');
        submitSpinner.classList.add('d-none');
    });
}

/**
 * Validate YouTube URL on client side
 * @param {string} url - URL to validate
 * @returns {boolean} True if valid YouTube URL
 */
function isValidYouTubeURL(url) {
    try {
        const urlObj = new URL(url);
        const hostname = urlObj.hostname.toLowerCase();
        
        // Check if it's a YouTube domain
        const validHosts = ['youtube.com', 'www.youtube.com', 'youtu.be', 'm.youtube.com'];
        if (!validHosts.includes(hostname)) {
            return false;
        }
        
        // Check for valid YouTube patterns
        const patterns = [
            /youtube\.com\/watch\?v=([a-zA-Z0-9_-]{11})/,           // Video
            /youtube\.com\/channel\/([a-zA-Z0-9_-]+)/,              // Channel ID
            /youtube\.com\/c\/([a-zA-Z0-9_-]+)/,                    // Custom channel
            /youtube\.com\/user\/([a-zA-Z0-9_-]+)/,                 // User channel
            /youtube\.com\/@([a-zA-Z0-9_.-]+)/,                     // Handle
            /youtu\.be\/([a-zA-Z0-9_-]{11})/,                       // Short video URL
        ];
        
        return patterns.some(pattern => pattern.test(url));
    } catch {
        return false;
    }
}

/**
 * Show suggestion toast message
 * @param {string} message - Message to show
 * @param {string} type - Type: 'success' or 'error'
 */
function showSuggestionToast(message, type) {
    const toastContainer = document.getElementById('suggestionToast');
    if (!toastContainer) {
        console.error('Toast container not found');
        return;
    }
    
    const toast = toastContainer.querySelector('.toast');
    const toastBody = toast.querySelector('.toast-body');
    
    // Set message and style
    toastBody.textContent = message;
    toast.className = `toast align-items-center text-white border-0 bg-${type === 'success' ? 'success' : 'danger'} show`;
    
    // Show toast container
    toastContainer.style.display = 'block';
    
    // Add a slight delay to ensure visibility
    setTimeout(() => {
        toast.style.opacity = '1';
        toast.style.transform = 'translateY(0)';
    }, 100);
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
        hideSuggestionToast();
    }, 5000);
}

/**
 * Hide suggestion toast
 */
function hideSuggestionToast() {
    const toastContainer = document.getElementById('suggestionToast');
    if (!toastContainer) return;
    
    const toast = toastContainer.querySelector('.toast');
    
    // Fade out animation
    toast.style.opacity = '0';
    toast.style.transform = 'translateY(-20px)';
    
    // Hide after animation
    setTimeout(() => {
        toastContainer.style.display = 'none';
        toast.classList.remove('show');
    }, 300);
} 