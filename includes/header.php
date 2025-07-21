<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?></title>
    <link rel="stylesheet" href="/public/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="/public/js/main.js" defer></script>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-brand">
                <i class="fas fa-calendar-check"></i>
                <span><?= APP_NAME ?></span>
            </div>
            
            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="nav-menu">
                    <div class="nav-links">
                        <?php if (isAdmin()): ?>
                            <a href="/admin/dashboard" class="nav-link">
                                <i class="fas fa-tachometer-alt"></i> แดชบอร์ด
                            </a>
                            <a href="/admin/rooms" class="nav-link">
                                <i class="fas fa-door-open"></i> จัดการห้อง
                            </a>
                            <a href="/admin/bookings" class="nav-link">
                                <i class="fas fa-calendar-alt"></i> รายการจอง
                            </a>
                            <a href="/admin/users" class="nav-link">
                                <i class="fas fa-users"></i> ผู้ใช้งาน
                            </a>
                            <a href="/admin/reports" class="nav-link">
                                <i class="fas fa-chart-bar"></i> รายงาน
                            </a>
                        <?php else: ?>
                            <a href="/user/dashboard" class="nav-link">
                                <i class="fas fa-home"></i> หน้าหลัก
                            </a>
                            <a href="/user/book_room" class="nav-link">
                                <i class="fas fa-plus-circle"></i> จองห้อง
                            </a>
                            <a href="/user/my_bookings" class="nav-link">
                                <i class="fas fa-list"></i> รายการของฉัน
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <div class="nav-user">
                        <div class="user-info">
                            <i class="fas fa-user-circle"></i>
                            <span><?= htmlspecialchars($_SESSION['username']) ?></span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="user-dropdown">
                            <a href="/auth/logout">
                                <i class="fas fa-sign-out-alt"></i> ออกจากระบบ
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </nav>
    
    <main class="main-content">
        <?php displayAlert(); ?>