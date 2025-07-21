// Main JavaScript for Meeting Room Booking System
console.log('Meeting Room Booking System Loaded');

// Global variables
let selectedTimeSlots = [];
let currentDate = new Date();

// DOM Ready
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
});

function initializeApp() {
    // Initialize time slot selection
    initializeTimeSlots();
    
    // Initialize calendar
    initializeCalendar();
    
    // Initialize form validation
    initializeFormValidation();
    
    // Initialize alerts auto-hide
    initializeAlerts();
    
    // Initialize navigation
    initializeNavigation();
}

// Time Slots Management
function initializeTimeSlots() {
    const timeSlots = document.querySelectorAll('.time-slot');
    
    timeSlots.forEach(slot => {
        if (!slot.classList.contains('booked')) {
            slot.addEventListener('click', function() {
                toggleTimeSlot(this);
            });
        }
    });
}

function toggleTimeSlot(slot) {
    const timeValue = slot.getAttribute('data-time');
    
    if (slot.classList.contains('selected')) {
        slot.classList.remove('selected');
        removeFromSelectedSlots(timeValue);
    } else {
        slot.classList.add('selected');
        addToSelectedSlots(timeValue);
    }
    
    updateTimeSelection();
}

function addToSelectedSlots(timeValue) {
    if (!selectedTimeSlots.includes(timeValue)) {
        selectedTimeSlots.push(timeValue);
        selectedTimeSlots.sort();
    }
}

function removeFromSelectedSlots(timeValue) {
    const index = selectedTimeSlots.indexOf(timeValue);
    if (index > -1) {
        selectedTimeSlots.splice(index, 1);
    }
}

function updateTimeSelection() {
    const startTimeInput = document.getElementById('time_start');
    const endTimeInput = document.getElementById('time_end');
    
    if (startTimeInput && endTimeInput && selectedTimeSlots.length > 0) {
        startTimeInput.value = selectedTimeSlots[0];
        
        // Calculate end time (1 hour after last selected slot)
        const lastSlot = selectedTimeSlots[selectedTimeSlots.length - 1];
        const [hours, minutes] = lastSlot.split(':').map(Number);
        const endTime = `${(hours + 1).toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}`;
        endTimeInput.value = endTime;
    }
}

// Calendar Management
function initializeCalendar() {
    const calendarContainer = document.getElementById('calendar');
    if (calendarContainer) {
        renderCalendar(currentDate);
    }
}

function renderCalendar(date) {
    const calendarContainer = document.getElementById('calendar');
    const year = date.getFullYear();
    const month = date.getMonth();
    
    // Create calendar header
    const header = document.createElement('div');
    header.className = 'calendar-header';
    header.innerHTML = `
        <button onclick="previousMonth()" class="btn">&lt;</button>
        <h3>${getThaiMonthName(month)} ${year + 543}</h3>
        <button onclick="nextMonth()" class="btn">&gt;</button>
    `;
    
    // Create calendar grid
    const grid = document.createElement('div');
    grid.className = 'calendar-grid';
    
    // Add day headers
    const dayHeaders = ['อา', 'จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส'];
    dayHeaders.forEach(day => {
        const dayHeader = document.createElement('div');
        dayHeader.textContent = day;
        dayHeader.style.fontWeight = 'bold';
        dayHeader.style.textAlign = 'center';
        dayHeader.style.padding = '0.5rem';
        grid.appendChild(dayHeader);
    });
    
    // Calculate first day of month and number of days
    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    
    // Add empty cells for days before month starts
    for (let i = 0; i < firstDay; i++) {
        const emptyDay = document.createElement('div');
        emptyDay.className = 'calendar-day';
        grid.appendChild(emptyDay);
    }
    
    // Add days of the month
    for (let day = 1; day <= daysInMonth; day++) {
        const dayElement = document.createElement('div');
        dayElement.className = 'calendar-day';
        dayElement.textContent = day;
        dayElement.onclick = () => selectDate(year, month, day);
        
        // Highlight today
        const today = new Date();
        if (year === today.getFullYear() && month === today.getMonth() && day === today.getDate()) {
            dayElement.style.background = 'rgba(255, 215, 0, 0.5)';
        }
        
        grid.appendChild(dayElement);
    }
    
    // Clear and update calendar
    calendarContainer.innerHTML = '';
    calendarContainer.appendChild(header);
    calendarContainer.appendChild(grid);
}

