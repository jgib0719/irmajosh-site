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
                
                <div class="form-group">
                    <label for="eventDate">Date *</label>
                    <input type="datetime-local" id="eventDate" name="start" required class="form-control">
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="multiDayEvent" name="multi_day">
                        Multi-day event
                    </label>
                </div>
                
                <div class="form-group" id="endDateGroup" style="display: none;">
                    <label for="eventEnd">End Date *</label>
                    <input type="datetime-local" id="eventEnd" name="end" class="form-control">
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-close-modal="addEventModal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Event</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- View Event Modal -->
    <div class="modal" id="viewEventModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Event Details</h2>
                <button class="modal-close" data-close-modal="viewEventModal">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="viewEventId">
                
                <div class="form-group">
                    <label><strong>Title</strong></label>
                    <p id="viewEventTitle"></p>
                </div>
                
                <div class="form-group">
                    <label><strong>Description</strong></label>
                    <p id="viewEventDescription"></p>
                </div>
                
                <div class="form-group">
                    <label><strong>Start</strong></label>
                    <p id="viewEventStart"></p>
                </div>
                
                <div class="form-group">
                    <label><strong>End</strong></label>
                    <p id="viewEventEnd"></p>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" id="deleteEventBtn">Delete</button>
                    <button type="button" class="btn btn-secondary" data-close-modal="viewEventModal">Close</button>
                    <button type="button" class="btn btn-primary" id="editEventBtn">Edit</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit Event Modal -->
    <div class="modal" id="editEventModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Edit Event</h2>
                <button class="modal-close" data-close-modal="editEventModal">&times;</button>
            </div>
            <form id="editEventForm" class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" id="editEventId" name="event_id">
                
                <div class="form-group">
                    <label for="editEventTitle">Title *</label>
                    <input type="text" id="editEventTitle" name="title" required class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="editEventDescription">Description</label>
                    <textarea id="editEventDescription" name="description" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="editEventDate">Start *</label>
                    <input type="datetime-local" id="editEventDate" name="start" required class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="editEventEnd">End *</label>
                    <input type="datetime-local" id="editEventEnd" name="end" required class="form-control">
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-close-modal="editEventModal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
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
    
    <?php if (!empty($initialDate)): ?>
    const initialDate = '<?= htmlspecialchars($initialDate) ?>';
    <?php else: ?>
    const initialDate = null;
    <?php endif; ?>
    
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        initialDate: initialDate || undefined,
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
            info.jsEvent.preventDefault();
            showEventDetail(info.event);
        },
        
        select: function(info) {
            // Calculate duration in days
            const durationMs = info.end - info.start;
            const durationDays = durationMs / (1000 * 60 * 60 * 24);
            
            // Set start date with current hour (or noon if all-day)
            const now = new Date();
            const startDateTime = new Date(info.start);
            startDateTime.setHours(now.getHours());
            startDateTime.setMinutes(0);
            startDateTime.setSeconds(0);
            
            document.getElementById('eventDate').value = startDateTime.toISOString().slice(0, 16);
            
            // Check if truly multi-day (more than 1 day selected)
            if (durationDays > 1) {
                document.getElementById('multiDayEvent').checked = true;
                document.getElementById('endDateGroup').style.display = 'block';
                const endDateTime = new Date(info.end);
                endDateTime.setHours(now.getHours());
                endDateTime.setMinutes(0);
                document.getElementById('eventEnd').value = endDateTime.toISOString().slice(0, 16);
                document.getElementById('eventEnd').required = true;
            } else {
                // Single day event - default unchecked
                document.getElementById('multiDayEvent').checked = false;
                document.getElementById('endDateGroup').style.display = 'none';
                document.getElementById('eventEnd').value = '';
                document.getElementById('eventEnd').required = false;
            }
            
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
    
    // Multi-day checkbox handler
    document.getElementById('multiDayEvent').addEventListener('change', function() {
        const endDateGroup = document.getElementById('endDateGroup');
        const endDateInput = document.getElementById('eventEnd');
        
        if (this.checked) {
            endDateGroup.style.display = 'block';
            endDateInput.required = true;
            
            // Set end date to 1 day after start if not set
            const startDate = document.getElementById('eventDate').value;
            if (startDate && !endDateInput.value) {
                const start = new Date(startDate);
                start.setDate(start.getDate() + 1);
                endDateInput.value = start.toISOString().slice(0, 16);
            }
        } else {
            endDateGroup.style.display = 'none';
            endDateInput.required = false;
            endDateInput.value = '';
        }
    });
    
    // Add event button
    document.getElementById('addEventBtn').addEventListener('click', function() {
        const now = new Date();
        now.setMinutes(0);
        
        // Reset form
        document.getElementById('addEventForm').reset();
        document.getElementById('eventDate').value = now.toISOString().slice(0, 16);
        document.getElementById('multiDayEvent').checked = false;
        document.getElementById('endDateGroup').style.display = 'none';
        document.getElementById('eventEnd').value = '';
        document.getElementById('eventEnd').required = false;
        
        openModal('addEventModal');
    });
    
    // Add event form submission
    document.getElementById('addEventForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const data = Object.fromEntries(formData);
        
        // If not multi-day, set end time to 1 hour after start
        if (!data.multi_day || !data.end) {
            const startDate = new Date(data.start);
            startDate.setHours(startDate.getHours() + 1);
            data.end = startDate.toISOString().slice(0, 16);
        }
        
        // Remove multi_day flag before sending
        delete data.multi_day;
        
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
    
    // Show event detail modal
    function showEventDetail(event) {
        document.getElementById('viewEventId').value = event.id;
        document.getElementById('viewEventTitle').textContent = event.title;
        document.getElementById('viewEventDescription').textContent = event.extendedProps.description || 'No description';
        
        // Format dates nicely
        const startDate = new Date(event.start);
        const endDate = new Date(event.end);
        
        const dateOptions = { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric', 
            hour: '2-digit', 
            minute: '2-digit' 
        };
        
        document.getElementById('viewEventStart').textContent = startDate.toLocaleString('en-US', dateOptions);
        document.getElementById('viewEventEnd').textContent = endDate.toLocaleString('en-US', dateOptions);
        
        openModal('viewEventModal');
    }
    
    // Edit event button
    document.getElementById('editEventBtn').addEventListener('click', function() {
        const eventId = document.getElementById('viewEventId').value;
        const title = document.getElementById('viewEventTitle').textContent;
        const description = document.getElementById('viewEventDescription').textContent;
        
        // Get the current event from calendar
        const event = calendar.getEventById(eventId);
        
        if (event) {
            document.getElementById('editEventId').value = eventId;
            document.getElementById('editEventTitle').value = title;
            document.getElementById('editEventDescription').value = description === 'No description' ? '' : description;
            document.getElementById('editEventDate').value = new Date(event.start).toISOString().slice(0, 16);
            document.getElementById('editEventEnd').value = new Date(event.end).toISOString().slice(0, 16);
            
            closeModal('viewEventModal');
            openModal('editEventModal');
        }
    });
    
    // Edit event form submission
    document.getElementById('editEventForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const data = Object.fromEntries(formData);
        const eventId = data.event_id;
        
        delete data.event_id; // Don't send this in the body
        
        try {
            const response = await fetch(`/calendar/events/${eventId}`, {
                method: 'PUT',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': data.csrf_token
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (response.ok) {
                closeModal('editEventModal');
                calendar.refetchEvents();
                showAlert('Event updated successfully', 'success');
            } else {
                showAlert(result.error || 'Failed to update event', 'error');
            }
        } catch (error) {
            showAlert('Failed to update event', 'error');
        }
    });
    
    // Delete event button
    document.getElementById('deleteEventBtn').addEventListener('click', async function() {
        if (!confirm('Are you sure you want to delete this event?')) {
            return;
        }
        
        const eventId = document.getElementById('viewEventId').value;
        
        try {
            const response = await fetch(`/calendar/events/${eventId}`, {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
                }
            });
            
            const result = await response.json();
            
            if (response.ok) {
                closeModal('viewEventModal');
                calendar.refetchEvents();
                showAlert('Event deleted successfully', 'success');
            } else {
                showAlert(result.error || 'Failed to delete event', 'error');
            }
        } catch (error) {
            showAlert('Failed to delete event', 'error');
        }
    });
});

// Modal functions are now in modal.js (loaded globally)
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
?>
