<?php
/**
 * Shopping List View
 */

ob_start();
?>

<div class="shopping-list-page">
    <div class="page-header">
        <h1 class="page-title">Shopping List</h1>
        <button class="btn btn-primary" id="addItemBtn">
            <span class="btn-icon">+</span> Add Item
        </button>
    </div>

    <!-- Active Items -->
    <div class="shopping-section">
        <h2 class="section-title">To Buy</h2>
        <?php if (empty($activeItems)): ?>
            <p class="empty-state">Your shopping list is empty.</p>
        <?php else: ?>
            <ul class="shopping-list" id="activeList">
                <?php foreach ($activeItems as $item): ?>
                    <li class="shopping-item" data-id="<?= $item['id'] ?>">
                        <label class="checkbox-container">
                            <input type="checkbox" class="item-checkbox" data-id="<?= $item['id'] ?>">
                            <span class="checkmark"></span>
                            <span class="item-name"><?= htmlspecialchars($item['item_name']) ?></span>
                        </label>
                        <button class="btn-icon delete-btn" data-id="<?= $item['id'] ?>" title="Delete">🗑️</button>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <!-- Completed Items -->
    <div class="shopping-section completed-section">
        <h2 class="section-title">Recently Bought</h2>
        <?php if (empty($completedItems)): ?>
            <p class="text-muted">No recently bought items.</p>
        <?php else: ?>
            <ul class="shopping-list completed-list" id="completedList">
                <?php foreach ($completedItems as $item): ?>
                    <li class="shopping-item completed" data-id="<?= $item['id'] ?>">
                        <div class="item-info">
                            <span class="item-name"><?= htmlspecialchars($item['item_name']) ?></span>
                            <span class="item-date">Bought <?= date('M j, g:i a', strtotime($item['completed_at'])) ?></span>
                        </div>
                        <button class="btn-icon delete-btn" data-id="<?= $item['id'] ?>" title="Delete">🗑️</button>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<!-- Add Item Modal -->
<div class="modal" id="addItemModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Add Item</h2>
            <button class="modal-close" data-close-modal="addItemModal">&times;</button>
        </div>
        <form id="addItemForm" class="modal-body">
            <div class="form-group">
                <label for="itemName">Item Name:</label>
                <input type="text" name="item_name" id="itemName" required class="form-control" placeholder="e.g., Milk, Eggs, Bread" autofocus>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal="addItemModal">Cancel</button>
                <button type="submit" class="btn btn-primary">Add to List</button>
            </div>
        </form>
    </div>
</div>

<style>
    .shopping-list-page { max-width: 800px; margin: 0 auto; padding: 1rem; }
    .shopping-section { margin-bottom: 2rem; }
    .shopping-list { list-style: none; padding: 0; }
    
    .shopping-item { 
        display: flex; 
        align-items: center; 
        justify-content: space-between; 
        padding: 1rem; 
        background: var(--bg-secondary); 
        border: 1px solid var(--border-color); 
        border-radius: var(--radius-md); 
        margin-bottom: 0.5rem; 
        transition: background-color 0.2s;
    }
    
    .shopping-item:hover { background: var(--bg-hover); }
    
    .checkbox-container { 
        display: flex; 
        align-items: center; 
        cursor: pointer; 
        flex-grow: 1; 
        user-select: none;
    }
    
    .checkbox-container input { display: none; }
    
    .checkmark {
        width: 24px;
        height: 24px;
        border: 2px solid var(--primary);
        border-radius: 4px;
        margin-right: 1rem;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
    }
    
    .checkbox-container input:checked ~ .checkmark {
        background: var(--primary);
        color: white;
    }
    
    .checkbox-container input:checked ~ .checkmark::after {
        content: '✓';
        font-size: 16px;
        font-weight: bold;
    }
    
    .item-name { font-size: 1.1rem; }
    
    .delete-btn { opacity: 0.5; transition: opacity 0.2s; }
    .delete-btn:hover { opacity: 1; color: var(--danger); }
    
    /* Completed Items */
    .completed-list .shopping-item { opacity: 0.7; }
    .completed-list .item-name { text-decoration: line-through; color: var(--text-secondary); }
    .item-info { display: flex; flex-direction: column; }
    .item-date { font-size: 0.8rem; color: var(--text-muted); }
    
    .text-muted { color: var(--text-secondary); font-style: italic; }
</style>

<script nonce="<?= cspNonce() ?>">
document.addEventListener('DOMContentLoaded', function() {
    // Add Item Modal
    const addItemBtn = document.getElementById('addItemBtn');
    const addItemModal = document.getElementById('addItemModal');
    const itemNameInput = document.getElementById('itemName');
    
    if (addItemBtn) {
        addItemBtn.addEventListener('click', function() {
            openModal('addItemModal');
            // Focus input after modal opens
            setTimeout(() => itemNameInput.focus(), 100);
        });
    }
    
    // Add Item Form
    const addItemForm = document.getElementById('addItemForm');
    if (addItemForm) {
        addItemForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            try {
                const res = await fetch('/shopping-list', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-CSRF-Token': '<?= csrfToken() ?>' }
                });
                const data = await res.json();
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.error);
                }
            } catch (err) {
                alert('Failed to add item');
            }
        });
    }
    
    // Checkbox Toggle
    document.querySelectorAll('.item-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', async function() {
            const id = this.dataset.id;
            const completed = this.checked;
            
            // Optimistic UI update: remove from list immediately
            const listItem = this.closest('.shopping-item');
            listItem.style.opacity = '0.5';
            
            try {
                const res = await fetch(`/shopping-list/${id}/toggle`, {
                    method: 'PUT',
                    headers: { 
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': '<?= csrfToken() ?>' 
                    },
                    body: JSON.stringify({ completed: completed })
                });
                
                if (res.ok) {
                    location.reload();
                } else {
                    alert('Failed to update item');
                    listItem.style.opacity = '1';
                    this.checked = !completed; // Revert
                }
            } catch (err) {
                alert('Failed to update item');
                listItem.style.opacity = '1';
                this.checked = !completed; // Revert
            }
        });
    });
    
    // Delete Button
    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', async function() {
            if (!confirm('Delete this item?')) return;
            
            const id = this.dataset.id;
            const listItem = this.closest('.shopping-item');
            
            try {
                const res = await fetch(`/shopping-list/${id}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-Token': '<?= csrfToken() ?>' }
                });
                
                if (res.ok) {
                    listItem.remove();
                    if (document.querySelectorAll('#activeList .shopping-item').length === 0) {
                        location.reload(); // Reload to show empty state
                    }
                } else {
                    alert('Failed to delete item');
                }
            } catch (err) {
                alert('Failed to delete item');
            }
        });
    });
});
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
?>