function previousMonth() {
    currentDate.setMonth(currentDate.getMonth() - 1);
    renderCalendar(currentDate);
}

function nextMonth() {
    currentDate.setMonth(currentDate.getMonth() + 1);
    renderCalendar(currentDate);
}

function selectDate(year, month, day) {
    const dateInput = document.getElementById('date');
    if (dateInput) {
        const selectedDate = `${year}-${(month + 1).toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`;
        dateInput.value = selectedDate;
        
        // Load available time slots for selected date
        loadTimeSlots(selectedDate);
    }
}

function getThaiMonthName(month) {
    const months = [
        'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
        'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'
    ];
    return months[month];
}

// Form Validation
function initializeFormValidation() {
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateForm(this)) {
                e.preventDefault();
            }
        });
    });
}

function validateForm(form) {
    let isValid = true;
    const requiredFields = form.querySelectorAll('[required]');
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            showFieldError(field, 'กรุณากรอกข้อมูลให้ครบถ้วน');
            isValid = false;
        } else {
            clearFieldError(field);
        }
    });
    
    // Validate booking form specifically
    if (form.id === 'bookingForm') {
        isValid = validateBookingForm(form) && isValid;
    }
    
    return isValid;
}

function validateBookingForm(form) {
    let isValid = true;
    
    const dateInput = form.querySelector('[name="date"]');
    const timeStart = form.querySelector('[name="time_start"]');
    const timeEnd = form.querySelector('[name="time_end"]');
    
    // Check if date is not in the past
    if (dateInput && dateInput.value) {
        const selectedDate = new Date(dateInput.value);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        if (selectedDate < today) {
            showFieldError(dateInput, 'ไม่สามารถจองย้อนหลังได้');
            isValid = false;
        }
    }
    
    // Check if end time is after start time
    if (timeStart && timeEnd && timeStart.value && timeEnd.value) {
        if (timeStart.value >= timeEnd.value) {
            showFieldError(timeEnd, 'เวลาสิ้นสุดต้องมากกว่าเวลาเริ่มต้น');
            isValid = false;
        }
    }
    
    return isValid;
}

function showFieldError(field, message) {
    clearFieldError(field);
    
    const errorDiv = document.createElement('div');
    errorDiv.className = 'field-error';
    errorDiv.style.color = '#f44336';
    errorDiv.style.fontSize = '0.875rem';
    errorDiv.style.marginTop = '0.25rem';
    errorDiv.textContent = message;
    
    field.parentNode.appendChild(errorDiv);
    field.style.borderColor = '#f44336';
}

function clearFieldError(field) {
    const existingError = field.parentNode.querySelector('.field-error');
    if (existingError) {
        existingError.remove();
    }
    field.style.borderColor = '';
}

// Alerts Management
function initializeAlerts() {
    const alerts = document.querySelectorAll('.alert');
    
    alerts.forEach(alert => {
        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            fadeOut(alert);
        }, 5000);
        
        // Add close button
        const closeBtn = document.createElement('span');
        closeBtn.innerHTML = '&times;';
        closeBtn.style.float = 'right';
        closeBtn.style.cursor = 'pointer';
        closeBtn.style.fontSize = '1.2rem';
        closeBtn.onclick = () => fadeOut(alert);
        
        alert.insertBefore(closeBtn, alert.firstChild);
    });
}

