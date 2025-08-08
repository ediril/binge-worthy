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
        
        // Setup custom fullscreen
        setupCustomFullscreen();
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
    
    // Check if we should force autoplay from URL parameter
    const urlParams = new URLSearchParams(window.location.search);
    const shouldAutoplay = urlParams.get('autoplay') === '1';
    if (shouldAutoplay) {
        console.log('Autoplay requested via URL parameter');
        // Remove the URL parameter but keep the intent
        sessionStorage.setItem('forceAutoplay', 'true');
        // Clean up the URL without reloading the page
        const cleanUrl = window.location.pathname + '?id=' + urlParams.get('id');
        window.history.replaceState({}, '', cleanUrl);
    }
    
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
    console.log('YouTube IFrame API is ready');
    
    const iframe = document.getElementById('video-player');
    if (!iframe) {
        console.error('No video-player iframe found!');
        return;
    }
    
    console.log('Found iframe:', iframe);
    
    // Extract video ID from iframe src
    const src = iframe.src;
    console.log('Original iframe src:', src);
    
    const videoIdMatch = src.match(/embed\/([a-zA-Z0-9_-]+)/);
    if (!videoIdMatch) {
        console.error('Could not extract video ID from iframe src:', src);
        return;
    }
    
    const videoId = videoIdMatch[1];
    console.log('Extracted video ID:', videoId);
    
    // Replace iframe with YouTube player - simplified configuration
    console.log('Creating YouTube player with video ID:', videoId);
    
    try {
        window.player = new YT.Player('video-player', {
            height: '100%',
            width: '100%',
            videoId: videoId,
            playerVars: {
                autoplay: 1,
                controls: 1,
                fs: 0,           // Disable YouTube's fullscreen button
                rel: 0,          // Disable related videos
                enablejsapi: 1
            },
            events: {
                onReady: onPlayerReady,
                onStateChange: onPlayerStateChange,
                onError: onPlayerError
            }
        });
        
        console.log('YouTube player created successfully');
    } catch (e) {
        console.error('Error creating YouTube player:', e);
    }
}

/**
 * Called when player is ready
 */
function onPlayerReady(event) {
    console.log('YouTube player is ready');
    
    // Debug player configuration
    const iframe = event.target.getIframe();
    console.log('Player iframe element:', iframe);
    console.log('Player iframe src:', iframe.src);
    console.log('Current video ID from player:', event.target.getVideoData());
    console.log('Player state at ready:', event.target.getPlayerState());
    
    // Debug: Test if navigateToVideo function is available
    console.log('navigateToVideo function available:', typeof navigateToVideo);
    window.testVideoSwitch = () => {
        console.log('Testing video switch manually...');
        navigateToVideo('3885'); // Use a test video ID
    };
    console.log('Added window.testVideoSwitch() for manual testing');
    
    // Try to hide fullscreen button with CSS injection
    hideFullscreenButton();
    
    // Check if we came back via navigation
    const preventAutoPlay = sessionStorage.getItem('preventAutoPlay');
    const forceAutoplay = sessionStorage.getItem('forceAutoplay');
    
    if (preventAutoPlay && !forceAutoplay) {
        console.log('Skipping autoplay due to navigation');
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
    } else {
        console.log('Attempting to start video playback');
        
        // Clear any autoplay flags
        if (forceAutoplay) {
            sessionStorage.removeItem('forceAutoplay');
            sessionStorage.removeItem('preventAutoPlay');
        }
        
        // Simple autoplay - just try once after a delay
        setTimeout(() => {
            try {
                console.log('Attempting simple autoplay');
                const state = event.target.getPlayerState();
                console.log('Player state before autoplay:', state);
                event.target.playVideo();
                
                // Wait longer and check more carefully
                setTimeout(() => {
                    const newState = event.target.getPlayerState();
                    console.log('Player state after autoplay attempt:', newState);
                    
                    // Only show hint if video is definitely not playing and not buffering
                    if (newState !== YT.PlayerState.PLAYING && 
                        newState !== YT.PlayerState.BUFFERING && 
                        newState !== YT.PlayerState.UNSTARTED) {
                        console.log('Autoplay blocked - showing click-to-play hint');
                        showClickToPlayHint();
                    } else if (newState === YT.PlayerState.PLAYING) {
                        console.log('Autoplay successful!');
                    } else {
                        console.log('Video loading or buffering, not showing hint');
                    }
                }, 2000); // Wait 2 seconds instead of 1
            } catch (e) {
                console.log('Autoplay failed:', e);
            }
        }, 1000);
    }
    
    // No need to start progress monitoring since we only show overlay at video end
}

