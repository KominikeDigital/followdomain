<?php
// Prevent direct access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

/**
 * Register a new user
 */
function registerUser($pdo, $username, $email, $password, $plan = 'free') {
    $username = trim($username);
    $email = trim(strtolower($email));
    $plan = in_array($plan, ['free', 'bronze', 'silver', 'gold']) ? $plan : 'free';
    
    if (empty($username) || empty($email) || empty($password)) {
        return ['success' => false, 'message' => 'Lütfen tüm alanları doldurun.'];
    }
    
    if (strlen($username) < 3) {
        return ['success' => false, 'message' => 'Kullanıcı adı en az 3 karakter olmalıdır.'];
    }
    
    if (strlen($password) < 6) {
        return ['success' => false, 'message' => 'Şifre en az 6 karakter olmalıdır.'];
    }
    
    // Check if username already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'Bu kullanıcı adı zaten alınmış.'];
    }
    
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'Bu e-posta adresi zaten kayıtlı.'];
    }
    
    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
    $now = date('Y-m-d H:i:s');
    $apiKey = 'da_' . bin2hex(random_bytes(16));
    $verificationToken = bin2hex(random_bytes(16));
    
    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, api_key, api_plan, created_at, is_verified, verification_token) VALUES (?, ?, ?, ?, ?, ?, 0, ?)");
        $stmt->execute([$username, $email, $passwordHash, $apiKey, $plan, $now, $verificationToken]);
        $userId = $pdo->lastInsertId();
        
        // Log activity
        logActivity($pdo, $userId, "Hesap oluşturuldu (Doğrulama bekleniyor).");
        
        return [
            'success' => true, 
            'message' => 'Kayıt başarıyla oluşturuldu!',
            'verification_token' => $verificationToken,
            'user_id' => $userId
        ];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Kayıt sırasında bir hata oluştu: ' . $e->getMessage()];
    }
}

/**
 * Login a user
 */
function loginUser($pdo, $usernameOrEmail, $password) {
    $usernameOrEmail = trim($usernameOrEmail);
    
    if (empty($usernameOrEmail) || empty($password)) {
        return ['success' => false, 'message' => 'Lütfen tüm alanları doldurun.'];
    }
    
    // Find by username or email
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$usernameOrEmail, $usernameOrEmail]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password_hash'])) {
        if ((int)($user['is_verified'] ?? 0) === 0) {
            return ['success' => false, 'message' => 'Lütfen giriş yapmadan önce e-posta adresinizi doğrulayın.'];
        }
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        
        // Generate API key for existing users if missing
        if (empty($user['api_key'])) {
            $newKey = 'da_' . bin2hex(random_bytes(16));
            $stmtUpdate = $pdo->prepare("UPDATE users SET api_key = ? WHERE id = ?");
            $stmtUpdate->execute([$newKey, $user['id']]);
        }
        
        logActivity($pdo, $user['id'], "Sisteme giriş yapıldı.");
        return ['success' => true];
    }
    
    return ['success' => false, 'message' => 'Hatalı kullanıcı adı, e-posta veya şifre.'];
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Require login (redirect if guest)
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: " . url("login"));
        exit;
    }
}

/**
 * Get current user array
 */
function getCurrentUser($pdo) {
    if (!isLoggedIn()) return null;
    
    $stmt = $pdo->prepare("SELECT id, username, email, created_at FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

/**
 * Log user activity
 */
function logActivity($pdo, $userId, $action) {
    $now = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, created_at) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $action, $now]);
}
