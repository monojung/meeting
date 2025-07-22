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

$room_id = (int)($_GET['room_id'] ?? 0);
$date = $_GET['date'] ?? '';

if (!$room_id || !$date) {
    echo json_encode(['success' => false, 'error' => 'Room ID and date required']);
    exit;
}

// Validate date format
if (!DateTime::createFromFormat('Y-m-d', $date)) {
    echo json_encode(['success' => false, 'error' => 'Invalid date format']);
    exit;
}

try {
    // Database connection
    $pdo = new PDO("sqlite:" . BASE_PATH . "/database.db");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get existing bookings for this room and date
    $stmt = $pdo->prepare("
        SELECT time_start, time_end 
        FROM " . TABLE_BOOKINGS . " 
        WHERE room_id = ? AND date = ?
    ");
    
    $stmt->execute([$room_id, $date]);
    $existingBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Define all available time slots
    $allTimeSlots = TIME_SLOTS;
    $timeSlotStatus = [];
    
    foreach ($allTimeSlots as $time => $label) {
        $endTime = date('H:i', strtotime($time . ' +1 hour'));
        $isBooked = false;
        
        // Check if this time slot conflicts with existing bookings
        foreach ($existingBookings as $booking) {
            $bookingStart = $booking['time_start'];
            $bookingEnd = $booking['time_end'];
            
            // Check for time conflict
            if (($time < $bookingEnd && $endTime > $bookingStart)) {
                $isBooked = true;
                break;
            }
        }
        
        $timeSlotStatus[$time] = $isBooked ? 'booked' : 'available';
    }
    
    echo json_encode([
        'success' => true,
        'date' => $date,
        'room_id' => $room_id,
        'time_slots' => $timeSlotStatus
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>