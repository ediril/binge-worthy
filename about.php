<!DOCTYPE html>
<html lang="en">
<?php $page_title = 'About - Binge-Worthy'; include 'templates/head.php'; ?>
<body>
    <?php $current_page = 'about'; include 'templates/navbar.php'; ?>

    <div class="container-fluid">
        <br>
        
        <!-- About Section -->
        <div class="row justify-content-center">
            <div class="col-lg-8 col-md-10">
                <div class="about-content">
                    <h1 class="text-center mb-4">About</h1>
                    
                    <div class="about-description">
                        <p class="lead">
                            Binge-Worthy is a curated educational content platform designed to help you discover and organize high-quality educational videos from across the web.
                        </p>
                        
                        <h3 class="mt-4 mb-3">What We Offer</h3>
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <div class="feature-card">
                                    <h4><span class="me-2">📚</span>Curated Content</h4>
                                    <p>We carefully select and organize educational videos to ensure you get the best learning experience possible.</p>
                                </div>
                            </div>
                            <div class="col-md-6 mb-4">
                                <div class="feature-card">
                                    <h4><span class="me-2">🗂️</span>Smart Categories</h4>
                                    <p>Browse content by categories to find exactly what you're looking for, from science to technology and beyond.</p>
                                </div>
                            </div>
                            <div class="col-md-6 mb-4">
                                <div class="feature-card">
                                    <h4><span class="me-2">📺</span>Channel Discovery</h4>
                                    <p>Discover new educational channels and creators who share knowledge in engaging and accessible ways.</p>
                                </div>
                            </div>
                            <div class="col-md-6 mb-4">
                                <div class="feature-card">
                                    <h4><span class="me-2">🎯</span>Personalized Suggestions</h4>
                                    <p>Get video recommendations based on your viewing history and interests to continue your learning journey.</p>
                                </div>
                            </div>
                        </div>

                        <h3 class="mt-4 mb-3">Our Mission</h3>
                        <p>
                            We believe that education should be accessible, engaging, and easy to navigate. Our mission is to create a platform that brings together the best educational content from creators around the world, making it simple for learners to find, organize, and enjoy quality educational videos.
                        </p>
                        
                        <h3 class="mt-4 mb-3">Get Started</h3>
                        <p>
                            Ready to start your learning journey? Browse our <a href="categories.php" class="text-decoration-none">categories</a> to discover videos in your areas of interest, or check out our <a href="index.php" class="text-decoration-none">homepage</a> for the latest additions and suggested content.
                        </p>

                        <div class="text-center mt-5">
                            <a href="categories.php" class="btn btn-primary btn-lg me-3">Browse Categories</a>
                            <a href="index.php" class="btn btn-outline-primary btn-lg">Back to Home</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'templates/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="static/script.js"></script>
</body>
</html> 