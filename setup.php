<?php
/**
 * Database Setup Script
 * Run this file once to create database and initial data
 */

require_once 'includes/config.php';

echo "<h2>Setting up Meeting Room Booking System Database...</h2>";

try {
    // Create SQLite database
    $pdo = new PDO("sqlite:" . BASE_PATH . "/database.db");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Read and execute SQL file
    $sql = file_get_contents('sql/database.sql');
    $pdo->exec($sql);
    
    echo "<p style='color: green;'>✓ Database created successfully!</p>";
    
    // Create default admin user if not exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $stmt->execute(['admin']);
    
    if ($stmt->fetchColumn() == 0) {
        $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        $stmt->execute(['admin', $hashedPassword, 'admin']);
        echo "<p style='color: green;'>✓ Default admin user created (admin/admin123)</p>";
    }
    
    // Create sample rooms
    $rooms = [
        ['ห้องประชุมใหญ่', 20],
        ['ห้องประชุมเล็ก A', 8], 
        ['ห้องประชุมเล็ก B', 6],
        ['ห้องประชุม VIP', 12],
        ['ห้องอบรม', 30]
    ];
    
    foreach ($rooms as $room) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM rooms WHERE room_name = ?");
        $stmt->execute([$room[0]]);
        
        if ($stmt->fetchColumn() == 0) {
            $stmt = $pdo->prepare("INSERT INTO rooms (room_name, capacity, status) VALUES (?, ?, 'available')");
            $stmt->execute([$room[0], $room[1]]);
        }
    }
    
    echo "<p style='color: green;'>✓ Sample rooms created!</p>";
    
    // Create sample users
    $users = [
        ['user1', 'user123'],
        ['user2', 'user123'], 
        ['manager1', 'manager123']
    ];
    
    foreach ($users as $user) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$user[0]]);
        
        if ($stmt->fetchColumn() == 0) {
            $hashedPassword = password_hash($user[1], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'user')");
            $stmt->execute([$user[0], $hashedPassword]);
        }
    }
    
    echo "<p style='color: green;'>✓ Sample users created!</p>";
    
    echo "<h3 style='color: green;'>Setup Complete!</h3>";
    echo "<p>You can now login with:</p>";
    echo "<ul>";
    echo "<li>Admin: admin / admin123</li>";
    echo "<li>User: user1 / user123</li>";
    echo "</ul>";
    echo "<p><a href='index.php'>Go to Application</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>