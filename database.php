<?php

/**
 * Get database connection
 */
function get_database() {
    try {
        $db = new PDO('sqlite:binge_worthy.db');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
        return $db;
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

/**
 * Create database tables
 */
function create_tables($db) {
    // Create categories table
    $db->exec("
        CREATE TABLE IF NOT EXISTS categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            description TEXT,
            is_active INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Create channels table
    $db->exec("
        CREATE TABLE IF NOT EXISTS channels (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            description TEXT,
            youtube_url TEXT NOT NULL UNIQUE,
            thumbnail_url TEXT,
            is_active INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Create videos table
    $db->exec("
        CREATE TABLE IF NOT EXISTS videos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            channel_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            description TEXT,
            video_url TEXT NOT NULL UNIQUE,
            thumbnail_url TEXT,
            category_id INTEGER NOT NULL,
            is_active INTEGER DEFAULT 1,
            is_featured INTEGER DEFAULT 0,
            view_count INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (channel_id) REFERENCES channels (id),
            FOREIGN KEY (category_id) REFERENCES categories (id)
        )
    ");
    
    // Insert default categories
    $db->exec("
        INSERT OR IGNORE INTO categories (name, description) VALUES
        ('Tech', 'Technology, programming, and software development'),
        ('Business', 'Business, entrepreneurship, and marketing'),
        ('Science', 'Science, research, and educational content'),
        ('Education', 'Educational and tutorial content'),
        ('Other', 'Miscellaneous content')
    ");
    
    // Create indexes for better performance
    $db->exec("CREATE INDEX IF NOT EXISTS idx_videos_channel_id ON videos(channel_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_videos_category_id ON videos(category_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_videos_created_at ON videos(created_at)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_videos_is_active ON videos(is_active)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_videos_is_featured ON videos(is_featured)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_categories_name ON categories(name)");
}

// Check if this file is being run directly from command line
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'] ?? '')) {
    $db_file = 'binge_worthy.db';
    
    if (file_exists($db_file)) {
        echo "Warning: Database file '$db_file' already exists. No action taken.\n";
        echo "If you want to recreate the database, delete the existing file first.\n";
        exit(1);
    } else {
        echo "Creating database '$db_file'...\n";
        try {
            $db = get_database();
            create_tables($db);
            echo "Database created successfully with all tables and default data.\n";
        } catch (Exception $e) {
            echo "Error creating database: " . $e->getMessage() . "\n";
            exit(1);
        }
    }
}

?> 