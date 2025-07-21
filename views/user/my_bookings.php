<?php
checkLogin();

$error = '';
$success = '';

// Handle booking actions
if ($_POST) {
    $action = $_POST['action'] ?? '';
    $booking_id = (int)($_POST['booking_id'] ?? 0);
    
    if ($action === 'cancel' && $booking_id) {
        // Verify booking belongs to user
        $stmt = $pdo->prepare("SELECT * FROM " . TABLE_BOOKINGS . " WHERE id = ? AND user_id = ?");
        $stmt->execute([$booking_id, $_SESSION['user_id']]);
        $booking = $stmt->fetch();
        
        if ($booking) {
            // Check if booking is in the future
            $bookingDateTime = new DateTime($booking['date'] . ' ' . $booking['time_start']);
            $now = new DateTime();
            
            if ($bookingDateTime > $now) {
                $stmt = $pdo->prepare("DELETE FROM " . TABLE_BOOKINGS . " WHERE id = ?");
                if ($stmt->execute([$booking_id])) {
                    $success = 'ยกเลิกการจองสำเร็จ';
                } else {
                    $error = 'เกิดข้อผิดพลาดในการยกเลิก';
                }
            } else {
                $error = 'ไม่สามารถยกเลิกการจองที่ผ่านมาแล้ว';
            }
        } else {
            $error = 'ไม่พบการจองที่ระบุ';
        }
    }
}

// Get user bookings
$filter = $_GET['filter'] ?? 'all';
$whereClause = '';
$params = [$_SESSION['user_id']];

switch ($filter) {
    case 'upcoming':
        $whereClause = 'AND b.date >= CURDATE()';
        break;
    case 'past':
        $whereClause = 'AND b.date < CURDATE()';
        break;
    case 'today':
        $whereClause = 'AND b.date = CURDATE()';
        break;
}

