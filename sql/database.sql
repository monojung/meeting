-- Meeting Room Booking System Database Schema
-- SQLite Database

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Rooms table
CREATE TABLE IF NOT EXISTS rooms (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    room_name VARCHAR(100) NOT NULL,
    capacity INTEGER NOT NULL,
    status ENUM('available', 'maintenance') DEFAULT 'available',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Bookings table
CREATE TABLE IF NOT EXISTS bookings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    room_id INTEGER NOT NULL,
    date DATE NOT NULL,
    time_start TIME NOT NULL,
    time_end TIME NOT NULL,
    purpose TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
);

-- Indexes for better performance
CREATE INDEX IF NOT EXISTS idx_bookings_user_id ON bookings(user_id);
CREATE INDEX IF NOT EXISTS idx_bookings_room_id ON bookings(room_id);
CREATE INDEX IF NOT EXISTS idx_bookings_date ON bookings(date);
CREATE INDEX IF NOT EXISTS idx_bookings_user_date ON bookings(user_id, date);
CREATE INDEX IF NOT EXISTS idx_bookings_room_date ON bookings(room_id, date);

-- Insert default admin user
INSERT OR IGNORE INTO users (username, password, role) 
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');
-- Default password is 'admin123'

-- Insert sample rooms
INSERT OR IGNORE INTO rooms (room_name, capacity, status) VALUES 
('ห้องประชุมใหญ่', 20, 'available'),
('ห้องประชุมเล็ก A', 8, 'available'),
('ห้องประชุมเล็ก B', 6, 'available'),
('ห้องประชุม VIP', 12, 'available'),
('ห้องอบรม', 30, 'available');

-- Insert sample users
INSERT OR IGNORE INTO users (username, password, role) VALUES 
('user1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user'),
('user2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user'),
('manager1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user');

-- Insert sample bookings for demonstration
INSERT OR IGNORE INTO bookings (user_id, room_id, date, time_start, time_end, purpose) VALUES 
(2, 1, DATE('now', '+1 day'), '09:00', '10:00', 'ประชุมทีมขาย รายสัปดาห์'),
(3, 2, DATE('now', '+2 days'), '14:00', '16:00', 'อบรมพนักงานใหม่'),
(2, 3, DATE('now', '+3 days'), '10:00', '12:00', 'นำเสนอโครงการใหม่'),
(4, 1, DATE('now', '+1 day'), '13:00', '15:00', 'ประชุมคณะกรรมการบริหาร'),
(3, 4, DATE('now', '+4 days'), '16:00', '17:00', 'พบลูกค้า VIP');

-- Trigger to update updated_at timestamp
CREATE TRIGGER IF NOT EXISTS update_users_timestamp 
AFTER UPDATE ON users
BEGIN
    UPDATE users SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
END;

CREATE TRIGGER IF NOT EXISTS update_rooms_timestamp 
AFTER UPDATE ON rooms
BEGIN
    UPDATE rooms SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
END;

CREATE TRIGGER IF NOT EXISTS update_bookings_timestamp 
AFTER UPDATE ON bookings
BEGIN
    UPDATE bookings SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
END;

-- Create views for commonly used queries
CREATE VIEW IF NOT EXISTS booking_details AS
SELECT 
    b.id,
    b.date,
    b.time_start,
    b.time_end,
    b.purpose,
    b.created_at,
    u.username,
    r.room_name,
    r.capacity,
    CASE 
        WHEN datetime(b.date || ' ' || b.time_end) < datetime('now', 'localtime') THEN 'completed'
        WHEN datetime(b.date || ' ' || b.time_start) <= datetime('now', 'localtime') 
             AND datetime(b.date || ' ' || b.time_end) > datetime('now', 'localtime') THEN 'in_progress'
        ELSE 'upcoming'
    END as status
FROM bookings b
JOIN users u ON b.user_id = u.id
JOIN rooms r ON b.room_id = r.id;

-- Create view for room utilization statistics
CREATE VIEW IF NOT EXISTS room_utilization AS
SELECT 
    r.id,
    r.room_name,
    r.capacity,
    r.status,
    COUNT(b.id) as total_bookings,
    SUM(
        CASE 
            WHEN b.date >= date('now') THEN 1 
            ELSE 0 
        END
    ) as upcoming_bookings,
    SUM(
        (julianday(datetime(b.date || ' ' || b.time_end)) - 
         julianday(datetime(b.date || ' ' || b.time_start))) * 24
    ) as total_hours_booked
