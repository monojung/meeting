<?php
define('APP_NAME', 'ระบบจองห้องประชุม');
date_default_timezone_set('Asia/Bangkok');

// Google Sheets Configuration
define('GOOGLE_SHEETS_ID', '1rRgSpwzFQz9m_DCor7vu1Q8KOkr6z28PGlycM0iQ8jM');
define('GOOGLE_API_KEY', 'AIzaSyDgLz5TflZJ5pV4LT133M5HkoZCemQF2oc');
define('GOOGLE_SERVICE_ACCOUNT_FILE', '../config/service-account.json');

// Session timeout (30 minutes)
define('SESSION_TIMEOUT', 1800);

// Application paths
define('BASE_PATH', dirname(__DIR__));
define('PUBLIC_PATH', BASE_PATH . '/public');
define('VIEWS_PATH', BASE_PATH . '/views');
define('INCLUDES_PATH', BASE_PATH . '/includes');

// Database table names
define('TABLE_USERS', 'users');
define('TABLE_ROOMS', 'rooms');
define('TABLE_BOOKINGS', 'bookings');

// Default admin credentials (change these!)
define('DEFAULT_ADMIN_USERNAME', 'admin');
define('DEFAULT_ADMIN_PASSWORD', 'admin123');

// Time slots for booking
define('TIME_SLOTS', [
    '08:00' => '08:00-09:00',
    '09:00' => '09:00-10:00',
    '10:00' => '10:00-11:00',
    '11:00' => '11:00-12:00',
    '13:00' => '13:00-14:00',
    '14:00' => '14:00-15:00',
    '15:00' => '15:00-16:00',
    '16:00' => '16:00-17:00'
]);
?>