$stmt = $pdo->prepare("
    SELECT b.*, r.room_name, r.capacity 
    FROM " . TABLE_BOOKINGS . " b 
    JOIN " . TABLE_ROOMS . " r ON b.room_id = r.id 
    WHERE b.user_id = ? $whereClause
    ORDER BY b.date DESC, b.time_start DESC
");
$stmt->execute($params);
$bookings = $stmt->fetchAll();

// Get statistics
$stmt = $pdo->prepare("SELECT COUNT(*) FROM " . TABLE_BOOKINGS . " WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$totalBookings = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM " . TABLE_BOOKINGS . " WHERE user_id = ? AND date >= CURDATE()");
$stmt->execute([$_SESSION['user_id']]);
$upcomingBookings = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM " . TABLE_BOOKINGS . " WHERE user_id = ? AND date < CURDATE()");
$stmt->execute([$_SESSION['user_id']]);
$pastBookings = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM " . TABLE_BOOKINGS . " WHERE user_id = ? AND date = CURDATE()");
$stmt->execute([$_SESSION['user_id']]);
$todayBookings = $stmt->fetchColumn();
?>

<div class="glass-card">
    <div class="card-header">
        <i class="fas fa-list"></i>
        รายการจองของฉัน
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
            <div class="stat-label">ทั้งหมด</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $upcomingBookings ?></div>
            <div class="stat-label">จะมาถึง</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $todayBookings ?></div>
            <div class="stat-label">วันนี้</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $pastBookings ?></div>
            <div class="stat-label">ผ่านมาแล้ว</div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="card" style="margin-bottom: 1.5rem;">
        <div class="card-header">
            <i class="fas fa-filter"></i>
            กรองรายการ
        </div>
        <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
            <a href="?filter=all" class="btn <?= $filter === 'all' ? 'btn-success' : '' ?>">
                <i class="fas fa-list"></i> ทั้งหมด (<?= $totalBookings ?>)
            </a>
            <a href="?filter=today" class="btn <?= $filter === 'today' ? 'btn-success' : '' ?>">
                <i class="fas fa-calendar-day"></i> วันนี้ (<?= $todayBookings ?>)
            </a>
            <a href="?filter=upcoming" class="btn <?= $filter === 'upcoming' ? 'btn-success' : '' ?>">
                <i class="fas fa-clock"></i> จะมาถึง (<?= $upcomingBookings ?>)
            </a>
            <a href="?filter=past" class="btn <?= $filter === 'past' ? 'btn-success' : '' ?>">
                <i class="fas fa-history"></i> ผ่านมาแล้ว (<?= $pastBookings ?>)
            </a>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div style="display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
        <a href="/user/book_room" class="btn btn-success">
            <i class="fas fa-plus-circle"></i> จองห้องใหม่
        </a>
        <button onclick="printReport()" class="btn">
            <i class="fas fa-print"></i> พิมพ์รายการ
        </button>
        <button onclick="exportToCSV('bookingsTable', 'my_bookings.csv')" class="btn">
            <i class="fas fa-download"></i> ส่งออก CSV
        </button>
    </div>
    
    <!-- Bookings List -->
    <?php if ($bookings): ?>
    <div class="table-container">
        <table class="table" id="bookingsTable">
            <thead>
                <tr>
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
                
                // Determine status
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
                            <button onclick="viewDetails(<?= $booking['id'] ?>)" class="btn" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">
                                <i class="fas fa-eye"></i> ดู
                            </button>
                            <?php if ($canCancel): ?>
                                <form method="post" style="display: inline;" onsubmit="return confirmDelete('คุณแน่ใจหรือไม่ที่จะยกเลิกการจองนี้?')">
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
    <div class="card">
        <div style="text-align: center; padding: 3rem;">
            <i class="fas fa-calendar-times" style="font-size: 4rem; color: rgba(255,255,255,0.3); margin-bottom: 1rem;"></i>
            <h3 style="color: white; margin-bottom: 1rem;">
                <?php
                switch ($filter) {
                    case 'today': echo 'ไม่มีการจองสำหรับวันนี้'; break;
                    case 'upcoming': echo 'ไม่มีการจองที่จะมาถึง'; break;
                    case 'past': echo 'ไม่มีประวัติการจอง'; break;
                    default: echo 'คุณยังไม่มีการจองห้องประชุม'; break;
                }
                ?>
            </h3>
            <p style="color: rgba(255,255,255,0.8); margin-bottom: 2rem;">
                เริ่มต้นจองห้องประชุมเพื่อใช้งานในองค์กร
            </p>
            <a href="/user/book_room" class="btn btn-success">
                <i class="fas fa-plus-circle"></i> จองห้องใหม่
            </a>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Booking Details Modal -->
<div id="bookingModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000; justify-content: center; align-items: center;">
    <div class="glass-card" style="max-width: 500px; width: 90%; max-height: 80vh; overflow-y: auto;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <span><i class="fas fa-info-circle"></i> รายละเอียดการจอง</span>
            <button onclick="closeModal()" style="background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div id="modalContent">
            <!-- Content will be loaded here -->
        </div>
    </div>
</div>

<script>
function viewDetails(bookingId) {
    // Find booking data from the table
    const bookings = <?= json_encode($bookings) ?>;
    const booking = bookings.find(b => b.id == bookingId);
    
    if (booking) {
        const content = `
            <div style="padding: 1rem 0;">
                <div style="margin-bottom: 1.5rem;">
                    <h4 style="color: #ffd700; margin-bottom: 0.5rem;">
                        <i class="fas fa-door-open"></i> ${booking.room_name}
                    </h4>
                    <p style="color: rgba(255,255,255,0.8);">
                        <i class="fas fa-users"></i> ความจุ: ${booking.capacity} คน
                    </p>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                    <div>
                        <h5 style="color: white; margin-bottom: 0.5rem;">
                            <i class="fas fa-calendar"></i> วันที่
                        </h5>
                        <p style="color: rgba(255,255,255,0.8);">
                            ${formatThaiDate(booking.date)}
                        </p>
                    </div>
                    <div>
                        <h5 style="color: white; margin-bottom: 0.5rem;">
                            <i class="fas fa-clock"></i> เวลา
                        </h5>
                        <p style="color: rgba(255,255,255,0.8);">
                            ${booking.time_start.substring(0,5)} - ${booking.time_end.substring(0,5)}
                        </p>
                    </div>
                </div>
                
                <div style="margin-bottom: 1.5rem;">
                    <h5 style="color: white; margin-bottom: 0.5rem;">
                        <i class="fas fa-edit"></i> วัตถุประสงค์
                    </h5>
                    <div style="background: rgba(255,255,255,0.1); padding: 1rem; border-radius: 10px; color: rgba(255,255,255,0.9);">
                        ${booking.purpose}
                    </div>
                </div>
                
                <div style="text-align: center;">
                    <button onclick="closeModal()" class="btn">
                        <i class="fas fa-times"></i> ปิด
                    </button>
                </div>
            </div>
        `;
        
        document.getElementById('modalContent').innerHTML = content;
        document.getElementById('bookingModal').style.display = 'flex';
    }
}

function closeModal() {
    document.getElementById('bookingModal').style.display = 'none';
}

// Close modal when clicking outside
document.getElementById('bookingModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
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

// Auto-refresh for real-time updates (optional)
setInterval(function() {
    // Only refresh if viewing today's bookings
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('filter') === 'today') {
        location.reload();
    }
}, 60000); // Refresh every minute
</script>

<style>
@media print {
    .btn, .card-header, .stats-grid, nav {
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

.table tbody tr:hover {
    background: rgba(255, 255, 255, 0.1);
}

.btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
</style>font-weight: 600;">
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
                        <div style="