/**
 * Handle YouTube player errors
 */
function onPlayerError(event) {
    console.error('YouTube Player Error:', event.data);
    const errorMessages = {
        2: 'Invalid video ID',
        5: 'Video not playable in HTML5 player',
        100: 'Video not found or private',
        101: 'Video does not allow embedding',
        150: 'Video does not allow embedding'
    };
    console.error('Error meaning:', errorMessages[event.data] || 'Unknown error');
}

/**
 * Hide YouTube's fullscreen button with CSS injection
 */
function hideFullscreenButton() {
    // Add CSS to hide fullscreen button
    const style = document.createElement('style');
    style.textContent = `
        .ytp-fullscreen-button,
        .ytp-button[aria-label*="fullscreen"],
        .ytp-button[aria-label*="Fullscreen"],
        button[aria-label*="fullscreen"],
        button[aria-label*="Fullscreen"] {
            display: none !important;
        }
    `;
    document.head.appendChild(style);
    
    // Also try to periodically check and hide any fullscreen buttons that appear
    setInterval(() => {
        const buttons = document.querySelectorAll('.ytp-fullscreen-button, .ytp-button[aria-label*="fullscreen"], .ytp-button[aria-label*="Fullscreen"]');
        buttons.forEach(button => {
            button.style.display = 'none';
        });
    }, 1000);
}

/**
 * Attempt to start video playback
 */
function startVideoPlayback(player) {
    try {
        console.log('Attempting to start video playback');
        player.playVideo();
        
        // Multiple retry attempts
        const retryPlay = (attempts = 0) => {
            if (attempts > 5) return; // Max 5 attempts
            
            setTimeout(() => {
                const state = player.getPlayerState();
                console.log('Retry attempt', attempts + 1, 'state:', state);
                
                if (state !== YT.PlayerState.PLAYING && state !== YT.PlayerState.BUFFERING) {
                    player.playVideo();
                    retryPlay(attempts + 1);
                }
            }, 200 * (attempts + 1)); // Increasing delays: 200ms, 400ms, 600ms...
        };
        
        retryPlay();
    } catch (e) {
        console.log('Playback start failed:', e);
    }
}

/**
 * Show subtle click-to-play hint when autoplay is blocked
 */
function showClickToPlayHint() {
    const wrapper = document.getElementById('video-player-wrapper');
    if (!wrapper || document.getElementById('click-hint')) return;
    
    const hint = document.createElement('div');
    hint.id = 'click-hint';
    hint.innerHTML = '▶️ Click to play';
    hint.style.cssText = `
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: rgba(0, 0, 0, 0.7);
        color: white;
        padding: 8px 16px;
        border-radius: 4px;
        font-size: 14px;
        z-index: 100;
        cursor: pointer;
        transition: opacity 0.3s ease;
        opacity: 0;
    `;
    
    wrapper.appendChild(hint);
    
    // Fade in
    setTimeout(() => hint.style.opacity = '1', 100);
    
    // Click handler
    const playVideo = () => {
        if (window.player) {
            window.player.playVideo();
            hint.remove();
        }
    };
    
    hint.addEventListener('click', playVideo);
    wrapper.addEventListener('click', playVideo);
    
    // Auto-remove after 10 seconds
    setTimeout(() => {
        if (hint.parentNode) {
            hint.style.opacity = '0';
            setTimeout(() => hint.remove(), 300);
        }
    }, 10000);
}

/**
 * Handle player state changes
 */
