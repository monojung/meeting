<?php
checkLogin();

$error = '';
$success = '';

// Get all available rooms
$stmt = $pdo->prepare("SELECT * FROM " . TABLE_ROOMS . " WHERE status = 'available' ORDER BY room_name");
$stmt->execute();
$rooms = $stmt->fetchAll();

// Pre-select room if specified in URL
$selectedRoomId = $_GET['room_id'] ?? '';

if ($_POST) {
    $room_id = (int)$_POST['room_id'];
    $date = $_POST['date'];
    $time_start = $_POST['time_start'];
    $time_end = $_POST['time_end'];
    $purpose = sanitize($_POST['purpose']);
    
    // Validation
    if (empty($room_id) || empty($date) || empty($time_start) || empty($time_end) || empty($purpose)) {
        $error = 'กรุณากรอกข้อมูลให้ครบถ้วน';
    } elseif ($date < date('Y-m-d')) {
        $error = 'ไม่สามารถจองย้อนหลังได้';
    } elseif ($time_start >= $time_end) {
        $error = 'เวลาสิ้นสุดต้องมากกว่าเวลาเริ่มต้น';
    } elseif (!isTimeSlotAvailable($pdo, $room_id, $date, $time_start, $time_end)) {
        $error = 'เวลาดังกล่าวมีการจองแล้ว';
    } else {
        // Create booking
        try {
            $stmt = $pdo->prepare("
                INSERT INTO " . TABLE_BOOKINGS . " (user_id, room_id, date, time_start, time_end, purpose) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            if ($stmt->execute([$_SESSION['user_id'], $room_id, $date, $time_start, $time_end, $purpose])) {
                $bookingId = $pdo->lastInsertId();
                
                // Get booking details for Google Sheets sync
                $stmt = $pdo->prepare("
                    SELECT b.*, u.username, r.room_name 
                    FROM " . TABLE_BOOKINGS . " b 
                    JOIN " . TABLE_USERS . " u ON b.user_id = u.id 
                    JOIN " . TABLE_ROOMS . " r ON b.room_id = r.id 
                    WHERE b.id = ?
                ");
                $stmt->execute([$bookingId]);
                $bookingDetails = $stmt->fetch();
                
                // Sync to Google Sheets
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
                
                $success = 'จองห้องประชุมสำเร็จ';
                
                // Reset form
                $room_id = $date = $time_start = $time_end = $purpose = '';
            }
        } catch (Exception $e) {
            $error = 'เกิดข้อผิดพลาดในการจอง: ' . $e->getMessage();
        }
    }
}
?>

<div class="glass-card">
    <div class="card-header">
        <i class="fas fa-calendar-plus"></i>
        จองห้องประชุม
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
    
    <form method="post" action="" id="bookingForm">
        <div class="card-grid">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-door-open"></i>
                    เลือกห้องประชุม
                </div>
                
                <div class="form-group">
                    <label for="room_id">ห้องประชุม</label>
                    <select id="room_id" name="room_id" class="form-control" required>
                        <option value="">-- เลือกห้องประชุม --</option>
                        <?php foreach ($rooms as $room): ?>
                            <option value="<?= $room['id'] ?>" 
                                    <?= $selectedRoomId == $room['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($room['room_name']) ?> 
                                (ความจุ: <?= $room['capacity'] ?> คน)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <?php if ($rooms): ?>
                <div class="room-preview" style="margin-top: 1rem;">
                    <h4 style="margin-bottom: 1rem; color: white;">ห้องประชุมที่ใช้งานได้</h4>
                    <div style="display: grid; gap: 1rem;">
                        <?php foreach ($rooms as $room): ?>
                        <div class="room-card" data-room-id="<?= $room['id'] ?>" 
                             style="background: rgba(255,255,255,0.1); padding: 1rem; border-radius: 10px; cursor: pointer; border: 2px solid transparent; transition: all 0.3s ease;"
                             onclick="selectRoom(<?= $room['id'] ?>)">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <div style="font-weight: 600; color: white; margin-bottom: 0.25rem;">
                                        <?= htmlspecialchars($room['room_name']) ?>
                                    </div>
                                    <div style="font-size: 0.875rem; color: rgba(255,255,255,0.8);">
                                        <i class="fas fa-users"></i> ความจุ: <?= $room['capacity'] ?> คน
                                    </div>
                                </div>
                                <i class="fas fa-check-circle" style="color: #4CAF50; font-size: 1.5rem; display: none;"></i>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-calendar-alt"></i>
                    เลือกวันที่และเวลา
                </div>
                
                <div class="form-group">
                    <label for="date">วันที่</label>
                    <input type="date" id="date" name="date" class="form-control" 
                           min="<?= date('Y-m-d') ?>" 
                           value="<?= isset($_POST['date']) ? $_POST['date'] : '' ?>" required>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="time_start">เวลาเริ่มต้น</label>
                        <select id="time_start" name="time_start" class="form-control" required>
                            <option value="">-- เลือกเวลา --</option>
                            <?php foreach (TIME_SLOTS as $time => $label): ?>
                                <option value="<?= $time ?>" 
                                        <?= (isset($_POST['time_start']) && $_POST['time_start'] == $time) ? 'selected' : '' ?>>
                                    <?= $time ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="time_end">เวลาสิ้นสุด</label>
                        <select id="time_end" name="time_end" class="form-control" required>
                            <option value="">-- เลือกเวลา --</option>
                            <?php foreach (TIME_SLOTS as $time => $label): ?>
                                <option value="<?= date('H:i', strtotime($time . ' +1 hour')) ?>" 
                                        <?= (isset($_POST['time_end']) && $_POST['time_end'] == date('H:i', strtotime($time . ' +1 hour'))) ? 'selected' : '' ?>>
                                    <?= date('H:i', strtotime($time . ' +1 hour')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <!-- Time Slots Visual -->
                <div id="timeSlots" class="time-slots" style="margin-top: 1rem;">
                    <p style="text-align: center; color: rgba(255,255,255,0.8);">
                        เลือกห้องและวันที่เพื่อดูช่วงเวลาที่ว่าง
                    </p>
                </div>
            </div>
        </div>
        
        <div class="card" style="margin-top: 1.5rem;">
            <div class="card-header">
                <i class="fas fa-edit"></i>
                รายละเอียดการจอง
            </div>
            
            <div class="form-group">
                <label for="purpose">วัตถุประสงค์การใช้งาน</label>
                <textarea id="purpose" name="purpose" class="form-control" rows="4" 
                          placeholder="ระบุวัตถุประสงค์การใช้ห้องประชุม เช่น ประชุมทีม, การนำเสนอ, อบรม เป็นต้น" 
                          required><?= isset($_POST['purpose']) ? htmlspecialchars($_POST['purpose']) : '' ?></textarea>
            </div>
            
            <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-calendar-check"></i> ยืนยันการจอง
                </button>
                <a href="/user/dashboard" class="btn">
                    <i class="fas fa-times"></i> ยกเลิก
                </a>
            </div>
        </div>
    </form>
</div>

<script>
// Room selection
function selectRoom(roomId) {
    document.getElementById('room_id').value = roomId;
    
    // Update visual selection
    document.querySelectorAll('.room-card').forEach(card => {
        card.style.borderColor = 'transparent';
        card.querySelector('.fa-check-circle').style.display = 'none';
    });
    
    const selectedCard = document.querySelector(`[data-room-id="${roomId}"]`);
    selectedCard.style.borderColor = '#4CAF50';
    selectedCard.querySelector('.fa-check-circle').style.display = 'block';
    
    // Load time slots if date is selected
    const dateInput = document.getElementById('date');
    if (dateInput.value) {
        loadTimeSlots(dateInput.value, roomId);
    }
}

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

// Initialize room selection if pre-selected
<?php if ($selectedRoomId): ?>
selectRoom(<?= $selectedRoomId ?>);
<?php endif; ?>
</script>