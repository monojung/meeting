<?php
checkLogin();
if (!isAdmin()) {
    header('Location: /user/dashboard');
    exit;
}

$error = '';
$success = '';

// Handle user actions
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $username = sanitize($_POST['username']);
        $password = $_POST['password'];
        $role = $_POST['role'];
        
        if (empty($username) || empty($password)) {
            $error = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน';
        } elseif (strlen($username) < 3) {
            $error = 'ชื่อผู้ใช้ต้องมีอย่างน้อย 3 ตัวอักษร';
        } elseif (strlen($password) < 6) {
            $error = 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร';
        } else {
            // Check if username exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM " . TABLE_USERS . " WHERE username = ?");
            $stmt->execute([$username]);
            
            if ($stmt->fetchColumn() > 0) {
                $error = 'ชื่อผู้ใช้นี้มีอยู่แล้ว';
            } else {
                $hashedPassword = hashPassword($password);
                $stmt = $pdo->prepare("INSERT INTO " . TABLE_USERS . " (username, password, role) VALUES (?, ?, ?)");
                
                if ($stmt->execute([$username, $hashedPassword, $role])) {
                    $success = 'เพิ่มผู้ใช้งานสำเร็จ';
                } else {
                    $error = 'เกิดข้อผิดพลาดในการเพิ่มผู้ใช้งาน';
                }
            }
        }
    } elseif ($action === 'edit') {
        $user_id = (int)$_POST['user_id'];
        $username = sanitize($_POST['username']);
        $role = $_POST['role'];
        $new_password = $_POST['new_password'];
        
        if (empty($username)) {
            $error = 'กรุณากรอกชื่อผู้ใช้';
        } elseif (strlen($username) < 3) {
            $error = 'ชื่อผู้ใช้ต้องมีอย่างน้อย 3 ตัวอักษร';
        } else {
            // Check if username exists for other users
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM " . TABLE_USERS . " WHERE username = ? AND id != ?");
            $stmt->execute([$username, $user_id]);
            
            if ($stmt->fetchColumn() > 0) {
                $error = 'ชื่อผู้ใช้นี้มีอยู่แล้ว';
            } else {
                if (!empty($new_password)) {
                    if (strlen($new_password) < 6) {
                        $error = 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร';
                    } else {
                        $hashedPassword = hashPassword($new_password);
                        $stmt = $pdo->prepare("UPDATE " . TABLE_USERS . " SET username = ?, password = ?, role = ? WHERE id = ?");
                        $result = $stmt->execute([$username, $hashedPassword, $role, $user_id]);
                    }
                } else {
                    $stmt = $pdo->prepare("UPDATE " . TABLE_USERS . " SET username = ?, role = ? WHERE id = ?");
                    $result = $stmt->execute([$username, $role, $user_id]);
                }
                
                if (!$error && $result) {
                    $success = 'แก้ไขผู้ใช้งานสำเร็จ';
                } elseif (!$error) {
                    $error = 'เกิดข้อผิดพลาดในการแก้ไข';
                }
            }
        }
    } elseif ($action === 'delete') {
        $user_id = (int)$_POST['user_id'];
        
        // Check if user is current admin
        if ($user_id == $_SESSION['user_id']) {
            $error = 'ไม่สามารถลบบัญชีของตนเองได้';
        } else {
            // Check if user has bookings
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM " . TABLE_BOOKINGS . " WHERE user_id = ? AND date >= CURDATE()");
            $stmt->execute([$user_id]);
            $futureBookings = $stmt->fetchColumn();
            
            if ($futureBookings > 0) {
                $error = 'ไม่สามารถลบผู้ใช้ที่มีการจองในอนาคตได้';
            } else {
                $stmt = $pdo->prepare("DELETE FROM " . TABLE_USERS . " WHERE id = ?");
                if ($stmt->execute([$user_id])) {
                    $success = 'ลบผู้ใช้งานสำเร็จ';
                } else {
                    $error = 'เกิดข้อผิดพลาดในการลบผู้ใช้งาน';
                }
            }
        }
    }
}