function onPlayerStateChange(event) {
    console.log('Player state changed:', event.data);
    
    // Handle video swapping autoplay and fullscreen restoration
    if (event.data === YT.PlayerState.CUED || event.data === YT.PlayerState.UNSTARTED) {
        // New video has been loaded/cued
        const forceAutoplay = sessionStorage.getItem('forceAutoplay');
        if (forceAutoplay) {
            console.log('New video loaded, forcing autoplay');
            sessionStorage.removeItem('forceAutoplay');
            
            // Try to start the new video
            setTimeout(() => {
                try {
                    event.target.playVideo();
                } catch (e) {
                    console.log('Could not autoplay new video:', e);
                }
            }, 100);
        }
    }
    
    // Handle fullscreen restoration after video loads
    if (event.data === YT.PlayerState.PLAYING) {
        const shouldRestoreFullscreen = sessionStorage.getItem('restoreFullscreen');
        const wasInFullscreen = sessionStorage.getItem('wasInFullscreen');
        
        if (shouldRestoreFullscreen || wasInFullscreen) {
            console.log('=== FULLSCREEN RESTORATION CHECK ===');
            console.log('Video started playing, checking fullscreen restoration');
            console.log('Current window.isFullscreen:', window.isFullscreen);
            console.log('Document fullscreen element:', document.fullscreenElement);
            console.log('Body classes:', document.body.className);
            
            // Clean up flags
            sessionStorage.removeItem('restoreFullscreen');
            sessionStorage.removeItem('wasInFullscreen');
            
            // Restore fullscreen if we're not already in it
            const isInFullscreen = !!(document.fullscreenElement || window.isFullscreen);
            
            if (!isInFullscreen) {
                console.log('NOT in fullscreen - RESTORING fullscreen mode after video swap');
                setTimeout(() => {
                    console.log('Calling toggleCustomFullscreen to restore fullscreen');
                    toggleCustomFullscreen();
                }, 500); // Increased delay
            } else {
                console.log('Already in fullscreen, no restoration needed');
            }
        }
    }
    
    // Also handle restoration when video is ready/cued
    if (event.data === YT.PlayerState.CUED) {
        const shouldRestoreFullscreen = sessionStorage.getItem('restoreFullscreen');
        if (shouldRestoreFullscreen) {
            console.log('Video cued, checking if need to restore fullscreen');
            if (!window.isFullscreen && !document.fullscreenElement) {
                console.log('Restoring fullscreen after video cued');
                sessionStorage.removeItem('restoreFullscreen');
                setTimeout(() => {
                    toggleCustomFullscreen();
                }, 100);
            }
        }
    }
    
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
        console.log('Overlay found, showing overlay');
        console.log('Current fullscreen state:', window.isFullscreen);
        console.log('Body classes:', document.body.className);
        
        // Check if we're in fullscreen mode
        if (window.isFullscreen) {
            console.log('Adding fullscreen overlay class');
            overlay.classList.add('fullscreen-overlay');
            // Force high z-index for fullscreen
            overlay.style.zIndex = '999999999';
        } else {
            console.log('Not in fullscreen, removing fullscreen class');
            overlay.classList.remove('fullscreen-overlay');
            overlay.style.zIndex = '10';
        }
        
        // First set display to flex (but still invisible due to opacity: 0)
        overlay.style.display = 'flex';
        console.log('Set overlay display to flex');
        
        // Then trigger the transition by adding the show class after a brief delay
        setTimeout(() => {
            overlay.classList.add('show');
            console.log('Added show class to overlay');
            console.log('Overlay final classes:', overlay.className);
            console.log('Overlay final styles:', {
                display: overlay.style.display,
                zIndex: overlay.style.zIndex,
                position: getComputedStyle(overlay).position,
                top: getComputedStyle(overlay).top,
                left: getComputedStyle(overlay).left,
                width: getComputedStyle(overlay).width,
                height: getComputedStyle(overlay).height
            });
        }, 10);
        
        // Start countdown for next video
        startNextVideoCountdown();
        
        // Initialize suggested video cards
        initializeSuggestedVideoCards();
    } else {
        console.log('Overlay element not found');
    }
}

/**
 * Setup custom fullscreen with keyboard controls like scrubbler
 */
function setupCustomFullscreen() {
    console.log('Setting up custom fullscreen with keyboard controls');
    
    // Create keyboard capture overlay similar to scrubbler
    createKeyboardCaptureOverlay();
    
    // Setup keyboard event handlers
    setupKeyboardHandlers();
    
    // Listen for fullscreen change events
    document.addEventListener('fullscreenchange', handleFullscreenChange);
    document.addEventListener('webkitfullscreenchange', handleFullscreenChange);
    document.addEventListener('mozfullscreenchange', handleFullscreenChange);
    document.addEventListener('MSFullscreenChange', handleFullscreenChange);
}

/**
 * Create keyboard capture overlay for focus management
 */
function createKeyboardCaptureOverlay() {
    if (document.getElementById('keyboardCaptureOverlay')) return;
    
    const overlay = document.createElement('div');
    overlay.id = 'keyboardCaptureOverlay';
    overlay.className = 'keyboard-capture-overlay';
    overlay.tabIndex = 0; // Make it focusable
    overlay.style.cssText = `
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        pointer-events: none;
        z-index: 1;
        outline: none;
    `;
    
    const wrapper = document.getElementById('video-player-wrapper');
    if (wrapper) {
        wrapper.appendChild(overlay);
        // Focus it to ensure keyboard events are captured
        overlay.focus({ preventScroll: true });
    }
}

/**
 * Setup keyboard event handlers like scrubbler
 */
