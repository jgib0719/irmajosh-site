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
        <div class="search-container" style="position: relative; margin-right: 1rem; flex-grow: 1; max-width: 300px;">
            <input type="text" id="eventSearch" class="form-control" placeholder="Search events & tasks..." style="width: 100%;">
            <div id="searchResults" class="search-results" style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: var(--color-bg-secondary); border: 1px solid var(--border-color); border-radius: 0.5rem; z-index: 1000; max-height: 300px; overflow-y: auto; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);"></div>
        </div>
        <div class="page-actions">
            <button class="btn btn-secondary" id="syncCalendarBtn">
                <span>↻</span> Sync
            </button>
            <button class="btn btn-primary" id="addEventBtn">
                <span>+</span> Add Event
            </button>
        </div>
    </div>
    
    <div class="calendar-container">
        <div id="calendar"></div>
    </div>
    
    <!-- Sync Calendar Modal -->
    <div class="modal" id="syncCalendarModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Sync Calendar</h2>
                <button class="modal-close" data-close-modal="syncCalendarModal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Subscribe to your calendar in Outlook, Google Calendar, or Apple Calendar using this URL:</p>
                
                <div class="form-group">
                    <label for="icalUrl">iCal Feed URL</label>
                    <div style="display: flex; gap: 0.5rem;">
                        <input type="text" id="icalUrl" class="form-control" readonly value="<?= env('APP_URL') ?>/calendar/feed/<?= $user['ical_token'] ?? '' ?>">
                        <button class="btn btn-secondary" id="copyIcalUrl">Copy</button>
                    </div>
                    <small class="text-muted">Keep this URL private. It grants read-only access to your calendar.</small>
                </div>
                
                <div class="form-group" style="margin-top: 1.5rem; border-top: 1px solid var(--border-color); padding-top: 1rem;">
                    <label>Security</label>
                    <p class="text-sm">If you suspect your feed URL has been compromised, you can generate a new one. This will break existing subscriptions.</p>
                    <button class="btn btn-danger btn-sm" id="refreshFeedTokenBtn">Reset Feed URL</button>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-close-modal="syncCalendarModal">Close</button>
                </div>
            </div>
        </div>
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
                    <label>Color</label>
                    <div class="color-swatches">
                        <label class="color-swatch">
                            <input type="radio" name="color" value="#3b82f6" checked>
                            <span style="background-color: #3b82f6;"></span>
                        </label>
                        <label class="color-swatch">
                            <input type="radio" name="color" value="#10b981">
                            <span style="background-color: #10b981;"></span>
                        </label>
                        <label class="color-swatch">
                            <input type="radio" name="color" value="#ef4444">
                            <span style="background-color: #ef4444;"></span>
                        </label>
                        <label class="color-swatch">
                            <input type="radio" name="color" value="#f59e0b">
                            <span style="background-color: #f59e0b;"></span>
                        </label>
                        <label class="color-swatch">
                            <input type="radio" name="color" value="#8b5cf6">
                            <span style="background-color: #8b5cf6;"></span>
                        </label>
                        <label class="color-swatch">
                            <input type="radio" name="color" value="#ec4899">
                            <span style="background-color: #ec4899;"></span>
                        </label>
                        <label class="color-swatch">
                            <input type="radio" name="color" value="#6b7280">
                            <span style="background-color: #6b7280;"></span>
                        </label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="isAllDay" name="is_all_day" value="1">
                        <span>All Day Event</span>
                    </label>
                </div>

                <div class="form-group" id="timeInputs">
                    <label for="eventDate">Start *</label>
                    <input type="datetime-local" id="eventDate" name="start" required class="form-control" step="900">
                    
                    <label for="eventEnd" style="margin-top: 1rem; display: block;">End *</label>
                    <input type="datetime-local" id="eventEnd" name="end" required class="form-control" step="900">
                </div>

                <div class="form-group" id="dateInputs" style="display: none;">
                    <label for="eventDateDay">Start Date *</label>
                    <input type="date" id="eventDateDay" name="start_date" class="form-control">
                    
                    <label for="eventEndDay" style="margin-top: 1rem; display: block;">End Date *</label>
                    <input type="date" id="eventEndDay" name="end_date" class="form-control">
                </div>
                
                <div class="form-group">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="checkbox" id="recurringEvent" name="is_recurring" style="width: 1.2rem; height: 1.2rem; cursor: pointer;">
                        <span style="user-select: none;">Recurring event</span>
                    </div>
                </div>
                
                <div id="recurrenceOptions" style="display: none;">
                    <div class="form-group">
                        <label for="recurrenceType">Repeat</label>
                        <select id="recurrenceType" name="recurrence_type" class="form-control">
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                            <option value="yearly">Yearly</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="recurrenceInterval">Every</label>
                        <div style="display: flex; gap: 0.5rem; align-items: center;">
                            <input type="number" id="recurrenceInterval" name="recurrence_interval" min="1" value="1" class="form-control" style="width: 100px;">
                            <span id="intervalLabel">day(s)</span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="recurrenceEnd">Ends on (optional)</label>
                        <input type="date" id="recurrenceEnd" name="recurrence_end" class="form-control">
                    </div>
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
                <input type="hidden" id="viewEventDateValue">
                <input type="hidden" id="viewIsRecurring">
                
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
                
                <div class="form-group" id="viewRecurrenceInfo" style="display: none;">
                    <label><strong>Recurrence</strong></label>
                    <p id="viewRecurrenceText"></p>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" id="deleteEventBtn">Delete</button>
                    <button type="button" class="btn btn-secondary" data-close-modal="viewEventModal">Close</button>
                    <button type="button" class="btn btn-primary" id="editEventBtn">Edit</button>
                    <a href="#" class="btn btn-primary" id="viewTaskBtn" style="display: none;">View Task</a>
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
                <input type="hidden" id="editIsRecurring" name="is_recurring_event">
                
                <div class="form-group">
                    <label for="editEventTitle">Title *</label>
                    <input type="text" id="editEventTitle" name="title" required class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="editEventDescription">Description</label>
                    <textarea id="editEventDescription" name="description" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label>Color</label>
                    <div class="color-swatches">
                        <label class="color-swatch">
                            <input type="radio" name="color" value="#3b82f6" checked>
                            <span style="background-color: #3b82f6;"></span>
                        </label>
                        <label class="color-swatch">
                            <input type="radio" name="color" value="#10b981">
                            <span style="background-color: #10b981;"></span>
                        </label>
                        <label class="color-swatch">
                            <input type="radio" name="color" value="#ef4444">
                            <span style="background-color: #ef4444;"></span>
                        </label>
                        <label class="color-swatch">
                            <input type="radio" name="color" value="#f59e0b">
                            <span style="background-color: #f59e0b;"></span>
                        </label>
                        <label class="color-swatch">
                            <input type="radio" name="color" value="#8b5cf6">
                            <span style="background-color: #8b5cf6;"></span>
                        </label>
                        <label class="color-swatch">
                            <input type="radio" name="color" value="#ec4899">
                            <span style="background-color: #ec4899;"></span>
                        </label>
                        <label class="color-swatch">
                            <input type="radio" name="color" value="#6b7280">
                            <span style="background-color: #6b7280;"></span>
                        </label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="editEventDate">Start *</label>
                    <input type="datetime-local" id="editEventDate" name="start" required class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="editEventEnd">End *</label>
                    <input type="datetime-local" id="editEventEnd" name="end" required class="form-control">
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="editRecurringEvent" disabled>
                        Recurring event
                    </label>
                </div>
                
                <div id="editRecurrenceOptions" style="display: none;">
                    <div class="form-group">
                        <label for="editRecurrenceType">Repeat</label>
                        <select id="editRecurrenceType" name="recurrence_type" class="form-control" disabled>
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                            <option value="yearly">Yearly</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="editRecurrenceInterval">Every</label>
                        <div style="display: flex; gap: 0.5rem; align-items: center;">
                            <input type="number" id="editRecurrenceInterval" name="recurrence_interval" min="1" value="1" class="form-control" style="width: 100px;" disabled>
                            <span id="editIntervalLabel">day(s)</span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="editRecurrenceEnd">Ends on (optional)</label>
                        <input type="date" id="editRecurrenceEnd" name="recurrence_end" class="form-control" disabled>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-close-modal="editEventModal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Recurring Event Edit Confirmation Modal -->
    <div class="modal" id="recurringEditModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Edit Recurring Event</h2>
                <button class="modal-close" data-close-modal="recurringEditModal">&times;</button>
            </div>
            <div class="modal-body">
                <p>This is a recurring event. What would you like to do?</p>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" id="editThisOccurrence">Edit only this occurrence</button>
                    <button type="button" class="btn btn-primary" id="editAllOccurrences">Edit all occurrences</button>
                    <button type="button" class="btn btn-secondary" data-close-modal="recurringEditModal">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Recurring Event Delete Confirmation Modal -->
    <div class="modal" id="recurringDeleteModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Delete Recurring Event</h2>
                <button class="modal-close" data-close-modal="recurringDeleteModal">&times;</button>
            </div>
            <div class="modal-body">
                <p>This is a recurring event. What would you like to do?</p>
                
                <div class="modal-footer" style="flex-direction: column; gap: 0.5rem; align-items: stretch;">
                    <button type="button" class="btn btn-danger" id="deleteThisOccurrence">Delete this occurrence only</button>
                    <button type="button" class="btn btn-danger" id="deleteFutureOccurrences">Delete this and all future occurrences</button>
                    <button type="button" class="btn btn-danger" id="deleteAllOccurrences">Delete all occurrences</button>
                    <button type="button" class="btn btn-secondary" data-close-modal="recurringDeleteModal">Cancel</button>
                </div>
            </div>
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
    
    // Helper function to format date for datetime-local input (preserves local time)
    function formatDateTimeLocal(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        return `${year}-${month}-${day}T${hours}:${minutes}`;
    }
    
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        initialDate: initialDate || undefined,
        height: 'auto',
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
        
        // Add class to past days
        dayCellClassNames: function(arg) {
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const cellDate = new Date(arg.date);
            cellDate.setHours(0, 0, 0, 0);
            
            if (cellDate < today) {
                return ['fc-day-past'];
            }
            return [];
        },
        
        dateClick: function(info) {
            if (window.innerWidth <= 768) {
                // On mobile, a single click on a day should open the add event modal.
                const startDateTime = new Date(info.date);
                startDateTime.setHours(7, 0, 0, 0);

                // Set end time to 1 hour after start
                const endDateTime = new Date(startDateTime);
                endDateTime.setHours(endDateTime.getHours() + 1);

                // Reset form and set dates
                document.getElementById('addEventForm').reset();
                document.getElementById('eventDate').value = formatDateTimeLocal(startDateTime);
                document.getElementById('eventEnd').value = formatDateTimeLocal(endDateTime);
                
                // Set date inputs for all day
                document.getElementById('eventDateDay').value = startDateTime.toISOString().slice(0, 10);
                document.getElementById('eventEndDay').value = startDateTime.toISOString().slice(0, 10);
                
                document.getElementById('recurringEvent').checked = false;
                document.getElementById('recurrenceOptions').style.display = 'none';
                document.getElementById('isAllDay').checked = false;
                toggleAllDay(false);
                
                // Reset color
                document.querySelector('input[name="color"][value="#3b82f6"]').checked = true;

                openModal('addEventModal');
            }
        },

        eventClick: function(info) {
            info.jsEvent.preventDefault();
            showEventDetail(info.event);
        },
        
        select: function(info) {
            // Reset form first
            document.getElementById('addEventForm').reset();
            
            let startDateTime, endDateTime;
            
            if (info.allDay) {
                // All day selection (e.g. Month view) -> Default to 7am-8am
                startDateTime = new Date(info.start);
                startDateTime.setHours(7, 0, 0, 0);
                
                endDateTime = new Date(startDateTime);
                endDateTime.setHours(endDateTime.getHours() + 1);
                
                // For date inputs (all day mode)
                document.getElementById('eventDateDay').value = info.startStr;
                
                // Calculate inclusive end date for date input
                const endDateInclusive = new Date(info.end);
                endDateInclusive.setDate(endDateInclusive.getDate() - 1);
                document.getElementById('eventEndDay').value = endDateInclusive.toISOString().slice(0, 10);
                
            } else {
                // Time selection (e.g. Week/Day view) -> Use selected times
                startDateTime = info.start;
                endDateTime = info.end;
                
                document.getElementById('eventDateDay').value = info.startStr.slice(0, 10);
                document.getElementById('eventEndDay').value = info.endStr.slice(0, 10);
            }
            
            // Set time inputs
            document.getElementById('eventDate').value = formatDateTimeLocal(startDateTime);
            document.getElementById('eventEnd').value = formatDateTimeLocal(endDateTime);
            
            // Reset UI state
            document.getElementById('recurringEvent').checked = false;
            document.getElementById('recurrenceOptions').style.display = 'none';
            document.getElementById('isAllDay').checked = false;
            toggleAllDay(false);
            document.querySelector('input[name="color"][value="#3b82f6"]').checked = true;
            
            openModal('addEventModal');
        }
    });
    
    calendar.render();
    
    // Check for URL params to pre-fill add event modal (e.g. from Date Night page)
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('action') === 'add') {
        const title = urlParams.get('title');
        const description = urlParams.get('description');
        
        if (title) {
            // Default to tonight at 7 PM
            const now = new Date();
            now.setHours(19, 0, 0, 0);
            const endTime = new Date(now);
            endTime.setHours(22, 0, 0, 0);
            
            document.getElementById('addEventForm').reset();
            document.getElementById('eventTitle').value = title;
            if (description) document.getElementById('eventDescription').value = description;
            
            document.getElementById('eventDate').value = formatDateTimeLocal(now);
            document.getElementById('eventEnd').value = formatDateTimeLocal(endTime);
            
            document.getElementById('eventDateDay').value = now.toISOString().slice(0, 10);
            document.getElementById('eventEndDay').value = now.toISOString().slice(0, 10);
            
            // Select Pink color for Date Night
            const pinkSwatch = document.querySelector('input[name="color"][value="#ec4899"]');
            if (pinkSwatch) pinkSwatch.checked = true;
            
            openModal('addEventModal');
            
            // Clean URL
            window.history.replaceState({}, document.title, window.location.pathname);
        }
    }
    
    // Modal close button event listeners
    document.querySelectorAll('[data-close-modal]').forEach(btn => {
        btn.addEventListener('click', function() {
            closeModal(this.dataset.closeModal);
        });
    });
    
    // Recurring event checkbox handler
    document.getElementById('recurringEvent').addEventListener('change', function() {
        const recurrenceOptions = document.getElementById('recurrenceOptions');
        
        if (this.checked) {
            recurrenceOptions.style.display = 'block';
        } else {
            recurrenceOptions.style.display = 'none';
        }
    });
    
    // Update interval label based on recurrence type
    document.getElementById('recurrenceType').addEventListener('change', function() {
        const intervalLabel = document.getElementById('intervalLabel');
        const labels = {
            'daily': 'day(s)',
            'weekly': 'week(s)',
            'monthly': 'month(s)',
            'yearly': 'year(s)'
        };
        intervalLabel.textContent = labels[this.value] || 'day(s)';
    });
    
        // Add event button
    document.getElementById('addEventBtn').addEventListener('click', function() {
        const now = new Date();
        // Set to 7:00 AM today
        now.setHours(7, 0, 0, 0);
        
        // Set end time to 1 hour after start (8:00 AM)
        const endTime = new Date(now);
        endTime.setHours(endTime.getHours() + 1);
        
        // Reset form
        document.getElementById('addEventForm').reset();
        document.getElementById('eventDate').value = formatDateTimeLocal(now);
        document.getElementById('eventEnd').value = formatDateTimeLocal(endTime);
        
        // Set date inputs for all day
        document.getElementById('eventDateDay').value = now.toISOString().slice(0, 10);
        document.getElementById('eventEndDay').value = now.toISOString().slice(0, 10);
        
        // Reset UI state
        document.getElementById('recurringEvent').checked = false;
        document.getElementById('recurrenceOptions').style.display = 'none';
        document.getElementById('recurrenceInterval').value = '1';
        document.getElementById('isAllDay').checked = false;
        toggleAllDay(false);
        
        // Reset color to default
        document.querySelector('input[name="color"][value="#3b82f6"]').checked = true;
        
        openModal('addEventModal');
    });

    // All Day Toggle Logic
    const isAllDayCheckbox = document.getElementById('isAllDay');
    const timeInputs = document.getElementById('timeInputs');
    const dateInputs = document.getElementById('dateInputs');
    const eventDateInput = document.getElementById('eventDate');
    const eventEndInput = document.getElementById('eventEnd');
    const eventDateDayInput = document.getElementById('eventDateDay');
    const eventEndDayInput = document.getElementById('eventEndDay');

    function toggleAllDay(isAllDay) {
        if (isAllDay) {
            timeInputs.style.display = 'none';
            dateInputs.style.display = 'block';
            eventDateInput.removeAttribute('required');
            eventEndInput.removeAttribute('required');
            eventDateDayInput.setAttribute('required', '');
            eventEndDayInput.setAttribute('required', '');
        } else {
            timeInputs.style.display = 'block';
            dateInputs.style.display = 'none';
            eventDateInput.setAttribute('required', '');
            eventEndInput.setAttribute('required', '');
            eventDateDayInput.removeAttribute('required');
            eventEndDayInput.removeAttribute('required');
        }
    }

    isAllDayCheckbox.addEventListener('change', function() {
        toggleAllDay(this.checked);
        
        // Sync values when toggling
        if (this.checked) {
            // Switching to All Day: take date part from time inputs
            if (eventDateInput.value) eventDateDayInput.value = eventDateInput.value.slice(0, 10);
            if (eventEndInput.value) eventEndDayInput.value = eventEndInput.value.slice(0, 10);
        } else {
            // Switching to Time: take date from date inputs and add default times (7am/8am)
            if (eventDateDayInput.value) {
                eventDateInput.value = eventDateDayInput.value + 'T07:00';
            }
            if (eventEndDayInput.value) {
                eventEndInput.value = eventEndDayInput.value + 'T08:00';
            }
        }
    });

    // Sync date inputs when time inputs change
    eventDateInput.addEventListener('change', function() {
        if (this.value) eventDateDayInput.value = this.value.slice(0, 10);
    });
    eventEndInput.addEventListener('change', function() {
        if (this.value) eventEndDayInput.value = this.value.slice(0, 10);
    });
    
    // Sync time inputs when date inputs change (preserve time if possible, else default)
    eventDateDayInput.addEventListener('change', function() {
        if (this.value) {
            const timePart = eventDateInput.value ? eventDateInput.value.slice(11) : '07:00';
            eventDateInput.value = this.value + 'T' + timePart;
        }
    });
    eventEndDayInput.addEventListener('change', function() {
        if (this.value) {
            const timePart = eventEndInput.value ? eventEndInput.value.slice(11) : '08:00';
            eventEndInput.value = this.value + 'T' + timePart;
        }
    });

    // Sync Calendar Button
    document.getElementById('syncCalendarBtn').addEventListener('click', function() {
        openModal('syncCalendarModal');
    });

    // Copy URL Button
    document.getElementById('copyIcalUrl').addEventListener('click', function() {
        const urlInput = document.getElementById('icalUrl');
        urlInput.select();
        document.execCommand('copy'); // Fallback for older browsers
        
        // Try modern API
        if (navigator.clipboard) {
            navigator.clipboard.writeText(urlInput.value).then(() => {
                const originalText = this.textContent;
                this.textContent = 'Copied!';
                setTimeout(() => {
                    this.textContent = originalText;
                }, 2000);
            });
        } else {
             const originalText = this.textContent;
             this.textContent = 'Copied!';
             setTimeout(() => {
                 this.textContent = originalText;
             }, 2000);
        }
    });


    
    // Show event detail modal
    function showEventDetail(event) {
        const isTask = event.extendedProps.type === 'task';
        const isAllDay = event.allDay || event.extendedProps.is_all_day;
        
        document.getElementById('viewEventId').value = event.id;
        document.getElementById('viewEventDateValue').value = event.startStr; // Store ISO date string
        document.getElementById('viewEventTitle').textContent = event.title;
        document.getElementById('viewEventDescription').textContent = event.extendedProps.description || 'No description';
        
        // Format dates nicely
        const startDate = new Date(event.start);
        const endDate = event.end ? new Date(event.end) : null;
        
        const dateOptions = { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric', 
            hour: '2-digit', 
            minute: '2-digit' 
        };
        
        if (isAllDay) {
            // Hide start/end times for all-day events
            document.getElementById('viewEventStart').parentElement.style.display = 'none';
            document.getElementById('viewEventEnd').parentElement.style.display = 'none';
        } else {
            // Show start/end times for regular events
            document.getElementById('viewEventStart').parentElement.style.display = 'block';
            document.getElementById('viewEventStart').textContent = startDate.toLocaleString('en-US', dateOptions);
            
            if (endDate) {
                document.getElementById('viewEventEnd').textContent = endDate.toLocaleString('en-US', dateOptions);
                document.getElementById('viewEventEnd').parentElement.style.display = 'block';
            } else {
                document.getElementById('viewEventEnd').parentElement.style.display = 'none';
            }
        }
        
        // Show recurrence info if applicable
        const recurrenceInfo = document.getElementById('viewRecurrenceInfo');
        const recurrenceText = document.getElementById('viewRecurrenceText');
        
        if (event.extendedProps.recurrence_type) {
            document.getElementById('viewIsRecurring').value = 'true';
            const type = event.extendedProps.recurrence_type;
            const interval = event.extendedProps.recurrence_interval || 1;
            const endDate = event.extendedProps.recurrence_end;
            
            let text = `Repeats every ${interval > 1 ? interval + ' ' : ''}${type}`;
            if (endDate) {
                const endDateObj = new Date(endDate);
                text += ` until ${endDateObj.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}`;
            }
            
            recurrenceText.textContent = text;
            recurrenceInfo.style.display = 'block';
        } else {
            document.getElementById('viewIsRecurring').value = 'false';
            recurrenceInfo.style.display = 'none';
        }

        // Task specific UI adjustments
        const modalTitle = document.querySelector('#viewEventModal .modal-title');
        const deleteBtn = document.getElementById('deleteEventBtn');
        const editBtn = document.getElementById('editEventBtn');
        const viewTaskBtn = document.getElementById('viewTaskBtn');

        if (isTask) {
            modalTitle.textContent = 'Task Details';
            deleteBtn.style.display = 'none';
            editBtn.style.display = 'none';
            viewTaskBtn.style.display = 'inline-block';
            
            if (event.extendedProps.is_shared) {
                viewTaskBtn.href = '/tasks/shared';
            } else {
                viewTaskBtn.href = '/tasks/private';
            }
        } else {
            modalTitle.textContent = 'Event Details';
            deleteBtn.style.display = 'inline-block';
            editBtn.style.display = 'inline-block';
            viewTaskBtn.style.display = 'none';
        }
        
        openModal('viewEventModal');
    }
    
    // Add event form submission
    document.getElementById('addEventForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const data = Object.fromEntries(formData);
        
        // Handle checkbox values
        data.is_all_day = document.getElementById('isAllDay').checked ? 1 : 0;
        data.is_recurring = document.getElementById('recurringEvent').checked ? 1 : 0;
        
        try {
            const response = await fetch('/calendar/events', {
                method: 'POST',
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

    // Refresh Feed Token
    const refreshFeedTokenBtn = document.getElementById('refreshFeedTokenBtn');
    if (refreshFeedTokenBtn) {
        refreshFeedTokenBtn.addEventListener('click', async function() {
            if (!confirm('Are you sure? This will break all existing calendar subscriptions.')) {
                return;
            }
            
            try {
                const response = await fetch('/calendar/feed/token', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                
                const result = await response.json();
                
                if (response.ok) {
                    document.getElementById('icalUrl').value = result.url;
                    showAlert('Feed URL reset successfully', 'success');
                } else {
                    showAlert(result.error || 'Failed to reset feed URL', 'error');
                }
            } catch (error) {
                showAlert('Failed to reset feed URL', 'error');
            }
        });
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
            
            // Use the actual event start/end times from the event object
            // Parse the date strings to create proper Date objects in local time
            const startDate = new Date(event.start);
            const endDate = event.end ? new Date(event.end) : new Date(startDate.getTime() + 60 * 60 * 1000);
            
            // Format to local datetime-local format (preserves local time zone)
            document.getElementById('editEventDate').value = formatDateTimeLocal(startDate);
            document.getElementById('editEventEnd').value = formatDateTimeLocal(endDate);
            
            // Set color
            const eventColor = event.extendedProps.color || event.backgroundColor || '#3b82f6';
            // Try to find the matching swatch in the edit form
            const colorInput = document.querySelector(`#editEventForm input[name="color"][value="${eventColor}"]`);
            if (colorInput) {
                colorInput.checked = true;
            } else {
                // If color doesn't match any swatch (legacy event), default to blue
                document.querySelector('#editEventForm input[name="color"][value="#3b82f6"]').checked = true;
            }
            
            // Populate recurrence fields
            const recurrenceType = event.extendedProps.recurrence_type;
            const recurrenceInterval = event.extendedProps.recurrence_interval;
            const recurrenceEnd = event.extendedProps.recurrence_end;
            
            if (recurrenceType) {
                // Store that this is a recurring event
                document.getElementById('editIsRecurring').value = 'true';
                document.getElementById('editRecurringEvent').checked = true;
                document.getElementById('editRecurrenceOptions').style.display = 'block';
                document.getElementById('editRecurrenceType').value = recurrenceType;
                document.getElementById('editRecurrenceInterval').value = recurrenceInterval || 1;
                
                // Update interval label
                const labels = {
                    'daily': 'day(s)',
                    'weekly': 'week(s)',
                    'monthly': 'month(s)',
                    'yearly': 'year(s)'
                };
                document.getElementById('editIntervalLabel').textContent = labels[recurrenceType] || 'day(s)';
                
                if (recurrenceEnd) {
                    document.getElementById('editRecurrenceEnd').value = recurrenceEnd;
                } else {
                    document.getElementById('editRecurrenceEnd').value = '';
                }
            } else {
                document.getElementById('editIsRecurring').value = 'false';
                document.getElementById('editRecurringEvent').checked = false;
                document.getElementById('editRecurrenceOptions').style.display = 'none';
                document.getElementById('editRecurrenceEnd').value = '';
            }
            
            closeModal('viewEventModal');
            openModal('editEventModal');
        }
    });
    
    // Edit event form submission
    document.getElementById('editEventForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const isRecurring = document.getElementById('editIsRecurring').value === 'true';
        
        // If it's a recurring event, show confirmation modal
        if (isRecurring) {
            openModal('recurringEditModal');
            return;
        }
        
        // Non-recurring event - proceed with update
        await submitEventEdit(false);
    });
    
    // Handle recurring event edit choices
    document.getElementById('editThisOccurrence').addEventListener('click', async function() {
        closeModal('recurringEditModal');
        await submitEventEdit(false); // Edit only this occurrence
    });
    
    document.getElementById('editAllOccurrences').addEventListener('click', async function() {
        closeModal('recurringEditModal');
        await submitEventEdit(true); // Edit all occurrences
    });
    
    // Function to submit event edit
    async function submitEventEdit(updateAll) {
        const formData = new FormData(document.getElementById('editEventForm'));
        const data = Object.fromEntries(formData);
        const eventId = data.event_id;
        const isRecurring = data.is_recurring_event === 'true';
        
        delete data.event_id;
        delete data.is_recurring_event;
        
        // Add flag for whether to update all occurrences
        if (isRecurring) {
            data.update_all = updateAll;
        }
        
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
    }
    
    // Delete event button
    document.getElementById('deleteEventBtn').addEventListener('click', async function() {
        const isRecurring = document.getElementById('viewIsRecurring').value === 'true';
        
        if (isRecurring) {
            openModal('recurringDeleteModal');
            return;
        }
        
        if (!confirm('Are you sure you want to delete this event?')) {
            return;
        }
        
        await submitEventDelete('all');
    });
    
    // Handle recurring event delete choices
    document.getElementById('deleteThisOccurrence').addEventListener('click', async function() {
        closeModal('recurringDeleteModal');
        await submitEventDelete('single');
    });
    
    document.getElementById('deleteFutureOccurrences').addEventListener('click', async function() {
        closeModal('recurringDeleteModal');
        await submitEventDelete('future');
    });
    
    document.getElementById('deleteAllOccurrences').addEventListener('click', async function() {
        closeModal('recurringDeleteModal');
        await submitEventDelete('all');
    });
    
    async function submitEventDelete(mode) {
        const eventId = document.getElementById('viewEventId').value;
        const date = document.getElementById('viewEventDateValue').value;
        
        try {
            // Use query params for delete mode and date
            const params = new URLSearchParams({
                mode: mode,
                date: date
            });
            
            const response = await fetch(`/calendar/events/${eventId}?${params.toString()}`, {
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
    }
    
    // Search functionality
    const searchInput = document.getElementById('eventSearch');
    const searchResults = document.getElementById('searchResults');
    let searchTimeout;

    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim();
        
        if (query.length < 2) {
            searchResults.style.display = 'none';
            return;
        }
        
        searchTimeout = setTimeout(() => performSearch(query), 300);
    });

    // Close search results when clicking outside
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
            searchResults.style.display = 'none';
        }
    });

    async function performSearch(query) {
        try {
            const response = await fetch(`/calendar/search?q=${encodeURIComponent(query)}`);
            const results = await response.json();
            
            searchResults.innerHTML = '';
            
            if (results.length === 0) {
                const noResults = document.createElement('div');
                noResults.style.padding = '0.75rem';
                noResults.style.color = 'var(--text-secondary)';
                noResults.textContent = 'No results found';
                searchResults.appendChild(noResults);
            } else {
                results.forEach(item => {
                    const div = document.createElement('div');
                    div.className = 'search-result-item';
                    div.style.padding = '0.75rem';
                    div.style.cursor = 'pointer';
                    div.style.borderBottom = '1px solid var(--border-color)';
                    
                    // Hover effect
                    div.onmouseover = () => div.style.backgroundColor = 'rgba(255,255,255,0.05)';
                    div.onmouseout = () => div.style.backgroundColor = 'transparent';
                    
                    const date = item.date ? new Date(item.date).toLocaleDateString() : 'No date';
                    const icon = item.type === 'task' ? (item.status === 'completed' ? '✓' : '☐') : '📅';
                    
                    div.innerHTML = `
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-weight: 500;">${icon} ${item.title}</span>
                            <span style="font-size: 0.8rem; color: var(--text-secondary);">${date}</span>
                        </div>
                    `;
                    
                    div.addEventListener('click', () => {
                        if (item.date) {
                            calendar.gotoDate(item.date);
                        }
                        searchResults.style.display = 'none';
                        searchInput.value = '';
                    });
                    
                    searchResults.appendChild(div);
                });
            }
            
            searchResults.style.display = 'block';
        } catch (error) {
            console.error('Search failed:', error);
        }
    }
});

// Modal functions are now in modal.js (loaded globally)
</script>

<style>
.color-swatches {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}
.color-swatch {
    cursor: pointer;
    position: relative;
}
.color-swatch input {
    position: absolute;
    opacity: 0;
    cursor: pointer;
}
.color-swatch span {
    display: block;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    border: 2px solid transparent;
    transition: transform 0.1s;
}
.color-swatch input:checked + span {
    border-color: var(--text-primary);
    transform: scale(1.1);
    box-shadow: 0 0 0 2px var(--color-bg), 0 0 0 4px var(--primary);
}
.checkbox-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    user-select: none;
}
.checkbox-label input {
    width: 1.2rem;
    height: 1.2rem;
    cursor: pointer;
}
</style>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
?>
