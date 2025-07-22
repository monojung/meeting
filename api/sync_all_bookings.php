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

try {
    // Database connection
    $pdo = new PDO("sqlite:" . BASE_PATH . "/database.db");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get all bookings with user and room information
    $stmt = $pdo->prepare("
        SELECT b.*, u.username, r.room_name 
        FROM " . TABLE_BOOKINGS . " b 
        JOIN " . TABLE_USERS . " u ON b.user_id = u.id 
        JOIN " . TABLE_ROOMS . " r ON b.room_id = r.id 
        ORDER BY b.date DESC, b.time_start DESC
    ");
    
    $stmt->execute();
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($bookings)) {
        echo json_encode([
            'success' => true,
            'count' => 0,
            'message' => 'No bookings to sync'
        ]);
        exit;
    }
    
    // First, create headers in Google Sheets
    createGoogleSheetsHeaders();
    
    $successCount = 0;
    $errors = [];
    
    foreach ($bookings as $booking) {
        $syncData = [
            'id' => $booking['id'],
            'user_name' => $booking['username'],
            'room_name' => $booking['room_name'],
            'date' => $booking['date'],
            'time_start' => $booking['time_start'],
            'time_end' => $booking['time_end'],
            'purpose' => $booking['purpose']
        ];
        
        if (syncBookingToGoogleSheets($syncData)) {
            $successCount++;
        } else {
            $errors[] = "Failed to sync booking ID: " . $booking['id'];
        }
        
        // Add small delay to avoid rate limiting
        usleep(100000); // 0.1 second
    }
    
    if ($successCount === count($bookings)) {
        echo json_encode([
            'success' => true,
            'count' => $successCount,
            'message' => "Successfully synced all $successCount bookings"
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'count' => $successCount,
            'total' => count($bookings),
            'errors' => $errors,
            'message' => "Synced $successCount out of " . count($bookings) . " bookings"
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Sync error: ' . $e->getMessage()
    ]);
}
?>