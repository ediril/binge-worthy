<?php

require_once 'database.php';

/**
 * Database merge script for BingeWorthy
 * Downloads remote database via FTP, merges with local database,
 * and uploads the merged result back to the server
 */

/**
 * Load FTP configuration from YAML file
 */
function load_ftp_config() {
    $config_file = 'ftp_config.yaml';
    
    if (!file_exists($config_file)) {
        throw new Exception("FTP configuration file not found: {$config_file}");
    }
    
    $yaml_content = file_get_contents($config_file);
    $lines = explode("\n", $yaml_content);
    $config = [];
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }
        
        if (strpos($line, ':') !== false) {
            list($key, $value) = explode(':', $line, 2);
            $config[trim($key)] = trim($value);
        }
    }
    
    // Validate required fields
    $required_fields = ['host', 'user', 'password', 'remote_dir'];
    foreach ($required_fields as $field) {
        if (empty($config[$field])) {
            throw new Exception("Missing required FTP configuration field: {$field}");
        }
    }
    
    return $config;
}

// Load FTP configuration
$ftp_config = load_ftp_config();

const LOCAL_DB_PATH = 'binge_worthy.db';

/**
 * Log message with timestamp
 */
function log_message($message) {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
}

/**
 * Download database from FTP server
 */
function download_remote_database($ftp_config) {
    $timestamp = date('YmdHis');
    $local_filename = "binge_worthy_{$timestamp}.db";
    
    $remote_dir = !empty($ftp_config['remote_dir']) ? $ftp_config['remote_dir'] : '';
    $remote_db_path = $remote_dir ? $remote_dir . '/binge_worthy.db' : 'binge_worthy.db';
    
    log_message("Connecting to FTP server: " . $ftp_config['host']);
    
    $ftp_conn = ftp_connect($ftp_config['host']);
    if (!$ftp_conn) {
        throw new Exception("Failed to connect to FTP server");
    }
    
    if (!ftp_login($ftp_conn, $ftp_config['user'], $ftp_config['password'])) {
        ftp_close($ftp_conn);
        throw new Exception("Failed to login to FTP server");
    }
    
    ftp_pasv($ftp_conn, true);
    
    log_message("Downloading remote database from: " . $remote_db_path . " to: " . $local_filename);
    
    if (!ftp_get($ftp_conn, $local_filename, $remote_db_path, FTP_BINARY)) {
        ftp_close($ftp_conn);
        throw new Exception("Failed to download remote database");
    }
    
    ftp_close($ftp_conn);
    log_message("Remote database downloaded successfully");
    
    return $local_filename;
}

/**
 * Upload database to FTP server
 */
function upload_merged_database($ftp_config) {
    log_message("Connecting to FTP server for upload");
    
    $remote_dir = !empty($ftp_config['remote_dir']) ? $ftp_config['remote_dir'] : '';
    $remote_db_path = $remote_dir ? $remote_dir . '/binge_worthy.db' : 'binge_worthy.db';
    
    $ftp_conn = ftp_connect($ftp_config['host']);
    if (!$ftp_conn) {
        throw new Exception("Failed to connect to FTP server for upload");
    }
    
    if (!ftp_login($ftp_conn, $ftp_config['user'], $ftp_config['password'])) {
        ftp_close($ftp_conn);
        throw new Exception("Failed to login to FTP server for upload");
    }
    
    ftp_pasv($ftp_conn, true);
    
    // Rename remote file with timestamp
    $timestamp = date('YmdHis');
    $backup_name = $remote_dir ? $remote_dir . "/binge_worthy_backup_{$timestamp}.db" : "binge_worthy_backup_{$timestamp}.db";
    
    log_message("Backing up remote database as: " . $backup_name);
    if (!ftp_rename($ftp_conn, $remote_db_path, $backup_name)) {
        log_message("Warning: Could not backup remote database");
    }
    
    log_message("Uploading merged database to: " . $remote_db_path);
    if (!ftp_put($ftp_conn, $remote_db_path, LOCAL_DB_PATH, FTP_BINARY)) {
        ftp_close($ftp_conn);
        throw new Exception("Failed to upload merged database");
    }
    
    ftp_close($ftp_conn);
    log_message("Merged database uploaded successfully");
}

/**
 * Merge remote database into local database
 */