FROM rooms r
LEFT JOIN bookings b ON r.id = b.room_id
GROUP BY r.id, r.room_name, r.capacity, r.status;

-- Create view for user activity statistics
CREATE VIEW IF NOT EXISTS user_activity AS
SELECT 
    u.id,
    u.username,
    u.role,
    COUNT(b.id) as total_bookings,
    SUM(
        CASE 
            WHEN b.date >= date('now') THEN 1 
            ELSE 0 
        END
    ) as upcoming_bookings,
    MAX(b.date) as last_booking_date,
    SUM(
        (julianday(datetime(b.date || ' ' || b.time_end)) - 
         julianday(datetime(b.date || ' ' || b.time_start))) * 24
    ) as total_hours_booked
FROM users u
LEFT JOIN bookings b ON u.id = b.user_id
GROUP BY u.id, u.username, u.role;

-- Monthly booking statistics view
CREATE VIEW IF NOT EXISTS monthly_stats AS
SELECT 
    strftime('%Y-%m', date) as month,
    COUNT(*) as booking_count,
    COUNT(DISTINCT user_id) as unique_users,
    COUNT(DISTINCT room_id) as unique_rooms,
    SUM(
        (julianday(datetime(date || ' ' || time_end)) - 
         julianday(datetime(date || ' ' || time_start))) * 24
    ) as total_hours
FROM bookings
GROUP BY strftime('%Y-%m', date)
ORDER BY month DESC;

-- Daily booking statistics view
CREATE VIEW IF NOT EXISTS daily_stats AS
SELECT 
    date as booking_date,
    COUNT(*) as booking_count,
    COUNT(DISTINCT user_id) as unique_users,
    COUNT(DISTINCT room_id) as unique_rooms,
    strftime('%w', date) as day_of_week
FROM bookings
GROUP BY date
ORDER BY date DESC;

-- Peak hours analysis view
CREATE VIEW IF NOT EXISTS peak_hours AS
SELECT 
    CAST(strftime('%H', time_start) AS INTEGER) as hour,
    COUNT(*) as booking_count,
    COUNT(DISTINCT room_id) as rooms_used,
    COUNT(DISTINCT user_id) as users_active
FROM bookings
GROUP BY CAST(strftime('%H', time_start) AS INTEGER)
ORDER BY hour;

-- Stored procedure equivalent for checking availability
-- Note: SQLite doesn't support stored procedures, so this is a comment for reference
-- This logic should be implemented in PHP using the isTimeSlotAvailable function

/*
-- Conflict detection logic:
-- A new booking conflicts with existing booking if:
-- (new_start < existing_end) AND (new_end > existing_start)

SELECT COUNT(*) as conflicts
FROM bookings 
WHERE room_id = ? 
  AND date = ? 
  AND ((time_start < ? AND time_end > ?) 
       OR (time_start < ? AND time_end > ?)
       OR (time_start >= ? AND time_end <= ?));
*/

-- Create a table for system settings (optional)
CREATE TABLE IF NOT EXISTS system_settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Insert default system settings
INSERT OR IGNORE INTO system_settings (setting_key, setting_value, description) VALUES 
('google_sheets_sync', 'enabled', 'Enable/disable Google Sheets synchronization'),
('booking_advance_days', '30', 'Maximum days in advance for booking'),
('min_booking_duration', '1', 'Minimum booking duration in hours'),
('max_booking_duration', '8', 'Maximum booking duration in hours'),
('notification_email', 'admin@company.com', 'Email for system notifications'),
('system_timezone', 'Asia/Bangkok', 'System timezone'),
('business_hours_start', '08:00', 'Business hours start time'),
('business_hours_end', '18:00', 'Business hours end time'),
('auto_cancel_minutes', '15', 'Auto-cancel bookings if no-show (minutes after start time)');

-- Update trigger for system_settings
CREATE TRIGGER IF NOT EXISTS update_settings_timestamp 
AFTER UPDATE ON system_settings
BEGIN
    UPDATE system_settings SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
END;