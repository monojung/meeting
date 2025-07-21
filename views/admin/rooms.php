<?php
checkLogin();
if (!isAdmin()) {
    header('Location: /user/dashboard');
    exit;
}

$error = '';
$success = '';

// Handle room actions
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $room_name = sanitize($_POST['room_name']);
        $capacity = (int)$_POST['capacity'];
        $status = $_POST['status'];
        
        if (empty($room_name) || $capacity <= 0) {
            $error = 'กรุณากรอกข้อมูลให้ครบถ้วนและถูกต้อง';
        } else {
            $stmt = $pdo->prepare("INSERT INTO " . TABLE_ROOMS . " (room_name, capacity, status) VALUES (?, ?, ?)");
            if ($stmt->execute([$room_name, $capacity, $status])) {
                $success = 'เพิ่มห้องประชุมสำเร็จ';
            } else {
                $error = 'เกิดข้อผิดพลาดในการเพิ่มห้อง';
            }
        }
    } elseif ($action === 'edit') {
        $room_id = (int)$_POST['room_id'];
        $room_name = sanitize($_POST['room_name']);
        $capacity = (int)$_POST['capacity'];
        $status = $_POST['status'];
        
        if (empty($room_name) || $capacity <= 0) {
            $error = 'กรุณากรอกข้อมูลให้ครบถ้วนและถูกต้อง';
        } else {
            $stmt = $pdo->prepare("UPDATE " . TABLE_ROOMS . " SET room_name = ?, capacity = ?, status = ? WHERE id = ?");
            if ($stmt->execute([$room_name, $capacity, $status, $room_id])) {
                $success = 'แก้ไขห้องประชุมสำเร็จ';
            } else {
                $error = 'เกิดข้อผิดพลาดในการแก้ไข';
            }
        }
    } elseif ($action === 'delete') {
        $room_id = (int)$_POST['room_id'];
        
        // Check if room has bookings
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM " . TABLE_BOOKINGS . " WHERE room_id = ? AND date >= CURDATE()");
        $stmt->execute([$room_id]);
        $futureBookings = $stmt->fetchColumn();
        
        if ($futureBookings > 0) {
            $error = 'ไม่สามารถลบห้องที่มีการจองในอนาคตได้';
        } else {
            $stmt = $pdo->prepare("DELETE FROM " . TABLE_ROOMS . " WHERE id = ?");
            if ($stmt->execute([$room_id])) {
                $success = 'ลบห้องประชุมสำเร็จ';
            } else {
                $error = 'เกิดข้อผิดพลาดในการลบห้อง';
            }
        }
    }
}