// Get all users with their booking statistics
$stmt = $pdo->prepare("
    SELECT u.*, 
           COUNT(b.id) as total_bookings,
           COUNT(CASE WHEN b.date >= CURDATE() THEN 1 END) as upcoming_bookings,
           MAX(b.date) as last_booking_date
    FROM " . TABLE_USERS . " u 
    LEFT JOIN " . TABLE_BOOKINGS . " b ON u.id = b.user_id 
    GROUP BY u.id 
    ORDER BY u.role DESC, u.username
");
$stmt->execute();
$users = $stmt->fetchAll();

// Get user statistics
$stmt = $pdo->prepare("SELECT COUNT(*) FROM " . TABLE_USERS);
$stmt->execute();
$totalUsers = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM " . TABLE_USERS . " WHERE role = 'admin'");
$stmt->execute();
$adminUsers = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM " . TABLE_USERS . " WHERE role = 'user'");
$stmt->execute();
$regularUsers = $stmt->fetchColumn();

// Active users (users with bookings in last 30 days)
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT user_id) 
    FROM " . TABLE_BOOKINGS . " 
    WHERE date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
");
$stmt->execute();
$activeUsers = $stmt->fetchColumn();
?>

<div class="glass-card">
    <div class="card-header">
        <i class="fas fa-users"></i>
        จัดการผู้ใช้งาน
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
            <div class="stat-number"><?= $totalUsers ?></div>
            <div class="stat-label">
                <i class="fas fa-users"></i>
                ผู้ใช้ทั้งหมด
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $adminUsers ?></div>
            <div class="stat-label">
                <i class="fas fa-user-shield"></i>
                ผู้ดูแลระบบ
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $regularUsers ?></div>
            <div class="stat-label">
                <i class="fas fa-user"></i>
                ผู้ใช้ทั่วไป
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $activeUsers ?></div>
            <div class="stat-label">
                <i class="fas fa-user-check"></i>
                ใช้งานล่าสุด (30 วัน)
            </div>
        </div>
    </div>
    
    <!-- Add User Form -->
    <div class="card" style="margin-bottom: 2rem;">
        <div class="card-header">
            <i class="fas fa-user-plus"></i>
            เพิ่มผู้ใช้งานใหม่
        </div>
        
        <form method="post" id="addUserForm">
            <input type="hidden" name="action" value="add">
            <div style="display: grid; grid-template-columns: 2fr 2fr 1fr auto; gap: 1rem; align-items: end;">
                <div class="form-group">
                    <label for="username">ชื่อผู้ใช้</label>
                    <input type="text" id="username" name="username" class="form-control" 
                           placeholder="ชื่อผู้ใช้งาน" minlength="3" maxlength="50" required>
                </div>
                
                <div class="form-group">
                    <label for="password">รหัสผ่าน</label>
                    <input type="password" id="password" name="password" class="form-control" 
                           placeholder="รหัสผ่าน" minlength="6" required>
                </div>
                
                <div class="form-group">
                    <label for="role">ประเภทผู้ใช้</label>
                    <select id="role" name="role" class="form-control" required>
                        <option value="user">ผู้ใช้ทั่วไป</option>
                        <option value="admin">ผู้ดูแลระบบ</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-plus"></i> เพิ่ม
                </button>
            </div>
        </form>
    </div>
    
    <!-- Users List -->
    <div class="card">
        <div class="card-header">
            <i class="fas fa-list"></i>
            รายการผู้ใช้งาน (<?= count($users) ?> คน)
        </div>
        
        <?php if ($users): ?>
            <div class="table-container">
                <table class="table" id="usersTable">
                    <thead>
                        <tr>
                            <th>ชื่อผู้ใช้</th>
                            <th>ประเภท</th>
                            <th>การจองทั้งหมด</th>
                            <th>การจองที่จะมาถึง</th>
                            <th>ใช้งานล่าสุด</th>
                            <th>การดำเนินการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                        <i class="fas fa-star" style="color: #ffd700;" title="คุณ"></i>
                                    <?php endif; ?>
                                    <div>
                                        <div style="font-weight: 600; color: white;">
                                            <?= htmlspecialchars($user['username']) ?>
                                        </div>
                                        <div style="font-size: 0.75rem; opacity: 0.6;">
                                            ID: <?= $user['id'] ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php
                                $roleClass = $user['role'] === 'admin' ? 'btn-warning' : 'btn-success';
                                $roleText = $user['role'] === 'admin' ? 'ผู้ดูแลระบบ' : 'ผู้ใช้ทั่วไป';
                                $roleIcon = $user['role'] === 'admin' ? 'fa-user-shield' : 'fa-user';
                                ?>
                                <span class="btn <?= $roleClass ?>" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">
                                    <i class="fas <?= $roleIcon ?>"></i> <?= $roleText ?>
                                </span>
                            </td>
                            <td>
                                <span style="color: white; font-weight: 600;">
                                    <?= $user['total_bookings'] ?>
                                </span>
                            </td>
                            <td>
                                <span style="color: <?= $user['upcoming_bookings'] > 0 ? '#FF9800' : 'rgba(255,255,255,0.6)' ?>; font-weight: 600;">
                                    <?= $user['upcoming_bookings'] ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($user['last_booking_date']): ?>
                                    <span style="color: rgba(255,255,255,0.8); font-size: 0.875rem;">
                                        <?= formatThaiDate($user['last_booking_date']) ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: rgba(255,255,255,0.5); font-size: 0.875rem;">
                                        ยังไม่เคยใช้งาน
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                    <button onclick="editUser(<?= htmlspecialchars(json_encode($user)) ?>)" 
                                            class="btn" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">
                                        <i class="fas fa-edit"></i> แก้ไข
                                    </button>
                                    <button onclick="viewUserBookings(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')" 
                                            class="btn btn-warning" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">
                                        <i class="fas fa-calendar-alt"></i> ดูการจอง
                                    </button>
                                    <?php if ($user['id'] != $_SESSION['user_id'] && $user['upcoming_bookings'] == 0): ?>
                                        <form method="post" style="display: inline;" 
                                              onsubmit="return confirmDelete('คุณแน่ใจหรือไม่ที่จะลบผู้ใช้ <?= htmlspecialchars($user['username']) ?>?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                            <button type="submit" class="btn btn-danger" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">
                                                <i class="fas fa-trash"></i> ลบ
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="btn" style="padding: 0.25rem 0.5rem; font-size: 0.75rem; opacity: 0.5; cursor: not-allowed;">
                                            <i class="fas fa-ban"></i> 
                                            <?= $user['id'] == $_SESSION['user_id'] ? 'ตนเอง' : 'ไม่สามารถลบได้' ?>
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
                <button onclick="exportToCSV('usersTable', 'users_list.csv')" class="btn">
                    <i class="fas fa-download"></i> ส่งออก CSV
                </button>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 3rem;">
                <i class="fas fa-users" style="font-size: 4rem; color: rgba(255,255,255,0.3); margin-bottom: 1rem;"></i>
                <h3 style="color: white; margin-bottom: 1rem;">ไม่มีผู้ใช้งาน</h3>
                <p style="color: rgba(255,255,255,0.8); margin-bottom: 2rem;">
                    เริ่มต้นโดยการเพิ่มผู้ใช้งานแรก
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Edit User Modal -->
<div id="editUserModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000; justify-content: center; align-items: center;">
    <div class="glass-card" style="max-width: 500px; width: 90%;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <span><i class="fas fa-user-edit"></i> แก้ไขผู้ใช้งาน</span>
            <button onclick="closeEditModal()" style="background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form method="post" id="editUserForm">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="user_id" id="edit_user_id">
            
            <div class="form-group">
                <label for="edit_username">ชื่อผู้ใช้</label>
                <input type="text" id="edit_username" name="username" class="form-control" 
                       minlength="3" maxlength="50" required>
            </div>
            
            <div class="form-group">
                <label for="edit_role">ประเภทผู้ใช้</label>
                <select id="edit_role" name="role" class="form-control" required>
                    <option value="user">ผู้ใช้ทั่วไป</option>
                    <option value="admin">ผู้ดูแลระบบ</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="edit_new_password">รหัสผ่านใหม่ (ไม่บังคับ)</label>
                <input type="password" id="edit_new_password" name="new_password" class="form-control" 
                       placeholder="ปล่อยว่างหากไม่ต้องการเปลี่ยน" minlength="6">
                <small style="color: rgba(255,255,255,0.7); font-size: 0.875rem; margin-top: 0.25rem; display: block;">
                    หากต้องการเปลี่ยนรหัสผ่าน กรุณากรอกรหัสผ่านใหม่
                </small>
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

