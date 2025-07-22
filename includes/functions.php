<?php
// Load mock Google API if vendor/autoload.php exists
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function checkLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /auth/login');
        exit;
    }
    
    // Check session timeout
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        session_destroy();
        header('Location: /auth/login?timeout=1');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function formatThaiDate($date) {
    $months = [
        '01' => 'มกราคม', '02' => 'กุมภาพันธ์', '03' => 'มีนาคม',
        '04' => 'เมษายน', '05' => 'พฤษภาคม', '06' => 'มิถุนายน',
        '07' => 'กรกฎาคม', '08' => 'สิงหาคม', '09' => 'กันยายน',
        '10' => 'ตุลาคม', '11' => 'พฤศจิกายน', '12' => 'ธันวาคม'
    ];
    
    $timestamp = strtotime($date);
    $day = date('j', $timestamp);
    $month = $months[date('m', $timestamp)];
    $year = date('Y', $timestamp) + 543;
    
    return "$day $month $year";
}

function formatTime($time) {
    return substr($time, 0, 5);
}

// Google Sheets Functions (Mock implementation for development)
function getGoogleSheetsService() {
    try {
        if (class_exists('Google_Client')) {
            $client = new Google_Client();
            $client->setApplicationName('Meeting Room Booking System');
            
            if (defined('GOOGLE_SHEETS_ID') && file_exists(GOOGLE_SERVICE_ACCOUNT_FILE)) {
                $client->setScopes(['https://www.googleapis.com/auth/spreadsheets']);
                $client->setAuthConfig(GOOGLE_SERVICE_ACCOUNT_FILE);
                return new Google_Service_Sheets($client);
            }
        }
    } catch (Exception $e) {
        error_log('Google Sheets service error: ' . $e->getMessage());
    }
    
    return null;
}

function syncBookingToGoogleSheets($bookingData) {
    try {
        $service = getGoogleSheetsService();
        if (!$service) {
            error_log('Google Sheets service not available - booking data: ' . json_encode($bookingData));
            return true; // Return true for development mode
        }
        
        $values = [
            [
                $bookingData['id'],
                $bookingData['user_name'],
                $bookingData['room_name'],
                $bookingData['date'],
                $bookingData['time_start'],
                $bookingData['time_end'],
                $bookingData['purpose'],
                date('Y-m-d H:i:s')
            ]
        ];
        
        $body = new Google_Service_Sheets_ValueRange();
        $body->setValues($values);
        
        $params = ['valueInputOption' => 'RAW'];
        
        $result = $service->spreadsheets_values->append(
            GOOGLE_SHEETS_ID,
            'Bookings!A:H',
            $body,
            $params
        );
        
        return true;
    } catch (Exception $e) {
        error_log('Google Sheets sync error: ' . $e->getMessage());
        return true; // Don't fail booking if sync fails
    }
}

function getBookingsFromGoogleSheets() {
    try {
        $service = getGoogleSheetsService();
        if (!$service) return [];
        
        $response = $service->spreadsheets_values->get(GOOGLE_SHEETS_ID, 'Bookings!A2:H');
        return $response->getValues() ?: [];
    } catch (Exception $e) {
        error_log('Google Sheets read error: ' . $e->getMessage());
        return [];
    }
}

function createGoogleSheetsHeaders() {
    try {
        $service = getGoogleSheetsService();
        if (!$service) return true;
        
        $headers = [
            ['ID', 'ผู้จอง', 'ห้องประชุม', 'วันที่', 'เวลาเริ่ม', 'เวลาสิ้นสุด', 'วัตถุประสงค์', 'วันที่บันทึก']
        ];
        
        $body = new Google_Service_Sheets_ValueRange();
        $body->setValues($headers);
        
        $params = ['valueInputOption' => 'RAW'];
        
        $result = $service->spreadsheets_values->update(
            GOOGLE_SHEETS_ID,
            'Bookings!A1:H1',
            $body,
            $params
        );
        
        return true;
    } catch (Exception $e) {
        error_log('Google Sheets headers error: ' . $e->getMessage());
        return false;
    }
}

function showAlert($message, $type = 'info') {
    $_SESSION['alert'] = [
        'message' => $message,
        'type' => $type
    ];
}

function displayAlert() {
    if (isset($_SESSION['alert'])) {
        $alert = $_SESSION['alert'];
        $class = $alert['type'] === 'error' ? 'alert-error' : 'alert-success';
        echo "<div class='alert {$class}'>{$alert['message']}</div>";
        unset($_SESSION['alert']);
    }
}

function isTimeSlotAvailable($pdo, $room_id, $date, $time_start, $time_end, $booking_id = null) {
    $sql = "SELECT COUNT(*) FROM bookings 
            WHERE room_id = ? AND date = ? 
            AND ((time_start < ? AND time_end > ?) 
                 OR (time_start < ? AND time_end > ?)
                 OR (time_start >= ? AND time_end <= ?))";
    
    $params = [$room_id, $date, $time_end, $time_start, $time_start, $time_start, $time_start, $time_end];
    
    if ($booking_id) {
        $sql .= " AND id != ?";
        $params[] = $booking_id;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchColumn() == 0;
}
?>