function merge_databases($remote_db_file) {
    log_message("Starting database merge process");
    
    // Open both databases
    $local_db = get_database();
    $remote_db = new PDO('sqlite:' . $remote_db_file);
    $remote_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $local_db->beginTransaction();
    
    try {
        // Merge categories
        log_message("Merging categories...");
        $remote_categories = $remote_db->query("SELECT * FROM categories")->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $local_db->prepare("INSERT OR IGNORE INTO categories (name, created_at) VALUES (?, ?)");
        
        foreach ($remote_categories as $category) {
            $stmt->execute([$category['name'], $category['created_at']]);
        }
        
        // Merge channels
        log_message("Merging channels...");
        $remote_channels = $remote_db->query("SELECT * FROM channels")->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $local_db->prepare("
            INSERT OR REPLACE INTO channels 
            (name, description, youtube_url, thumbnail_url, last_fetched, created_at) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($remote_channels as $channel) {
            $stmt->execute([
                $channel['name'],
                $channel['description'],
                $channel['youtube_url'],
                $channel['thumbnail_url'],
                $channel['last_fetched'],
                $channel['created_at']
            ]);
        }
        
        // Merge videos
        log_message("Merging videos...");
        $remote_videos = $remote_db->query("
            SELECT v.*, c.youtube_url as channel_url 
            FROM videos v 
            JOIN channels c ON v.channel_id = c.id
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        $find_channel_stmt = $local_db->prepare("SELECT id FROM channels WHERE youtube_url = ?");
        $insert_video_stmt = $local_db->prepare("
            INSERT OR REPLACE INTO videos 
            (channel_id, title, video_url, thumbnail_url, is_featured, published_at) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($remote_videos as $video) {
            $find_channel_stmt->execute([$video['channel_url']]);
            $local_channel = $find_channel_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($local_channel) {
                $insert_video_stmt->execute([
                    $local_channel['id'],
                    $video['title'],
                    $video['video_url'],
                    $video['thumbnail_url'],
                    $video['is_featured'],
                    $video['published_at']
                ]);
            }
        }
        
        // Merge channel_categories
        log_message("Merging channel categories...");
        $remote_channel_categories = $remote_db->query("
            SELECT cc.*, ch.youtube_url as channel_url, cat.name as category_name
            FROM channel_categories cc
            JOIN channels ch ON cc.channel_id = ch.id
            JOIN categories cat ON cc.category_id = cat.id
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        $find_local_channel_stmt = $local_db->prepare("SELECT id FROM channels WHERE youtube_url = ?");
        $find_local_category_stmt = $local_db->prepare("SELECT id FROM categories WHERE name = ?");
        $insert_cc_stmt = $local_db->prepare("
            INSERT OR IGNORE INTO channel_categories (channel_id, category_id, created_at) 
            VALUES (?, ?, ?)
        ");
        
        foreach ($remote_channel_categories as $cc) {
            $find_local_channel_stmt->execute([$cc['channel_url']]);
            $local_channel = $find_local_channel_stmt->fetch(PDO::FETCH_ASSOC);
            
            $find_local_category_stmt->execute([$cc['category_name']]);
            $local_category = $find_local_category_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($local_channel && $local_category) {
                $insert_cc_stmt->execute([
                    $local_channel['id'],
                    $local_category['id'],
                    $cc['created_at']
                ]);
            }
        }
        
        $local_db->commit();
        log_message("Database merge completed successfully");
        
    } catch (Exception $e) {
        $local_db->rollBack();
        throw new Exception("Database merge failed: " . $e->getMessage());
    }
}

/**
 * Main merge process
 */
function run_merge() {
    global $ftp_config;
    
    try {
        log_message("Starting database merge process");
        
        // Check if local database exists
        if (!file_exists(LOCAL_DB_PATH)) {
            throw new Exception("Local database file not found: " . LOCAL_DB_PATH);
        }
        
        // Download remote database
        $remote_db_file = download_remote_database($ftp_config);
        
        // Merge databases
        merge_databases($remote_db_file);
        
        // Upload merged database
        upload_merged_database($ftp_config);
        
        log_message("Database merge process completed successfully");
        log_message("Downloaded database preserved as: " . $remote_db_file);
        
    } catch (Exception $e) {
        log_message("ERROR: " . $e->getMessage());
        exit(1);
    }
}

// Check if this file is being run directly from command line
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'] ?? '')) {
    echo "BingeWorthy Database Merge Tool\n";
    echo "================================\n\n";
    
    run_merge();
}

?>