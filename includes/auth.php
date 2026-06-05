<?php
// Prevent direct access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

/**
 * Calculate password strength for new registrations
 */
function getPasswordStrengthScore($password) {
    $score = 0;
    if (strlen($password) >= 8) $score++;
    if (preg_match('/[a-z]/', $password) && preg_match('/[A-Z]/', $password)) $score++;
    if (preg_match('/\d/', $password)) $score++;
    if (preg_match('/[^a-zA-Z0-9]/', $password)) $score++;
    if (strlen($password) >= 12) $score++;
    return $score;
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
    
    if (strlen($password) < 8) {
        return ['success' => false, 'message' => 'Şifre en az 8 karakter olmalıdır.'];
    }

    if (getPasswordStrengthScore($password) < 3) {
        return ['success' => false, 'message' => 'Lütfen büyük/küçük harf, rakam ve özel karakter içeren daha güçlü bir şifre belirleyin.'];
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
        $pendingPlan = in_array($plan, ['bronze', 'silver', 'gold'], true) ? $plan : null;
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, api_key, api_plan, pending_plan, created_at, is_verified, verification_token) VALUES (?, ?, ?, ?, 'free', ?, ?, 0, ?)");
        $stmt->execute([$username, $email, $passwordHash, $apiKey, $pendingPlan, $now, $verificationToken]);
        $userId = $pdo->lastInsertId();
        
        // Log activity
        logActivity($pdo, $userId, "Hesap oluşturuldu (Doğrulama bekleniyor).");
        
        return [
            'success' => true, 
            'message' => 'Kayıt başarıyla oluşturuldu!',
            'verification_token' => $verificationToken,
            'user_id' => $userId,
            'pending_plan' => $pendingPlan
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
            return [
                'success' => false,
                'message' => 'Lütfen giriş yapmadan önce e-posta adresinizi doğrulayın.',
                'needs_verification' => true,
                'user_id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'verification_token' => $user['verification_token']
            ];
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
 * Check if the current session has a verified admin login.
 */
function isAdminLoggedIn() {
    global $config;

    $configuredAdminEmail = strtolower(trim((string)($config['admin_email'] ?? '')));
    $sessionAdminEmail = strtolower(trim((string)($_SESSION['admin_email'] ?? '')));

    return isset($_SESSION['admin_logged_in'])
        && $_SESSION['admin_logged_in'] === true
        && $configuredAdminEmail !== ''
        && $sessionAdminEmail !== ''
        && hash_equals($configuredAdminEmail, $sessionAdminEmail);
}

/**
 * Clear only admin auth flags without logging the normal user account out.
 */
function clearAdminSession() {
    unset($_SESSION['admin_logged_in'], $_SESSION['admin_email'], $_SESSION['admin_login_at']);
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
