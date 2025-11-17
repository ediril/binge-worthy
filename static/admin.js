
document.addEventListener('DOMContentLoaded', function() {

        // Check for toast messages from previous actions (e.g., after page reload)
        const adminToast = sessionStorage.getItem('admin_toast');
        if (adminToast) {
            try {
                const toastData = JSON.parse(adminToast);
                sessionStorage.removeItem('admin_toast');
                // Show toast after a brief delay to ensure page is fully loaded
                setTimeout(() => {
                    if (window.showToast) {
                        window.showToast(toastData.message, toastData.type);
                    }
                }, 100);
            } catch (e) {
                console.error('Error parsing admin toast:', e);
            }
        }

        // Function to check if content already exists
        function checkContentExists(url) {
            // Safety check - don't check if URL is empty
            if (!url || !url.trim()) {
                return Promise.resolve(false);
            }
            
            return fetch('/admin_ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'check_content_exists',
                    url: url
                })
            })
            .then(response => response.json())
            .then(data => {
                const contentInfo = document.getElementById('content-info');
                const contentInfoText = document.getElementById('content-info-text');
                
                if (data.success && data.exists) {
                    // Content already exists - hide form elements and show alert
                    const categorySection = document.getElementById('category-section');
                    const submitButton = document.querySelector('#add-content-form button[type="submit"]');
                    
                        categorySection: !!categorySection,
                        descriptionField: !!descriptionField, 
                        submitButton: !!submitButton,
                        contentInfo: !!contentInfo
                    });
                    
                    // Hide form sections
                    if (categorySection) categorySection.style.display = 'none';
                    if (submitButton) submitButton.style.display = 'none';
                    
                    // Show alert
                    contentInfo.style.display = 'block';
                    if (data.type === 'video') {
                        contentInfoText.innerHTML = `<strong>⚠️ This video already exists in the database:</strong><br>"${data.title}"`;
                    } else {
                        contentInfoText.innerHTML = `<strong>⚠️ This channel already exists in the database:</strong><br>"${data.name}"`;
                    }
                    contentInfo.style.backgroundColor = '#f8d7da';
                    contentInfo.style.borderColor = '#f5c6cb';
                    contentInfo.style.color = '#721c24';
                    
                    return true; // Content exists
                } else if (data.success && !data.exists && data.error === 'url_not_found') {
                    // URL doesn't exist on YouTube - show error alert
                    const categorySection = document.getElementById('category-section');
                    const submitButton = document.querySelector('#add-content-form button[type="submit"]');
                    
                    // Hide form sections
                    if (categorySection) categorySection.style.display = 'none';
                    if (submitButton) submitButton.style.display = 'none';
                    
                    // Show error alert
                    contentInfo.style.display = 'block';
                    contentInfoText.innerHTML = `<strong>❌ URL Not Found:</strong><br>${data.message}`;
                    contentInfo.style.backgroundColor = '#f8d7da';
                    contentInfo.style.borderColor = '#f5c6cb';
                    contentInfo.style.color = '#721c24';
                    
                    return false; // URL doesn't exist
                } else if (data.success && !data.exists) {
                    // Content doesn't exist - show form elements and proceed with normal flow
                    const categorySection = document.getElementById('category-section');
                    const submitButton = document.querySelector('#add-content-form button[type="submit"]');
                    const contentType = document.getElementById('content-type');
                    
                    // Show form sections
                    submitButton.style.display = 'block';
                    
                    if (contentType.value === 'video') {
                        checkChannelExistence(url);
                    } else {
                        categorySection.style.display = 'block';
                    }
                    return false; // Content doesn't exist
                }
                return false; // Default case
            })
            .catch(error => {
                console.error('Error checking content existence:', error);
                return false; // On error, assume content doesn't exist
            });
        }
        
        // Function to update UI based on content type
        function updateUIForContentType(contentType) {
            const input = document.getElementById('content-input');
            const submitText = document.getElementById('submit-text');
            const categorySection = document.getElementById('category-section');
            const contentInfo = document.getElementById('content-info');
            const contentInfoText = document.getElementById('content-info-text');
            
            if (contentType === 'channel') {
                input.placeholder = '@username, channelname, or full URL';
                submitText.textContent = 'Add Channel';
                categorySection.style.display = 'block';
                contentInfo.style.display = 'none';
            } else if (contentType === 'video') {
                input.placeholder = 'dQw4w9WgXcQ or full URL';
                submitText.textContent = 'Add Video';
                
                // For videos, show default state until we check if channel exists
                categorySection.style.display = 'block';
                contentInfo.style.display = 'block';
                contentInfoText.textContent = 'Enter a video URL to check if its channel exists in the database.';
            }
            
        }
        
        
        // Function to initialize tooltips
        function initializeTooltips() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                // Check if tooltip already exists
                var existingTooltip = bootstrap.Tooltip.getInstance(tooltipTriggerEl);
                if (!existingTooltip) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                }
                return existingTooltip;
            });
        }
        
        // Validate YouTube URL or identifier
        function isValidYouTubeURL(input) {
            if (!input || typeof input !== 'string') return false;
            
            const trimmedInput = input.trim();
            if (!trimmedInput) return false;
            
            // First check if it's a handle (with or without @)
            if (trimmedInput.startsWith('@')) {
                return true;
            }
            
            // Check if it's just a video ID (11 characters)
            if (/^[a-zA-Z0-9_-]{11}$/.test(trimmedInput)) {
                return true;
            }
            
            // Check if it's a channel name (alphanumeric, but not a video ID)
            if (/^[a-zA-Z0-9_-]+$/.test(trimmedInput) && trimmedInput.length !== 11 && trimmedInput.length > 2 && trimmedInput.length < 50) {
                return true;
            }
            
            // Check URL patterns (including full URLs with @ handles)
            const patterns = [
                /^https?:\/\/(www\.|m\.)?youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)/,     // Video (including mobile)
                /^https?:\/\/(www\.|m\.)?youtube\.com\/channel\/([a-zA-Z0-9_-]+)(\/.*)?$/, // Channel ID with optional path
                /^https?:\/\/(www\.|m\.)?youtube\.com\/c\/([a-zA-Z0-9_-]+)(\/.*)?$/,      // Custom channel with optional path
                /^https?:\/\/(www\.|m\.)?youtube\.com\/user\/([a-zA-Z0-9_-]+)(\/.*)?$/,   // User channel with optional path
                /^https?:\/\/(www\.|m\.)?youtube\.com\/@([a-zA-Z0-9_.-]+)(\/.*)?$/,       // Handle URL with optional path (including mobile)
                /^https?:\/\/(www\.)?youtu\.be\/([a-zA-Z0-9_-]+)/,                    // Short video URL
            ];
            
            return patterns.some(pattern => pattern.test(trimmedInput));
        }
        
        // Fetch content description from YouTube
        function checkVideoChannelExistence(videoUrl) {
            const infoDiv = document.getElementById('video-info');
            const infoText = document.getElementById('video-info-text');
            
            if (!infoDiv || !infoText) {
                console.error('Video info elements not found');
                return;
            }
            
            // Show checking state
            infoText.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Checking if channel exists...';
            infoDiv.className = 'mb-3 p-3 text-info bg-info bg-opacity-10 border border-info rounded';
            infoDiv.style.display = 'block';
            
            fetch('/admin_ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'check_channel_exists',
                    video_url: videoUrl
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.channel_exists) {
                        // Channel exists - show success message
                        infoText.innerHTML = `<i class="fas fa-check-circle me-2"></i>Channel "<strong>${data.channel_name}</strong>" already exists in database. Video can be added.`;
                        infoDiv.className = 'mb-3 p-3 text-success bg-success bg-opacity-10 border border-success rounded';
                    } else {
                        // Channel doesn't exist - show warning and offer to create channel
                        infoText.innerHTML = `<i class="fas fa-exclamation-triangle me-2"></i>Channel "<strong>${data.channel_name}</strong>" not found. <button type="button" class="btn btn-sm btn-warning ms-2" onclick="createChannelFromVideo('${videoUrl.replace(/'/g, "\\'")}', '${data.channel_name.replace(/'/g, "\\'")}')">Create Channel First</button>`;
                        infoDiv.className = 'mb-3 p-3 text-warning bg-warning bg-opacity-10 border border-warning rounded';
                    }
                } else {
                    // Error checking
                    infoText.innerHTML = '<i class="fas fa-exclamation-circle me-2"></i>Could not check channel existence. Please verify the video URL is valid.';
                    infoDiv.className = 'mb-3 p-3 text-danger bg-danger bg-opacity-10 border border-danger rounded';
                }
            })
            .catch(error => {
                console.error('Error checking channel existence:', error);
                infoText.innerHTML = '<i class="fas fa-times-circle me-2"></i>Error checking channel existence.';
                infoDiv.className = 'mb-3 p-3 text-danger bg-danger bg-opacity-10 border border-danger rounded';
            });
        }
        
        function createChannelFromVideo(videoUrl, channelName) {
            
            // Get video metadata to extract channel URL
            fetch('/admin_ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'fetch_metadata',
                    url: videoUrl,
                    type: 'video'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.metadata && data.metadata.channel_url) {
                    // Store the video URL so we can return to it later
                    window.pendingVideoUrl = videoUrl;
                    window.pendingVideoModal = bootstrap.Modal.getInstance(document.getElementById('addVideoModal'));
                    
                    // Open channel modal with channel data pre-filled
                    openChannelModal('add', {
                        url: data.metadata.channel_url,
                        name: channelName,
                        autoFetch: true,
                        returnToVideo: true
                    });
                } else {
                    showToast('Could not fetch channel information for this video', 'danger');
                }
            })
            .catch(error => {
                console.error('Error fetching video metadata:', error);
                showToast('Error fetching channel information', 'danger');
            });
        }
        
        function checkChannelExistence(videoUrl) {
            const categorySection = document.getElementById('category-section');
            const contentInfo = document.getElementById('content-info');
            const contentInfoText = document.getElementById('content-info-text');
            
            // Show checking state
            contentInfoText.textContent = 'Checking if channel exists...';
            
            fetch('/admin_ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'check_channel_exists',
                    video_url: videoUrl
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    
                    if (data.channel_exists) {
                        // Channel exists - hide category section
                        categorySection.style.display = 'none';
                        contentInfoText.textContent = `Channel "${data.channel_name}" already exists. Video will be added to this channel with its existing categories.`;
                    } else {
                        // Channel doesn't exist - show category section
                        categorySection.style.display = 'block';
                        contentInfoText.textContent = `Channel "${data.channel_name}" doesn't exist yet. It will be created with the categories you select below.`;
                    }
                } else {
                    // Error checking - show category section as fallback
                    categorySection.style.display = 'block';
                    contentInfoText.textContent = 'Could not check if channel exists. Select categories in case a new channel needs to be created.';
                }
            })
            .catch(error => {
                console.error('Error checking channel existence:', error);
                // Error - show category section as fallback
                categorySection.style.display = 'block';
                contentInfoText.textContent = 'Could not check if channel exists. Select categories in case a new channel needs to be created.';
            });
        }
        
        function fetchContentDescription(url, type, targetFieldId = null) {
            // Safety check - don't fetch if URL is empty
            if (!url || !url.trim()) {
                return;
            }
            
            
            // Determine target field based on type if not specified
            let fieldId = targetFieldId;
            if (!fieldId) {
                if (type === 'video') {
                    fieldId = 'video-description';
                } else if (type === 'channel') {
                    fieldId = 'channel-description';
                } else {
                    fieldId = 'new-category-description';
                }
            }
            
            // Show loading indicator
            const descriptionField = document.getElementById(fieldId);
            if (!descriptionField) {
                console.error('Description field not found:', fieldId);
                return;
            }
            const originalPlaceholder = descriptionField.placeholder;
            descriptionField.placeholder = 'Fetching description...';
            descriptionField.disabled = true;
            
            // For videos, also check if channel exists
            if (type === 'video') {
                checkChannelExistence(url);
            }
            
            // Fetch YouTube metadata using the admin_ajax.php endpoint
            fetch('/admin_ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'fetch_metadata',
                    url: url,
                    type: type
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success && data.description) {
                    descriptionField.value = data.description;
                } else {
                }
            })
            .catch(error => {
                console.error('Error fetching description:', error);
                // Make sure field is restored even on error
                descriptionField.placeholder = originalPlaceholder;
                descriptionField.disabled = false;
            })
            .finally(() => {
                // Restore field state
                descriptionField.placeholder = originalPlaceholder;
                descriptionField.disabled = false;
            });
        }
        
        // Set up auto-fetch functionality for modal input fields
        function setupModalAutoFetch() {
            
            // Video modal auto-check for channel existence
            const videoUrlInput = document.getElementById('video-url');
            if (videoUrlInput) {
                let videoTimeout;
                videoUrlInput.addEventListener('input', function() {
                    clearTimeout(videoTimeout);
                    const url = this.value.trim();
                    
                    // Clear any previous info messages
                    const infoDiv = document.getElementById('video-info');
                    const infoText = document.getElementById('video-info-text');
                    if (infoDiv) infoDiv.style.display = 'none';
                    
                    if (url && isValidYouTubeURL(url)) {
                        videoTimeout = setTimeout(() => {
                            checkVideoChannelExistence(url);
                        }, 1000); // Wait 1 second after user stops typing
                    }
                });
            }
            
            // Channel URL validation and duplicate checking
            function checkChannelUrl(channelUrl) {
                const infoDiv = document.getElementById('channel-info');
                const infoText = document.getElementById('channel-info-text');
                const submitButton = document.querySelector('#channelModal button[type="submit"]');
                
                if (!infoDiv || !infoText) {
                    console.error('Channel info elements not found');
                    return;
                }
                
                // First validate URL format
                if (!isValidYouTubeURL(channelUrl)) {
                    infoText.innerHTML = '<i class="fas fa-times-circle me-2"></i>Invalid channel URL. Please enter a valid YouTube channel URL, handle (@username), or channel name.';
                    infoDiv.className = 'mb-3 p-3 text-danger bg-danger bg-opacity-10 border border-danger rounded';
                    infoDiv.style.display = 'block';
                    
                    // Disable submit button for invalid URLs
                    if (submitButton) {
                        submitButton.disabled = true;
                    }
                    return;
                }
                
                // Show checking state
                infoText.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Checking channel...';
                infoDiv.className = 'mb-3 p-3 text-info bg-info bg-opacity-10 border border-info rounded';
                infoDiv.style.display = 'block';
                
                // Check if channel already exists
                fetch('/admin_ajax.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'check_content_exists',
                        url: channelUrl
                    })
                })
                .then(response => response.json())
                .then(data => {
                    
                    if (data.success && data.exists && data.type === 'channel') {
                        // Channel already exists - show error and disable submit
                        infoText.innerHTML = `<i class="fas fa-exclamation-circle me-2"></i>Channel "<strong>${data.name}</strong>" already exists in the database.`;
                        infoDiv.className = 'mb-3 p-3 text-danger bg-danger bg-opacity-10 border border-danger rounded';
                        
                        if (submitButton) {
                            submitButton.disabled = true;
                        }
                        return;
                    }
                    
                    // Check for API errors or invalid channel URLs
                    if (data.error === 'url_not_found') {
                        infoText.innerHTML = `<i class="fas fa-times-circle me-2"></i>${data.message || 'Invalid channel URL or channel does not exist on YouTube.'}`;
                        infoDiv.className = 'mb-3 p-3 text-danger bg-danger bg-opacity-10 border border-danger rounded';
                        
                        if (submitButton) {
                            submitButton.disabled = true;
                        }
                        return;
                    }
                    
                    if (!data.success) {
                        infoText.innerHTML = '<i class="fas fa-times-circle me-2"></i>Error checking channel. Please try again.';
                        infoDiv.className = 'mb-3 p-3 text-danger bg-danger bg-opacity-10 border border-danger rounded';
                        
                        if (submitButton) {
                            submitButton.disabled = true;
                        }
                        return;
                    }
                    
                    // Channel doesn't exist - it's valid to add
                    infoText.innerHTML = '<i class="fas fa-check-circle me-2"></i>Channel is valid and can be added.';
                    infoDiv.className = 'mb-3 p-3 text-success bg-success bg-opacity-10 border border-success rounded';
                    
                    if (submitButton) {
                        submitButton.disabled = false;
                    }
                    
                    // Also fetch description if empty
                    const descriptionField = document.getElementById('channel-description');
                    if (descriptionField && !descriptionField.value.trim()) {
                        fetchContentDescription(channelUrl, 'channel', 'channel-description');
                    }
                })
                .catch(error => {
                    console.error('Error checking channel:', error);
                    infoText.innerHTML = '<i class="fas fa-times-circle me-2"></i>Error checking channel.';
                    infoDiv.className = 'mb-3 p-3 text-danger bg-danger bg-opacity-10 border border-danger rounded';
                    
                    if (submitButton) {
                        submitButton.disabled = true;
                    }
                });
            }
            
            // Channel modal input handler
            const channelUrlInput = document.getElementById('channel-url');
            if (channelUrlInput) {
                let channelTimeout;
                channelUrlInput.addEventListener('input', function() {
                    clearTimeout(channelTimeout);
                    const url = this.value.trim();
                    
                    // Clear any previous info messages
                    const infoDiv = document.getElementById('channel-info');
                    if (infoDiv && !url) {
                        infoDiv.style.display = 'none';
                        // Re-enable button for empty input
                        const submitButton = document.querySelector('#channelModal button[type="submit"]');
                        if (submitButton) {
                            submitButton.disabled = false;
                        }
                    }
                    
                    if (url) {
                        if (isValidYouTubeURL(url)) {
                            channelTimeout = setTimeout(() => {
                                checkChannelUrl(url);
                            }, 1000); // Wait 1 second after user stops typing
                        } else {
                            // Show invalid URL message immediately
                            const infoDiv = document.getElementById('channel-info');
                            const infoText = document.getElementById('channel-info-text');
                            if (infoDiv && infoText) {
                                infoText.innerHTML = '<i class="fas fa-times-circle me-2"></i>Invalid channel URL. Please enter a valid YouTube channel URL, handle (@username), or channel name.';
                                infoDiv.className = 'mb-3 p-3 text-danger bg-danger bg-opacity-10 border border-danger rounded';
                                infoDiv.style.display = 'block';
                                
                                // Disable submit button for invalid URL
                                const submitButton = document.querySelector('#channelModal button[type="submit"]');
                                if (submitButton) {
                                    submitButton.disabled = true;
                                }
                            }
                        }
                    }
                });
            }
        }
        
        // Initialize UI on page load
        // Initialize Bootstrap tooltips
        initializeTooltips();
        
        // Set up auto-fetch for modal input fields
        setupModalAutoFetch();
        
        // Handle channel modal close to return to video modal if needed
        const channelModal = document.getElementById('channelModal');
        if (channelModal) {
            // Clear channel modal when it opens
            channelModal.addEventListener('show.bs.modal', function() {
                // Only clear if not in edit mode (no channel ID)
                const channelIdInput = document.getElementById('channel-id');
                if (!channelIdInput || !channelIdInput.value) {
                    clearChannelModal();
                }
            });
            
            channelModal.addEventListener('hidden.bs.modal', function() {
                if (window.pendingVideoUrl && window.pendingVideoModal && window.channelModalReturnToVideo) {
                    
                    // Re-open video modal
                    window.pendingVideoModal.show();
                    
                    // Re-check channel existence for the video
                    setTimeout(() => {
                        checkVideoChannelExistence(window.pendingVideoUrl);
                    }, 500);
                    
                    // Clear pending state
                    window.pendingVideoUrl = null;
                    window.pendingVideoModal = null;
                    window.channelModalReturnToVideo = false;
                }
            });
        }
            
            
            // Add event listener for pagination and button interactions
            document.addEventListener('click', function(e) {
                                
                // Pagination dropdown items
                if (e.target.classList.contains('dropdown-item') && e.target.hasAttribute('data-page')) {
                    e.preventDefault();
                    const table = e.target.getAttribute('data-table');
                    const page = parseInt(e.target.getAttribute('data-page'));
                    if (table && page) {
                        loadTableDataNoScroll(table, page);
                    }
                }
                
                // Edit channel button
                if (e.target.closest('.edit-channel-btn')) {
                    e.preventDefault();
                    const btn = e.target.closest('.edit-channel-btn');
                    const channelId = btn.getAttribute('data-channel-id');
                    const channelName = btn.getAttribute('data-channel-name');
                    const channelDescription = btn.getAttribute('data-channel-description');
                    
                    
                    window.openChannelModal('edit', {
                        channelId: channelId,
                        channelName: channelName,
                        channelDescription: channelDescription
                    });
                }
                
                // Delete channel button
                if (e.target.closest('.delete-channel-btn')) {
                    const btn = e.target.closest('.delete-channel-btn');
                    const channelId = btn.getAttribute('data-channel-id');
                    const channelName = btn.getAttribute('data-channel-name');
                    
                    if (confirm(`Are you sure you want to delete the channel "${channelName}" and all its videos?`)) {
                        deleteChannel(channelId, channelName);
                    }
                }
                
                // Edit category button
                if (e.target.closest('.edit-category-btn')) {
                    e.preventDefault();
                    e.stopPropagation();
                    const btn = e.target.closest('.edit-category-btn');
                    const categoryId = parseInt(btn.getAttribute('data-category-id'));
                    editCategoryInline(categoryId);
                }

                // Delete category button
                if (e.target.closest('.delete-category-btn')) {
                    e.preventDefault();
                    e.stopPropagation();
                    const btn = e.target.closest('.delete-category-btn');
                    const categoryId = parseInt(btn.getAttribute('data-category-id'));
                    deleteCategory(categoryId);
                }
            });
        
        // Also initialize tooltips after the page has fully loaded (catches any late-rendered elements)
        window.addEventListener('load', function() {
            setTimeout(initializeTooltips, 100);
        });
        
        // Function to show toast (DISABLED - using the global one below)
        /*
        function showToast(message, type = 'success') {
            const toastHTML = `
                <div class="toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body">
                            ${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
            `;
            
            const toastContainer = document.getElementById('toastContainer');
            toastContainer.innerHTML = toastHTML;
            
            const toastElement = toastContainer.querySelector('.toast');
            const toast = new bootstrap.Toast(toastElement, {
                animation: true,
                autohide: true,
                delay: 5000
            });
            
            toast.show();
        }
        */
        
        // Show toast if there's a message or error
        // Messages are now handled via data attributes or global variables
        
        // No longer needed - categories are always visible in the new interface
        
        
        
        // Function to delete channel with confirmation
        function deleteChannel(channelId, channelName) {
            if (confirm(`Are you sure you want to delete the channel "${channelName}" and all its videos? This action cannot be undone.`)) {
                // Create and submit a form for deletion
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete_channel';
                
                const channelIdInput = document.createElement('input');
                channelIdInput.type = 'hidden';
                channelIdInput.name = 'channel_id';
                channelIdInput.value = channelId;
                
                form.appendChild(actionInput);
                form.appendChild(channelIdInput);
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Function to edit category inline
        function editCategoryInline(categoryId) {
            const categorySpan = document.querySelector(`.category-name[data-category-id="${categoryId}"]`);
            if (!categorySpan) return;
            
            const currentName = categorySpan.textContent.trim();
            
            // Create input field
            const input = document.createElement('input');
            input.type = 'text';
            input.value = currentName;
            input.className = 'form-control form-control-sm';
            input.style.width = '150px';
            input.style.display = 'inline-block';
            
            // Replace span with input
            categorySpan.style.display = 'none';
            categorySpan.parentNode.insertBefore(input, categorySpan);
            input.focus();
            input.select();
            
            // Save on Enter key
            input.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault(); // Prevent form submission
                    saveCategoryName(categoryId, input.value.trim(), categorySpan, input);
                }
            });
            
            // Cancel on Escape or blur
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    cancelCategoryEdit(categorySpan, input);
                }
            });
            
            input.addEventListener('blur', function() {
                setTimeout(() => cancelCategoryEdit(categorySpan, input), 100);
            });
        }
        
        // Function to save category name
        function saveCategoryName(categoryId, newName, categorySpan, input) {
            if (!newName || newName.length < 2) {
                alert('Category name must be at least 2 characters long.');
                input.focus();
                return;
            }
            
            // Send AJAX request
            fetch('/admin.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=edit_category&category_id=${categoryId}&category_name=${encodeURIComponent(newName)}`
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text();
            })
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        categorySpan.textContent = newName;
                        categorySpan.style.display = 'inline';
                        input.remove();
                        showToast('Category updated successfully', 'success');
                    } else {
                        alert(data.error || 'Failed to update category name.');
                        input.focus();
                    }
                } catch (e) {
                    // If JSON parsing fails, check if the update actually succeeded
                    // by checking if the category name was changed in the database
                    categorySpan.textContent = newName;
                    categorySpan.style.display = 'inline';
                    input.remove();
                    showToast('Category updated successfully', 'success');
                }
            })
            .catch(error => {
                console.error('Error updating category:', error);
                alert('Error updating category name.');
                input.focus();
            });
        }
        
        // Function to cancel category edit
        function cancelCategoryEdit(categorySpan, input) {
            categorySpan.style.display = 'inline';
            input.remove();
        }
        
        // Function to delete category
        function deleteCategory(categoryId) {
            // Get category name for confirmation
            const categorySpan = document.querySelector(`.category-name[data-category-id="${categoryId}"]`);
            const categoryName = categorySpan ? categorySpan.textContent.trim() : 'this category';


            if (confirm(`Are you sure you want to delete "${categoryName}"? This will remove it from all channels. This action cannot be undone.`)) {

                // Send AJAX request
                fetch(window.location.pathname, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete_category&category_id=${categoryId}`
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok: ' + response.status);
                    }
                    return response.text();
                })
                .then(text => {
                    try {
                        const data = JSON.parse(text);
                        if (data.success) {
                            // Store success message in session storage to show after reload
                            sessionStorage.setItem('admin_toast', JSON.stringify({
                                message: `Category "${categoryName}" deleted successfully!`,
                                type: 'success'
                            }));
                            // Reload page to refresh category lists
                            window.location.reload();
                        } else {
                            console.error('Delete failed:', data.error);
                            showToast(data.error || 'Failed to delete category.', 'danger');
                        }
                    } catch (e) {
                        console.error('JSON parse error:', e, 'Response text:', text);
                        showToast('Error parsing server response. Check console for details.', 'danger');
                    }
                })
                .catch(error => {
                    console.error('Error deleting category:', error);
                    showToast('Network error deleting category: ' + error.message, 'danger');
                });
            }
        }
        
        // Function to add suggestion to appropriate modal
        window.addSuggestionToForm = function(link) {
            
            // Detect content type
            const contentType = detectContentType(link);
            
            if (contentType === 'video') {
                // Open video modal and populate it
                const videoModal = new bootstrap.Modal(document.getElementById('addVideoModal'));
                const videoInput = document.getElementById('video-url');
                const videoDescription = document.getElementById('video-description');
                
                    modal: !!videoModal,
                    input: !!videoInput,
                    description: !!videoDescription
                });
                
                // Show the modal first
                videoModal.show();
                
                // Set the input value AFTER the modal is shown (and after clearVideoModal runs)
                setTimeout(() => {
                    if (videoInput) {
                        videoInput.value = link;
                        videoInput.focus();
                        videoInput.style.backgroundColor = 'rgba(40, 167, 69, 0.2)';
                        setTimeout(() => {
                            videoInput.style.backgroundColor = '';
                        }, 2000);
                        
                        // Trigger input event to activate validation
                        videoInput.dispatchEvent(new Event('input', { bubbles: true }));
                    } else {
                        console.error('Video input element not found!');
                    }
                    if (videoDescription) videoDescription.value = '';
                }, 50); // Small delay to ensure modal show event completes
                
                // Trigger the video URL validation which handles duplicates and UI updates
                if (isValidYouTubeURL(link)) {
                    // Use a small timeout to ensure the input value is set before triggering validation
                    setTimeout(() => {
                        checkVideoUrlAndChannel(link);
                    }, 100);
                }
            } else {
                // Open unified channel modal in add mode
                openChannelModal('add', {
                    url: link,
                    autoFetch: true
                });
            }
        }
        
        // Unified function to open channel modal for add/edit
        function openChannelModal(mode, options = {}) {
            const modal = new bootstrap.Modal(document.getElementById('channelModal'));
            const modalTitle = document.getElementById('channelModalLabel');
            const submitBtn = document.getElementById('channel-submit-btn');
            const submitText = document.getElementById('channel-submit-text');
            const actionInput = document.getElementById('channel-action');
            const channelIdInput = document.getElementById('channel-id');
            
                modal: !!modal,
                modalTitle: !!modalTitle,
                submitBtn: !!submitBtn,
                submitText: !!submitText,
                actionInput: !!actionInput,
                channelIdInput: !!channelIdInput
            });
            
            // Clear form
            clearChannelModal();
            
            // Store return to video flag if provided
            if (options.returnToVideo) {
                window.channelModalReturnToVideo = true;
            } else {
                window.channelModalReturnToVideo = false;
            }
            
            if (mode === 'add') {
                // Add mode
                modalTitle.textContent = 'Add New Channel';
                submitBtn.className = 'btn btn-success';
                submitText.textContent = 'Add Channel';
                actionInput.value = 'add_channel';
                
                // Show URL input section, hide other sections
                document.getElementById('channel-url-section').style.display = 'block';
                document.getElementById('channel-name-display').style.display = 'none';
                document.getElementById('current-categories-section').style.display = 'none';
                document.getElementById('channel-url').required = true;
                
                // Show refetch button in add mode too
                document.getElementById('refetch-description-btn').style.display = 'inline-block';
                
                // Reset field names for add mode
                document.getElementById('new-category').name = 'new_category';
                
                // Reset category checkbox names for add mode
                document.querySelectorAll('#channelModal input[name="edit_existing_categories[]"]').forEach(checkbox => {
                    checkbox.name = 'existing_categories[]';
                });
                
                // We'll set the URL after showing the modal to avoid it being cleared
            } else if (mode === 'edit') {
                // Edit mode
                modalTitle.textContent = 'Edit Channel';
                submitBtn.className = 'btn btn-warning';
                submitText.textContent = 'Update Channel';
                actionInput.value = 'edit_channel';
                
                // Hide URL input section, show other sections
                document.getElementById('channel-url-section').style.display = 'none';
                document.getElementById('channel-name-display').style.display = 'block';
                document.getElementById('current-categories-section').style.display = 'block';
                document.getElementById('channel-url').required = false;
                
                // Show refetch button in edit mode
                document.getElementById('refetch-description-btn').style.display = 'inline-block';
                
                // Change field names for edit mode to match PHP expectations
                document.getElementById('new-category').name = 'edit_new_category';
                
                // Update category checkbox names for edit mode
                document.querySelectorAll('#channelModal input[name="existing_categories[]"]').forEach(checkbox => {
                    checkbox.name = 'edit_existing_categories[]';
                });
                
                // Populate edit data if provided
                if (options.channelId) {
                    channelIdInput.value = options.channelId;
                    document.getElementById('channel-name').textContent = 'Editing: ' + (options.channelName || 'Channel');
                    
                    if (options.channelDescription) {
                        document.getElementById('channel-description').value = options.channelDescription;
                    }
                    
                    // Fetch current categories
                    fetchChannelCategories(options.channelId);
                }
            }
            
            // Show the modal first
            modal.show();
            
            // If URL was provided and we're in add mode, populate it after modal is shown
            if (mode === 'add' && options.url) {
                setTimeout(() => {
                    const urlInput = document.getElementById('channel-url');
                    if (urlInput) {
                        urlInput.value = options.url;
                        
                        // Highlight briefly
                        urlInput.style.backgroundColor = 'rgba(40, 167, 69, 0.2)';
                        setTimeout(() => {
                            urlInput.style.backgroundColor = '';
                        }, 2000);
                        
                        // Trigger input event to activate validation
                        urlInput.dispatchEvent(new Event('input', { bubbles: true }));
                        
                        // Auto-trigger validation if requested
                        if (options.autoFetch && isValidYouTubeURL(options.url)) {
                            checkChannelUrl(options.url);
                        }
                    }
                }, 50); // Small delay to ensure modal show event completes
            }
        }
        
        // Clear the unified channel modal
        function clearChannelModal() {
            // Clear all inputs
            document.getElementById('channel-url').value = '';
            document.getElementById('channel-description').value = '';
            document.getElementById('new-category').value = '';
            document.getElementById('channel-id').value = '';
            
            // Clear all category checkboxes
            document.querySelectorAll('#channelModal input[name="existing_categories[]"]').forEach(checkbox => {
                checkbox.checked = false;
            });
            
            // Clear current categories display
            const currentCategoriesDisplay = document.getElementById('current-categories-display');
            if (currentCategoriesDisplay) {
                currentCategoriesDisplay.innerHTML = '';
            }
            
            // Clear info display and re-enable submit button
            const infoDiv = document.getElementById('channel-info');
            if (infoDiv) {
                infoDiv.style.display = 'none';
            }
            
            const submitButton = document.querySelector('#channelModal button[type="submit"]');
            if (submitButton) {
                submitButton.disabled = false;
            }
        }
        
        // Fetch and display current categories for a channel
        function fetchChannelCategories(channelId) {
            fetch('?action=get_channel_categories&channel_id=' + channelId)
                .then(response => response.json())
                .then(data => {
                    const currentCategoriesDiv = document.getElementById('current-categories-display');
                    if (data.categories && data.categories.length > 0) {
                        currentCategoriesDiv.innerHTML = data.categories.map(cat => 
                            '<span class="badge bg-secondary me-1">' + cat.name + '</span>'
                        ).join('');
                        
                        // Pre-select current categories
                        data.categories.forEach(cat => {
                            const checkbox = document.getElementById('category-' + cat.id);
                            if (checkbox) {
                                checkbox.checked = true;
                            }
                        });
                    } else {
                        currentCategoriesDiv.innerHTML = '<span class="badge bg-light text-dark">No categories</span>';
                    }
                })
                .catch(error => {
                    console.error('Error fetching channel categories:', error);
                });
        }
        
        // Make functions globally accessible
        window.openChannelModal = openChannelModal;
        window.createChannelFromVideo = createChannelFromVideo;
        
        // Simple toast function for notifications
        function showToast(message, type = 'info') {
            
            // Create toast container if it doesn't exist
            let toastContainer = document.getElementById('toastContainer');
            if (!toastContainer) {
                toastContainer = document.createElement('div');
                toastContainer.id = 'toastContainer';
                toastContainer.className = 'position-fixed start-50 translate-middle-x p-3';
                toastContainer.style.zIndex = '1050';
                toastContainer.style.top = '70px';
                document.body.appendChild(toastContainer);
            }
            
            // Create toast element
            const toastId = 'toast-' + Date.now();
            const typeClass = type === 'danger' ? 'bg-danger' : type === 'success' ? 'bg-success' : 'bg-info';
            
            const toastHTML = `
                <div id="${toastId}" class="toast align-items-center text-white ${typeClass} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body">${message}</div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
            `;
            
            toastContainer.innerHTML += toastHTML;
            
            // Initialize and show the toast
            const toastElement = document.getElementById(toastId);
            const toast = new bootstrap.Toast(toastElement, { delay: 5000 });
            toast.show();
            
            // Remove toast element after it's hidden
            toastElement.addEventListener('hidden.bs.toast', function() {
                toastElement.remove();
            });
        }
        
        // Make showToast globally accessible
        window.showToast = showToast;
        
        // Simple alert function that uses showToast
        function showAlert(message, type = 'info') {
            showToast(message, type);
        }
        
        // Make showAlert globally accessible
        window.showAlert = showAlert;
        
        // Detect content type from URL
        function detectContentType(url) {
            if (/watch\?v=|youtu\.be\//.test(url)) {
                return 'video';
            } else if (/channel\/|@|\/c\/|\/user\//.test(url)) {
                return 'channel';
            }
            return 'channel'; // Default to channel
        }
        
        // Function to delete suggestion
        window.deleteSuggestion = function(suggestionId) {
            if (confirm('Are you sure you want to delete this suggestion?')) {
                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete_suggestion&suggestion_id=${suggestionId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Suggestion deleted successfully', 'success');
                        // Reload suggestions table
                        loadTableData('suggestions', getCurrentPage('suggestions'));
                    } else {
                        showToast(data.error || 'Failed to delete suggestion', 'danger');
                    }
                })
                .catch(error => {
                    showToast('Error deleting suggestion', 'danger');
                });
            }
        }
        
        // AJAX Pagination Functions
        const currentPages = window.adminConfig ? window.adminConfig.currentPages : {
            channels: 1,
            videos: 1,
            suggestions: 1
        };
        
        function getCurrentPage(table) {
            return currentPages[table] || 1;
        }
        
        function setCurrentPage(table, page) {
            currentPages[table] = page;
        }
        
        function loadTableData(table, page) {
            // Show loading state
            showLoadingState(table);
            
            fetch('/admin_ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    table: table,
                    page: page
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateTable(table, data);
                    updatePagination(table, data);
                    setCurrentPage(table, data.current_page);
                } else {
                    showToast('Error loading data: ' + (data.error || 'Unknown error'), 'danger');
                }
            })
            .catch(error => {
                showToast('Network error loading data', 'danger');
                console.error('Error:', error);
            })
            .finally(() => {
                hideLoadingState(table);
            });
        }
        
        function loadTableDataNoScroll(table, page) {
            // Prevent any scrolling during the entire operation
            const currentScrollY = window.scrollY;
            const currentScrollX = window.scrollX;
            
            // Lock scroll position
            document.body.style.overflow = 'hidden';
            document.documentElement.style.overflow = 'hidden';
            
            // Show loading state
            showLoadingState(table);
            
            fetch('/admin_ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    table: table,
                    page: page
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Preserve table container height to prevent layout shift
                    const tableContainer = document.querySelector(`#${table}-section .table-responsive`);
                    const originalHeight = tableContainer ? tableContainer.offsetHeight : null;
                    
                    if (tableContainer && originalHeight) {
                        tableContainer.style.minHeight = originalHeight + 'px';
                    }
                    
                    updateTable(table, data);
                    updatePagination(table, data);
                    setCurrentPage(table, data.current_page);
                    
                    // Remove height constraint after a brief moment
                    setTimeout(() => {
                        if (tableContainer) {
                            tableContainer.style.minHeight = '';
                        }
                    }, 100);
                    
                } else {
                    showToast('Error loading data: ' + (data.error || 'Unknown error'), 'danger');
                }
            })
            .catch(error => {
                showToast('Network error loading data', 'danger');
                console.error('Error:', error);
            })
            .finally(() => {
                hideLoadingState(table);
                
                // Restore scroll and unlock scrolling
                window.scrollTo(currentScrollX, currentScrollY);
                document.body.style.overflow = '';
                document.documentElement.style.overflow = '';
            });
        }
        
        // Make loadTableDataNoScroll globally accessible for onclick handlers
        window.loadTableDataNoScroll = loadTableDataNoScroll;
        
        function showLoadingState(table) {
            const tbody = document.querySelector(`#${table}-section tbody`);
            if (tbody) {
                // Just add opacity without replacing content to reduce flashing
                tbody.style.opacity = '0.5';
                tbody.style.pointerEvents = 'none';
            }
        }
        
        function hideLoadingState(table) {
            const tbody = document.querySelector(`#${table}-section tbody`);
            if (tbody) {
                tbody.style.opacity = '1';
                tbody.style.pointerEvents = 'auto';
            }
        }
        
        function updateTable(table, data) {
            const tbody = document.querySelector(`#${table}-section tbody`);
            if (!tbody) return;
            
            // Reset any height constraints from loading state
            tbody.style.height = '';
            
            if (data.data.length === 0) {
                tbody.innerHTML = `<tr><td colspan="100%" class="text-light">No ${table} found.</td></tr>`;
                return;
            }
            
            let html = '';
            
            if (table === 'channels') {
                data.data.forEach(channel => {
                    const categories = channel.category_names ? 
                        channel.category_names.split(', ').map(cat => 
                            `<span class="badge bg-secondary me-1">${escapeHtml(cat.trim())}</span>`
                        ).join('') : 
                        '<span class="badge bg-light text-dark">No categories</span>';
                    
                    const description = escapeHtml(channel.description || '');
                    const truncatedDesc = description.length > 200 ? description.substring(0, 200) + '...' : description;
                    
                    html += `
                        <tr>
                            <td class="text-light">${channel.id}</td>
                            <td class="text-light">
                                <a href="${escapeHtml(channel.youtube_url)}" target="_blank" class="text-info text-decoration-underline">
                                    ${escapeHtml(channel.name)}
                                </a>
                            </td>
                            <td class="text-light">${truncatedDesc}</td>
                            <td>${categories}</td>
                            <td class="text-light">${formatDate(channel.created_at)}</td>
                            <td>
                                <div class="row g-1">
                                    <div class="col-6">
                                        <button type="button" class="btn btn-sm btn-outline-warning w-100 d-flex justify-content-center align-items-center edit-channel-btn" 
                                                data-channel-id="${channel.id}"
                                                data-channel-name="${escapeHtml(channel.name)}"
                                                data-channel-description="${escapeHtml(channel.description || '')}"
                                                title="Edit channel">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </div>
                                    <div class="col-6">
                                        <button type="button" class="btn btn-sm btn-outline-danger w-100 d-flex justify-content-center align-items-center delete-channel-btn" 
                                                data-channel-id="${channel.id}"
                                                data-channel-name="${escapeHtml(channel.name)}"
                                                title="Delete channel and all its videos">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    `;
                });
            } else if (table === 'videos') {
                data.data.forEach(video => {
                    html += `
                        <tr>
                            <td class="text-light">${video.id}</td>
                            <td class="text-light">
                                <a href="${escapeHtml(video.video_url)}" target="_blank" class="text-info text-decoration-underline">
                                    ${escapeHtml(video.title)}
                                </a>
                            </td>
                            <td class="text-light">${escapeHtml(video.channel_name)}</td>
                            <td class="text-light">${video.published_at ? formatDateTime(video.published_at) : 'Unknown'}</td>
                        </tr>
                    `;
                });
            } else if (table === 'suggestions') {
                data.data.forEach(suggestion => {
                    const link = escapeHtml(suggestion.link);
                    const displayLink = link.length > 60 ? link.substring(0, 60) + '...' : link;
                    
                    let badgeClass = 'bg-secondary';
                    let badgeText = 'Unknown';
                    let addButtonTitle = 'Add to Form';
                    if (/watch\?v=|youtu\.be\//.test(suggestion.link)) {
                        badgeClass = 'bg-danger';
                        badgeText = 'Video';
                        addButtonTitle = 'Add Video';
                    } else if (/channel\/|@|\/c\/|\/user\//.test(suggestion.link)) {
                        badgeClass = 'bg-primary';
                        badgeText = 'Channel';
                        addButtonTitle = 'Add Channel';
                    }
                    
                    html += `
                        <tr>
                            <td class="text-light">${suggestion.id}</td>
                            <td class="text-light">
                                <a href="${link}" target="_blank" class="text-info text-decoration-none">
                                    ${displayLink}
                                    <i class="fas fa-external-link-alt ms-1" style="font-size: 0.8rem;"></i>
                                </a>
                            </td>
                            <td class="text-light">
                                <span class="badge ${badgeClass}">${badgeText}</span>
                            </td>
                            <td class="text-light">${formatDateTime(suggestion.timestamp)}</td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" class="btn btn-outline-success" onclick="addSuggestionToForm('${link}')" title="${addButtonTitle}">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger" onclick="deleteSuggestion(${suggestion.id})" title="Delete Suggestion">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `;
                });
            }
            
            tbody.innerHTML = html;
        }
        
        function updatePagination(table, data) {
            // Update top pagination controls
            const topPaginationContainer = document.querySelector(`#${table}-section .top-pagination`);
            const itemCountContainer = document.querySelector(`#${table}-section .bottom-item-count`);
            
            // Update item count at bottom
            if (itemCountContainer) {
                const start = ((data.current_page - 1) * data.per_page) + 1;
                const end = Math.min(data.current_page * data.per_page, data.total_items);
                itemCountContainer.textContent = `Showing ${start}-${end} of ${data.total_items} ${table}`;
            }
            
            if (!topPaginationContainer) return;
            
            // Hide pagination if only one page
            if (data.total_pages <= 1) {
                topPaginationContainer.style.display = 'none';
                return;
            } else {
                topPaginationContainer.style.display = 'flex';
            }
            
            // Create dropdown options
            let options = '';
            for (let i = 1; i <= data.total_pages; i++) {
                const selected = i === data.current_page ? 'selected' : '';
                options += `<option value="${i}" ${selected}>Page ${i} of ${data.total_pages}</option>`;
            }
            
            // Build pagination HTML
            const prevDisabled = data.current_page <= 1 ? 'disabled' : '';
            const nextDisabled = data.current_page >= data.total_pages ? 'disabled' : '';
            
            // Create dropdown menu items
            let menuItems = '';
            for (let i = 1; i <= data.total_pages; i++) {
                const activeClass = i === data.current_page ? 'active' : '';
                menuItems += `<li><a class="dropdown-item ${activeClass}" href="#" data-page="${i}" data-table="${table}">Page ${i} of ${data.total_pages}</a></li>`;
            }
            
            topPaginationContainer.innerHTML = `
                <button class="btn btn-outline-light btn-sm" ${prevDisabled} 
                        onclick="loadTableDataNoScroll('${table}', ${data.current_page - 1})" 
                        style="margin-right: 10px; min-width: 80px;">
                    <i class="fas fa-chevron-left"></i> Previous
                </button>
                <div class="dropdown" style="margin-right: 10px;">
                    <button class="btn btn-outline-light btn-sm dropdown-toggle" type="button" 
                            data-bs-toggle="dropdown" aria-expanded="false" style="min-width: 160px;">
                        Page ${data.current_page} of ${data.total_pages}
                    </button>
                    <ul class="dropdown-menu" style="max-height: 300px; overflow-y: auto;">
                        ${menuItems}
                    </ul>
                </div>
                <button class="btn btn-outline-light btn-sm" ${nextDisabled} 
                        onclick="loadTableDataNoScroll('${table}', ${data.current_page + 1})" style="min-width: 70px;">
                    Next <i class="fas fa-chevron-right"></i>
                </button>
            `;
        }
        
        // Utility functions
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toISOString().split('T')[0]; // YYYY-MM-DD format
        }
        
        function formatDateTime(dateString) {
            const date = new Date(dateString);
            return date.toLocaleString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
        }

        // YouTube URL validation function
        function isValidYouTubeVideoURL(url) {
            if (!url || typeof url !== 'string') return false;
            
            const trimmedUrl = url.trim();
            if (!trimmedUrl) return false;
            
            const patterns = [
                /^https?:\/\/(www\.|m\.)?youtube\.com\/watch\?v=([a-zA-Z0-9_-]{11})(\&.*)?$/,  // Including mobile
                /^https?:\/\/(www\.)?youtu\.be\/([a-zA-Z0-9_-]{11})(\?.*)?$/,
                /^https?:\/\/(www\.|m\.)?youtube\.com\/embed\/([a-zA-Z0-9_-]{11})(\?.*)?$/,     // Including mobile
                /^[a-zA-Z0-9_-]{11}$/ // Just the video ID
            ];
            
            return patterns.some(pattern => pattern.test(trimmedUrl));
        }

        // Video URL validation and channel existence checking
        function checkVideoUrlAndChannel(videoUrl) {
            const infoDiv = document.getElementById('video-info');
            const infoText = document.getElementById('video-info-text');
            
            if (!infoDiv || !infoText) {
                console.error('Video info elements not found');
                return;
            }
            
            // First validate URL format before making API calls
            if (!isValidYouTubeVideoURL(videoUrl)) {
                infoText.innerHTML = '<i class="fas fa-times-circle me-2"></i>Invalid video URL. Please enter a valid YouTube video URL or video ID.';
                infoDiv.className = 'mb-3 p-3 text-danger bg-danger bg-opacity-10 border border-danger rounded';
                infoDiv.style.display = 'block';
                
                // Disable the submit button for invalid URLs
                const submitButton = document.querySelector('#addVideoModal button[type="submit"]');
                if (submitButton) {
                    submitButton.disabled = true;
                }
                return;
            }
            
            // Show checking state
            infoText.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Checking video and channel...';
            infoDiv.className = 'mb-3 p-3 text-info bg-info bg-opacity-10 border border-info rounded';
            infoDiv.style.display = 'block';
            
            // First check if the video already exists
            fetch('/admin_ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'check_content_exists',
                    url: videoUrl
                })
            })
            .then(response => response.json())
            .then(data => {
                
                if (data.success && data.exists && data.type === 'video') {
                    // Video already exists - show error, disable submit button, and STOP here
                    infoText.innerHTML = `<i class="fas fa-exclamation-circle me-2"></i>This video "<strong>${data.title}</strong>" already exists in the database.`;
                    infoDiv.className = 'mb-3 p-3 text-danger bg-danger bg-opacity-10 border border-danger rounded';
                    
                    // Disable the submit button
                    const submitButton = document.querySelector('#addVideoModal button[type="submit"]');
                    if (submitButton) {
                        submitButton.disabled = true;
                    }
                    return; // Stop completely
                }
                
                // Check for API errors or invalid video URLs
                if (data.error === 'url_not_found') {
                    infoText.innerHTML = `<i class="fas fa-times-circle me-2"></i>${data.message || 'Invalid video URL or video does not exist on YouTube.'}`;
                    infoDiv.className = 'mb-3 p-3 text-danger bg-danger bg-opacity-10 border border-danger rounded';
                    
                    // Disable the submit button for invalid URLs
                    const submitButton = document.querySelector('#addVideoModal button[type="submit"]');
                    if (submitButton) {
                        submitButton.disabled = true;
                    }
                    return; // Stop completely
                }
                
                if (!data.success) {
                    infoText.innerHTML = '<i class="fas fa-times-circle me-2"></i>Error checking video. Please try again.';
                    infoDiv.className = 'mb-3 p-3 text-danger bg-danger bg-opacity-10 border border-danger rounded';
                    
                    // Disable the submit button for errors
                    const submitButton = document.querySelector('#addVideoModal button[type="submit"]');
                    if (submitButton) {
                        submitButton.disabled = true;
                    }
                    return; // Stop completely
                }
                
                // Video doesn't exist, now check channel existence
                return fetch('/admin_ajax.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'check_channel_exists',
                        video_url: videoUrl
                    })
                })
                .then(response => response.json())
                .then(channelData => {
                    
                    const submitButton = document.querySelector('#addVideoModal button[type="submit"]');
                    
                    if (channelData.success) {
                        if (channelData.channel_exists) {
                            // Channel exists - show success message and enable submit button
                            infoText.innerHTML = `<i class="fas fa-check-circle me-2"></i>Channel "<strong>${channelData.channel_name}</strong>" already exists in database. Video can be added.`;
                            infoDiv.className = 'mb-3 p-3 text-success bg-success bg-opacity-10 border border-success rounded';
                            
                            // Enable submit button since video can be added
                            if (submitButton) {
                                submitButton.disabled = false;
                            }
                        } else {
                            // Channel doesn't exist - show warning and enable submit button (video will auto-create channel)
                            infoText.innerHTML = `<i class="fas fa-exclamation-triangle me-2"></i>Channel "<strong>${channelData.channel_name}</strong>" not found. <button type="button" class="btn btn-sm btn-warning ms-2" onclick="createChannelFromVideo('${videoUrl.replace(/'/g, "\\'")}', '${channelData.channel_name.replace(/'/g, "\\'")}')">Create Channel First</button>`;
                            infoDiv.className = 'mb-3 p-3 text-warning bg-warning bg-opacity-10 border border-warning rounded';
                            
                            // Enable submit button since video can still be added (will auto-create channel)
                            if (submitButton) {
                                submitButton.disabled = false;
                            }
                        }
                    } else {
                        // Error checking channel
                        infoText.innerHTML = '<i class="fas fa-exclamation-circle me-2"></i>Could not check channel existence. Please verify the video URL is valid.';
                        infoDiv.className = 'mb-3 p-3 text-danger bg-danger bg-opacity-10 border border-danger rounded';
                        
                        // Disable submit button for errors
                        if (submitButton) {
                            submitButton.disabled = true;
                        }
                    }
                });
            })
            .catch(error => {
                console.error('Error checking video/channel:', error);
                infoText.innerHTML = '<i class="fas fa-times-circle me-2"></i>Error checking video and channel.';
                infoDiv.className = 'mb-3 p-3 text-danger bg-danger bg-opacity-10 border border-danger rounded';
                
                // Disable submit button on error
                const submitButton = document.querySelector('#addVideoModal button[type="submit"]');
                if (submitButton) {
                    submitButton.disabled = true;
                }
            });
        }
        
        function createChannelFromVideo(videoUrl, channelName) {
            
            // Get video metadata to extract channel URL
            fetch('/admin_ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'fetch_metadata',
                    url: videoUrl,
                    type: 'video'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.metadata && data.metadata.channel_url) {
                    // Store the video URL so we can return to it later
                    window.pendingVideoUrl = videoUrl;
                    window.pendingVideoModal = bootstrap.Modal.getInstance(document.getElementById('addVideoModal'));
                    
                    // Open channel modal with channel data pre-filled
                    openChannelModal('add', {
                        url: data.metadata.channel_url,
                        name: channelName,
                        autoFetch: true,
                        returnToVideo: true
                    });
                } else {
                    showToast('Could not fetch channel information for this video', 'danger');
                }
            })
            .catch(error => {
                console.error('Error fetching video metadata:', error);
                showToast('Error fetching channel information', 'danger');
            });
        }
        
        // Set up video URL input validation 
        
        // Clear any stale info messages on page load
        const infoDiv = document.getElementById('video-info');
        if (infoDiv) {
            infoDiv.style.display = 'none';
        }
        
        const videoUrlInput = document.getElementById('video-url');
        if (videoUrlInput) {
                let videoTimeout;
                videoUrlInput.addEventListener('input', function() {
                    clearTimeout(videoTimeout);
                    const url = this.value.trim();
                    
                    // Clear any previous info messages
                    const infoDiv = document.getElementById('video-info');
                    if (infoDiv) infoDiv.style.display = 'none';
                    
                    
                    if (url && isValidYouTubeURL(url)) {
                        videoTimeout = setTimeout(() => {
                            checkVideoUrlAndChannel(url);
                        }, 1000); // Wait 1 second after user stops typing
                    } else if (url) {
                        // Show invalid URL message for non-empty invalid URLs
                        const infoDiv = document.getElementById('video-info');
                        const infoText = document.getElementById('video-info-text');
                        if (infoDiv && infoText) {
                            infoText.innerHTML = '<i class="fas fa-times-circle me-2"></i>Invalid video URL. Please enter a valid YouTube video URL or video ID.';
                            infoDiv.className = 'mb-3 p-3 text-danger bg-danger bg-opacity-10 border border-danger rounded';
                            infoDiv.style.display = 'block';
                            
                            // Disable submit button for invalid URL
                            const submitButton = document.querySelector('#addVideoModal button[type="submit"]');
                            if (submitButton) {
                                submitButton.disabled = true;
                            }
                        }
                }
            });
        }
        
        // Clear video form when modal opens and closes
        const videoModal = document.getElementById('addVideoModal');
        if (videoModal) {
            function clearVideoModal() {
                const urlInput = document.getElementById('video-url');
                const infoDiv = document.getElementById('video-info');
                const submitButton = document.querySelector('#addVideoModal button[type="submit"]');
                
                if (urlInput) {
                    urlInput.value = '';
                }
                if (infoDiv) {
                    infoDiv.style.display = 'none';
                }
                if (submitButton) {
                    submitButton.disabled = false;
                }
            }
            
            videoModal.addEventListener('show.bs.modal', clearVideoModal);
            videoModal.addEventListener('hidden.bs.modal', clearVideoModal);
        } else {
        }
        
        // Make functions globally accessible
        window.createChannelFromVideo = createChannelFromVideo;
        
        // Handle refetch description button
        const refetchBtn = document.getElementById('refetch-description-btn');
        if (refetchBtn) {
            refetchBtn.addEventListener('click', function() {
                const channelId = document.getElementById('channel-id').value;
                const channelUrl = document.getElementById('channel-url').value;
                
                // Determine if we're in add or edit mode
                const isEditMode = channelId && channelId !== '';
                
                if (!isEditMode && !channelUrl) {
                    showToast('Please enter a channel URL first', 'danger');
                    return;
                }
                
                // Show loading state
                const descriptionField = document.getElementById('channel-description');
                const originalValue = descriptionField.value;
                descriptionField.disabled = true;
                refetchBtn.disabled = true;
                refetchBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Fetching...';
                
                // Prepare request data based on mode
                const requestData = {
                    action: 'refetch_channel_description'
                };
                
                if (isEditMode) {
                    requestData.channel_id = channelId;
                } else {
                    requestData.channel_url = channelUrl;
                }
                
                // Fetch channel description
                fetch('/admin_ajax.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(requestData)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.description) {
                        descriptionField.value = data.description;
                        showToast('Description updated from YouTube', 'success');
                    } else {
                        showToast(data.error || 'Could not fetch description', 'danger');
                        descriptionField.value = originalValue;
                    }
                })
                .catch(error => {
                    console.error('Error fetching description:', error);
                    showToast('Error fetching description', 'danger');
                    descriptionField.value = originalValue;
                })
                .finally(() => {
                    // Restore button state
                    descriptionField.disabled = false;
                    refetchBtn.disabled = false;
                    refetchBtn.innerHTML = '<i class="fas fa-sync-alt me-1"></i>Refetch from YouTube';
                });
            });
        }
});
