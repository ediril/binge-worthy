<?php
require_once 'common.php';
require_once 'database.php';

$db = get_database();
$videos_per_page = 100;
$requested_page = max(1, (int)($_GET['page'] ?? 1));
$total_videos = (int)$db->query('SELECT COUNT(*) FROM videos')->fetchColumn();
$pagination = generate_pagination_data($total_videos, $requested_page, $videos_per_page);

$videos_stmt = $db->prepare("
    SELECT v.*, c.name AS channel_name, c.slug AS channel_slug,
           (SELECT GROUP_CONCAT(cat.name, ', ')
            FROM channel_categories cc
            JOIN categories cat ON cc.category_id = cat.id
            WHERE cc.channel_id = v.channel_id) AS category
    FROM videos v
    JOIN channels c ON v.channel_id = c.id
    ORDER BY v.published_at DESC, v.id DESC
    LIMIT :limit OFFSET :offset
");
$videos_stmt->bindValue(':limit', $pagination['items_per_page'], PDO::PARAM_INT);
$videos_stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
$videos_stmt->execute();
$videos = $videos_stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'All Videos - Binge-Worthy';
$current_page = 'videos';
$videos_url = app_url('videos');
?>

<!DOCTYPE html>
<html lang="en">
<?php include 'templates/partials/head.php'; ?>
<body>
    <?php include 'templates/partials/navbar.php'; ?>

    <div class="container-fluid p-0">
        <br>

        <div class="page-header mb-4 px-4">
            <h1 class="section-title mb-2">All Videos</h1>
            <p class="text-light mb-0">Browse the newest videos in our collection.</p>
        </div>

        <section class="channels-section px-4">
            <?php if (empty($videos)): ?>
                <div class="alert alert-info mx-4">
                    <h4 class="alert-heading">No videos found</h4>
                    <p class="mb-0">No videos have been added to our database yet.</p>
                </div>
            <?php else: ?>
                <?php echo render_pagination_controls($pagination, $videos_url); ?>
                <div class="row g-4">
                    <?php foreach ($videos as $video): ?>
                        <div class="col-lg-3 col-md-4 col-sm-6 col-12 mb-4">
                            <?php render_video_card($video); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php echo render_pagination_controls($pagination, $videos_url); ?>
                <?php echo render_item_count($pagination, 'videos'); ?>
            <?php endif; ?>
        </section>
    </div>

    <?php include 'templates/partials/footer.php'; ?>
