<?php
/**
 * Meeting Room Booking System
 * Main Entry Point
 */

// Start session
session_start();

// Load configuration and functions
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Database connection
try {
    $pdo = new PDO("sqlite:" . BASE_PATH . "/database.db");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Get the current URL path
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);
$path = trim($path, '/');

// Route handling
switch ($path) {
    case '':
    case 'index.php':
        // Redirect to appropriate dashboard based on login status
        if (isset($_SESSION['user_id'])) {
            $redirect = isAdmin() ? 'admin/dashboard' : 'user/dashboard';
            header("Location: /$redirect");
        } else {
            header('Location: /auth/login');
        }
        exit;
        
    case 'auth/login':
        include 'includes/header.php';
        include 'views/auth/login.php';
        break;
        
    case 'auth/register':
        include 'includes/header.php';
        include 'views/auth/register.php';
        break;
        
    case 'auth/logout':
        include 'views/auth/logout.php';
        break;
        
    case 'user/dashboard':
        include 'includes/header.php';
        include 'views/user/dashboard.php';
        include 'includes/footer.php';
        break;
        
    case 'user/book_room':
        include 'includes/header.php';
        include 'views/user/book_room.php';
        include 'includes/footer.php';
        break;
        
    case 'user/my_bookings':
        include 'includes/header.php';
        include 'views/user/my_bookings.php';
        include 'includes/footer.php';
        break;
        
    case 'admin/dashboard':
        include 'includes/header.php';
        include 'views/admin/dashboard.php';
        include 'includes/footer.php';
        break;
        
    case 'admin/rooms':
        include 'includes/header.php';
        include 'views/admin/rooms.php';
        include 'includes/footer.php';
        break;
        
    case 'admin/bookings':
        include 'includes/header.php';
        include 'views/admin/bookings.php';
        include 'includes/footer.php';
        break;
        
    case 'admin/users':
        include 'includes/header.php';
        include 'views/admin/users.php';
        include 'includes/footer.php';
        break;
        
    case 'admin/reports':
        include 'includes/header.php';
        include 'views/admin/reports.php';
        include 'includes/footer.php';
        break;
        
    default:
        // Handle API routes
        if (strpos($path, 'api/') === 0) {
            $api_file = str_replace('api/', '', $path) . '.php';
            $api_path = __DIR__ . '/api/' . $api_file;
            
            if (file_exists($api_path)) {
                include $api_path;
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'API endpoint not found']);
            }
        } else {
            // 404 Page
            http_response_code(404);
            include 'includes/header.php';
            ?>
            <div class="glass-card" style="text-align: center; margin-top: 5rem;">
                <div style="padding: 3rem;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 4rem; color: #ff6b6b; margin-bottom: 2rem;"></i>
                    <h1 style="color: white; margin-bottom: 1rem;">404 - ไม่พบหน้าที่ต้องการ</h1>
                    <p style="color: rgba(255,255,255,0.8); margin-bottom: 2rem;">
                        ขออภัย ไม่พบหน้าที่คุณต้องการ
                    </p>
                    <a href="/" class="btn btn-success">
                        <i class="fas fa-home"></i> กลับหน้าหลัก
                    </a>
                </div>
            </div>
            <?php
            include 'includes/footer.php';
        }
        break;
}
?>