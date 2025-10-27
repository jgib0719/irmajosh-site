<?php
/**
 * Calendar View
 * 
 * @var array $user - Current user
 */

ob_start();
?>

<!-- FullCalendar CSS -->
<link rel="stylesheet" href="/assets/css/vendor/fullcalendar.min.css?v=<?= getAssetVersion() ?>">

<div class="calendar-page">
    <div class="page-header">
        <h1 class="page-title"><?= t('calendar') ?></h1>
        <div class="page-actions">
            <button class="btn btn-primary" id="addEventBtn">
                <span>+</span> Add Event
            </button>
        </div>
    </div>
    
    <div class="calendar-container">
        <div id="calendar"></div>
    </div>
    
    <!-- Add Event Modal -->
    <div class="modal" id="addEventModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Add Event</h2>
                <button class="modal-close" data-close-modal="addEventModal">&times;</button>
            </div>
            <form id="addEventForm" class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                
                <div class="form-group">
                    <label for="eventTitle">Title *</label>
                    <input type="text" id="eventTitle" name="title" required class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="eventDescription">Description</label>
                    <textarea id="eventDescription" name="description" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="eventStart">Start *</label>
                        <input type="datetime-local" id="eventStart" name="start" required class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="eventEnd">End *</label>
                        <input type="datetime-local" id="eventEnd" name="end" required class="form-control">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="eventLocation">Location</label>
                    <input type="text" id="eventLocation" name="location" class="form-control">
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-close-modal="addEventModal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Event</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- FullCalendar JS -->
<script src="/assets/js/vendor/fullcalendar.min.js?v=<?= getAssetVersion() ?>"></script>

<script nonce="<?= cspNonce() ?>">
document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('calendar');
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        events: '/calendar/events',
        editable: true,
        selectable: true,
        selectMirror: true,
        dayMaxEvents: true,
        
        eventClick: function(info) {
            if (info.event.url) {
                window.open(info.event.url, '_blank');
                info.jsEvent.preventDefault();
            }
        },
        
        select: function(info) {
            document.getElementById('eventStart').value = info.startStr.slice(0, 16);
            document.getElementById('eventEnd').value = info.endStr.slice(0, 16);
            openModal('addEventModal');
        }
    });
    
    calendar.render();
    
    // Modal close button event listeners
    document.querySelectorAll('[data-close-modal]').forEach(btn => {
        btn.addEventListener('click', function() {
            closeModal(this.dataset.closeModal);
        });
    });
    
    // Add event button
    document.getElementById('addEventBtn').addEventListener('click', function() {
        const now = new Date();
        now.setMinutes(0);
        const oneHourLater = new Date(now.getTime() + 60 * 60 * 1000);
        
        document.getElementById('eventStart').value = now.toISOString().slice(0, 16);
        document.getElementById('eventEnd').value = oneHourLater.toISOString().slice(0, 16);
        openModal('addEventModal');
    });
    
    // Add event form submission
    document.getElementById('addEventForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const data = Object.fromEntries(formData);
        
        try {
            const response = await fetch('/calendar/events', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': data.csrf_token
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (response.ok) {
                closeModal('addEventModal');
                calendar.refetchEvents();
                showAlert('Event created successfully', 'success');
                this.reset();
            } else {
                showAlert(result.error || 'Failed to create event', 'error');
            }
        } catch (error) {
            showAlert('Failed to create event', 'error');
        }
    });
});

// Modal functions are now in modal.js (loaded globally)
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
?>
