<?php
// Default to 'home' if no current page is specified
$current_page = isset($current_page) ? $current_page : 'home';
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid mx-1 px-4">
        <a class="navbar-brand" href="/">Binge Worthy Shows</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'channels') ? 'active' : ''; ?>" href="/channels">Channels</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'categories') ? 'active' : ''; ?>" href="/categories">Categories</a>
                </li>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'blog') ? 'active' : ''; ?>" href="/blog">Blog</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'about') ? 'active' : ''; ?>" href="/about">About</a>
                </li>
                <?php 
                // Only show admin button on localhost
                $is_localhost = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', 'localhost:8080', '127.0.0.1:8080']) || 
                               (isset($_SERVER['HTTP_HOST']) && preg_match('/^localhost:\d+$/', $_SERVER['HTTP_HOST']));
                if ($is_localhost): 
                ?>
                    <li class="nav-item ms-2 d-flex align-items-center">
                        <a href="/admin" class="btn btn-outline-light btn-sm">Admin</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav> 