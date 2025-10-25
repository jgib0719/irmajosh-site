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
            <button class="btn btn-primary" onclick="openModal('createRequestModal')">
                <span>+</span> New Request
            </button>
        </div>
    </div>
    
    <div class="requests-container">
        <?php if (empty($requests)): ?>
            <div class="empty-state">
                <p>No schedule requests yet</p>
                <button class="btn btn-primary" onclick="openModal('createRequestModal')">
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
                                <strong>From:</strong> <?= htmlspecialchars($request['requester_name']) ?>
                            </div>
                            <div class="request-detail">
                                <strong>Email:</strong> <?= htmlspecialchars($request['requester_email']) ?>
                            </div>
                            <div class="request-detail">
                                <strong>Duration:</strong> <?= $request['duration_minutes'] ?> minutes
                            </div>
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
                                <button class="btn btn-success" onclick="acceptRequest(<?= $request['id'] ?>)">
                                    Accept
                                </button>
                                <button class="btn btn-danger" onclick="declineRequest(<?= $request['id'] ?>)">
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
                <button class="modal-close" onclick="closeModal('createRequestModal')">&times;</button>
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
                    <label for="requesterName">Your Name *</label>
                    <input type="text" id="requesterName" name="requester_name" required class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="requesterEmail">Your Email *</label>
                    <input type="email" id="requesterEmail" name="requester_email" required class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="duration">Duration (minutes) *</label>
                    <select id="duration" name="duration_minutes" required class="form-control">
                        <option value="15">15 minutes</option>
                        <option value="30" selected>30 minutes</option>
                        <option value="45">45 minutes</option>
                        <option value="60">1 hour</option>
                        <option value="90">1.5 hours</option>
                        <option value="120">2 hours</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Proposed Time Slots *</label>
                    <div id="timeSlots">
                        <div class="time-slot-row">
                            <input type="datetime-local" name="slots[0][start]" required class="form-control">
                            <button type="button" class="btn-icon" onclick="removeSlot(this)" style="visibility: hidden">
                                🗑️
                            </button>
                        </div>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="addSlot()">
                        + Add Another Slot
                    </button>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('createRequestModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Send Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script nonce="<?= cspNonce() ?>">
let slotCounter = 1;

function addSlot() {
    const slotsContainer = document.getElementById('timeSlots');
    const newSlot = document.createElement('div');
    newSlot.className = 'time-slot-row';
    newSlot.innerHTML = `
        <input type="datetime-local" name="slots[${slotCounter}][start]" required class="form-control">
        <button type="button" class="btn-icon" onclick="removeSlot(this)">
            🗑️
        </button>
    `;
    slotsContainer.appendChild(newSlot);
    slotCounter++;
}

function removeSlot(button) {
    button.parentElement.remove();
}

function openModal(modalId) {
    document.getElementById(modalId).classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

document.getElementById('createRequestForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const data = {
        csrf_token: formData.get('csrf_token'),
        title: formData.get('title'),
        description: formData.get('description'),
        requester_name: formData.get('requester_name'),
        requester_email: formData.get('requester_email'),
        duration_minutes: parseInt(formData.get('duration_minutes')),
        slots: []
    };
    
    // Collect all time slots
    const slotInputs = document.querySelectorAll('#timeSlots input[type="datetime-local"]');
    slotInputs.forEach(input => {
        if (input.value) {
            data.slots.push({ start: input.value });
        }
    });
    
    try {
        const response = await fetch('/schedule/send-request', {
            method: 'POST',
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
                    <input type="datetime-local" name="slots[0][start]" required class="form-control">
                    <button type="button" class="btn-icon" onclick="removeSlot(this)" style="visibility: hidden">
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