function fadeOut(element) {
    element.style.transition = 'opacity 0.5s ease';
    element.style.opacity = '0';
    setTimeout(() => {
        if (element.parentNode) {
            element.parentNode.removeChild(element);
        }
    }, 500);
}

// Navigation
function initializeNavigation() {
    // Mobile menu toggle
    const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
    const navLinks = document.querySelector('.nav-links');
    
    if (mobileMenuBtn && navLinks) {
        mobileMenuBtn.addEventListener('click', () => {
            navLinks.classList.toggle('active');
        });
    }
    
    // Active page highlighting
    highlightActivePage();
}

function highlightActivePage() {
    const currentPath = window.location.pathname;
    const navLinks = document.querySelectorAll('.nav-link');
    
    navLinks.forEach(link => {
        if (link.getAttribute('href') === currentPath) {
            link.style.background = 'rgba(255, 255, 255, 0.3)';
        }
    });
}

// AJAX Functions
function loadTimeSlots(date, roomId = null) {
    const roomSelect = document.getElementById('room_id');
    const selectedRoomId = roomId || (roomSelect ? roomSelect.value : null);
    
    if (!selectedRoomId || !date) return;
    
    fetch(`/api/get_time_slots.php?date=${date}&room_id=${selectedRoomId}`)
        .then(response => response.json())
        .then(data => {
            updateTimeSlotsDisplay(data);
        })
        .catch(error => {
            console.error('Error loading time slots:', error);
        });
}

function updateTimeSlotsDisplay(timeSlots) {
    const timeSlotsContainer = document.getElementById('timeSlots');
    if (!timeSlotsContainer) return;
    
    timeSlotsContainer.innerHTML = '';
    
    Object.entries(timeSlots).forEach(([time, status]) => {
        const slotElement = document.createElement('div');
        slotElement.className = `time-slot ${status}`;
        slotElement.setAttribute('data-time', time);
        slotElement.textContent = time;
        
        if (status !== 'booked') {
            slotElement.addEventListener('click', function() {
                toggleTimeSlot(this);
            });
        }
        
        timeSlotsContainer.appendChild(slotElement);
    });
}

// Google Sheets Integration
function syncToGoogleSheets(bookingData) {
    fetch('/api/sync_google_sheets.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(bookingData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Data synced to Google Sheets successfully');
        } else {
            console.error('Failed to sync to Google Sheets:', data.error);
        }
    })
    .catch(error => {
        console.error('Error syncing to Google Sheets:', error);
    });
}

// Utility Functions
function formatThaiDate(date) {
    const options = { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric',
        locale: 'th-TH',
        calendar: 'buddhist'
    };
    return new Date(date).toLocaleDateString('th-TH', options);
}

function formatTime(time) {
    return time.substring(0, 5); // Remove seconds if present
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Room availability checker
function checkRoomAvailability() {
    const dateInput = document.getElementById('date');
    const roomSelect = document.getElementById('room_id');
    
    if (dateInput && roomSelect) {
        const debouncedCheck = debounce(() => {
            if (dateInput.value && roomSelect.value) {
                loadTimeSlots(dateInput.value, roomSelect.value);
            }
        }, 300);
        
        dateInput.addEventListener('change', debouncedCheck);
        roomSelect.addEventListener('change', debouncedCheck);
    }
}

// Initialize room availability checker when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    checkRoomAvailability();
});

// Confirmation dialogs
function confirmDelete(message = 'คุณแน่ใจหรือไม่ที่จะลบข้อมูลนี้?') {
    return confirm(message);
}

// Print functionality
function printReport() {
    window.print();
}

// Export functionality
function exportToCSV(tableId, filename = 'report.csv') {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    let csv = '';
    const rows = table.querySelectorAll('tr');
    
    rows.forEach(row => {
        const cols = row.querySelectorAll('td, th');
        const rowData = Array.from(cols).map(col => `"${col.textContent.trim()}"`).join(',');
        csv += rowData + '\n';
    });
    
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename;
    link.click();
}