// Get all rooms with booking statistics
$stmt = $pdo->prepare("
    SELECT r.*, 
           COUNT(b.id) as total_bookings,
           COUNT(CASE WHEN b.date >= CURDATE() THEN 1 END) as upcoming_bookings,
           COUNT(CASE WHEN b.date = CURDATE() THEN 1 END) as today_bookings
    FROM " . TABLE_ROOMS . " r 
    LEFT JOIN " . TABLE_BOOKINGS . " b ON r.id = b.room_id 
    GROUP BY r.id 
    ORDER BY r.room_name
");
$stmt->execute();
$rooms = $stmt->fetchAll();

// Get room statistics
$stmt = $pdo->prepare("SELECT COUNT(*) FROM " . TABLE_ROOMS);
$stmt->execute();
$totalRooms = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM " . TABLE_ROOMS . " WHERE status = 'available'");
$stmt->execute();
$availableRooms = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM " . TABLE_ROOMS . " WHERE status = 'maintenance'");
$stmt->execute();
$maintenanceRooms = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT AVG(capacity) FROM " . TABLE_ROOMS);
$stmt->execute();
$avgCapacity = round($stmt->fetchColumn());
?>

<div class="glass-card">
    <div class="card-header">
        <i class="fas fa-door-open"></i>
        จัดการห้องประชุม
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
            <div class="stat-number"><?= $totalRooms ?></div>
            <div class="stat-label">
                <i class="fas fa-door-open"></i>
                ห้องทั้งหมด
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $availableRooms ?></div>
            <div class="stat-label">
                <i class="fas fa-check-circle"></i>
                พร้อมใช้งาน
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $maintenanceRooms ?></div>
            <div class="stat-label">
                <i class="fas fa-tools"></i>
                ปรับปรุง/ซ่อมแซม
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $avgCapacity ?></div>
            <div class="stat-label">
                <i class="fas fa-users"></i>
                ความจุเฉลี่ย
            </div>
        </div>
    </div>
    
    <!-- Add Room Form -->
    <div class="card" style="margin-bottom: 2rem;">
        <div class="card-header">
            <i class="fas fa-plus-circle"></i>
            เพิ่มห้องประชุมใหม่
        </div>
        
        <form method="post" id="addRoomForm">
            <input type="hidden" name="action" value="add">
            <div style="display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 1rem; align-items: end;">
                <div class="form-group">
                    <label for="room_name">ชื่อห้องประชุม</label>
                    <input type="text" id="room_name" name="room_name" class="form-control" 
                           placeholder="เช่น ห้องประชุมใหญ่, Conference Room A" required>
                </div>
                
                <div class="form-group">
                    <label for="capacity">ความจุ (คน)</label>
                    <input type="number" id="capacity" name="capacity" class="form-control" 
                           min="1" max="100" placeholder="เช่น 10" required>
                </div>
                
                <div class="form-group">
                    <label for="status">สถานะ</label>
                    <select id="status" name="status" class="form-control" required>
                        <option value="available">พร้อมใช้งาน</option>
                        <option value="maintenance">ปรับปรุง/ซ่อมแซม</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-plus"></i> เพิ่ม
                </button>
            </div>
        </form>
    </div>
    
    <!-- Rooms List -->
    <div class="card">
        <div class="card-header">
            <i class="fas fa-list"></i>
            รายการห้องประชุม (<?= count($rooms) ?> ห้อง)
        </div>
        
        <?php if ($rooms): ?>
            <div class="table-container">
                <table class="table" id="roomsTable">
                    <thead>
                        <tr>
                            <th>ชื่อห้อง</th>
                            <th>ความจุ</th>
                            <th>สถานะ</th>
                            <th>การจองทั้งหมด</th>
                            <th>จองวันนี้</th>
                            <th>จองที่จะมาถึง</th>
                            <th>การดำเนินการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rooms as $room): ?>
                        <tr>
                            <td>
                                <div style="font-weight: 600; color: white;">
                                    <?= htmlspecialchars($room['room_name']) ?>
                                </div>
                            </td>
                            <td>
                                <span style="color: #ffd700;">
                                    <i class="fas fa-users"></i> <?= $room['capacity'] ?> คน
                                </span>
                            </td>
                            <td>
                                <?php
                                $statusClass = $room['status'] === 'available' ? 'btn-success' : 'btn-warning';
                                $statusText = $room['status'] === 'available' ? 'พร้อมใช้งาน' : 'ปรับปรุง/ซ่อมแซม';
                                $statusIcon = $room['status'] === 'available' ? 'fa-check-circle' : 'fa-tools';
                                ?>
                                <span class="btn <?= $statusClass ?>" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">
                                    <i class="fas <?= $statusIcon ?>"></i> <?= $statusText ?>
                                </span>
                            </td>
                            <td>
                                <span style="color: white; font-weight: 600;">
                                    <?= $room['total_bookings'] ?>
                                </span>
                            </td>
                            <td>
                                <span style="color: <?= $room['today_bookings'] > 0 ? '#4CAF50' : 'rgba(255,255,255,0.6)' ?>; font-weight: 600;">
                                    <?= $room['today_bookings'] ?>
                                </span>
                            </td>
                            <td>
                                <span style="color: <?= $room['upcoming_bookings'] > 0 ? '#FF9800' : 'rgba(255,255,255,0.6)' ?>; font-weight: 600;">
                                    <?= $room['upcoming_bookings'] ?>
                                </span>
                            </td>
                            <td>
                                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                    <button onclick="editRoom(<?= htmlspecialchars(json_encode($room)) ?>)" 
                                            class="btn" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">
                                        <i class="fas fa-edit"></i> แก้ไข
                                    </button>
                                    <button onclick="viewRoomBookings(<?= $room['id'] ?>, '<?= htmlspecialchars($room['room_name']) ?>')" 
                                            class="btn btn-warning" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">
                                        <i class="fas fa-calendar-alt"></i> ดูการจอง
                                    </button>
                                    <?php if ($room['upcoming_bookings'] == 0): ?>
                                        <form method="post" style="display: inline;" 
                                              onsubmit="return confirmDelete('คุณแน่ใจหรือไม่ที่จะลบห้อง <?= htmlspecialchars($room['room_name']) ?>?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="room_id" value="<?= $room['id'] ?>">
                                            <button type="submit" class="btn btn-danger" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">
                                                <i class="fas fa-trash"></i> ลบ
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="btn" style="padding: 0.25rem 0.5rem; font-size: 0.75rem; opacity: 0.5; cursor: not-allowed;">
                                            <i class="fas fa-ban"></i> ไม่สามารถลบได้
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Export Options -->
            <div style="display: flex; gap: 1rem; margin-top: 1rem; justify-content: center; flex-wrap: wrap;">
                <button onclick="printReport()" class="btn">
                    <i class="fas fa-print"></i> พิมพ์รายการ
                </button>
                <button onclick="exportToCSV('roomsTable', 'rooms_list.csv')" class="btn">
                    <i class="fas fa-download"></i> ส่งออก CSV
                </button>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 3rem;">
                <i class="fas fa-door-closed" style="font-size: 4rem; color: rgba(255,255,255,0.3); margin-bottom: 1rem;"></i>
                <h3 style="color: white; margin-bottom: 1rem;">ยังไม่มีห้องประชุม</h3>
                <p style="color: rgba(255,255,255,0.8); margin-bottom: 2rem;">
                    เริ่มต้นโดยการเพิ่มห้องประชุมแรกของคุณ
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Edit Room Modal -->
<div id="editRoomModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000; justify-content: center; align-items: center;">
    <div class="glass-card" style="max-width: 500px; width: 90%;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <span><i class="fas fa-edit"></i> แก้ไขห้องประชุม</span>
            <button onclick="closeEditModal()" style="background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form method="post" id="editRoomForm">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="room_id" id="edit_room_id">
            
            <div class="form-group">
                <label for="edit_room_name">ชื่อห้องประชุม</label>
                <input type="text" id="edit_room_name" name="room_name" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="edit_capacity">ความจุ (คน)</label>
                <input type="number" id="edit_capacity" name="capacity" class="form-control" min="1" max="100" required>
            </div>
            
            <div class="form-group">
                <label for="edit_status">สถานะ</label>
                <select id="edit_status" name="status" class="form-control" required>
                    <option value="available">พร้อมใช้งาน</option>
                    <option value="maintenance">ปรับปรุง/ซ่อมแซม</option>
                </select>
            </div>
            
            <div style="display: flex; gap: 1rem; justify-content: center;">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i> บันทึก
                </button>
                <button type="button" onclick="closeEditModal()" class="btn">
                    <i class="fas fa-times"></i> ยกเลิก
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Room Bookings Modal -->
<div id="roomBookingsModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000; justify-content: center; align-items: center;">
    <div class="glass-card" style="max-width: 800px; width: 90%; max-height: 80vh; overflow-y: auto;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <span id="roomBookingsTitle"><i class="fas fa-calendar-alt"></i> การจองห้องประชุม</span>
            <button onclick="closeBookingsModal()" style="background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div id="roomBookingsContent">
            <!-- Content will be loaded here -->
        </div>
    </div>
