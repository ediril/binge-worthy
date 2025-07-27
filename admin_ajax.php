<?php
/**
 * AJAX endpoint for admin pagination
 */

require_once 'common.php';
require_once 'database.php';

// Set JSON response header
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get and validate input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['table']) || !isset($input['page'])) {
    echo json_encode(['error' => 'Invalid input']);
    exit;
}

$table = $input['table'];
$page = max(1, intval($input['page']));

try {
    $db = get_database();
    
    switch ($table) {
        case 'channels':
            $per_page = ADMIN_CHANNELS_PER_PAGE;
            $offset = ($page - 1) * $per_page;
            
            // Get total count
            $count_stmt = $db->prepare("SELECT COUNT(*) as total FROM channels");
            $count_stmt->execute();
            $total = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
            $total_pages = max(1, ceil($total / $per_page));
            
            // Get channels data
            $stmt = $db->prepare("
                SELECT c.*, GROUP_CONCAT(cat.name, ', ') as category_names
                FROM channels c 
                LEFT JOIN channel_categories cc ON c.id = cc.channel_id 
                LEFT JOIN categories cat ON cc.category_id = cat.id
                GROUP BY c.id
                ORDER BY c.created_at DESC
                LIMIT :limit OFFSET :offset
            ");
            $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $data,
                'current_page' => $page,
                'total_pages' => $total_pages,
                'total_items' => $total,
                'per_page' => $per_page
            ]);
            break;
            
        case 'videos':
            $per_page = ADMIN_VIDEOS_PER_PAGE;
            $offset = ($page - 1) * $per_page;
            
            // Get total count
            $count_stmt = $db->prepare("SELECT COUNT(*) as total FROM videos");
            $count_stmt->execute();
            $total = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
            $total_pages = max(1, ceil($total / $per_page));
            
            // Get videos data
            $stmt = $db->prepare("
                SELECT v.*, c.name as channel_name, GROUP_CONCAT(cat.name, ', ') as category 
                FROM videos v 
                JOIN channels c ON v.channel_id = c.id 
                LEFT JOIN channel_categories cc ON c.id = cc.channel_id 
                LEFT JOIN categories cat ON cc.category_id = cat.id
                GROUP BY v.id
                ORDER BY v.created_at DESC
                LIMIT :limit OFFSET :offset
            ");
            $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $data,
                'current_page' => $page,
                'total_pages' => $total_pages,
                'total_items' => $total,
                'per_page' => $per_page
            ]);
            break;
            
        case 'suggestions':
            $per_page = ADMIN_SUGGESTIONS_PER_PAGE;
            $offset = ($page - 1) * $per_page;
            
            try {
                $suggestions_db = new PDO('sqlite:suggestions.db');
                $suggestions_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Get total count
                $count_stmt = $suggestions_db->prepare("SELECT COUNT(*) as total FROM suggestions");
                $count_stmt->execute();
                $total = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
                $total_pages = max(1, ceil($total / $per_page));
                
                // Get suggestions data
                $stmt = $suggestions_db->prepare("SELECT * FROM suggestions ORDER BY timestamp DESC LIMIT :limit OFFSET :offset");
                $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                $stmt->execute();
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'data' => $data,
                    'current_page' => $page,
                    'total_pages' => $total_pages,
                    'total_items' => $total,
                    'per_page' => $per_page
                ]);
            } catch (PDOException $e) {
                echo json_encode([
                    'success' => true,
                    'data' => [],
                    'current_page' => 1,
                    'total_pages' => 1,
                    'total_items' => 0,
                    'per_page' => $per_page
                ]);
            }
            break;
            
        default:
            echo json_encode(['error' => 'Invalid table']);
            break;
    }
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Database error']);
}
?>