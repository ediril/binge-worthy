<?php

// Deny web access; allow CLI only
if (php_sapi_name() !== 'cli') {
    require_once __DIR__ . '/common.php';
    show_404_page();
}

require_once 'database.php';

/**
 * Database upload script for BingeWorthy
 * Uploads local database to remote server via FTP
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
 * Upload database to FTP server
 */
function upload_database($ftp_config) {
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
    
    log_message("Uploading database to: " . $remote_db_path);
    if (!ftp_put($ftp_conn, $remote_db_path, LOCAL_DB_PATH, FTP_BINARY)) {
        ftp_close($ftp_conn);
        throw new Exception("Failed to upload database");
    }
    
    ftp_close($ftp_conn);
    log_message("Database uploaded successfully");
}

/**
 * Main upload process
 */
function run_upload() {
    global $ftp_config;
    
    try {
        log_message("Starting database upload process");
        
        // Check if local database exists
        if (!file_exists(LOCAL_DB_PATH)) {
            throw new Exception("Local database file not found: " . LOCAL_DB_PATH);
        }
        
        // Upload database
        upload_database($ftp_config);
        
        log_message("Database upload process completed successfully");
        
    } catch (Exception $e) {
        log_message("ERROR: " . $e->getMessage());
        exit(1);
    }
}

// Check if this file is being run directly from command line
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'] ?? '')) {
    echo "BingeWorthy Database Upload Tool\n";
    echo "=================================\n\n";
    
    run_upload();
}

?>
