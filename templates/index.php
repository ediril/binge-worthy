<!DOCTYPE html>
<html lang="en">
<?php include 'templates/partials/head.php'; ?>
<body>
    <?php include 'templates/partials/navbar.php'; ?>

    <div class="container-fluid p-0">

        <!-- New Channels Section -->
        <br>
        <section class="video-section mb-4 px-4">
            <h2 class="section-title mb-3">New Channels</h2>
            <div class="row g-4">
                <?php if (!empty($new_channels)): ?>
                    <?php foreach ($new_channels as $channel): ?>
                        <div class="col-lg-3 col-md-4 col-sm-6 col-12 mb-4">
                            <?php render_channel_card($channel); ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted">No new channels in the last 7 days.</p>
                <?php endif; ?>
            </div>
        </section>

        <!-- Suggested Videos Section -->
        <br><br>
        <section class="video-section mb-4 px-4">
            <h2 class="section-title mb-3">Have you watched these?</h2>
            <div class="video-row d-flex pb-3">
                <?php if (empty($suggested_videos)): ?>
                    <p class="text-muted">No suggested videos available.</p>
                <?php else: ?>
                    <?php foreach ($suggested_videos as $video): ?>
                        <?php render_video_card($video); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <!-- Suggest Content Section -->
         <br>
        <section class="suggestion-section mb-4 px-4">
            <div class="row justify-content-center">
                <div class="col-lg-6 col-md-8">
                    <div class="suggestion-card p-4 rounded">
                        <h3 class="section-title mb-3 text-center">Know a great channel or video?</h3>
                        <p class="text-center text-muted mb-4">Share it with us!</p>
                        <form id="suggestionForm" class="suggestion-form" action="/suggestions" method="post">
                            <div class="input-group mb-3">
                                <input type="url" 
                                       id="suggestionLink" 
                                       name="link"
                                       class="form-control" 
                                       placeholder="Paste channel or video URL here..." 
                                       required>
                                <button type="submit" class="btn btn-primary" id="submitSuggestion">
                                    <span class="submit-text">Submit</span>
                                    <span class="submit-spinner spinner-border spinner-border-sm d-none" role="status"></span>
                                </button>
                            </div>
                        </form>
                        
                        <!-- Toast container for this section -->
                        <div id="suggestionToast" class="position-fixed start-50 translate-middle-x" style="z-index: 1050; top: 100px; display: none;">
                            <div class="toast align-items-center text-white border-0" role="alert">
                                <div class="d-flex">
                                    <div class="toast-body"></div>
                                    <button type="button" class="btn-close btn-close-white me-2 m-auto" onclick="hideSuggestionToast()"></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <?php include 'templates/partials/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="static/script.js"></script>
</body>
</html> 