function setupKeyboardHandlers() {
    const overlay = document.getElementById('keyboardCaptureOverlay');
    if (!overlay) return;
    
    overlay.addEventListener('keydown', handleKeydown);
    
    // Maintain focus on overlay
    setInterval(() => {
        if (document.activeElement !== overlay && 
            document.activeElement.tagName !== 'INPUT' && 
            document.activeElement.tagName !== 'TEXTAREA' &&
            !document.activeElement.isContentEditable) {
            overlay.focus({ preventScroll: true });
        }
    }, 100);
}

/**
 * Handle keyboard events like scrubbler
 */
function handleKeydown(e) {
    if (!window.player) return;
    
    // Don't handle if user is typing in input
    if (document.activeElement.tagName === 'INPUT' || 
        document.activeElement.tagName === 'TEXTAREA' ||
        document.activeElement.isContentEditable) {
        return;
    }
    
    console.log('Keydown:', e.code);
    
    switch(e.code) {
        case 'Space':
            e.preventDefault();
            const state = window.player.getPlayerState();
            if (state === YT.PlayerState.PLAYING) {
                window.player.pauseVideo();
            } else {
                window.player.playVideo();
            }
            break;
            
        case 'ArrowLeft':
            e.preventDefault();
            const currentTime = window.player.getCurrentTime();
            window.player.seekTo(Math.max(0, currentTime - 10), true);
            break;
            
        case 'ArrowRight':
            e.preventDefault();
            const currentTime2 = window.player.getCurrentTime();
            const duration = window.player.getDuration();
            window.player.seekTo(Math.min(duration, currentTime2 + 10), true);
            break;
            
        case 'KeyF':
            e.preventDefault();
            toggleCustomFullscreen();
            break;
            
        case 'Escape':
            e.preventDefault();
            // Check if video end overlay is showing first
            const overlay = document.getElementById('video-end-overlay');
            if (overlay && overlay.classList.contains('show')) {
                console.log('ESC pressed - hiding video overlay');
                hideVideoOverlay();
            } else if (window.isFullscreen) {
                console.log('ESC pressed - exiting fullscreen');
                toggleCustomFullscreen();
            }
            break;
    }
}

/**
 * Toggle custom fullscreen mode like scrubbler
 */
function toggleCustomFullscreen() {
    const wrapper = document.getElementById('video-player-wrapper');
    if (!wrapper) return;
    
    if (!window.isFullscreen) {
        console.log('Entering fullscreen');
        try {
            if (wrapper.requestFullscreen) {
                wrapper.requestFullscreen();
            } else if (wrapper.mozRequestFullScreen) {
                wrapper.mozRequestFullScreen();
            } else if (wrapper.webkitRequestFullscreen) {
                wrapper.webkitRequestFullscreen();
            } else if (wrapper.msRequestFullscreen) {
                wrapper.msRequestFullscreen();
            }
        } catch (err) {
            console.error('Error entering fullscreen:', err);
        }
    } else {
        console.log('Exiting fullscreen');
        try {
            if (document.exitFullscreen) {
                document.exitFullscreen();
            } else if (document.mozCancelFullScreen) {
                document.mozCancelFullScreen();
            } else if (document.webkitExitFullscreen) {
                document.webkitExitFullscreen();
            } else if (document.msExitFullscreen) {
                document.msExitFullscreen();
            }
        } catch (err) {
            console.error('Error exiting fullscreen:', err);
        }
    }
}

/**
 * Handle fullscreen state changes
 */
