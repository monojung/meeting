<?php
if (isset($_SESSION['user_id'])) {
    $redirect = isAdmin() ? '/admin/dashboard' : '/user/dashboard';
    header("Location: $redirect");
    exit;
}

$error = '';
$success = '';

if ($_POST) {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($username) || empty($password) || empty($confirm_password)) {
        $error = 'กรุณากรอกข้อมูลให้ครบถ้วน';
    } elseif (strlen($username) < 3) {
        $error = 'ชื่อผู้ใช้ต้องมีอย่างน้อย 3 ตัวอักษร';
    } elseif (strlen($password) < 6) {
        $error = 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร';
    } elseif ($password !== $confirm_password) {
        $error = 'รหัสผ่านไม่ตรงกัน';
    } else {
        // Check if username already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM " . TABLE_USERS . " WHERE username = ?");
        $stmt->execute([$username]);
        
        if ($stmt->fetchColumn() > 0) {
            $error = 'ชื่อผู้ใช้นี้มีอยู่แล้ว';
        } else {
            // Create new user
            $hashedPassword = hashPassword($password);
            $stmt = $pdo->prepare("INSERT INTO " . TABLE_USERS . " (username, password, role) VALUES (?, ?, 'user')");
            
            if ($stmt->execute([$username, $hashedPassword])) {
                $success = 'สมัครสมาชิกสำเร็จ กรุณาเข้าสู่ระบบ';
            } else {
                $error = 'เกิดข้อผิดพลาดในการสมัครสมาชิก';
            }
        }
    }
}
?>

<div class="login-container">
    <div class="glass-card login-card">
        <div class="login-header">
            <i class="fas fa-user-plus" style="font-size: 3rem; color: #ffd700; margin-bottom: 1rem;"></i>
            <h2>สมัครสมาชิก</h2>
            <p>สร้างบัญชีใหม่สำหรับระบบจองห้องประชุม</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?= $error ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= $success ?>
            </div>
        <?php endif; ?>
        
        <form method="post" action="" id="registerForm">
            <div class="form-group">
                <label for="username">
                    <i class="fas fa-user"></i> ชื่อผู้ใช้
                </label>
                <input type="text" id="username" name="username" class="form-control" 
                       value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>" 
                       required autofocus minlength="3" maxlength="50">
                <small style="color: rgba(255,255,255,0.7); font-size: 0.875rem; margin-top: 0.25rem; display: block;">
                    ต้องมีอย่างน้อย 3 ตัวอักษร
                </small>
            </div>
            
            <div class="form-group">
                <label for="password">
                    <i class="fas fa-lock"></i> รหัสผ่าน
                </label>
                <input type="password" id="password" name="password" class="form-control" 
                       required minlength="6">
                <small style="color: rgba(255,255,255,0.7); font-size: 0.875rem; margin-top: 0.25rem; display: block;">
                    ต้องมีอย่างน้อย 6 ตัวอักษร
                </small>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">
                    <i class="fas fa-lock"></i> ยืนยันรหัสผ่าน
                </label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-success" style="width: 100%;">
                    <i class="fas fa-user-plus"></i> สมัครสมาชิก
                </button>
            </div>
        </form>
        
        <div class="login-links">
            <p>มีบัญชีอยู่แล้ว? <a href="/auth/login">เข้าสู่ระบบ</a></p>
        </div>
    </div>
</div>

<script>
document.getElementById('registerForm').addEventListener('submit', function(e) {
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    if (password !== confirmPassword) {
        e.preventDefault();
        alert('รหัสผ่านไม่ตรงกัน');
        return false;
    }
    
    if (password.length < 6) {
        e.preventDefault();
        alert('รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร');
        return false;
    }
});

// Real-time password confirmation check
document.getElementById('confirm_password').addEventListener('input', function() {
    const password = document.getElementById('password').value;
    const confirmPassword = this.value;
    
    if (confirmPassword && password !== confirmPassword) {
        this.style.borderColor = '#f44336';
    } else {
        this.style.borderColor = '';
    }
});
</script>

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