</div>

<script>
function editRoom(room) {
    document.getElementById('edit_room_id').value = room.id;
    document.getElementById('edit_room_name').value = room.room_name;
    document.getElementById('edit_capacity').value = room.capacity;
    document.getElementById('edit_status').value = room.status;
    document.getElementById('editRoomModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editRoomModal').style.display = 'none';
}

function viewRoomBookings(roomId, roomName) {
    document.getElementById('roomBookingsTitle').innerHTML = 
        `<i class="fas fa-calendar-alt"></i> การจอง: ${roomName}`;
    
    // Show loading
    document.getElementById('roomBookingsContent').innerHTML = 
        '<div style="text-align: center; padding: 2rem;"><i class="fas fa-spinner fa-spin" style="font-size: 2rem;"></i><br>กำลังโหลด...</div>';
    
    document.getElementById('roomBookingsModal').style.display = 'flex';
    
    // Fetch room bookings
    fetch(`/api/get_room_bookings.php?room_id=${roomId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayRoomBookings(data.bookings);
            } else {
                document.getElementById('roomBookingsContent').innerHTML = 
                    '<div style="text-align: center; padding: 2rem; color: #f44336;">เกิดข้อผิดพลาดในการโหลดข้อมูล</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('roomBookingsContent').innerHTML = 
                '<div style="text-align: center; padding: 2rem; color: #f44336;">เกิดข้อผิดพลาดในการเชื่อมต่อ</div>';
        });
}

function displayRoomBookings(bookings) {
    if (bookings.length === 0) {
        document.getElementById('roomBookingsContent').innerHTML = 
            '<div style="text-align: center; padding: 2rem;"><i class="fas fa-calendar-times" style="font-size: 2rem; margin-bottom: 1rem;"></i><br>ไม่มีการจองสำหรับห้องนี้</div>';
        return;
    }
    
    let html = '<div class="table-container"><table class="table"><thead><tr><th>ผู้จอง</th><th>วันที่</th><th>เวลา</th><th>วัตถุประสงค์</th><th>สถานะ</th></tr></thead><tbody>';
    
    bookings.forEach(booking => {
        const bookingDate = new Date(booking.date + ' ' + booking.time_start);
        const now = new Date();
        let status = bookingDate > now ? 'รอการประชุม' : 'เสร็จสิ้น';
        let statusClass = bookingDate > now ? 'btn-warning' : 'btn-success';
        
        html += `
            <tr>
                <td>${booking.username}</td>
                <td>${formatThaiDate(booking.date)}</td>
                <td>${booking.time_start.substring(0,5)} - ${booking.time_end.substring(0,5)}</td>
                <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;">${booking.purpose}</td>
                <td><span class="btn ${statusClass}" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">${status}</span></td>
            </tr>
        `;
    });
    
    html += '</tbody></table></div>';
    document.getElementById('roomBookingsContent').innerHTML = html;
}

function closeBookingsModal() {
    document.getElementById('roomBookingsModal').style.display = 'none';
}

// Close modals when clicking outside
document.getElementById('editRoomModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});

document.getElementById('roomBookingsModal').addEventListener('click', function(e) {
    if (e.target === this) closeBookingsModal();
});

// Form validation
document.getElementById('addRoomForm').addEventListener('submit', function(e) {
    const roomName = document.getElementById('room_name').value.trim();
    const capacity = parseInt(document.getElementById('capacity').value);
    
    if (roomName.length < 3) {
        e.preventDefault();
        alert('ชื่อห้องประชุมต้องมีอย่างน้อย 3 ตัวอักษร');
        return false;
    }
    
    if (capacity < 1 || capacity > 100) {
        e.preventDefault();
        alert('ความจุต้องอยู่ระหว่าง 1-100 คน');
        return false;
    }
});

document.getElementById('editRoomForm').addEventListener('submit', function(e) {
    const roomName = document.getElementById('edit_room_name').value.trim();
    const capacity = parseInt(document.getElementById('edit_capacity').value);
    
    if (roomName.length < 3) {
        e.preventDefault();
        alert('ชื่อห้องประชุมต้องมีอย่างน้อย 3 ตัวอักษร');
        return false;
    }
    
    if (capacity < 1 || capacity > 100) {
        e.preventDefault();
        alert('ความจุต้องอยู่ระหว่าง 1-100 คน');
        return false;
    }
});
</script>