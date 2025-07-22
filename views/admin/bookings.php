<?php
checkLogin();
if (!isAdmin()) {
    header('Location: /user/dashboard');
    exit;
}

$error = '';
$success = '';

// Handle booking actions
if ($_POST) {
    $action = $_POST['action'] ?? '';
    $booking_id = (int)($_POST['booking_id'] ?? 0);
    
    if ($action === 'cancel' && $booking_id) {
        $stmt = $pdo->prepare("DELETE FROM " . TABLE_BOOKINGS . " WHERE id = ?");
        if ($stmt->execute([$booking_id])) {
            $success = 'ยกเลิกการจองสำเร็จ';
        } else {
            $error = 'เกิดข้อผิดพลาดในการยกเลิก';
        }
    } elseif ($action === 'add') {
        $user_id = (int)$_POST['user_id'];
        $room_id = (int)$_POST['room_id'];
        $date = $_POST['date'];
        $time_start = $_POST['time_start'];
        $time_end = $_POST['time_end'];
        $purpose = sanitize($_POST['purpose']);
        
        if (empty($user_id) || empty($room_id) || empty($date) || empty($time_start) || empty($time_end) || empty($purpose)) {
            $error = 'กรุณากรอกข้อมูลให้ครบถ้วน';
        } elseif ($date < date('Y-m-d')) {
            $error = 'ไม่สามารถจองย้อนหลังได้';
        } elseif ($time_start >= $time_end) {
            $error = 'เวลาสิ้นสุดต้องมากกว่าเวลาเริ่มต้น';
        } elseif (!isTimeSlotAvailable($pdo, $room_id, $date, $time_start, $time_end)) {
            $error = 'เวลาดังกล่าวมีการจองแล้ว';
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO " . TABLE_BOOKINGS . " (user_id, room_id, date, time_start, time_end, purpose) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            if ($stmt->execute([$user_id, $room_id, $date, $time_start, $time_end, $purpose])) {
                $success = 'เพิ่มการจองสำเร็จ';
                
                // Sync to Google Sheets
                $bookingId = $pdo->lastInsertId();
                $stmt = $pdo->prepare("
                    SELECT b.*, u.username, r.room_name 
                    FROM " . TABLE_BOOKINGS . " b 
                    JOIN " . TABLE_USERS . " u ON b.user_id = u.id 
                    JOIN " . TABLE_ROOMS . " r ON b.room_id = r.id 
                    WHERE b.id = ?
                ");
                $stmt->execute([$bookingId]);
                $bookingDetails = $stmt->fetch();
                
                $syncData = [
                    'id' => $bookingDetails['id'],
                    'user_name' => $bookingDetails['username'],
                    'room_name' => $bookingDetails['room_name'],
                    'date' => $bookingDetails['date'],
                    'time_start' => $bookingDetails['time_start'],
                    'time_end' => $bookingDetails['time_end'],
                    'purpose' => $bookingDetails['purpose']
                ];
                
                syncBookingToGoogleSheets($syncData);
            } else {
                $error = 'เกิดข้อผิดพลาดในการเพิ่มการจอง';
            }
        }
    }
}

// Get filter parameters
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';
$room_filter = $_GET['room_id'] ?? '';
$date_filter = $_GET['date'] ?? '';

// Build query
$whereConditions = [];
$params = [];

if ($filter === 'today') {
    $whereConditions[] = 'b.date = CURDATE()';
} elseif ($filter === 'upcoming') {
    $whereConditions[] = 'b.date >= CURDATE()';
} elseif ($filter === 'past') {
    $whereConditions[] = 'b.date < CURDATE()';
} elseif ($filter === 'this_week') {
    $whereConditions[] = 'YEARWEEK(b.date) = YEARWEEK(CURDATE())';
} elseif ($filter === 'this_month') {
    $whereConditions[] = 'YEAR(b.date) = YEAR(CURDATE()) AND MONTH(b.date) = MONTH(CURDATE())';
}

if ($search) {
    $whereConditions[] = '(u.username LIKE ? OR r.room_name LIKE ? OR b.purpose LIKE ?)';
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
}

if ($room_filter) {
    $whereConditions[] = 'b.room_id = ?';
    $params[] = $room_filter;
}

if ($date_filter) {
    $whereConditions[] = 'b.date = ?';
    $params[] = $date_filter;
}

$whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get bookings
$stmt = $pdo->prepare("
    SELECT b.*, u.username, r.room_name, r.capacity 
    FROM " . TABLE_BOOKINGS . " b 
    JOIN " . TABLE_USERS . " u ON b.user_id = u.id 
    JOIN " . TABLE_ROOMS . " r ON b.room_id = r.id 
    $whereClause
    ORDER BY b.date DESC, b.time_start DESC
");
$stmt->execute($params);
$bookings = $stmt->fetchAll();

// Get all users and rooms for the add form
$stmt = $pdo->prepare("SELECT id, username FROM " . TABLE_USERS . " WHERE role = 'user' ORDER BY username");
$stmt->execute();
$users = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT id, room_name, capacity FROM " . TABLE_ROOMS . " WHERE status = 'available' ORDER BY room_name");
$stmt->execute();
$rooms = $stmt->fetchAll();

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

$stmt = $pdo->prepare("SELECT COUNT(*) FROM " . TABLE_BOOKINGS . " WHERE date < CURDATE()");
$stmt->execute();
$pastBookings = $stmt->fetchColumn();
?>

<div class="glass-card">
    <div class="card-header">
        <i class="fas fa-calendar-alt"></i>
        จัดการการจองทั้งหมด
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?= $error ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?= $success ?>
        </div>
    <?php endif; ?>
    
    <!-- Statistics -->
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
            <div class="stat-number"><?= $upcomingBookings ?></div>
            <div class="stat-label">
                <i class="fas fa-clock"></i>
                จะมาถึง
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $pastBookings ?></div>
            <div class="stat-label">
                <i class="fas fa-history"></i>
                ผ่านมาแล้ว
            </div>
        </div>
    </div>
    
    <!-- Add New Booking -->
    <div class="card" style="margin-bottom: 2rem;">
        <div class="card-header">
            <i class="fas fa-plus-circle"></i>
            เพิ่มการจองใหม่ (สำหรับแอดมิน)
        </div>
        
        <form method="post" id="addBookingForm">
            <input type="hidden" name="action" value="add">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
                <div class="form-group">
                    <label for="user_id">ผู้ใช้งาน</label>
                    <select id="user_id" name="user_id" class="form-control" required>
                        <option value="">-- เลือกผู้ใช้งาน --</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['username']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="room_id">ห้องประชุม</label>
                    <select id="room_id" name="room_id" class="form-control" required>
                        <option value="">-- เลือกห้องประชุม --</option>
                        <?php foreach ($rooms as $room): ?>
                            <option value="<?= $room['id'] ?>">
                                <?= htmlspecialchars($room['room_name']) ?> (<?= $room['capacity'] ?> คน)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="date">วันที่</label>
                    <input type="date" id="date" name="date" class="form-control" min="<?= date('Y-m-d') ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="time_start">เวลาเริ่มต้น</label>
                    <select id="time_start" name="time_start" class="form-control" required>
                        <option value="">-- เลือกเวลา --</option>
                        <?php foreach (TIME_SLOTS as $time => $label): ?>
                            <option value="<?= $time ?>"><?= $time ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="time_end">เวลาสิ้นสุด</label>
                    <select id="time_end" name="time_end" class="form-control" required>
                        <option value="">-- เลือกเวลา --</option>
                        <?php foreach (TIME_SLOTS as $time => $label): ?>
                            <option value="<?= date('H:i', strtotime($time . ' +1 hour')) ?>">
                                <?= date('H:i', strtotime($time . ' +1 hour')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label for="purpose">วัตถุประสงค์การใช้งาน</label>
                <textarea id="purpose" name="purpose" class="form-control" rows="3" 
                          placeholder="ระบุวัตถุประสงค์การใช้ห้องประชุม" required></textarea>
            </div>
            
            <div style="text-align: center;">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-plus"></i> เพิ่มการจอง
                </button>
            </div>
        </form>
    </div>
    
    <!-- Filters -->
    <div class="card" style="margin-bottom: 1.5rem;">
        <div class="card-header">
            <i class="fas fa-filter"></i>
            กรองและค้นหา
        </div>
        
        <form method="get" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end;">
            <div class="form-group">
                <label for="filter">ประเภท</label>
                <select id="filter" name="filter" class="form-control">
                    <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>ทั้งหมด</option>
                    <option value="today" <?= $filter === 'today' ? 'selected' : '' ?>>วันนี้</option>
                    <option value="upcoming" <?= $filter === 'upcoming' ? 'selected' : '' ?>>จะมาถึง</option>
                    <option value="this_week" <?= $filter === 'this_week' ? 'selected' : '' ?>>สัปดาห์นี้</option>
                    <option value="this_month" <?= $filter === 'this_month' ? 'selected' : '' ?>>เดือนนี้</option>
                    <option value="past" <?= $filter === 'past' ? 'selected' : '' ?>>ผ่านมาแล้ว</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="search">ค้นหา</label>
                <input type="text" id="search" name="search" class="form-control" 
                       value="<?= htmlspecialchars($search) ?>" 
                       placeholder="ชื่อผู้ใช้, ห้อง, วัตถุประสงค์">
            </div>
            
            <div class="form-group">
                <label for="room_id">ห้องประชุม</label>
                <select id="room_id" name="room_id" class="form-control">
                    <option value="">-- ทุกห้อง --</option>
                    <?php foreach ($rooms as $room): ?>
                        <option value="<?= $room['id'] ?>" <?= $room_filter == $room['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($room['room_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="date">วันที่</label>
                <input type="date" id="date" name="date" class="form-control" value="<?= $date_filter ?>">
            </div>
            
            <div style="display: flex; gap: 0.5rem;">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-search"></i> ค้นหา
                </button>
                <a href="/admin/bookings" class="btn">
                    <i class="fas fa-times"></i> ล้าง
                </a>
            </div>
        </form>
    </div>
    
    <!-- Export Options -->
    <div style="display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; justify-content: center;">
        <button onclick="printReport()" class="btn">
            <i class="fas fa-print"></i> พิมพ์รายการ
        </button>
        <button onclick="exportToCSV('bookingsTable', 'bookings_report.csv')" class="btn">
            <i class="fas fa-download"></i> ส่งออก CSV
        </button>
        <button onclick="syncAllToGoogleSheets()" class="btn btn-warning">
            <i class="fas fa-sync"></i> Sync Google Sheets
        </button>
    </div>
    
    <!-- Bookings List -->
    <div class="card">
        <div class="card-header">
            <i class="fas fa-list"></i>
            รายการการจอง (<?= count($bookings) ?> รายการ)
        </div>
        
        <?php if ($bookings): ?>
            <div class="table-container">
                <table class="table" id="bookingsTable">
                    <thead>
                        <tr>
                            <th>ผู้จอง</th>
                            <th>ห้องประชุม</th>
                            <th>วันที่</th>
                            <th>เวลา</th>
                            <th>วัตถุประสงค์</th>
                            <th>สถานะ</th>
                            <th>การดำเนินการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking): ?>
                        <?php
                        $now = new DateTime();
                        $bookingDateTime = new DateTime($booking['date'] . ' ' . $booking['time_start']);
                        $bookingEndDateTime = new DateTime($booking['date'] . ' ' . $booking['time_end']);
                        
                        if ($bookingEndDateTime < $now) {
                            $status = 'เสร็จสิ้น';
                            $statusClass = 'btn-success';
                            $canCancel = false;
                        } elseif ($bookingDateTime <= $now && $now <= $bookingEndDateTime) {
                            $status = 'กำลังดำเนินการ';
                            $statusClass = 'btn-warning';
                            $canCancel = false;
                        } else {
                            $status = 'รอการประชุม';
                            $statusClass = 'btn-warning';
                            $canCancel = true;
                        }
                        ?>
                        <tr>
                            <td>
                                <div style="font-weight: 600;">
                                    <?= htmlspecialchars($booking['username']) ?>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight: 600;">
                                    <?= htmlspecialchars($booking['room_name']) ?>
                                </div>
                                <div style="font-size: 0.875rem; opacity: 0.8;">
                                    <i class="fas fa-users"></i> ความจุ: <?= $booking['capacity'] ?> คน
                                </div>
                            </td>
                            <td>
                                <div style="font-weight: 600;">
                                    <?= formatThaiDate($booking['date']) ?>
                                </div>
                                <div style="font-size: 0.875rem; opacity: 0.8;">
                                    <?php
                                    $dayName = ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์'];
                                    echo $dayName[date('w', strtotime($booking['date']))];
                                    ?>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight: 600;">
                                    <?= formatTime($booking['time_start']) ?> - <?= formatTime($booking['time_end']) ?>
                                </div>
                                <div style="font-size: 0.875rem; opacity: 0.8;">
                                    <?php
                                    $duration = (strtotime($booking['time_end']) - strtotime($booking['time_start'])) / 3600;
                                    echo $duration . ' ชั่วโมง';
                                    ?>
                                </div>
                            </td>
                            <td>
                                <div style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;">
                                    <?= htmlspecialchars($booking['purpose']) ?>
                                </div>
                            </td>
                            <td>
                                <span class="btn <?= $statusClass ?>" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">
                                    <?= $status ?>
                                </span>
                            </td>
                            <td>
                                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                    <button onclick="viewBookingDetails(<?= htmlspecialchars(json_encode($booking)) ?>)" 
                                            class="btn" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">
                                        <i class="fas fa-eye"></i> ดู
                                    </button>
                                    <?php if ($canCancel): ?>
                                        <form method="post" style="display: inline;" 
                                              onsubmit="return confirmDelete('คุณแน่ใจหรือไม่ที่จะยกเลิกการจองนี้?')">
                                            <input type="hidden" name="action" value="cancel">
                                            <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                            <button type="submit" class="btn btn-danger" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">
                                                <i class="fas fa-times"></i> ยกเลิก
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 3rem;">
                <i class="fas fa-calendar-times" style="font-size: 4rem; color: rgba(255,255,255,0.3); margin-bottom: 1rem;"></i>
                <h3 style="color: white; margin-bottom: 1rem;">ไม่พบการจองตามเงื่อนไขที่กำหนด</h3>
                <p style="color: rgba(255,255,255,0.8); margin-bottom: 2rem;">
                    ลองเปลี่ยนตัวกรองหรือเงื่อนไขการค้นหา
                </p>
                <a href="/admin/bookings" class="btn">
                    <i class="fas fa-refresh"></i> ดูทั้งหมด
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Booking Details Modal -->
<div id="bookingDetailsModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000; justify-content: center; align-items: center;">
    <div class="glass-card" style="max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <span><i class="fas fa-info-circle"></i> รายละเอียดการจอง</span>
            <button onclick="closeDetailsModal()" style="background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div id="bookingDetailsContent">
            <!-- Content will be loaded here -->
        </div>
    </div>
</div>

<script>
// Auto-select end time when start time changes
document.getElementById('time_start').addEventListener('change', function() {
    const startTime = this.value;
    if (startTime) {
        const startHour = parseInt(startTime.split(':')[0]);
        const endHour = startHour + 1;
        const endTime = endHour.toString().padStart(2, '0') + ':00';
        document.getElementById('time_end').value = endTime;
    }
});

function viewBookingDetails(booking) {
    const content = `
        <div style="padding: 1rem 0;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
                <div>
                    <h4 style="color: #ffd700; margin-bottom: 1rem;">
                        <i class="fas fa-user"></i> ข้อมูลผู้จอง
                    </h4>
                    <p style="color: white; margin-bottom: 0.5rem;">
                        <strong>ชื่อผู้ใช้:</strong> ${booking.username}
                    </p>
                </div>
                
                <div>
                    <h4 style="color: #ffd700; margin-bottom: 1rem;">
                        <i class="fas fa-door-open"></i> ข้อมูลห้อง
                    </h4>
                    <p style="color: white; margin-bottom: 0.5rem;">
                        <strong>ห้องประชุม:</strong> ${booking.room_name}
                    </p>
                    <p style="color: rgba(255,255,255,0.8);">
                        <strong>ความจุ:</strong> ${booking.capacity} คน
                    </p>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
                <div>
                    <h4 style="color: #ffd700; margin-bottom: 1rem;">
                        <i class="fas fa-calendar"></i> วันที่และเวลา
                    </h4>
                    <p style="color: white; margin-bottom: 0.5rem;">
                        <strong>วันที่:</strong> ${formatThaiDate(booking.date)}
                    </p>
                    <p style="color: rgba(255,255,255,0.8);">
                        <strong>เวลา:</strong> ${booking.time_start.substring(0,5)} - ${booking.time_end.substring(0,5)}
                    </p>
                </div>
                
                <div>
                    <h4 style="color: #ffd700; margin-bottom: 1rem;">
                        <i class="fas fa-info-circle"></i> รหัสการจอง
                    </h4>
                    <p style="color: white; font-family: monospace; font-size: 1.25rem;">
                        #${booking.id.toString().padStart(6, '0')}
                    </p>
                </div>
            </div>
            
            <div style="margin-bottom: 2rem;">
                <h4 style="color: #ffd700; margin-bottom: 1rem;">
                    <i class="fas fa-edit"></i> วัตถุประสงค์การใช้งาน
                </h4>
                <div style="background: rgba(255,255,255,0.1); padding: 1rem; border-radius: 10px; color: white; line-height: 1.6;">
                    ${booking.purpose}
                </div>
            </div>
            
            <div style="text-align: center;">
                <button onclick="closeDetailsModal()" class="btn">
                    <i class="fas fa-times"></i> ปิด
                </button>
            </div>
        </div>
    `;
    
    document.getElementById('bookingDetailsContent').innerHTML = content;
    document.getElementById('bookingDetailsModal').style.display = 'flex';
}

function closeDetailsModal() {
    document.getElementById('bookingDetailsModal').style.display = 'none';
}

function syncAllToGoogleSheets() {
    if (confirm('คุณต้องการ Sync ข้อมูลทั้งหมดไปยัง Google Sheets หรือไม่?')) {
        // Show loading
        const btn = event.target;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลัง Sync...';
        btn.disabled = true;
        
        fetch('/api/sync_all_bookings.php', { method: 'POST' })
            .then(response => response.json())
            .then(data => {
                btn.innerHTML = originalText;
                btn.disabled = false;
                
                if (data.success) {
                    alert(`Sync สำเร็จ: ${data.count} รายการ`);
                } else {
                    alert('เกิดข้อผิดพลาดในการเชื่อมต่อ');
            });
    }
}

// Close modal when clicking outside
document.getElementById('bookingDetailsModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeDetailsModal();
    }
});

// Format Thai date function for JavaScript
function formatThaiDate(dateString) {
    const months = [
        'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
        'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'
    ];
    
    const date = new Date(dateString);
    const day = date.getDate();
    const month = months[date.getMonth()];
    const year = date.getFullYear() + 543;
    
    return `${day} ${month} ${year}`;
}

// Real-time filter
document.getElementById('filter').addEventListener('change', function() {
    this.form.submit();
});

// Auto-submit search after typing (debounced)
let searchTimeout;
document.getElementById('search').addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        this.form.submit();
    }, 1000);
});
</script>

<style>
@media print {
    .btn, .card-header, .stats-grid, nav, form {
        display: none !important;
    }
    
    .glass-card {
        background: white !important;
        color: black !important;
        box-shadow: none !important;
        border: 1px solid #ddd !important;
    }
    
    .table th,
    .table td {
        color: black !important;
        border: 1px solid #ddd !important;
    }
    
    body {
        background: white !important;
    }
}
</style>เกิดข้อผิดพลาด: ' + data.error);
                }
            })
            .catch(error => {
                btn.innerHTML = originalText;
                btn.disabled = false;
                console.error('Error:', error);
                alert('เกิดข้อผิดพลาดในการเชื่อมต่อ');
            });