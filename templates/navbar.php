<?php
// Default to 'home' if no current page is specified
$current_page = isset($current_page) ? $current_page : 'home';
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid mx-1 px-4">
        <a class="navbar-brand" href="index.php">Binge-Worthy</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'categories') ? 'active' : ''; ?>" href="categories.php">Categories</a>
                </li>
            </ul>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a href="admin.php" class="btn btn-outline-light btn-sm">Admin</a>
                </li>
            </ul>
        </div>
    </div>
</nav> 