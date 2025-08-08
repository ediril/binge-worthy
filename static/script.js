// JavaScript for Binge-Worthy platform

// Configuration
const COUNTDOWN_SECONDS = 10;

/**
 * Open a video in the video player
 * @param {string} videoUrl - The URL of the video to open
 * @param {number} videoId - The database ID of the video (optional)
 */
function openVideo(videoUrl, videoId = null) {
    if (!videoUrl) return;
    
    // If we have a video ID, use the play page
    if (videoId) {
        window.location.href = `/play?id=${videoId}`;
        return;
    }
    
    // Extract video ID from YouTube URL to find in database
    const youtubeId = extractYouTubeVideoId(videoUrl);
    if (youtubeId) {
        // Try to find the video in our database first
        fetch(`/api/find-video.php?youtube_id=${youtubeId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.video_id) {
                    window.location.href = `/play?id=${data.video_id}`;
                } else {
                    // Fallback to opening YouTube directly
                    window.open(videoUrl, '_blank');
                }
            })
            .catch(() => {
                // Fallback to opening YouTube directly
                window.open(videoUrl, '_blank');
            });
    } else {
        // Not a YouTube URL, open directly
        window.open(videoUrl, '_blank');
    }
}

/**
 * Extract YouTube video ID from URL
 * @param {string} url - YouTube URL
 * @returns {string|null} Video ID or null
 */
function extractYouTubeVideoId(url) {
    const patterns = [
        /(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/
    ];
    
    for (const pattern of patterns) {
        const match = url.match(pattern);
        if (match) return match[1];
    }
    return null;
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
            // Don't do anything if the click was on a link or button
            if (e.target.tagName === 'A' || e.target.closest('a') || 
                e.target.tagName === 'BUTTON' || e.target.closest('button')) {
                return;
            }
            
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
    
    // Initialize video player if on play page
    if (document.getElementById('video-player')) {
        initializeVideoPlayer();
        
        // Handle back button to hide overlay
        setupBackButtonHandler();
        
        // Handle ESC key to dismiss overlay
        setupEscapeKeyHandler();
    }
    
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

/**
 * Initialize video player functionality
 */
function initializeVideoPlayer() {
    console.log('Initializing video player');
    
    // Clear any existing timers when page loads (e.g., from back button)
    clearNextVideoCountdown();
    
    // Make sure overlay is hidden on page load
    const overlay = document.getElementById('video-end-overlay');
    if (overlay) {
        overlay.classList.remove('show');
        overlay.style.display = 'none';
    }
    
    // Reset countdown display
    const countdownNumber = document.getElementById('countdown-number');
    const countdownText = document.getElementById('countdown-text');
    if (countdownNumber) countdownNumber.textContent = COUNTDOWN_SECONDS;
    if (countdownText) countdownText.textContent = COUNTDOWN_SECONDS;
    
    // Load YouTube IFrame API
    if (!window.YT) {
        const tag = document.createElement('script');
        tag.src = 'https://www.youtube.com/iframe_api';
        const firstScriptTag = document.getElementsByTagName('script')[0];
        firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
        
        // Set up the callback for when API is ready
        window.onYouTubeIframeAPIReady = onYouTubeIframeAPIReady;
    } else {
        // API already loaded
        onYouTubeIframeAPIReady();
    }
    
    // Handle page show event (back/forward cache)
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            console.log('Page loaded from cache - reinitializing player');
            // Page was loaded from cache, player might be stuck
            setTimeout(() => {
                if (window.player && window.player.getPlayerState) {
                    try {
                        const state = window.player.getPlayerState();
                        console.log('Current player state:', state);
                        
                        // If player is in a weird state, reload it
                        if (state === -1 || state === 5) { // unstarted or video cued
                            console.log('Player in problematic state, reloading iframe');
                            const iframe = document.getElementById('video-player');
                            if (iframe) {
                                const src = iframe.src;
                                iframe.src = '';
                                setTimeout(() => {
                                    iframe.src = src;
                                }, 100);
                            }
                        }
                    } catch (e) {
                        console.log('Error checking player state:', e);
                    }
                }
            }, 500);
        }
    });
    
    // Clear timers when page is about to unload
    window.addEventListener('beforeunload', function() {
        console.log('Page unloading, clearing timers');
        clearNextVideoCountdown();
    });
    
    // Also clear on visibility change (tab switching)
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            console.log('Page hidden, clearing timers');
            clearNextVideoCountdown();
        }
    });
}

/**
 * Called when YouTube IFrame API is ready
 */
function onYouTubeIframeAPIReady() {
    const iframe = document.getElementById('video-player');
    if (!iframe) return;
    
    // Extract video ID from iframe src
    const src = iframe.src;
    const videoIdMatch = src.match(/embed\/([a-zA-Z0-9_-]+)/);
    if (!videoIdMatch) return;
    
    const videoId = videoIdMatch[1];
    
    // Replace iframe with YouTube player
    window.player = new YT.Player('video-player', {
        height: '100%',
        width: '100%',
        videoId: videoId,
        playerVars: {
            autoplay: 1,
            rel: 0,
            enablejsapi: 1
        },
        events: {
            onReady: onPlayerReady,
            onStateChange: onPlayerStateChange
        }
    });
}

/**
 * Called when player is ready
 */
function onPlayerReady(event) {
    console.log('YouTube player is ready');
    
    // Check if we came back via navigation
    const preventAutoPlay = sessionStorage.getItem('preventAutoPlay');
    
    if (preventAutoPlay) {
        console.log('Skipping progress monitoring due to navigation');
        // Reset video to beginning if we came back via navigation
        try {
            event.target.seekTo(0, true);
            event.target.pauseVideo();
            console.log('Video reset to beginning');
        } catch (e) {
            console.log('Could not reset video:', e);
        }
        // Clear the flag for next time
        sessionStorage.removeItem('preventAutoPlay');
    }
    
    // No need to start progress monitoring since we only show overlay at video end
}

/**
 * Handle player state changes
 */
function onPlayerStateChange(event) {
    console.log('Player state changed:', event.data);
    
    // YT.PlayerState.ENDED = 0
    if (event.data === YT.PlayerState.ENDED) {
        // Check if we should show overlay
        const shouldShow = !sessionStorage.getItem('preventAutoPlay');
        
        if (shouldShow) {
            console.log('Video ended - showing overlay');
            // Show overlay after a short delay
            setTimeout(() => {
                showVideoOverlay();
            }, 500);
        } else {
            console.log('Video ended - skipping overlay due to navigation');
            sessionStorage.removeItem('preventAutoPlay');
        }
    }
}

/**
 * Show video end overlay
 */
function showVideoOverlay() {
    console.log('Attempting to show overlay');
    const overlay = document.getElementById('video-end-overlay');
    if (overlay) {
        console.log('Overlay found, adding show class');
        // First set display to flex (but still invisible due to opacity: 0)
        overlay.style.display = 'flex';
        // Then trigger the transition by adding the show class after a brief delay
        setTimeout(() => {
            overlay.classList.add('show');
        }, 10);
        
        // Start countdown for next video
        startNextVideoCountdown();
    } else {
        console.log('Overlay element not found');
    }
}

/**
 * Hide video end overlay
 */
function hideVideoOverlay() {
    const overlay = document.getElementById('video-end-overlay');
    if (overlay) {
        // Start the fade out by removing show class
        overlay.classList.remove('show');
        // Reset display after transition completes
        setTimeout(() => {
            // Only hide if still not showing (in case it was re-shown)
            if (!overlay.classList.contains('show')) {
                overlay.style.display = 'none';
            }
        }, 500);
        // Clear countdown when overlay is closed
        clearNextVideoCountdown();
    }
}


/**
 * Start countdown for next video
 */
function startNextVideoCountdown() {
    const nextVideoCard = document.querySelector('.next-video-card');
    if (!nextVideoCard) return;
    
    const videoId = nextVideoCard.getAttribute('data-video-id');
    if (!videoId) return;
    
    const countdownNumber = document.getElementById('countdown-number');
    const countdownText = document.getElementById('countdown-text');
    
    if (!countdownNumber || !countdownText) return;
    
    let seconds = COUNTDOWN_SECONDS;
    
    // Add click handler to go immediately
    nextVideoCard.addEventListener('click', () => {
        clearInterval(window.countdownInterval);
        navigateToVideo(videoId);
    });
    
    // Update countdown every second
    window.countdownInterval = setInterval(() => {
        seconds--;
        
        if (countdownNumber) countdownNumber.textContent = seconds;
        if (countdownText) countdownText.textContent = seconds;
        
        if (seconds <= 0) {
            clearInterval(window.countdownInterval);
            // Use the same cleanup sequence as manual navigation
            navigateToVideo(videoId);
        }
    }, 1000);
}

/**
 * Clear countdown if overlay is closed
 */
function clearNextVideoCountdown() {
    console.log('Clearing countdown timer');
    if (window.countdownInterval) {
        clearInterval(window.countdownInterval);
        window.countdownInterval = null;
    }
    // Also clear any timeout that might be set for navigation
    if (window.navigationTimeout) {
        clearTimeout(window.navigationTimeout);
        window.navigationTimeout = null;
    }
}

/**
 * Setup back button handler to hide overlay
 */
function setupBackButtonHandler() {
    // Push initial state when page loads
    history.replaceState({ overlayVisible: false }, '', window.location.href);
    
    // Listen for popstate (back/forward button)
    window.addEventListener('popstate', function(event) {
        const overlay = document.getElementById('video-end-overlay');
        if (overlay && overlay.classList.contains('show')) {
            // If overlay is visible, hide it without changing history
            hideVideoOverlay();
        }
    });
    
    // Update history state when overlay is shown
    const originalShowOverlay = showVideoOverlay;
    window.showVideoOverlay = function() {
        originalShowOverlay();
        // Replace current state when overlay is shown (no extra history entry)
        history.replaceState({ overlayVisible: true }, '', window.location.href);
    };
}


/**
 * Navigate to video with proper cleanup sequence
 */
function navigateToVideo(videoId) {
    console.log('Navigating to video:', videoId);
    
    // Step 1: Stop countdown and clear all timers immediately
    clearNextVideoCountdown();
    
    // Step 2: Close overlay
    const overlay = document.getElementById('video-end-overlay');
    if (overlay) {
        overlay.classList.remove('show');
    }
    
    // Step 3: Reset current video to beginning
    if (window.player && window.player.seekTo) {
        try {
            window.player.seekTo(0, true);
            window.player.pauseVideo();
            console.log('Current video reset before navigation');
        } catch (e) {
            console.log('Could not reset current video:', e);
        }
    }
    
    // Step 4: Set flag to prevent auto-overlay on the next page if user goes back
    sessionStorage.setItem('preventAutoPlay', 'true');
    
    // Step 5: Navigate after a brief delay for cleanup to complete
    window.navigationTimeout = setTimeout(() => {
        window.location.href = `/play?id=${videoId}`;
    }, 100); // Brief delay for cleanup
}

/**
 * Setup ESC key handler to dismiss overlay
 */
function setupEscapeKeyHandler() {
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' || event.keyCode === 27) {
            const overlay = document.getElementById('video-end-overlay');
            if (overlay && overlay.classList.contains('show')) {
                hideVideoOverlay();
            }
        }
    });
} 