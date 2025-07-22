<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

session_start();

header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isAdmin()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$room_id = (int)($_GET['room_id'] ?? 0);

if (!$room_id) {
    echo json_encode(['success' => false, 'error' => 'Room ID required']);
    exit;
}

try {
    // Database connection (assuming PDO is available)
    $pdo = new PDO("sqlite:" . BASE_PATH . "/database.db");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get room bookings with user information
    $stmt = $pdo->prepare("
        SELECT b.*, u.username, r.room_name 
        FROM " . TABLE_BOOKINGS . " b 
        JOIN " . TABLE_USERS . " u ON b.user_id = u.id 
        JOIN " . TABLE_ROOMS . " r ON b.room_id = r.id 
        WHERE b.room_id = ? 
        ORDER BY b.date DESC, b.time_start DESC
    ");
    
    $stmt->execute([$room_id]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'bookings' => $bookings
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>