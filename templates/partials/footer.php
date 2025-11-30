<footer class="footer py-2 mt-4">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12 text-center">
                <p>
                    <a href="/legal">Legal</a> • 
                    <a href="/privacy">Privacy</a>
                </p>
            </div>
        </div>
        <div class="row">
            <div class="col-12 text-center">
                <a href="https://startupfa.me/s/bingeworthy?utm_source=bingeworthyshows.tv" style="filter: brightness(0.85);" target="_blank"><img src="https://startupfa.me/badges/featured-badge-small.webp" alt="BingeWorthy - Featured on Startup Fame" width="224" height="32" /></a>
                <a href="https://starterbest.com" target="_blank" style="filter: brightness(0.85);" rel="noopener noreferrer"><img src="https://starterbest.com/badages-awards.svg" alt="Featured on Starter Best" style="height: 32px; width: auto;"/></a>
                <a href="https://wired.business" target="_blank" style="filter: brightness(0.85);"><img src="https://wired.business/badge0-dark.svg" alt="Featured on Wired Business" height="32" style="width:auto"></a>
                <p class="pt-3">🌘 <a href="https://emrahdiril.com">Emrah Diril</a> &copy; 2025. All Rights Reserved.</p>
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
    <script src="/static/script.js?v=<?php echo $js_version; ?>"></script>
    <script async src="https://scripts.simpleanalyticscdn.com/latest.js"></script>
</body>
</html> 