</main>
    
    <footer style="background: rgba(255,255,255,0.1); backdrop-filter: blur(20px); margin-top: 3rem; padding: 2rem 0; border-top: 1px solid rgba(255,255,255,0.2);">
        <div style="max-width: 1200px; margin: 0 auto; text-align: center; color: rgba(255,255,255,0.8); padding: 0 1rem;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem; margin-bottom: 2rem;">
                <div>
                    <h4 style="color: white; margin-bottom: 1rem;">
                        <i class="fas fa-calendar-check"></i>
                        <?= APP_NAME ?>
                    </h4>
                    <p style="font-size: 0.875rem; line-height: 1.6;">
                        ระบบจองห้องประชุมที่ทันสมัย พร้อมการเชื่อมต่อ Google Sheets 
                        และรายงานแบบ Real-time
                    </p>
                </div>
                
                <div>
                    <h4 style="color: white; margin-bottom: 1rem;">
                        <i class="fas fa-link"></i>
                        ลิงก์ที่เป็นประโยชน์
                    </h4>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem; font-size: 0.875rem;">
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <?php if (isAdmin()): ?>
                                <a href="/admin/dashboard" style="color: rgba(255,255,255,0.8); text-decoration: none;">แดชบอร์ดแอดมิน</a>
                                <a href="/admin/reports" style="color: rgba(255,255,255,0.8); text-decoration: none;">รายงานระบบ</a>
                            <?php else: ?>
                                <a href="/user/book_room" style="color: rgba(255,255,255,0.8); text-decoration: none;">จองห้องประชุม</a>
                                <a href="/user/my_bookings" style="color: rgba(255,255,255,0.8); text-decoration: none;">รายการจองของฉัน</a>
                            <?php endif; ?>
                        <?php endif; ?>
                        <a href="mailto:support@company.com" style="color: rgba(255,255,255,0.8); text-decoration: none;">ติดต่อสนับสนุน</a>
                    </div>
                </div>
                
                <div>
                    <h4 style="color: white; margin-bottom: 1rem;">
                        <i class="fas fa-info-circle"></i>
                        ข้อมูลระบบ
                    </h4>
                    <div style="font-size: 0.875rem;">
                        <p style="margin-bottom: 0.5rem;">
                            <strong>เวอร์ชัน:</strong> 1.0.0
                        </p>
                        <p style="margin-bottom: 0.5rem;">
                            <strong>อัปเดตล่าสุด:</strong> <?= date('d/m/Y') ?>
                        </p>
                        <p style="margin-bottom: 0.5rem;">
                            <strong>Google Sheets:</strong> 
                            <span style="color: <?= file_exists(GOOGLE_SERVICE_ACCOUNT_FILE) ? '#4CAF50' : '#ff6b6b' ?>;">
                                <?= file_exists(GOOGLE_SERVICE_ACCOUNT_FILE) ? 'เชื่อมต่อแล้ว' : 'ไม่เชื่อมต่อ' ?>
                            </span>
                        </p>
                    </div>
                </div>
            </div>
            
            <div style="border-top: 1px solid rgba(255,255,255,0.2); padding-top: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <div style="font-size: 0.875rem;">
                    <p style="margin: 0;">
                        © <?= date('Y') ?> <?= APP_NAME ?>. สร้างด้วย ❤️ สำหรับองค์กร
                    </p>
                </div>
                
                <div style="display: flex; gap: 1rem; font-size: 0.875rem;">
                    <span>
                        <i class="fas fa-users"></i>
                        ผู้ใช้งาน: 
                        <?php
                        if (isset($pdo)) {
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM " . TABLE_USERS);
                            $stmt->execute();
                            echo $stmt->fetchColumn();
                        }
                        ?>
                    </span>
                    <span>
                        <i class="fas fa-calendar"></i>
                        การจองวันนี้: 
                        <?php
                        if (isset($pdo)) {
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM " . TABLE_BOOKINGS . " WHERE date = CURDATE()");
                            $stmt->execute();
                            echo $stmt->fetchColumn();
                        }
                        ?>
                    </span>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Back to Top Button -->
    <button id="backToTop" onclick="scrollToTop()" style="
        position: fixed;
        bottom: 2rem;
        right: 2rem;
        background: linear-gradient(45deg, #667eea, #764ba2);
        color: white;
        border: none;
        border-radius: 50%;
        width: 50px;
        height: 50px;
        font-size: 1.25rem;
        cursor: pointer;
        display: none;
        z-index: 999;
        box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        transition: all 0.3s ease;
    ">
        <i class="fas fa-chevron-up"></i>
    </button>
    
    <script>
    // Back to top functionality
    window.addEventListener('scroll', function() {
        const backToTop = document.getElementById('backToTop');
        if (window.pageYOffset > 300) {
            backToTop.style.display = 'block';
        } else {
            backToTop.style.display = 'none';
        }
    });
    
    function scrollToTop() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    }
    
    // Add hover effect to back to top button
    document.getElementById('backToTop').addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-3px)';
        this.style.boxShadow = '0 6px 20px rgba(0,0,0,0.4)';
    });
    
    document.getElementById('backToTop').addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0)';
        this.style.boxShadow = '0 4px 15px rgba(0,0,0,0.3)';
    });
    
    // Add smooth animations to elements when they come into view
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);
    
    // Observe all cards for animation
    document.querySelectorAll('.card, .glass-card').forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(card);
    });
    
    // Add keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Alt + H = Home/Dashboard
        if (e.altKey && e.key === 'h') {
            e.preventDefault();
            <?php if (isset($_SESSION['user_id'])): ?>
                window.location.href = '<?= isAdmin() ? '/admin/dashboard' : '/user/dashboard' ?>';
            <?php else: ?>
                window.location.href = '/auth/login';
            <?php endif; ?>
        }
        
        // Alt + B = Book Room (for users)
        <?php if (isset($_SESSION['user_id']) && !isAdmin()): ?>
        if (e.altKey && e.key === 'b') {
            e.preventDefault();
            window.location.href = '/user/book_room';
        }
        
        // Alt + M = My Bookings
        if (e.altKey && e.key === 'm') {
            e.preventDefault();
            window.location.href = '/user/my_bookings';
        }
        <?php endif; ?>
        
        // Alt + L = Logout
        <?php if (isset($_SESSION['user_id'])): ?>
        if (e.altKey && e.key === 'l') {
            e.preventDefault();
            if (confirm('คุณต้องการออกจากระบบหรือไม่?')) {
                window.location.href = '/auth/logout';
            }
        }
        <?php endif; ?>
        
        // Esc = Close modals
        if (e.key === 'Escape') {
            document.querySelectorAll('[id$="Modal"]').forEach(modal => {
                if (modal.style.display === 'flex') {
                    modal.style.display = 'none';
                }
            });
        }
    });
    
    // Show keyboard shortcuts help
    function showKeyboardShortcuts() {
        alert(`คีย์บอร์ดลัด:
        
Alt + H = หน้าหลัก
${<?= isset($_SESSION['user_id']) && !isAdmin() ? "'Alt + B = จองห้อง\\nAlt + M = รายการจองของฉัน\\n'" : "''" ?>}Alt + L = ออกจากระบบ
Esc = ปิด Modal

กด F1 เพื่อดูคีย์บอร์ดลัดอีกครั้ง`);
    }
    
    // F1 for help
    document.addEventListener('keydown', function(e) {
        if (e.key === 'F1') {
            e.preventDefault();
            showKeyboardShortcuts();
        }
    });
    
    // Add loading indicator for form submissions
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function() {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังประมวลผล...';
                submitBtn.disabled = true;
                
                // Re-enable after 5 seconds as fallback
                setTimeout(() => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }, 5000);
            }
        });
    });
    </script>
</body>
</html>