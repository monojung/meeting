<?php
// 1. First, enable error reporting to see what's causing the 500 error
// Add this to the top of index.php (temporarily for debugging)

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'debug.log');

// 2. Fix the main issues I found in your code:

// ISSUE 1: Missing vendor/autoload.php
// The functions.php file includes 'vendor/autoload.php' but you don't have Composer dependencies
// Fix in includes/functions.php - remove or conditionally load:

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// ISSUE 2: Database connection inconsistency
// You're mixing SQLite and MySQL configurations
// Here's a corrected includes/db_connect.php:

try {
    // Use SQLite as configured in your database.sql
    $pdo = new PDO("sqlite:" . BASE_PATH . "/database.db");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Create tables if they don't exist
    $sql = file_get_contents(BASE_PATH . '/sql/database.sql');
    if ($sql) {
        $pdo->exec($sql);
    }
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection failed. Check logs for details.");
}

// ISSUE 3: Fix the routing in index.php
// Remove the MySQL-specific date functions and fix SQLite compatibility

// Updated index.php with proper error handling:
session_start();

// Enable error logging
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/debug.log');

require_once 'includes/config.php';
require_once 'includes/functions.php';

try {
    // Database connection
    $pdo = new PDO("sqlite:" . BASE_PATH . "/database.db");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Initialize database if needed
    if (!file_exists(BASE_PATH . "/database.db")) {
        $sql = file_get_contents(BASE_PATH . '/sql/database.sql');
        if ($sql) {
            $pdo->exec($sql);
        }
    }
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    die("Database error occurred. Please check the logs.");
}

// ISSUE 4: Fix MySQL-specific date functions in views
// Replace CURDATE() with date('Y-m-d') and similar SQLite-compatible functions

// For example, in admin/dashboard.php, replace:
// $stmt = $pdo->prepare("SELECT COUNT(*) FROM " . TABLE_BOOKINGS . " WHERE date = CURDATE()");
// With:
$stmt = $pdo->prepare("SELECT COUNT(*) FROM " . TABLE_BOOKINGS . " WHERE date = ?");
$stmt->execute([date('Y-m-d')]);

// ISSUE 5: Fix the Google Sheets integration
// Make the Google Sheets functions optional to prevent errors

function syncBookingToGoogleSheets($bookingData) {
    if (!file_exists(GOOGLE_SERVICE_ACCOUNT_FILE)) {
        error_log("Google Sheets service account file not found");
        return false; // Don't fail, just log and continue
    }
    
    try {
        if (!class_exists('Google_Client')) {
            error_log("Google API client not installed");
            return false;
        }
        
        $client = new Google_Client();
        $client->setApplicationName('Meeting Room Booking System');
        $client->setScopes(Google_Service_Sheets::SPREADSHEETS);
        $client->setAuthConfig(GOOGLE_SERVICE_ACCOUNT_FILE);
        
        $service = new Google_Service_Sheets($client);
        $spreadsheetId = GOOGLE_SHEETS_ID;
        
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
        
        $body = new Google_Service_Sheets_ValueRange([
            'values' => $values
        ]);
        
        $params = [
            'valueInputOption' => 'RAW'
        ];
        
        $result = $service->spreadsheets_values->append(
            $spreadsheetId,
            'Bookings!A:H',
            $body,
            $params
        );
        
        return true;
    } catch (Exception $e) {
        error_log('Google Sheets sync error: ' . $e->getMessage());
        return false; // Don't fail the booking if Google Sheets fails
    }
}

// ISSUE 6: Create a simple database initialization script
// Create init_db.php in your root directory:

<?php
require_once 'includes/config.php';

try {
    $pdo = new PDO("sqlite:" . BASE_PATH . "/database.db");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $sql = file_get_contents(__DIR__ . '/sql/database.sql');
    if ($sql) {
        $pdo->exec($sql);
        echo "Database initialized successfully!\n";
    } else {
        echo "Could not read database.sql file\n";
    }
} catch (Exception $e) {
    echo "Database initialization failed: " . $e->getMessage() . "\n";
}

// ISSUE 7: Fix path issues in your routing
// Make sure all paths are correct relative to your web server document root

// If your files are in /var/www/html/booking-system/, your .htaccess should be:
/*
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]
*/

// ISSUE 8: Debugging steps to identify the specific error:

// Step 1: Create a simple test file (test.php) in your root:
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "PHP Version: " . phpversion() . "<br>";
echo "Current directory: " . __DIR__ . "<br>";

// Test basic includes
try {
    require_once 'includes/config.php';
    echo "✓ Config loaded<br>";
} catch (Exception $e) {
    echo "✗ Config error: " . $e->getMessage() . "<br>";
}

try {
    require_once 'includes/functions.php';
    echo "✓ Functions loaded<br>";
} catch (Exception $e) {
    echo "✗ Functions error: " . $e->getMessage() . "<br>";
}

// Test database
try {
    $pdo = new PDO("sqlite:" . BASE_PATH . "/database.db");
    echo "✓ Database connected<br>";
} catch (Exception $e) {
    echo "✗ Database error: " . $e->getMessage() . "<br>";
}

// Step 2: Check your error logs
// Look in:
// - /var/log/apache2/error.log (Ubuntu/Debian)
// - /var/log/httpd/error_log (CentOS/RHEL)
// - Your project directory for debug.log

// Common fixes for 500 errors:

// Fix 1: File permissions
// Make sure your files have correct permissions:
// find /path/to/your/project -type f -exec chmod 644 {} \;
// find /path/to/your/project -type d -exec chmod 755 {} \;

// Fix 2: Missing directories
// Make sure these directories exist and are writable:
// mkdir -p config
// chmod 755 config

// Fix 3: Remove Google Sheets dependency temporarily
// Comment out all Google Sheets related code until basic functionality works

// Fix 4: Simplified index.php for testing:
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

define('APP_NAME', 'ระบบจองห้องประชุม');
define('BASE_PATH', __DIR__);
define('TABLE_USERS', 'users');
define('TABLE_ROOMS', 'rooms');
define('TABLE_BOOKINGS', 'bookings');

// Simple routing for testing
$path = trim($_SERVER['REQUEST_URI'], '/');

if (empty($path)) {
    echo "<h1>Welcome to Meeting Room Booking System</h1>";
    echo "<p>System is working!</p>";
    echo "<a href='/auth/login'>Go to Login</a>";
} else {
    echo "<h1>404 - Page Not Found</h1>";
    echo "<p>Path: " . htmlspecialchars($path) . "</p>";
}

// This will help you identify if the basic routing works
// Once this works, gradually add back your full functionality
?>