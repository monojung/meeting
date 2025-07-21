<?php
checkLogin();

// Get user statistics
$stmt = $pdo->prepare("SELECT COUNT(*) as total_bookings FROM " . TABLE_BOOKINGS . " WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$totalBookings = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) as upcoming_bookings FROM " . TABLE_BOOKINGS . " 
                       WHERE user_id = ? AND date >= CURDATE()");
$stmt->execute([$_SESSION['user_id']]);
$upcomingBookings = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) as today_bookings FROM " . TABLE_BOOKINGS . " 
                       WHERE user_id = ? AND date = CURDATE()");
$stmt->execute([$_SESSION['user_id']]);
$todayBookings = $stmt->fetchColumn();

// Get recent bookings
$stmt = $pdo->prepare("
    SELECT b.*, r.room_name 
    FROM " . TABLE_BOOKINGS . " b 
    JOIN " . TABLE_ROOMS . " r ON b.room_id = r.id 
    WHERE b.user_id = ? AND b.date >= CURDATE()
    ORDER BY b.date ASC, b.time_start ASC 
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$recentBookings = $stmt->fetchAll();

// Get available rooms
$stmt = $pdo->prepare("SELECT * FROM " . TABLE_ROOMS . " WHERE status = 'available' ORDER BY room_name");
$stmt->execute();
$availableRooms = $stmt->fetchAll();
?>

<div class="glass-card">
    <div class="card-header">
        <i class="fas fa-home"></i>
        ยินดีต้อนรับ, <?= htmlspecialchars($_SESSION['username']) ?>
    </div>
    
    <p style="color: rgba(255,255,255,0.8); margin-bottom: 2rem;">
        วันนี้คือ <?= formatThaiDate(date('Y-m-d')) ?>
    </p>
    
    <!-- Quick Actions -->
    <div class="card-grid" style="margin-bottom: 2rem;">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-plus-circle"></i>
                การดำเนินการด่วน
            </div>
            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <a href="/user/book_room" class="btn btn-success">
                    <i class="fas fa-calendar-plus"></i> จองห้องใหม่
                </a>
                <a href="/user/my_bookings" class="btn">
                    <i class="fas fa-list"></i> รายการของฉัน
                </a>
            </div>
        </div>
    </div>
    
    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?= $totalBookings ?></div>
            <div class="stat-label">
                <i class="fas fa-calendar-check"></i>
                การจองทั้งหมด
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-number"><?= $upcomingBookings ?></div>
            <div class="stat-label">
                <i class="fas fa-clock"></i>
                การจองที่จะมาถึง
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
            <div class="stat-number"><?= count($availableRooms) ?></div>
            <div class="stat-label">
                <i class="fas fa-door-open"></i>
                ห้องที่ใช้งานได้
            </div>
        </div>
    </div>
    
    <!-- Recent Bookings -->
    <?php if ($recentBookings): ?>
    <div class="card-grid">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-calendar-alt"></i>
                การจองที่จะมาถึง
            </div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ห้องประชุม</th>
                            <th>วันที่</th>
                            <th>เวลา</th>
                            <th>สถานะ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentBookings as $booking): ?>
                        <tr>
                            <td>
                                <i class="fas fa-door-open"></i>
                                <?= htmlspecialchars($booking['room_name']) ?>
                            </td>
                            <td><?= formatThaiDate($booking['date']) ?></td>
                            <td>
                                <?= formatTime($booking['time_start']) ?> - 
                                <?= formatTime($booking['time_end']) ?>
                            </td>
                            <td>
                                <?php
                                $now = new DateTime();
                                $bookingDate = new DateTime($booking['date'] . ' ' . $booking['time_start']);
                                $status = $bookingDate > $now ? 'รอการประชุม' : 'เสร็จสิ้น';
                                $statusClass = $bookingDate > $now ? 'btn-warning' : 'btn-success';
                                ?>
                                <span class="btn <?= $statusClass ?>" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">
                                    <?= $status ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div style="text-align: center; margin-top: 1rem;">
                <a href="/user/my_bookings" class="btn">
                    <i class="fas fa-list"></i> ดูรายการทั้งหมด
                </a>
            </div>
        </div>
        
        <!-- Available Rooms -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-door-open"></i>
                ห้องประชุมที่ใช้งานได้
            </div>
            <?php if ($availableRooms): ?>
                <div style="display: grid; gap: 1rem;">
                    <?php foreach ($availableRooms as $room): ?>
                    <div style="background: rgba(255,255,255,0.1); padding: 1rem; border-radius: 10px; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <div style="font-weight: 600; margin-bottom: 0.25rem;">
                                <?= htmlspecialchars($room['room_name']) ?>
                            </div>
                            <div style="font-size: 0.875rem; opacity: 0.8;">
                                <i class="fas fa-users"></i> ความจุ: <?= $room['capacity'] ?> คน
                            </div>
                        </div>
                        <a href="/user/book_room?room_id=<?= $room['id'] ?>" class="btn btn-success" style="padding: 0.5rem 1rem;">
                            <i class="fas fa-calendar-plus"></i> จอง
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p style="text-align: center; opacity: 0.8;">ไม่มีห้องประชุมที่ใช้งานได้</p>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="card">
        <div class="card-header">
            <i class="fas fa-calendar-alt"></i>
            การจองของคุณ
        </div>
        <div style="text-align: center; padding: 2rem;">
            <i class="fas fa-calendar-times" style="font-size: 3rem; color: rgba(255,255,255,0.3); margin-bottom: 1rem;"></i>
            <p style="margin-bottom: 1.5rem; opacity: 0.8;">คุณยังไม่มีการจองห้องประชุม</p>
            <a href="/user/book_room" class="btn btn-success">
                <i class="fas fa-plus-circle"></i> จองห้องแรกของคุณ
            </a>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Tips -->
    <div class="card" style="margin-top: 2rem; background: linear-gradient(45deg, rgba(255,193,7,0.2), rgba(255,152,0,0.2));">
        <div class="card-header">
            <i class="fas fa-lightbulb"></i>
            เคล็ดลับการใช้งาน
        </div>
        <ul style="margin: 0; padding-left: 1.5rem; color: rgba(255,255,255,0.9);">
            <li style="margin-bottom: 0.5rem;">จองห้องล่วงหน้าเพื่อความแน่ใจ</li>
            <li style="margin-bottom: 0.5rem;">ตรวจสอบความจุห้องให้เหมาะสมกับจำนวนผู้เข้าร่วม</li>
            <li style="margin-bottom: 0.5rem;">ระบุวัตถุประสงค์การใช้งานอย่างชัดเจน</li>
            <li style="margin-bottom: 0.5rem;">สามารถยกเลิกหรือแก้ไขการจองได้ในรายการของฉัน</li>
        </ul>
    </div>
</div>