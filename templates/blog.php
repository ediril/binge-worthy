<?php
require_once 'common.php';
require_once 'database.php';

$page_title = 'Blog - Binge-Worthy';
$page_description = 'Updates, insights, and thoughts on building the ultimate platform for educational and intellectually stimulating videos.';
$current_page = 'blog';
?>

<!DOCTYPE html>
<html lang="en">
<?php include 'templates/partials/head.php'; ?>
<body>
    <?php include 'templates/partials/navbar.php'; ?>

    <div class="container-fluid">
        <br>

        <!-- Blog Section -->
        <div class="row justify-content-center">
            <div class="col-lg-10 col-md-11">
                <div class="about-content">
                    <h1 class="text-center mb-4">Blog</h1>
                </div>
                
                <div class="blog-content">
                    <!-- Blog Post -->
                    <div class="blog-post mb-5">
                        <h2 id="august-1-2025" class="blog-post__title">
                            <a href="#august-1-2025" class="blog-post__link">August 1, 2025</a>
                        </h2>
                        <div class="blog-post__content">
                            <p>
                                Welcome to Binge-Worthy! We're building a special platform 
                                dedicated to curating interesting, intellectually stimulating videos on YouTube. No more mindless 
                                scrolling through brain-rotting entertainment.
                            </p>
                            <p>
                                Dozens of channels are already in the catalog and more on the way as we continue to discover more.
                                The site is barely functional at the moment, but we're working hard to add new features and improve the user experience.
                                So stay tuned!
                            </p>
                        </div>
                    </div>                    
                </div>
            </div>
        </div>
    </div>

    <?php include 'templates/partials/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="static/script.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>

    <style>
        .blog-section {
            margin-bottom: 3rem;
        }
        
        .blog-content {
            background: #2d2d2d;
            border-radius: 8px;
            padding: 2rem;
        }
        
        .blog-post {
            border-bottom: 1px solid #404040;
            padding-bottom: 2rem;
        }
        
        .blog-post:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }
        
        .blog-post__title {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 1rem;
            color: #e50914;
        }
        
        .blog-post__link {
            color: #e50914;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .blog-post__link:hover {
            color: #b20710;
            text-decoration: none;
        }
        
        .blog-post__content p {
            color: #d0d0d0;
            line-height: 1.6;
            margin-bottom: 1rem;
        }
        
        .blog-post__content p:last-child {
            margin-bottom: 0;
        }
    </style>
</body>
</html>