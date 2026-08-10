<footer class="footer py-2 mt-4">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12 text-center">
                <p class="pt-1">
                    <a href="<?php echo app_url('legal'); ?>">Legal</a> •
                    <a href="<?php echo app_url('privacy'); ?>">Privacy</a>
                </p>
            </div>
        </div>
        <div class="row">
            <div class="col-12 text-center">
                <p>🌘 <a href="https://emrahdiril.com">Emrah Diril</a> &copy; 2026. All Rights Reserved.</p>
            </div>
        </div>
    </div>
</footer> 

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php 
    // Use filemtime for smart cache busting
    $js_file = __DIR__ . '/../../static/script.js';
    $js_version = file_exists($js_file) ? filemtime($js_file) : time();
    ?>
    <script src="<?php echo app_url('static/script.js'); ?>?v=<?php echo $js_version; ?>"></script>
    <script async src="https://scripts.simpleanalyticscdn.com/latest.js"></script>
</body>
</html>
