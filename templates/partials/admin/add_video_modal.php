<!-- Add New Video Modal -->
<div class="modal fade" id="addVideoModal" tabindex="-1" aria-labelledby="addVideoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header">
                <h5 class="modal-title" id="addVideoModalLabel">Add New Video</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_video">
                    <input type="hidden" name="content_type" value="video">
                    
                    <!-- Video URL Input -->
                    <div class="mb-3">
                        <label for="video-url" class="form-label">YouTube Video URL</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="video-url" name="content_input" required 
                                   placeholder="https://youtube.com/watch?v=... or video ID">
                        </div>
                        <div class="form-text">Enter the full YouTube video URL or just the video ID</div>
                    </div>
                    
                    <!-- Video Info Display -->
                    <div id="video-info" class="mb-3 p-3 text-info bg-info bg-opacity-10 border border-info rounded" style="display: none;">
                        <i class="fas fa-info-circle me-2"></i>
                        <span id="video-info-text">Video information will appear here after entering URL.</span>
                    </div>
                    
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="video-submit-btn" disabled>
                        <i class="fas fa-video me-1"></i>Add Video
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>