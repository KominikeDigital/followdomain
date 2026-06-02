<?php
define('BASE_PATH', __DIR__ . '/..');
$config = require __DIR__ . '/../config.php';
$pdo = require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/functions.php';

echo "Database type: " . $config['db_type'] . "\n";

// Register user
$res = registerUser($pdo, 'testuser_' . time(), 'testuser_' . time() . '@test.com', 'password123', 'free');
echo "Registration Response:\n";
print_r($res);
