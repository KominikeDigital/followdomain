<?php
// Prevent direct access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

// Load configurations
global $config;
if (!isset($config) || !is_array($config)) {
    $config = require __DIR__ . '/../config.php';
}

try {
    $dbType = $config['db_type'];
    $pdo = null;
    
    if ($dbType === 'sqlite') {
        $dbPath = $config['sqlite_path'];
        $pdo = new PDO("sqlite:" . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
        // Optimize SQLite performance
        $pdo->exec("PRAGMA journal_mode = WAL");
        $pdo->exec("PRAGMA synchronous = NORMAL");
    } else {
        $host = $config['mysql_host'];
        $db = $config['mysql_db'];
        $user = $config['mysql_user'];
        $pass = $config['mysql_pass'];
        
        $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }
    
    // Auto initialize schema
    require_once __DIR__ . '/db_init.php';
    initializeDatabase($pdo, $dbType);
    
} catch (PDOException $e) {
    // Fail silently in production, but let's show a developer friendly message if the database fails
    error_log("Database Connection Error: " . $e->getMessage());
    die("Database Connection Error. Please verify your config.php database settings. Details: " . htmlspecialchars($e->getMessage()));
}

return $pdo;
