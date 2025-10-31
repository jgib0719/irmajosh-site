<?php
/**
 * Schedule Requests View
 * 
 * @var array $user - Current user
 * @var array $requests - Schedule requests array
 */

ob_start();
?>

<div class="schedule-requests-page">
    <div class="page-header">
        <h1 class="page-title"><?= t('schedule_requests') ?></h1>
        <div class="page-actions">
            <button class="btn btn-primary" data-open-modal="createRequestModal">
                <span>+</span> New Request
            </button>
        </div>
    </div>
    
    <div class="requests-container">
        <?php if (empty($requests)): ?>
            <div class="empty-state">
                <p>No schedule requests yet</p>
                <button class="btn btn-primary" data-open-modal="createRequestModal">
                    Create Your First Request
                </button>
            </div>
        <?php else: ?>
            <div class="requests-list">
                <?php foreach ($requests as $request): ?>
                    <div class="request-card" data-request-id="<?= $request['id'] ?>">
                        <div class="request-header">
                            <h3 class="request-title"><?= htmlspecialchars($request['title']) ?></h3>
                            <span class="request-status status-<?= $request['status'] ?>">
                                <?= ucfirst($request['status']) ?>
                            </span>
                        </div>
                        
                        <?php if (!empty($request['description'])): ?>
                            <p class="request-description"><?= nl2br(htmlspecialchars($request['description'])) ?></p>
                        <?php endif; ?>
                        
                        <div class="request-details">
                            <div class="request-detail">
                                <strong>Created:</strong> <?= date('M j, Y g:i A', strtotime($request['created_at'])) ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($request['slots'])): ?>
                            <div class="request-slots">
                                <strong>Proposed Times:</strong>
                                <ul class="slots-list">
                                    <?php foreach ($request['slots'] as $slot): ?>
                                        <li class="slot-item">
                                            <?= date('M j, Y g:i A', strtotime($slot['start_time'])) ?>
                                            -
                                            <?= date('g:i A', strtotime($slot['end_time'])) ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($request['status'] === 'pending'): ?>
                            <div class="request-actions">
                                <button class="btn btn-success" data-accept-request="<?= $request['id'] ?>">
                                    Accept
                                </button>
                                <button class="btn btn-danger" data-decline-request="<?= $request['id'] ?>">
                                    Decline
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Create Request Modal -->
    <div class="modal" id="createRequestModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">New Schedule Request</h2>
                <button class="modal-close" data-close-modal="createRequestModal">&times;</button>
            </div>
            <form id="createRequestForm" class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                
                <div class="form-group">
                    <label for="requestTitle">Title *</label>
                    <input type="text" id="requestTitle" name="title" required class="form-control" 
                           placeholder="e.g., Coffee Meeting">
                </div>
                
                <div class="form-group">
                    <label for="requestDescription">Description</label>
                    <textarea id="requestDescription" name="description" class="form-control" rows="3"
                              placeholder="Optional details about the meeting"></textarea>
                </div>
                
                <div class="form-group">
                    <label>Proposed Time Slots (Optional)</label>
                    <p class="form-help-text">You can add time slots now or leave blank and set them later when accepting the request.</p>
                    <div id="timeSlots">
                        <div class="time-slot-row">
                            <input type="datetime-local" name="slots[0][start]" class="form-control">
                            <button type="button" class="btn-icon remove-slot" style="visibility: hidden">
                                🗑️
                            </button>
                        </div>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" id="addSlotBtn">
                        + Add Another Slot
                    </button>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-close-modal="createRequestModal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Send Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script nonce="<?= cspNonce() ?>">
let slotCounter = 1;

// Initialize event listeners on page load
document.addEventListener('DOMContentLoaded', function() {
    // Modal open/close buttons
    document.querySelectorAll('[data-open-modal]').forEach(btn => {
        btn.addEventListener('click', function() {
            openModal(this.dataset.openModal);
        });
    });
    
    document.querySelectorAll('[data-close-modal]').forEach(btn => {
        btn.addEventListener('click', function() {
            closeModal(this.dataset.closeModal);
        });
    });
    
    // Add slot button
    document.getElementById('addSlotBtn').addEventListener('click', addSlot);
    
    // Remove slot buttons (delegated)
    document.getElementById('timeSlots').addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-slot') || e.target.closest('.remove-slot')) {
            const button = e.target.classList.contains('remove-slot') ? e.target : e.target.closest('.remove-slot');
            button.parentElement.remove();
        }
    });
    
    // Accept/decline request buttons
    document.querySelectorAll('[data-accept-request]').forEach(btn => {
        btn.addEventListener('click', function() {
            acceptRequest(parseInt(this.dataset.acceptRequest));
        });
    });
    
    document.querySelectorAll('[data-decline-request]').forEach(btn => {
        btn.addEventListener('click', function() {
            declineRequest(parseInt(this.dataset.declineRequest));
        });
    });
});

function addSlot() {
    const slotsContainer = document.getElementById('timeSlots');
    const newSlot = document.createElement('div');
    newSlot.className = 'time-slot-row';
    newSlot.innerHTML = `
        <input type="datetime-local" name="slots[${slotCounter}][start]" class="form-control">
        <button type="button" class="btn-icon remove-slot">
            🗑️
        </button>
    `;
    slotsContainer.appendChild(newSlot);
    slotCounter++;
}

// Modal functions are now in modal.js (loaded globally)

document.getElementById('createRequestForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const data = {
        csrf_token: formData.get('csrf_token'),
        title: formData.get('title'),
        description: formData.get('description'),
        slots: []
    };
    
    // Collect all time slots (optional)
    const slotInputs = document.querySelectorAll('#timeSlots input[type="datetime-local"]');
    slotInputs.forEach(input => {
        if (input.value) {
            data.slots.push({ start: input.value });
        }
    });
    
    try {
        const response = await fetch('/schedule/send-request', {
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
            closeModal('createRequestModal');
            showAlert('Schedule request sent successfully', 'success');
            this.reset();
            slotCounter = 1;
            // Reset to single slot
            document.getElementById('timeSlots').innerHTML = `
                <div class="time-slot-row">
                    <input type="datetime-local" name="slots[0][start]" class="form-control">
                    <button type="button" class="btn-icon remove-slot" style="visibility: hidden">
                        🗑️
                    </button>
                </div>
            `;
            window.location.reload();
        } else {
            showAlert(result.error || 'Failed to send request', 'error');
        }
    } catch (error) {
        showAlert('Failed to send request', 'error');
    }
});

async function acceptRequest(requestId) {
    if (!confirm('Accept this schedule request and add it to your calendar?')) {
        return;
    }
    
    try {
        const response = await fetch(`/schedule/${requestId}/accept`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        
        const result = await response.json();
        
        if (response.ok) {
            showAlert('Request accepted and added to calendar', 'success');
            window.location.reload();
        } else {
            showAlert(result.error || 'Failed to accept request', 'error');
        }
    } catch (error) {
        showAlert('Failed to accept request', 'error');
    }
}

async function declineRequest(requestId) {
    if (!confirm('Decline this schedule request?')) {
        return;
    }
    
    try {
        const response = await fetch(`/schedule/${requestId}/decline`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        
        const result = await response.json();
        
        if (response.ok) {
            showAlert('Request declined', 'success');
            window.location.reload();
        } else {
            showAlert(result.error || 'Failed to decline request', 'error');
        }
    } catch (error) {
        showAlert('Failed to decline request', 'error');
    }
}
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
?>
