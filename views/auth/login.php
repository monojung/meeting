<?php
if (isset($_SESSION['user_id'])) {
    $redirect = isAdmin() ? '/admin/dashboard' : '/user/dashboard';
    header("Location: $redirect");
    exit;
}

$error = '';

if ($_POST) {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน';
    } else {
        $stmt = $pdo->prepare("SELECT id, username, password, role FROM " . TABLE_USERS . " WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && verifyPassword($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['last_activity'] = time();
            
            $redirect = $user['role'] === 'admin' ? '/admin/dashboard' : '/user/dashboard';
            header("Location: $redirect");
            exit;
        } else {
            $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
        }
    }
}

// Check for timeout message
if (isset($_GET['timeout'])) {
    $error = 'เซสชันหมดอายุ กรุณาเข้าสู่ระบบใหม่';
}
?>

<div class="login-container">
    <div class="glass-card login-card">
        <div class="login-header">
            <i class="fas fa-calendar-check" style="font-size: 3rem; color: #ffd700; margin-bottom: 1rem;"></i>
            <h2><?= APP_NAME ?></h2>
            <p>เข้าสู่ระบบเพื่อจองห้องประชุม</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?= $error ?>
            </div>
        <?php endif; ?>
        
        <form method="post" action="" id="loginForm">
            <div class="form-group">
                <label for="username">
                    <i class="fas fa-user"></i> ชื่อผู้ใช้
                </label>
                <input type="text" id="username" name="username" class="form-control" 
                       value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>" 
                       required autofocus>
            </div>
            
            <div class="form-group">
                <label for="password">
                    <i class="fas fa-lock"></i> รหัสผ่าน
                </label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn" style="width: 100%;">
                    <i class="fas fa-sign-in-alt"></i> เข้าสู่ระบบ
                </button>
            </div>
        </form>
        
        <div class="login-links">
            <p>ยังไม่มีบัญชี? <a href="/auth/register">สมัครสมาชิก</a></p>
            <p style="margin-top: 1rem; font-size: 0.875rem; opacity: 0.7;">
                ผู้ดูแลระบบ: admin / admin123
            </p>
        </div>
    </div>
</div>

<style>
.login-container {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
}

.login-card .form-group label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.login-card .form-control:focus {
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(255, 255, 255, 0.2);
}
</style>