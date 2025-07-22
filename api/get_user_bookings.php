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

$user_id = (int)($_GET['user_id'] ?? 0);

if (!$user_id) {
    echo json_encode(['success' => false, 'error' => 'User ID required']);
    exit;
}

try {
    // Database connection
    $pdo = new PDO("sqlite:" . BASE_PATH . "/database.db");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get user bookings with room information
    $stmt = $pdo->prepare("
        SELECT b.*, r.room_name, r.capacity 
        FROM " . TABLE_BOOKINGS . " b 
        JOIN " . TABLE_ROOMS . " r ON b.room_id = r.id 
        WHERE b.user_id = ? 
        ORDER BY b.date DESC, b.time_start DESC
    ");
    
    $stmt->execute([$user_id]);
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