function handleFullscreenChange() {
    const wrapper = document.getElementById('video-player-wrapper');
    const wasFullscreen = window.isFullscreen;
    
    // Check current fullscreen state
    const fullscreenElement = document.fullscreenElement || 
                             document.webkitFullscreenElement || 
                             document.mozFullScreenElement || 
                             document.msFullscreenElement;
    
    window.isFullscreen = !!fullscreenElement;
    
    console.log('Fullscreen state changed:', {
        wasFullscreen: wasFullscreen,
        nowFullscreen: window.isFullscreen,
        fullscreenElement: fullscreenElement ? fullscreenElement.id : 'none'
    });
    
    if (window.isFullscreen && !wasFullscreen) {
        console.log('Entered fullscreen');
        document.body.classList.add('custom-fullscreen-active');
        // Apply fullscreen styles to wrapper
        if (wrapper) {
            wrapper.classList.add('custom-fullscreen-active');
        }
    } else if (!window.isFullscreen && wasFullscreen) {
        console.log('Exited fullscreen');
        document.body.classList.remove('custom-fullscreen-active');
        if (wrapper) {
            wrapper.classList.remove('custom-fullscreen-active');
        }
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
        // Clean up fullscreen class
        overlay.classList.remove('fullscreen-overlay');
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
 * Start countdown for next video (normal mode)
 */
function startNextVideoCountdown() {
    console.log('Starting next video countdown...');
    const nextVideoCard = document.querySelector('.next-video-card');
    if (!nextVideoCard) {
        console.log('No next video card found');
        return;
    }
    
    const videoId = nextVideoCard.getAttribute('data-video-id');
    const youtubeId = nextVideoCard.getAttribute('data-youtube-id');
    if (!videoId) {
        console.log('No video ID found on next video card');
        return;
    }
    
    console.log('Found next video:', videoId, youtubeId);
    
    const countdownNumber = document.getElementById('countdown-number');
    const countdownText = document.getElementById('countdown-text');
    
    if (!countdownNumber || !countdownText) {
        console.log('Countdown elements not found');
        return;
    }
    
    let seconds = COUNTDOWN_SECONDS;
    
    // Clear any existing interval
    if (window.countdownInterval) {
        clearInterval(window.countdownInterval);
    }
    
    // Remove existing click handlers by cloning the element
    const newCard = nextVideoCard.cloneNode(true);
    nextVideoCard.parentNode.replaceChild(newCard, nextVideoCard);
    
    // Add click handler to go immediately
    newCard.addEventListener('click', () => {
        console.log('Next video card clicked! Video ID:', videoId, 'YouTube ID:', youtubeId);
        clearInterval(window.countdownInterval);
        navigateToVideo(videoId, youtubeId);
    });
    
    // Update countdown every second
    window.countdownInterval = setInterval(() => {
        seconds--;
        
        // Re-find elements in case they were updated
        const currentCountdownNumber = document.getElementById('countdown-number');
        const currentCountdownText = document.getElementById('countdown-text');
        
        if (currentCountdownNumber) currentCountdownNumber.textContent = seconds;
        if (currentCountdownText) currentCountdownText.textContent = seconds;
        
        if (seconds <= 0) {
            clearInterval(window.countdownInterval);
            // Use the same cleanup sequence as manual navigation
            navigateToVideo(videoId, youtubeId);
        }
    }, 1000);
    
    console.log('Countdown started with', seconds, 'seconds');
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
 * Setup back button handler to hide overlay and handle video navigation
 */
function setupBackButtonHandler() {
    // Get initial video ID from URL
    const urlParams = new URLSearchParams(window.location.search);
    const initialVideoId = urlParams.get('id');
    
    // Track currently loaded video (not the URL)
    window.currentLoadedVideoId = initialVideoId;
    
    // Push initial state when page loads
    history.replaceState({ 
        overlayVisible: false,
        videoId: initialVideoId
    }, '', window.location.href);
    
    // Listen for popstate (back/forward button)
    window.addEventListener('popstate', function(event) {
        const overlay = document.getElementById('video-end-overlay');
        
        // First check if overlay is visible and hide it
        if (overlay && overlay.classList.contains('show')) {
            hideVideoOverlay();
            return; // Don't handle video navigation while overlay is visible
        }
        
        // Handle video navigation if state has a different videoId
        console.log('=== BACK BUTTON PRESSED ===');
        console.log('Event state:', event.state);
        console.log('Current URL:', window.location.href);
        
        if (event.state && event.state.videoId) {
            console.log('State video ID:', event.state.videoId);
            console.log('Currently loaded video ID:', window.currentLoadedVideoId);
            
            if (event.state.videoId !== window.currentLoadedVideoId) {
                console.log('Video IDs differ - navigating to video:', event.state.videoId);
                // Set flag to prevent auto-overlay since this is navigation
                sessionStorage.setItem('preventAutoPlay', 'true');
                // Load the video from history state
                swapToNewVideo(event.state.videoId);
                // Update current loaded video ID
                window.currentLoadedVideoId = event.state.videoId;
            } else {
                console.log('Video IDs match - no navigation needed');
            }
        } else {
            console.log('No state or videoId found in popstate event');
        }
    });
    
    // Update history state when overlay is shown
    const originalShowOverlay = showVideoOverlay;
    window.showVideoOverlay = function() {
        originalShowOverlay();
        // Replace current state when overlay is shown (no extra history entry)
        const urlParams = new URLSearchParams(window.location.search);
        const currentVideoId = urlParams.get('id');
        history.replaceState({ 
            overlayVisible: true,
            videoId: currentVideoId
        }, '', window.location.href);
    };
}


/**
 * Navigate to video with proper cleanup sequence - now swaps video on same page
 */
function navigateToVideo(videoId, youtubeId = null) {
    console.log('=== STARTING VIDEO SWITCH ===');
    console.log('navigateToVideo called with videoId:', videoId, 'youtubeId:', youtubeId);
    console.log('Current URL:', window.location.href);
    console.log('Player available:', !!window.player);
    console.log('Function called from:', new Error().stack);
    
    if (!videoId) {
        console.error('No video ID provided');
        return;
    }
    
    // If no YouTube ID provided, try to find it from the calling element
    if (!youtubeId) {
        // Try to find the element that was clicked with this videoId
        const overlayCards = document.querySelectorAll(`[data-video-id="${videoId}"]`);
        if (overlayCards.length > 0) {
            youtubeId = overlayCards[0].getAttribute('data-youtube-id');
            console.log('Found YouTube ID from element data attribute:', youtubeId);
        }
    }
    
    // Step 1: Stop countdown and clear all timers immediately
    clearNextVideoCountdown();
    
    // Step 2: Close overlay
    const overlay = document.getElementById('video-end-overlay');
    if (overlay) {
        console.log('Closing overlay');
        overlay.classList.remove('show');
        overlay.classList.remove('fullscreen-overlay');
        setTimeout(() => {
            if (!overlay.classList.contains('show')) {
                overlay.style.display = 'none';
            }
        }, 500);
    }
    
    // Step 3: Update URL parameter without page reload
    const newUrl = `/play?id=${videoId}`;
    try {
        window.history.pushState({ videoId: videoId }, '', newUrl);
        console.log('Updated URL to:', newUrl);
    } catch (e) {
        console.error('Failed to update URL:', e);
    }
    
    // Step 4: Fetch video information and swap the video
    swapToNewVideo(videoId, youtubeId);
}

/**
 * Swap the YouTube player to a new video while preserving fullscreen state
 */
function swapVideoPlayer(youtubeId, videoId) {
    console.log('=== LOADING NEW VIDEO ===');
    console.log('Loading new video:', youtubeId);
    console.log('Current window.isFullscreen before swap:', window.isFullscreen);
    console.log('Document fullscreen element before swap:', document.fullscreenElement);
    console.log('Body classes before swap:', document.body.className);
    
    // Store fullscreen state before video swap
    const wasInFullscreen = !!(document.fullscreenElement || window.isFullscreen);
    const fullscreenElement = document.fullscreenElement;
    
    console.log('Determined wasInFullscreen:', wasInFullscreen);
    
    if (wasInFullscreen) {
        console.log('=== SETTING FULLSCREEN RESTORATION FLAGS ===');
        console.log('Video swap in fullscreen mode - setting restoration flags');
        sessionStorage.setItem('restoreFullscreen', 'true');
        sessionStorage.setItem('wasInFullscreen', 'true');
    } else {
        console.log('Video swap in normal mode - no restoration needed');
    }
    
    try {
        window.player.loadVideoById({
            videoId: youtubeId,
            startSeconds: 0
        });
        console.log('Successfully called loadVideoById');
        
        // Update currently loaded video ID for back button tracking
        window.currentLoadedVideoId = videoId;
        console.log('Updated currentLoadedVideoId to:', videoId);
        
        // Set flag to ensure new video autoplays
        sessionStorage.setItem('forceAutoplay', 'true');
        console.log('Set forceAutoplay flag');
        
    } catch (e) {
        console.error('Error calling loadVideoById:', e);
    }
    
    // If we were in fullscreen and it got lost, restore immediately
    if (wasInFullscreen && fullscreenElement) {
        setTimeout(() => {
            console.log('Checking if fullscreen was lost after video load...');
            if (!document.fullscreenElement && !window.isFullscreen) {
                console.log('Fullscreen was lost, restoring immediately');
                toggleCustomFullscreen();
            }
        }, 100);
    }
}

/**
 * Swap to a new video without page reload
 */
function swapToNewVideo(videoId, youtubeId = null) {
    console.log('=== SWAPPING VIDEO ===');
    console.log('Video ID:', videoId, 'YouTube ID:', youtubeId);
    
    // If we have the YouTube ID directly, use it but still fetch video info for overlay
    if (youtubeId) {
        console.log('Using direct YouTube ID for video swap');
        swapVideoPlayer(youtubeId, videoId);
        
        // Still need to fetch video info to update overlay content
        console.log('Fetching video info for overlay update...');
        fetch(`/api/video-info.php?id=${videoId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.video) {
                    console.log('Got video info for overlay update:', data.video);
                    // Update page title and meta information
                    document.title = data.video.title + ' - BingeWorthy';
                    updateVideoDisplay(data.video);
                    // Update overlay content for new video
                    updateOverlayContent(data.video);
                    console.log('=== FAST VIDEO SWAP COMPLETE ===');
                } else {
                    console.log('Could not get video info for overlay update, using basic info');
                    // Create minimal video object for overlay update
                    const basicVideo = {
                        id: videoId,
                        channel_id: null, // We don't know this, will need to handle gracefully
                        title: 'Video ' + videoId
                    };
                    updateOverlayContent(basicVideo);
                }
            })
            .catch(error => {
                console.error('Error fetching video info for overlay:', error);
            });
        return;
    }
    
    // Otherwise fetch from API
    console.log('=== FETCHING VIDEO INFO ===');
    console.log('Fetching video information for:', videoId);
    
    const apiUrl = `/api/video-info.php?id=${videoId}`;
    console.log('API URL:', apiUrl);
    
    // Fetch video details from server
    fetch(apiUrl)
        .then(response => {
            console.log('API Response status:', response.status);
            console.log('API Response headers:', response.headers);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('=== API RESPONSE DATA ===');
            console.log('Full API response:', data);
            
            if (data.success && data.video) {
                const video = data.video;
                console.log('Video data received:', video);
                
                // Update page title and meta information
                document.title = video.title + ' - BingeWorthy';
                console.log('Updated page title:', document.title);
                updateVideoDisplay(video);
                
                // Load the new video in the existing player
                const youtubeId = extractYouTubeVideoId(video.video_url);
                console.log('Extracted YouTube ID:', youtubeId);
                
                if (youtubeId) {
                    swapVideoPlayer(youtubeId, videoId);
                } else {
                    console.error('Could not extract YouTube ID from:', video.video_url);
                }
                
                // Update overlay content for new video
                updateOverlayContent(video);
                console.log('=== VIDEO SWAP COMPLETE ===');
                
            } else {
                console.error('API returned failure:', data);
                console.log('API failed, but NOT falling back to page navigation to preserve fullscreen');
                console.log('Trying direct video load instead...');
                
                // Instead of page navigation, try to load video directly
                if (window.player && window.player.loadVideoById) {
                    // The videoId is our database ID, but we need the YouTube ID
                    // Let's try to get it from the API response first, or make another API call
                    console.log('API failed, but we still need YouTube ID for database ID:', videoId);
                    console.log('Unfortunately cannot load video without YouTube ID');
                    console.log('Must fall back to page navigation to get proper video data');
                    window.location.href = `/play?id=${videoId}`;
                } else {
                    console.log('Player not available, falling back to page navigation');
                    window.location.href = `/play?id=${videoId}`;
                }
            }
        })
        .catch(error => {
            console.error('=== API FETCH ERROR ===');
            console.error('Error fetching video information:', error);
            console.error('Error details:', {
                message: error.message,
                stack: error.stack,
                name: error.name
            });
            console.log('API fetch failed, trying direct video load to preserve fullscreen');
            
            // Cannot load video without YouTube ID from API
            console.log('Cannot load video without YouTube ID, falling back to page navigation');
            window.location.href = `/play?id=${videoId}`;
        });
}

/**
 * Update video display information on the page
 */
function updateVideoDisplay(video) {
    // Update video title
    const titleElement = document.querySelector('.video-player-title');
    if (titleElement) {
        titleElement.textContent = video.title;
    }
    
    // Update channel link and name
    const channelLink = document.querySelector('.video-player-channel');
    if (channelLink) {
        channelLink.href = `/channel?id=${video.channel_id}`;
        channelLink.textContent = video.channel_title;
    }
    
    // Update date
    const dateElement = document.querySelector('.video-player-date');
    if (dateElement && video.published_at) {
        const date = new Date(video.published_at);
        dateElement.textContent = date.toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric' 
        });
    }
}

/**
 * Update overlay content for new video
 */
function updateOverlayContent(video) {
    console.log('Updating overlay content for video:', video.id);
    
    // Update the "next video in channel" section
    updateNextVideoSection(video);
    
    // Update suggested videos section
    updateSuggestedVideosSection(video);
}

function updateNextVideoSection(video) {
    console.log('Updating next video section for channel:', video.channel_id);
    
    if (!video.channel_id) {
        console.log('No channel ID available, showing no more videos message');
        const nextVideoContainer = document.querySelector('.next-video-container');
        if (nextVideoContainer) {
            nextVideoContainer.innerHTML = '<p class="text-muted">No more videos in this channel</p>';
        }
        return;
    }
    
    // Fetch a random video from the same channel
    fetch(`/api/channel-videos.php?channel_id=${video.channel_id}&exclude=${video.id}&limit=1`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.videos.length > 0) {
                const nextVideo = data.videos[0];
                console.log('Found next video:', nextVideo);
                
                // Update the next video card HTML
                const nextVideoContainer = document.querySelector('.next-video-container');
                if (nextVideoContainer) {
                    const youtubeId = extractYouTubeVideoId(nextVideo.video_url);
                    const thumbnail = nextVideo.thumbnail_url || `https://img.youtube.com/vi/${youtubeId}/maxresdefault.jpg`;
                    
                    nextVideoContainer.innerHTML = `
                        <div class="next-video-card" data-video-id="${nextVideo.id}" data-youtube-id="${youtubeId}">
                            <div class="next-video-thumbnail-container">
                                <img src="${thumbnail}" 
                                     class="next-video-thumbnail" 
                                     alt="${nextVideo.title}">
                                <div class="countdown-overlay">
                                    <div class="countdown-circle">
                                        <span id="countdown-number">10</span>
                                    </div>
                                </div>
                            </div>
                            <div class="next-video-info">
                                <h5 class="next-video-title">
                                    ${nextVideo.title}
                                </h5>
                                <p class="next-video-channel">
                                    ${nextVideo.channel_name}
                                </p>
                                <p class="next-video-countdown">Starting in <span id="countdown-text">10</span> seconds...</p>
                            </div>
                        </div>
                    `;
                    console.log('Updated next video card HTML - handlers will be re-initialized when overlay shows');
                }
            } else {
                console.log('No more videos in this channel');
                const nextVideoContainer = document.querySelector('.next-video-container');
                if (nextVideoContainer) {
                    nextVideoContainer.innerHTML = '<p class="text-muted">No more videos in this channel</p>';
                }
            }
        })
        .catch(error => {
            console.error('Error fetching next video:', error);
        });
}

function updateSuggestedVideosSection(video) {
    console.log('Updating suggested videos section');
    
    // Fetch new suggested videos
    fetch('/api/suggested-videos.php?limit=3')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.videos.length > 0) {
                console.log('Found suggested videos:', data.videos);
                
                const suggestedContainer = document.getElementById('overlay-suggested-videos');
                if (suggestedContainer) {
                    suggestedContainer.innerHTML = data.videos.map(suggested => {
                        const youtubeId = extractYouTubeVideoId(suggested.video_url);
                        const thumbnail = suggested.thumbnail_url || `https://img.youtube.com/vi/${youtubeId}/maxresdefault.jpg`;
                        
                        return `
                            <div class="overlay-video-card" data-video-id="${suggested.id}" data-youtube-id="${youtubeId}" onclick="navigateToVideo(${suggested.id})">
                                <img src="${thumbnail}" 
                                     class="overlay-video-thumbnail" 
                                     alt="${suggested.title}">
                                <div class="overlay-video-info">
                                    <h6 class="overlay-video-title">
                                        ${suggested.title}
                                    </h6>
                                    <p class="overlay-video-channel">
                                        ${suggested.channel_name}
                                    </p>
                                </div>
                            </div>
                        `;
                    }).join('');
                    console.log('Updated suggested videos HTML');
                    
                    // Re-initialize event handlers for new suggested video cards
                    initializeSuggestedVideoCards();
                }
            }
        })
        .catch(error => {
            console.error('Error fetching suggested videos:', error);
        });
}

/**
 * Initialize event handlers for the next video card
 */
function initializeNextVideoCard() {
    // The countdown function handles the next video card initialization
    // Just restart the countdown for the new card
    console.log('Re-initializing next video card after HTML update');
    startNextVideoCountdown();
}

/**
 * Initialize event handlers for suggested video cards
 */
function initializeSuggestedVideoCards() {
    const suggestedCards = document.querySelectorAll('.overlay-video-card');
    console.log('Initializing suggested video card handlers, found:', suggestedCards.length);
    
    suggestedCards.forEach(card => {
        const videoId = card.getAttribute('data-video-id');
        const youtubeId = card.getAttribute('data-youtube-id');
        if (!videoId) return;
        
        // Remove existing onclick attribute to avoid conflicts
        card.removeAttribute('onclick');
        
        // Add proper event listener
        card.addEventListener('click', () => {
            console.log('Suggested video card clicked! Video ID:', videoId, 'YouTube ID:', youtubeId);
            navigateToVideo(videoId, youtubeId);
        });
    });
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

 