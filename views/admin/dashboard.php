<?php
checkLogin();
if (!isAdmin()) {
    header('Location: /user/dashboard');
    exit;
}

// Get statistics
$stmt = $pdo->prepare("SELECT COUNT(*) FROM " . TABLE_BOOKINGS);
$stmt->execute();
$totalBookings = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM " . TABLE_BOOKINGS . " WHERE date = CURDATE()");
$stmt->execute();
$todayBookings = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM " . TABLE_BOOKINGS . " WHERE date >= CURDATE()");
$stmt->execute();
$upcomingBookings = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM " . TABLE_USERS);
$stmt->execute();
$totalUsers = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM " . TABLE_ROOMS);
$stmt->execute();
$totalRooms = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM " . TABLE_ROOMS . " WHERE status = 'available'");
$stmt->execute();
$availableRooms = $stmt->fetchColumn();

// Get recent bookings
$stmt = $pdo->prepare("
    SELECT b.*, u.username, r.room_name 
    FROM " . TABLE_BOOKINGS . " b 
    JOIN " . TABLE_USERS . " u ON b.user_id = u.id 
    JOIN " . TABLE_ROOMS . " r ON b.room_id = r.id 
    WHERE b.date >= CURDATE()
    ORDER BY b.date ASC, b.time_start ASC 
    LIMIT 8
");
$stmt->execute();
$recentBookings = $stmt->fetchAll();

// Get room usage statistics
$stmt = $pdo->prepare("
    SELECT r.room_name, COUNT(b.id) as booking_count 
    FROM " . TABLE_ROOMS . " r 
    LEFT JOIN " . TABLE_BOOKINGS . " b ON r.id = b.room_id 
    GROUP BY r.id, r.room_name 
    ORDER BY booking_count DESC
");
$stmt->execute();
$roomUsage = $stmt->fetchAll();

// Get monthly booking trends (last 6 months)
$monthlyBookings = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM " . TABLE_BOOKINGS . " WHERE DATE_FORMAT(date, '%Y-%m') = ?");
    $stmt->execute([$month]);
    $count = $stmt->fetchColumn();
    $monthlyBookings[] = [
        'month' => $month,
        'count' => $count,
        'name' => date('M Y', strtotime($month . '-01'))
    ];
}

// Check system status
$systemStatus = [
    'database' => 'ปกติ',
    'google_sheets' => file_exists(GOOGLE_SERVICE_ACCOUNT_FILE) ? 'เชื่อมต่อแล้ว' : 'ไม่เชื่อมต่อ',
    'backup' => 'ปกติ'
];
?>

<div class="glass-card">
    <div class="card-header">
        <i class="fas fa-tachometer-alt"></i>
        แดชบอร์ดแอดมิน - <?= formatThaiDate(date('Y-m-d')) ?>
    </div>
    
    <p style="color: rgba(255,255,255,0.8); margin-bottom: 2rem;">
        ยินดีต้อนรับ, <?= htmlspecialchars($_SESSION['username']) ?> | สถานะ: ผู้ดูแลระบบ
    </p>
    
    <!-- Main Statistics -->
    <div class="stats-grid" style="margin-bottom: 2rem;">
        <div class="stat-card">
            <div class="stat-number"><?= $totalBookings ?></div>
            <div class="stat-label">
                <i class="fas fa-calendar-check"></i>
                การจองทั้งหมด
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-number"><?= $todayBookings ?></div>
            <div class="stat-label">
                <i class="fas fa-calendar-day"></i>
                การจองวันนี้
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-number"><?= $totalUsers ?></div>
            <div class="stat-label">
                <i class="fas fa-users"></i>
                ผู้ใช้งานทั้งหมด
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-number"><?= $availableRooms ?>/<?= $totalRooms ?></div>
            <div class="stat-label">
                <i class="fas fa-door-open"></i>
                ห้องที่ใช้งานได้
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="card" style="margin-bottom: 2rem;">
        <div class="card-header">
            <i class="fas fa-bolt"></i>
            การดำเนินการด่วน
        </div>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
            <a href="/admin/bookings" class="btn btn-success">
                <i class="fas fa-calendar-alt"></i> จัดการการจอง
            </a>
            <a href="/admin/rooms" class="btn">
                <i class="fas fa-door-open"></i> จัดการห้องประชุม
            </a>
            <a href="/admin/users" class="btn">
                <i class="fas fa-users"></i> จัดการผู้ใช้งาน
            </a>
            <a href="/admin/reports" class="btn btn-warning">
                <i class="fas fa-chart-bar"></i> ดูรายงาน
            </a>
        </div>
    </div>
    
    <div class="card-grid">
        <!-- Recent Bookings -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-clock"></i>
                การจองที่จะมาถึง
            </div>
            
            <?php if ($recentBookings): ?>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ผู้จอง</th>
                                <th>ห้อง</th>
                                <th>วันที่</th>
                                <th>เวลา</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentBookings as $booking): ?>
                            <tr>
                                <td><?= htmlspecialchars($booking['username']) ?></td>
                                <td><?= htmlspecialchars($booking['room_name']) ?></td>
                                <td>
                                    <?php
                                    $bookingDate = date('d/m', strtotime($booking['date']));
                                    $isToday = $booking['date'] === date('Y-m-d');
                                    echo $isToday ? '<strong>วันนี้</strong>' : $bookingDate;
                                    ?>
                                </td>
                                <td><?= formatTime($booking['time_start']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div style="text-align: center; margin-top: 1rem;">
                    <a href="/admin/bookings" class="btn">
                        <i class="fas fa-list"></i> ดูทั้งหมด
                    </a>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 2rem; color: rgba(255,255,255,0.8);">
                    <i class="fas fa-calendar-times" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                    <p>ไม่มีการจองที่จะมาถึง</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Room Usage -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-chart-pie"></i>
                สถิติการใช้งานห้อง
            </div>
            
            <?php if ($roomUsage): ?>
                <div style="max-height: 300px; overflow-y: auto;">
                    <?php foreach ($roomUsage as $usage): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem; background: rgba(255,255,255,0.05); margin-bottom: 0.5rem; border-radius: 8px;">
                            <span style="color: white;"><?= htmlspecialchars($usage['room_name']) ?></span>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <div style="background: rgba(255,255,255,0.2); height: 6px; width: 100px; border-radius: 3px; overflow: hidden;">
                                    <div style="background: #4CAF50; height: 100%; width: <?= $totalBookings > 0 ? ($usage['booking_count'] / $totalBookings * 100) : 0 ?>%; border-radius: 3px;"></div>
                                </div>
                                <span style="color: #ffd700; font-weight: 600; min-width: 30px; text-align: right;">
                                    <?= $usage['booking_count'] ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 2rem; color: rgba(255,255,255,0.8);">
                    <i class="fas fa-chart-pie" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                    <p>ไม่มีข้อมูลการใช้งาน</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Monthly Trends -->
    <div class="card" style="margin-top: 2rem;">
        <div class="card-header">
            <i class="fas fa-chart-line"></i>
            แนวโน้มการจอง 6 เดือนล่าสุด
        </div>
        
        <div style="display: flex; justify-content: space-between; align-items: end; height: 200px; padding: 1rem; background: rgba(255,255,255,0.05); border-radius: 10px; overflow-x: auto;">
            <?php 
            $maxCount = max(array_column($monthlyBookings, 'count'));
            $maxCount = $maxCount > 0 ? $maxCount : 1;
            ?>
            <?php foreach ($monthlyBookings as $data): ?>
                <div style="display: flex; flex-direction: column; align-items: center; margin: 0 1rem;">
                    <div style="background: linear-gradient(45deg, #667eea, #764ba2); width: 40px; height: <?= ($data['count'] / $maxCount) * 150 ?>px; border-radius: 4px 4px 0 0; min-height: 10px; position: relative;">
                        <span style="position: absolute; top: -25px; left: 50%; transform: translateX(-50%); color: #ffd700; font-weight: 600; white-space: nowrap;">
                            <?= $data['count'] ?>
                        </span>
                    </div>
                    <span style="margin-top: 0.5rem; font-size: 0.75rem; color: rgba(255,255,255,0.8); writing-mode: vertical-rl; text-orientation: mixed;">
                        <?= $data['name'] ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- System Status -->
    <div class="card" style="margin-top: 2rem;">
        <div class="card-header">
            <i class="fas fa-cogs"></i>
            สถานะระบบ
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
            <div style="background: rgba(76, 175, 80, 0.2); padding: 1rem; border-radius: 10px; text-align: center;">
                <i class="fas fa-database" style="font-size: 2rem; color: #4CAF50; margin-bottom: 0.5rem;"></i>
                <div style="color: white; font-weight: 600;">ฐานข้อมูล</div>
                <div style="color: rgba(255,255,255,0.8); font-size: 0.875rem;"><?= $systemStatus['database'] ?></div>
            </div>
            
            <div style="background: rgba(<?= $systemStatus['google_sheets'] === 'เชื่อมต่อแล้ว' ? '76, 175, 80' : '255, 152, 0' ?>, 0.2); padding: 1rem; border-radius: 10px; text-align: center;">
                <i class="fas fa-table" style="font-size: 2rem; color: <?= $systemStatus['google_sheets'] === 'เชื่อมต่อแล้ว' ? '#4CAF50' : '#FF9800' ?>; margin-bottom: 0.5rem;"></i>
                <div style="color: white; font-weight: 600;">Google Sheets</div>
                <div style="color: rgba(255,255,255,0.8); font-size: 0.875rem;"><?= $systemStatus['google_sheets'] ?></div>
            </div>
            
            <div style="background: rgba(76, 175, 80, 0.2); padding: 1rem; border-radius: 10px; text-align: center;">
                <i class="fas fa-shield-alt" style="font-size: 2rem; color: #4CAF50; margin-bottom: 0.5rem;"></i>
                <div style="color: white; font-weight: 600;">สำรองข้อมูล</div>
                <div style="color: rgba(255,255,255,0.8); font-size: 0.875rem;"><?= $systemStatus['backup'] ?></div>
            </div>
        </div>
    </div>
</div>