<?php
// Prevent direct access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

function initializeDatabase($pdo, $dbType) {
    $queries = [];
    
    if ($dbType === 'sqlite') {
        $queries[] = "CREATE TABLE IF NOT EXISTS settings (
            key_name TEXT PRIMARY KEY,
            val_value TEXT
        )";
        
        $queries[] = "CREATE TABLE IF NOT EXISTS domains (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            domain_name TEXT UNIQUE NOT NULL,
            expiration_date TEXT,
            registration_date TEXT,
            last_checked TEXT,
            follower_count INTEGER DEFAULT 1,
            registrar TEXT DEFAULT 'Unknown',
            status TEXT DEFAULT '',
            nameservers TEXT DEFAULT '',
            last_changed_date TEXT
        )";
        
        $queries[] = "CREATE TABLE IF NOT EXISTS followers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            domain_id INTEGER,
            email TEXT NOT NULL,
            created_at TEXT,
            notified_30 INTEGER DEFAULT 0,
            notified_7 INTEGER DEFAULT 0,
            notified_1 INTEGER DEFAULT 0,
            notified_0 INTEGER DEFAULT 0,
            UNIQUE(domain_id, email)
        )";
        
        $queries[] = "CREATE TABLE IF NOT EXISTS domain_history (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            domain_id INTEGER,
            event_type TEXT,
            event_description TEXT,
            created_at TEXT
        )";

        $queries[] = "CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            email TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            api_key TEXT UNIQUE NULL,
            api_plan TEXT DEFAULT 'free',
            api_queries_today INTEGER DEFAULT 0,
            last_api_query_date TEXT DEFAULT '',
            webhook_url TEXT NULL,
            created_at TEXT
        )";

        $queries[] = "CREATE TABLE IF NOT EXISTS user_domains (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            domain_name TEXT NOT NULL,
            is_favorite INTEGER DEFAULT 0,
            notify_60 INTEGER DEFAULT 1,
            notify_30 INTEGER DEFAULT 1,
            notify_14 INTEGER DEFAULT 1,
            notify_7 INTEGER DEFAULT 1,
            notify_3 INTEGER DEFAULT 1,
            notify_1 INTEGER DEFAULT 1,
            notified_60_sent INTEGER DEFAULT 0,
            notified_30_sent INTEGER DEFAULT 0,
            notified_14_sent INTEGER DEFAULT 0,
            notified_7_sent INTEGER DEFAULT 0,
            notified_3_sent INTEGER DEFAULT 0,
            notified_1_sent INTEGER DEFAULT 0,
            notified_0_sent INTEGER DEFAULT 0,
            created_at TEXT,
            UNIQUE(user_id, domain_name)
        )";

        $queries[] = "CREATE TABLE IF NOT EXISTS user_hostings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            hosting_provider TEXT NOT NULL,
            domain_name TEXT NOT NULL,
            expiration_date TEXT,
            notify_30 INTEGER DEFAULT 1,
            notify_7 INTEGER DEFAULT 1,
            notify_1 INTEGER DEFAULT 1,
            notified_30_sent INTEGER DEFAULT 0,
            notified_7_sent INTEGER DEFAULT 0,
            notified_1_sent INTEGER DEFAULT 0,
            notified_0_sent INTEGER DEFAULT 0,
            created_at TEXT
        )";

        $queries[] = "CREATE TABLE IF NOT EXISTS activity_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            action TEXT NOT NULL,
            created_at TEXT
        )";

        $queries[] = "CREATE TABLE IF NOT EXISTS integrations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            provider TEXT NOT NULL,
            api_key TEXT,
            email TEXT,
            status TEXT,
            created_at TEXT,
            UNIQUE(user_id, provider)
        )";

        $queries[] = "CREATE TABLE IF NOT EXISTS affiliate_clicks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            provider TEXT NOT NULL,
            target_url TEXT NOT NULL,
            ip_address TEXT,
            clicked_at TEXT
        )";

        $queries[] = "CREATE TABLE IF NOT EXISTS blog_posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            slug TEXT UNIQUE NOT NULL,
            category TEXT DEFAULT 'general',
            image_url TEXT,
            title_en TEXT,
            title_tr TEXT,
            title_es TEXT,
            title_de TEXT,
            content_en TEXT,
            content_tr TEXT,
            content_es TEXT,
            content_de TEXT,
            created_at TEXT
        )";

        $queries[] = "CREATE TABLE IF NOT EXISTS payments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            plan TEXT NOT NULL,
            amount REAL NOT NULL,
            currency TEXT DEFAULT 'USD',
            method TEXT NOT NULL,
            status TEXT DEFAULT 'pending',
            reference TEXT,
            notes TEXT,
            whop_order_id TEXT,
            created_at TEXT,
            confirmed_at TEXT
        )";
    } else {
        // MySQL
        $queries[] = "CREATE TABLE IF NOT EXISTS settings (
            key_name VARCHAR(100) PRIMARY KEY,
            val_value TEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        $queries[] = "CREATE TABLE IF NOT EXISTS domains (
            id INT AUTO_INCREMENT PRIMARY KEY,
            domain_name VARCHAR(255) UNIQUE NOT NULL,
            expiration_date DATETIME NULL,
            registration_date DATETIME NULL,
            last_checked DATETIME NULL,
            follower_count INT DEFAULT 1,
            registrar VARCHAR(255) DEFAULT 'Unknown',
            status TEXT NULL,
            nameservers TEXT NULL,
            last_changed_date DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        $queries[] = "CREATE TABLE IF NOT EXISTS followers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            domain_id INT,
            email VARCHAR(255) NOT NULL,
            created_at DATETIME NULL,
            notified_30 TINYINT DEFAULT 0,
            notified_7 TINYINT DEFAULT 0,
            notified_1 TINYINT DEFAULT 0,
            notified_0 TINYINT DEFAULT 0,
            UNIQUE KEY domain_email_unique (domain_id, email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        $queries[] = "CREATE TABLE IF NOT EXISTS domain_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            domain_id INT,
            event_type VARCHAR(50),
            event_description TEXT,
            created_at DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $queries[] = "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) UNIQUE NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            api_key VARCHAR(255) UNIQUE NULL,
            api_plan VARCHAR(50) DEFAULT 'free',
            api_queries_today INT DEFAULT 0,
            last_api_query_date VARCHAR(10) DEFAULT '',
            webhook_url TEXT NULL,
            created_at DATETIME
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $queries[] = "CREATE TABLE IF NOT EXISTS user_domains (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            domain_name VARCHAR(255) NOT NULL,
            is_favorite TINYINT DEFAULT 0,
            notify_60 TINYINT DEFAULT 1,
            notify_30 TINYINT DEFAULT 1,
            notify_14 TINYINT DEFAULT 1,
            notify_7 TINYINT DEFAULT 1,
            notify_3 TINYINT DEFAULT 1,
            notify_1 TINYINT DEFAULT 1,
            notified_60_sent TINYINT DEFAULT 0,
            notified_30_sent TINYINT DEFAULT 0,
            notified_14_sent TINYINT DEFAULT 0,
            notified_7_sent TINYINT DEFAULT 0,
            notified_3_sent TINYINT DEFAULT 0,
            notified_1_sent TINYINT DEFAULT 0,
            notified_0_sent TINYINT DEFAULT 0,
            created_at DATETIME,
            UNIQUE KEY user_domain_unique (user_id, domain_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $queries[] = "CREATE TABLE IF NOT EXISTS user_hostings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            hosting_provider VARCHAR(255) NOT NULL,
            domain_name VARCHAR(255) NOT NULL,
            expiration_date DATETIME NULL,
            notify_30 TINYINT DEFAULT 1,
            notify_7 TINYINT DEFAULT 1,
            notify_1 TINYINT DEFAULT 1,
            notified_30_sent TINYINT DEFAULT 0,
            notified_7_sent TINYINT DEFAULT 0,
            notified_1_sent TINYINT DEFAULT 0,
            notified_0_sent TINYINT DEFAULT 0,
            created_at DATETIME
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $queries[] = "CREATE TABLE IF NOT EXISTS activity_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            action TEXT NOT NULL,
            created_at DATETIME
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $queries[] = "CREATE TABLE IF NOT EXISTS integrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            provider VARCHAR(50) NOT NULL,
            api_key TEXT NULL,
            email VARCHAR(255) NULL,
            status VARCHAR(50) NULL,
            created_at DATETIME,
            UNIQUE KEY user_provider_unique (user_id, provider)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $queries[] = "CREATE TABLE IF NOT EXISTS affiliate_clicks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            provider VARCHAR(50) NOT NULL,
            target_url TEXT NOT NULL,
            ip_address VARCHAR(45) NULL,
            clicked_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $queries[] = "CREATE TABLE IF NOT EXISTS blog_posts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(255) UNIQUE NOT NULL,
            category VARCHAR(100) DEFAULT 'general',
            image_url TEXT,
            title_en TEXT,
            title_tr TEXT,
            title_es TEXT,
            title_de TEXT,
            content_en LONGTEXT,
            content_tr LONGTEXT,
            content_es LONGTEXT,
            content_de LONGTEXT,
            created_at DATETIME
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    }
    
    foreach ($queries as $q) {
        $pdo->exec($q);
    }

    // Add columns dynamically to users table if they do not exist
    $columnsToAdd = [
        'api_key' => 'TEXT NULL',
        'api_plan' => "TEXT DEFAULT 'free'",
        'api_queries_today' => 'INTEGER DEFAULT 0',
        'last_api_query_date' => "TEXT DEFAULT ''",
        'webhook_url' => 'TEXT NULL'
    ];
    
    if ($dbType === 'mysql') {
        $columnsToAdd = [
            'api_key' => 'VARCHAR(255) UNIQUE NULL',
            'api_plan' => "VARCHAR(50) DEFAULT 'free'",
            'api_queries_today' => 'INT DEFAULT 0',
            'last_api_query_date' => "VARCHAR(10) DEFAULT ''",
            'webhook_url' => 'TEXT NULL'
        ];
    }
    
    foreach ($columnsToAdd as $col => $definition) {
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN $col $definition");
        } catch (PDOException $e) {
            // Fails silently if column already exists
        }
    }

    // Add new tracking columns to affiliate_clicks if they don't exist
    $affiliateClicksCols = [
        'converted'   => 'INTEGER DEFAULT 0',
        'utm_source'  => 'TEXT NULL',
        'referrer'    => 'TEXT NULL',
    ];
    if ($dbType === 'mysql') {
        $affiliateClicksCols = [
            'converted'   => 'TINYINT(1) DEFAULT 0',
            'utm_source'  => 'VARCHAR(100) NULL',
            'referrer'    => 'TEXT NULL',
        ];
    }
    foreach ($affiliateClicksCols as $col => $definition) {
        try {
            $pdo->exec("ALTER TABLE affiliate_clicks ADD COLUMN $col $definition");
        } catch (PDOException $e) {
            // Fails silently if column already exists
        }
    }


    // Seed default admin user if users table is empty
    try {
        $count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        if ($count == 0) {
            $username = 'admin';
            $email = 'admin@tldix.com';
            $passwordHash = password_hash('admin', PASSWORD_BCRYPT);
            $apiKey = 'da_default_admin_api_key_test_123';
            $now = date('Y-m-d H:i:s');
            
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, api_key, api_plan, created_at) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$username, $email, $passwordHash, $apiKey, 'gold', $now]);
        }
    } catch (Exception $e) {
        // Fail silently
    }

    // Add Indexes for query performance and data locking prevention
    $indexQueries = [
        "CREATE INDEX IF NOT EXISTS idx_user_domains_user ON user_domains (user_id)",
        "CREATE INDEX IF NOT EXISTS idx_user_domains_name ON user_domains (domain_name)",
        "CREATE INDEX IF NOT EXISTS idx_user_hostings_user ON user_hostings (user_id)",
        "CREATE INDEX IF NOT EXISTS idx_followers_domain ON followers (domain_id)"
    ];
    
    if ($dbType === 'mysql') {
        $indexQueries = [
            "CREATE INDEX idx_user_domains_user ON user_domains (user_id)",
            "CREATE INDEX idx_user_domains_name ON user_domains (domain_name)",
            "CREATE INDEX idx_user_hostings_user ON user_hostings (user_id)",
            "CREATE INDEX idx_followers_domain ON followers (domain_id)"
        ];
    }
    
    foreach ($indexQueries as $idxQ) {
        try {
            $pdo->exec($idxQ);
        } catch (PDOException $e) {
            // Fails silently if index already exists
        }
    }
}