<!-- User Bookings Modal -->
<div id="userBookingsModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000; justify-content: center; align-items: center;">
    <div class="glass-card" style="max-width: 800px; width: 90%; max-height: 80vh; overflow-y: auto;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <span id="userBookingsTitle"><i class="fas fa-calendar-alt"></i> การจองของผู้ใช้</span>
            <button onclick="closeBookingsModal()" style="background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div id="userBookingsContent">
            <!-- Content will be loaded here -->
        </div>
    </div>
</div>

<script>
function editUser(user) {
    document.getElementById('edit_user_id').value = user.id;
    document.getElementById('edit_username').value = user.username;
    document.getElementById('edit_role').value = user.role;
    document.getElementById('edit_new_password').value = '';
    document.getElementById('editUserModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editUserModal').style.display = 'none';
}

function viewUserBookings(userId, username) {
    document.getElementById('userBookingsTitle').innerHTML = 
        `<i class="fas fa-calendar-alt"></i> การจองของ: ${username}`;
    
    // Show loading
    document.getElementById('userBookingsContent').innerHTML = 
        '<div style="text-align: center; padding: 2rem;"><i class="fas fa-spinner fa-spin" style="font-size: 2rem;"></i><br>กำลังโหลด...</div>';
    
    document.getElementById('userBookingsModal').style.display = 'flex';
    
    // Fetch user bookings
    fetch(`/api/get_user_bookings.php?user_id=${userId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayUserBookings(data.bookings);
            } else {
                document.getElementById('userBookingsContent').innerHTML = 
                    '<div style="text-align: center; padding: 2rem; color: #f44336;">เกิดข้อผิดพลาดในการโหลดข้อมูล</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('userBookingsContent').innerHTML = 
                '<div style="text-align: center; padding: 2rem; color: #f44336;">เกิดข้อผิดพลาดในการเชื่อมต่อ</div>';
        });
}

function displayUserBookings(bookings) {
    if (bookings.length === 0) {
        document.getElementById('userBookingsContent').innerHTML = 
            '<div style="text-align: center; padding: 2rem;"><i class="fas fa-calendar-times" style="font-size: 2rem; margin-bottom: 1rem;"></i><br>ผู้ใช้คนนี้ยังไม่มีการจอง</div>';
        return;
    }
    
    let html = '<div class="table-container"><table class="table"><thead><tr><th>ห้องประชุม</th><th>วันที่</th><th>เวลา</th><th>วัตถุประสงค์</th><th>สถานะ</th></tr></thead><tbody>';
    
    bookings.forEach(booking => {
        const bookingDate = new Date(booking.date + ' ' + booking.time_start);
        const now = new Date();
        let status = bookingDate > now ? 'รอการประชุม' : 'เสร็จสิ้น';
        let statusClass = bookingDate > now ? 'btn-warning' : 'btn-success';
        
        html += `
            <tr>
                <td>${booking.room_name}</td>
                <td>${formatThaiDate(booking.date)}</td>
                <td>${booking.time_start.substring(0,5)} - ${booking.time_end.substring(0,5)}</td>
                <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;">${booking.purpose}</td>
                <td><span class="btn ${statusClass}" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">${status}</span></td>
            </tr>
        `;
    });
    
    html += '</tbody></table></div>';
    document.getElementById('userBookingsContent').innerHTML = html;
}

function closeBookingsModal() {
    document.getElementById('userBookingsModal').style.display = 'none';
}

// Close modals when clicking outside
document.getElementById('editUserModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});

document.getElementById('userBookingsModal').addEventListener('click', function(e) {
    if (e.target === this) closeBookingsModal();
});

// Form validation
document.getElementById('addUserForm').addEventListener('submit', function(e) {
    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value;
    
    if (username.length < 3) {
        e.preventDefault();
        alert('ชื่อผู้ใช้ต้องมีอย่างน้อย 3 ตัวอักษร');
        return false;
    }
    
    if (password.length < 6) {
        e.preventDefault();
        alert('รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร');
        return false;
    }
});

document.getElementById('editUserForm').addEventListener('submit', function(e) {
    const username = document.getElementById('edit_username').value.trim();
    const newPassword = document.getElementById('edit_new_password').value;
    
    if (username.length < 3) {
        e.preventDefault();
        alert('ชื่อผู้ใช้ต้องมีอย่างน้อย 3 ตัวอักษร');
        return false;
    }
    
    if (newPassword && newPassword.length < 6) {
        e.preventDefault();
        alert('รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร');
        return false;
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
</script>