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
                    <div class="blog-post mb-4">
                        <h2 id="2025-08-10" class="blog-post__title">
                            <a href="#2025-08-10" class="blog-post__link">August 10, 2025</a>
                        </h2>
                        <div class="blog-post__content">
                            <p>
                                I hit a snag while vibe coding the video player feature. There was a minor bug, it was supposed to suggest the next video in the channel when the current video ends, and it did the first time but not the subsequent times.
                            </p><p>
                                The bad news is, despite trying, starting over and trying again several times, claude code just couldn't crack it.
                            </p><p>
                                If i was not a developer, i would have been lost.. Luckily I'm a developer and I should be able to fix it myself.
                            </p><p>
                                The good news is, when i dug into the code, i found it to be well organized, well commented and overall pleasant to work with. I see many devs, especially senior ones, ripping vibe code to shreds. What I have in my hands is quite good. I didn't count exactly but I think it is at several 1000s of lines at this point. Not huge but not too shabby either.
                            </p><p>
                                The issue turned out to be easy although it did span a few layers and it was sort of recursive in nature (not exactly but hard to explain without going into details)
                            </p><p>
                                Once I figured out what the issue was, I got CC to help me implement the fix too, which it executed masterfully.
                            </p><p>
                                All in all, it was good experience to see what was happening in the code because I haven't really been looking at it since the start.
                            </p><p>
                                Trying to figure out the issue did take some effort but the overall mental effort on my part was still much less than what it would have been without AI assistance.
                            </p><p>
                                I've been vibe coding since April and my experience is still that of AI being a net positive, a big net positive. I'm able to do things I have not been able to do due to lack of time and mental energy. 
                            </p><p>
                                That said, will you have similar results if you are not an experienced dev? Your milage will vary, but it's fair to say you simply won't be able to go as far.
                            </p><p>
                                I'm not sure what it is like with Replit, Lovable and the others though. Maybe they are superior in how far they can take someone with little to no dev experience.
                            </p>
                        </div>
                    </div>

                    <div class="blog-post mb-4">
                        <h2 id="2025-08-09" class="blog-post__title">
                            <a href="#2025-08-09" class="blog-post__link">August 9, 2025</a>
                        </h2>
                        <div class="blog-post__content">
                            <p>
                                I love the concept of improving a product little by litte, day by day; letting those improvements
                                accumulate over time to better and better experiences for users.
                            </p>
                            <p>
                                I spent time this weekend to add a video player. It is the same player that YouTube provides, but well integrated
                                to this site. When the video ends, it will suggest both the next video in the same channel, which provides a "playlist"
                                like experience. It will also provide suggestions for videos from other channels as well in case you don't wish to continue 
                                with the same channel.
                            </p>
                            <p>
                                Just a small improvement but there are many more to come. Right now there is no way to personalize the site
                                for each user and I think that would be a very good area to work on next.
                            </p>
                        </div>
                    </div>

                    <div class="blog-post mb-4">
                        <h2 id="2025-08-01" class="blog-post__title">
                            <a href="#2025-08-01" class="blog-post__link">August 1, 2025</a>
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