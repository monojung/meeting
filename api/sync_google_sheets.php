<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

session_start();

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'error' => 'Invalid input data']);
    exit;
}

// Required fields
$requiredFields = ['id', 'user_name', 'room_name', 'date', 'time_start', 'time_end', 'purpose'];
foreach ($requiredFields as $field) {
    if (!isset($input[$field])) {
        echo json_encode(['success' => false, 'error' => "Missing field: $field"]);
        exit;
    }
}

try {
    // Sync to Google Sheets
    $result = syncBookingToGoogleSheets($input);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Data synced to Google Sheets successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to sync to Google Sheets'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Sync error: ' . $e->getMessage()
    ]);
}
?>