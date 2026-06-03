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
        $dbDir = dirname($dbPath);
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0750, true);
        }

        $legacyPath = realpath(__DIR__ . '/../database.sqlite');
        $targetPath = realpath($dbDir) ? realpath($dbDir) . DIRECTORY_SEPARATOR . basename($dbPath) : $dbPath;
        if ($legacyPath && !file_exists($dbPath) && realpath(dirname($legacyPath)) !== realpath($dbDir)) {
            $migrated = @rename($legacyPath, $dbPath);
            if (!$migrated && @copy($legacyPath, $dbPath)) {
                @unlink($legacyPath);
                $migrated = true;
            }
            if ($migrated) {
                @chmod($dbPath, 0600);
            }
            foreach (['-wal', '-shm'] as $suffix) {
                $legacySidecar = $legacyPath . $suffix;
                if ($migrated && file_exists($legacySidecar)) {
                    @rename($legacySidecar, $targetPath . $suffix);
                }
            }
        }

        $pdo = new PDO("sqlite:" . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
        // Optimize SQLite performance
        $pdo->exec("PRAGMA busy_timeout = 5000");
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
