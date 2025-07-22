<?php
checkLogin();
if (!isAdmin()) {
    header('Location: /user/dashboard');
    exit;
}

// Get report parameters
$report_type = $_GET['type'] ?? 'overview';
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Overview Statistics
$stmt = $pdo->prepare("SELECT COUNT(*) FROM " . TABLE_BOOKINGS . " WHERE date BETWEEN ? AND ?");
$stmt->execute([$start_date, $end_date]);
$totalBookings = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(DISTINCT user_id) FROM " . TABLE_BOOKINGS . " WHERE date BETWEEN ? AND ?");
$stmt->execute([$start_date, $end_date]);
$activeUsers = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(DISTINCT room_id) FROM " . TABLE_BOOKINGS . " WHERE date BETWEEN ? AND ?");
$stmt->execute([$start_date, $end_date]);
$usedRooms = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT AVG(TIMESTAMPDIFF(HOUR, time_start, time_end)) FROM " . TABLE_BOOKINGS . " WHERE date BETWEEN ? AND ?");
$stmt->execute([$start_date, $end_date]);
$avgDuration = round($stmt->fetchColumn(), 1);

// Room Usage Report
$stmt = $pdo->prepare("
    SELECT r.room_name, r.capacity,
           COUNT(b.id) as booking_count,
           ROUND(AVG(TIMESTAMPDIFF(HOUR, b.time_start, b.time_end)), 1) as avg_duration,
           SUM(TIMESTAMPDIFF(HOUR, b.time_start, b.time_end)) as total_hours
    FROM " . TABLE_ROOMS . " r 
    LEFT JOIN " . TABLE_BOOKINGS . " b ON r.id = b.room_id AND b.date BETWEEN ? AND ?
    GROUP BY r.id, r.room_name, r.capacity
    ORDER BY booking_count DESC
");
$stmt->execute([$start_date, $end_date]);
$roomUsage = $stmt->fetchAll();

// User Activity Report
$stmt = $pdo->prepare("
    SELECT u.username,
           COUNT(b.id) as booking_count,
           SUM(TIMESTAMPDIFF(HOUR, b.time_start, b.time_end)) as total_hours,
           MIN(b.date) as first_booking,
           MAX(b.date) as last_booking
    FROM " . TABLE_USERS . " u 
    LEFT JOIN " . TABLE_BOOKINGS . " b ON u.id = b.user_id AND b.date BETWEEN ? AND ?
    WHERE u.role = 'user'
    GROUP BY u.id, u.username
    HAVING booking_count > 0
    ORDER BY booking_count DESC
");
$stmt->execute([$start_date, $end_date]);
$userActivity = $stmt->fetchAll();

// Daily Bookings Trend
$stmt = $pdo->prepare("
    SELECT DATE(date) as booking_date, 
           COUNT(*) as booking_count,
           DAYNAME(date) as day_name
    FROM " . TABLE_BOOKINGS . " 
    WHERE date BETWEEN ? AND ?
    GROUP BY DATE(date)
    ORDER BY DATE(date)
");
$stmt->execute([$start_date, $end_date]);
$dailyTrend = $stmt->fetchAll();

// Monthly Summary (last 12 months)
$monthlyStats = [];
for ($i = 11; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as bookings,
               COUNT(DISTINCT user_id) as users,
               COUNT(DISTINCT room_id) as rooms
        FROM " . TABLE_BOOKINGS . " 
        WHERE DATE_FORMAT(date, '%Y-%m') = ?
    ");
    $stmt->execute([$month]);
    $result = $stmt->fetch();
    $monthlyStats[] = [
        'month' => $month,
        'name' => date('M Y', strtotime($month . '-01')),
        'thai_name' => formatThaiMonth($month),
        'bookings' => $result['bookings'],
        'users' => $result['users'],
        'rooms' => $result['rooms']
    ];
}

// Peak Hours Analysis
$stmt = $pdo->prepare("
    SELECT HOUR(time_start) as hour,
           COUNT(*) as booking_count
    FROM " . TABLE_BOOKINGS . " 
    WHERE date BETWEEN ? AND ?
    GROUP BY HOUR(time_start)
    ORDER BY HOUR(time_start)
");
$stmt->execute([$start_date, $end_date]);
$hourlyStats = $stmt->fetchAll();

// Most Popular Purposes
$stmt = $pdo->prepare("
    SELECT purpose,
           COUNT(*) as count
    FROM " . TABLE_BOOKINGS . " 
    WHERE date BETWEEN ? AND ?
    GROUP BY purpose
    ORDER BY COUNT(*) DESC
    LIMIT 10
");
$stmt->execute([$start_date, $end_date]);
$popularPurposes = $stmt->fetchAll();

function formatThaiMonth($month) {
    $months = [
        '01' => 'ม.ค.', '02' => 'ก.พ.', '03' => 'มี.ค.', '04' => 'เม.ย.',
        '05' => 'พ.ค.', '06' => 'มิ.ย.', '07' => 'ก.ค.', '08' => 'ส.ค.',
        '09' => 'ก.ย.', '10' => 'ต.ค.', '11' => 'พ.ย.', '12' => 'ธ.ค.'
    ];
    $parts = explode('-', $month);
    return $months[$parts[1]] . ' ' . ($parts[0] + 543);
}
?>

<div class="glass-card">
    <div class="card-header">
        <i class="fas fa-chart-bar"></i>
        รายงานและสถิติการใช้งาน
    </div>
    
    <!-- Report Controls -->
    <div class="card" style="margin-bottom: 2rem;">
        <div class="card-header">
            <i class="fas fa-sliders-h"></i>
            เลือกช่วงเวลาและประเภทรายงาน
        </div>
        
        <form method="get" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end;">
            <div class="form-group">
                <label for="start_date">วันที่เริ่มต้น</label>
                <input type="date" id="start_date" name="start_date" class="form-control" value="<?= $start_date ?>">
            </div>
            
            <div class="form-group">
                <label for="end_date">วันที่สิ้นสุด</label>
                <input type="date" id="end_date" name="end_date" class="form-control" value="<?= $end_date ?>">
            </div>
            
            <div class="form-group">
                <label for="type">ประเภทรายงาน</label>
                <select id="type" name="type" class="form-control">
                    <option value="overview" <?= $report_type === 'overview' ? 'selected' : '' ?>>ภาพรวม</option>
                    <option value="detailed" <?= $report_type === 'detailed' ? 'selected' : '' ?>>รายละเอียด</option>
                    <option value="trends" <?= $report_type === 'trends' ? 'selected' : '' ?>>แนวโน้ม</option>
                </select>
            </div>
            
            <div style="display: flex; gap: 0.5rem;">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-chart-line"></i> สร้างรายงาน
                </button>
                <button type="button" onclick="printReport()" class="btn">
                    <i class="fas fa-print"></i> พิมพ์
                </button>
            </div>
        </form>
    </div>
    
    <!-- Overview Statistics -->
    <div class="stats-grid" style="margin-bottom: 2rem;">
        <div class="stat-card">
            <div class="stat-number"><?= $totalBookings ?></div>
            <div class="stat-label">
                <i class="fas fa-calendar-check"></i>
                การจองทั้งหมด
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $activeUsers ?></div>
            <div class="stat-label">
                <i class="fas fa-users"></i>
                ผู้ใช้งานที่ใช้บริการ
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $usedRooms ?></div>
            <div class="stat-label">
                <i class="fas fa-door-open"></i>
                ห้องที่ถูกใช้งาน
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $avgDuration ?> ชม.</div>
            <div class="stat-label">
                <i class="fas fa-clock"></i>
                ระยะเวลาเฉลี่ย
            </div>
        </div>
    </div>
    
    <?php if ($report_type === 'overview' || $report_type === 'detailed'): ?>
    <!-- Charts Section -->
    <div class="card-grid" style="margin-bottom: 2rem;">
        <!-- Monthly Trend Chart -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-chart-line"></i>
                แนวโน้มการจอง 12 เดือนล่าสุด
            </div>
            <div style="height: 300px; display: flex; align-items: end; justify-content: space-between; padding: 1rem; background: rgba(255,255,255,0.05); border-radius: 10px; overflow-x: auto;">
                <?php 
                $maxBookings = max(array_column($monthlyStats, 'bookings'));
                $maxBookings = $maxBookings > 0 ? $maxBookings : 1;
                ?>
                <?php foreach ($monthlyStats as $stat): ?>
                    <div style="display: flex; flex-direction: column; align-items: center; margin: 0 0.5rem; min-width: 60px;">
                        <div style="background: linear-gradient(45deg, #667eea, #764ba2); width: 30px; height: <?= ($stat['bookings'] / $maxBookings) * 200 ?>px; border-radius: 4px 4px 0 0; min-height: 5px; position: relative; transition: all 0.3s ease;" 
                             title="<?= $stat['bookings'] ?> การจอง">
                            <span style="position: absolute; top: -25px; left: 50%; transform: translateX(-50%); color: #ffd700; font-size: 0.75rem; font-weight: 600; white-space: nowrap;">
                                <?= $stat['bookings'] ?>
                            </span>
                        </div>
                        <span style="margin-top: 0.5rem; font-size: 0.7rem; color: rgba(255,255,255,0.8); writing-mode: vertical-rl; text-orientation: mixed;">
                            <?= $stat['thai_name'] ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Peak Hours Chart -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-clock"></i>
                ช่วงเวลาที่มีการจองมากที่สุด
            </div>
            <div style="height: 300px; display: flex; align-items: end; justify-content: space-between; padding: 1rem; background: rgba(255,255,255,0.05); border-radius: 10px;">
                <?php 
                $maxHour = max(array_column($hourlyStats, 'booking_count'));
                $maxHour = $maxHour > 0 ? $maxHour : 1;
                
                // Create array for all hours (8-17)
                $hourlyData = [];
                for ($h = 8; $h <= 17; $h++) {
                    $hourlyData[$h] = 0;
                }
                foreach ($hourlyStats as $stat) {
                    if ($stat['hour'] >= 8 && $stat['hour'] <= 17) {
                        $hourlyData[$stat['hour']] = $stat['booking_count'];
                    }
                }
                ?>
                <?php foreach ($hourlyData as $hour => $count): ?>
                    <div style="display: flex; flex-direction: column; align-items: center; margin: 0 0.25rem;">
                        <div style="background: linear-gradient(45deg, #FF6B6B, #FF8E53); width: 25px; height: <?= ($count / $maxHour) * 200 ?>px; border-radius: 4px 4px 0 0; min-height: 5px; position: relative;">
                            <?php if ($count > 0): ?>
                                <span style="position: absolute; top: -20px; left: 50%; transform: translateX(-50%); color: #ffd700; font-size: 0.7rem; font-weight: 600;">
                                    <?= $count ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <span style="margin-top: 0.5rem; font-size: 0.7rem; color: rgba(255,255,255,0.8);">
                            <?= $hour ?>:00
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($report_type === 'detailed'): ?>
    <!-- Detailed Reports -->
    <div class="card-grid" style="margin-bottom: 2rem;">
        <!-- Room Usage Report -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-door-open"></i>
                รายงานการใช้งานห้องประชุม
            </div>
            <?php if ($roomUsage): ?>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ห้องประชุม</th>
                                <th>ความจุ</th>
                                <th>จำนวนการจอง</th>
                                <th>ชั่วโมงรวม</th>
                                <th>เฉลี่ย/ครั้ง</th>
                                <th>อัตราการใช้งาน</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $totalPossibleBookings = max(array_column($roomUsage, 'booking_count'));
                            foreach ($roomUsage as $room): 
                                $usageRate = $totalPossibleBookings > 0 ? ($room['booking_count'] / $totalPossibleBookings * 100) : 0;
                            ?>
                            <tr>
                                <td style="font-weight: 600;"><?= htmlspecialchars($room['room_name']) ?></td>
                                <td><?= $room['capacity'] ?> คน</td>
                                <td><span style="color: #ffd700; font-weight: 600;"><?= $room['booking_count'] ?></span></td>
                                <td><?= $room['total_hours'] ?: 0 ?> ชม.</td>
                                <td><?= $room['avg_duration'] ?: 0 ?> ชม.</td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <div style="background: rgba(255,255,255,0.2); height: 6px; width: 100px; border-radius: 3px; overflow: hidden;">
                                            <div style="background: #4CAF50; height: 100%; width: <?= $usageRate ?>%; border-radius: 3px;"></div>
                                        </div>
                                        <span style="color: white; font-size: 0.875rem; min-width: 35px;">
                                            <?= round($usageRate) ?>%
                                        </span>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p style="text-align: center; padding: 2rem; color: rgba(255,255,255,0.8);">
                    ไม่มีข้อมูลการใช้งานห้องในช่วงเวลาที่เลือก
                </p>
            <?php endif; ?>
        </div>
        
        <!-- User Activity Report -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-users"></i>
                รายงานการใช้งานของผู้ใช้
            </div>
            <?php if ($userActivity): ?>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ชื่อผู้ใช้</th>
                                <th>จำนวนการจอง</th>
                                <th>ชั่วโมงรวม</th>
                                <th>ใช้งานครั้งแรก</th>
                                <th>ใช้งานล่าสุด</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($userActivity as $user): ?>
                            <tr>
                                <td style="font-weight: 600;"><?= htmlspecialchars($user['username']) ?></td>
                                <td><span style="color: #ffd700; font-weight: 600;"><?= $user['booking_count'] ?></span></td>
                                <td><?= $user['total_hours'] ?> ชม.</td>
                                <td style="font-size: 0.875rem;"><?= formatThaiDate($user['first_booking']) ?></td>
                                <td style="font-size: 0.875rem;"><?= formatThaiDate($user['last_booking']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p style="text-align: center; padding: 2rem; color: rgba(255,255,255,0.8);">
                    ไม่มีผู้ใช้งานในช่วงเวลาที่เลือก
                </p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Popular Purposes -->
    <div class="card">
        <div class="card-header">
            <i class="fas fa-list"></i>
            วัตถุประสงค์การใช้งานที่ได้รับความนิยม
        </div>
        <?php if ($popularPurposes): ?>
            <div style="display: grid; gap: 1rem;">
                <?php foreach ($popularPurposes as $purpose): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; background: rgba(255,255,255,0.05); border-radius: 8px;">
                        <span style="color: white; flex: 1;"><?= htmlspecialchars($purpose['purpose']) ?></span>
                        <span style="color: #ffd700; font-weight: 600; min-width: 50px; text-align: right;">
                            <?= $purpose['count'] ?> ครั้ง
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p style="text-align: center; padding: 2rem; color: rgba(255,255,255,0.8);">
                ไม่มีข้อมูลในช่วงเวลาที่เลือก
            </p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <?php if ($report_type === 'trends'): ?>
    <!-- Trends Analysis -->
    <div class="card">
        <div class="card-header">
            <i class="fas fa-chart-area"></i>
            การวิเคราะห์แนวโน้ม - รายวัน
        </div>
        <?php if ($dailyTrend): ?>
            <div style="height: 400px; display: flex; align-items: end; justify-content: space-between; padding: 1rem; background: rgba(255,255,255,0.05); border-radius: 10px; overflow-x: auto;">
                <?php 
                $maxDaily = max(array_column($dailyTrend, 'booking_count'));
                $maxDaily = $maxDaily > 0 ? $maxDaily : 1;
                ?>
                <?php foreach ($dailyTrend as $day): ?>
                    <div style="display: flex; flex-direction: column; align-items: center; margin: 0 2px; min-width: 30px;">
                        <div style="background: linear-gradient(45deg, #36D1DC, #5B86E5); width: 20px; height: <?= ($day['booking_count'] / $maxDaily) * 300 ?>px; border-radius: 4px 4px 0 0; min-height: 5px; position: relative;">
                            <span style="position: absolute; top: -20px; left: 50%; transform: translateX(-50%); color: #ffd700; font-size: 0.7rem; font-weight: 600; white-space: nowrap;">
                                <?= $day['booking_count'] ?>
                            </span>
                        </div>
                        <span style="margin-top: 0.5rem; font-size: 0.6rem; color: rgba(255,255,255,0.8); writing-mode: vertical-rl; text-orientation: mixed;">
                            <?= date('d/m', strtotime($day['booking_date'])) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Trend Summary -->
            <div style="margin-top: 1rem; padding: 1rem; background: rgba(255,255,255,0.1); border-radius: 10px;">
                <h4 style="color: #ffd700; margin-bottom: 1rem;">สรุปแนวโน้ม</h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <div>
                        <strong style="color: white;">วันที่มีการจองมากที่สุด:</strong><br>
                        <span style="color: rgba(255,255,255,0.8);">
                            <?php 
                            $maxDayData = array_reduce($dailyTrend, function($max, $day) {
                                return (!$max || $day['booking_count'] > $max['booking_count']) ? $day : $max;
                            });
                            echo formatThaiDate($maxDayData['booking_date']) . ' (' . $maxDayData['booking_count'] . ' การจอง)';
                            ?>
                        </span>
                    </div>
                    <div>
                        <strong style="color: white;">เฉลี่ยต่อวัน:</strong><br>
                        <span style="color: rgba(255,255,255,0.8);">
                            <?= round(array_sum(array_column($dailyTrend, 'booking_count')) / count($dailyTrend), 1) ?> การจอง
                        </span>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <p style="text-align: center; padding: 2rem; color: rgba(255,255,255,0.8);">
                ไม่มีข้อมูลในช่วงเวลาที่เลือก
            </p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <!-- Export Options -->
    <div style="display: flex; gap: 1rem; margin-top: 2rem; justify-content: center; flex-wrap: wrap;">
        <button onclick="exportReportCSV()" class="btn btn-success">
            <i class="fas fa-file-csv"></i> ส่งออก CSV
        </button>
        <button onclick="printReport()" class="btn">
            <i class="fas fa-print"></i> พิมพ์รายงาน
        </button>
        <button onclick="emailReport()" class="btn btn-warning">
            <i class="fas fa-envelope"></i> ส่งทางอีเมล
        </button>
    </div>
</div>

<script>
function exportReportCSV() {
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    const reportType = document.getElementById('type').value;
    
    window.open(`/api/export_report.php?start_date=${startDate}&end_date=${endDate}&type=${reportType}&format=csv`);
}

function emailReport() {
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    const reportType = document.getElementById('type').value;
    
    if (confirm('ส่งรายงานทางอีเมลไปยัง admin@company.com หรือไม่?')) {
        fetch('/api/email_report.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                start_date: startDate,
                end_date: endDate,
                type: reportType
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('ส่งรายงานทางอีเมลสำเร็จ');
            } else {
                alert('เกิดข้อผิดพลาด: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('เกิดข้อผิดพลาดในการส่งอีเมล');
        });
    }
}

// Auto-submit form when date changes
document.getElementById('start_date').addEventListener('change', function() {
    if (this.value && document.getElementById('end_date').value) {
        this.form.submit();
    }
});

document.getElementById('end_date').addEventListener('change', function() {
    if (this.value && document.getElementById('start_date').value) {
        this.form.submit();
    }
});

document.getElementById('type').addEventListener('change', function() {
    this.form.submit();
});
</script>

<style>
@media print {
    .btn, .card-header, nav, form {
        display: none !important;
    }
    
    .glass-card {
        background: white !important;
        color: black !important;
        box-shadow: none !important;
        border: 1px solid #ddd !important;
    }
    
    .stat-card {
        background: #f8f9fa !important;
        color: black !important;
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
    
    .stat-number {
        color: #333 !important;
    }
}
</style>