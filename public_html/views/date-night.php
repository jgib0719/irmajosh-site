<?php
/**
 * Date Night View
 */

ob_start();
?>

<div class="date-night-page">
    <!-- Hero Section: Gamification -->
    <div class="gamification-hero">
        <div class="points-display">
            <div class="points-label">Couples Points</div>
            <div class="points-value"><?= number_format($totalPoints) ?></div>
        </div>
        <div class="level-display">
            <div class="level-info">
                <span class="level-badge">Level <?= $level ?></span>
                <span class="next-level">Next level at <?= number_format($nextLevelPoints) ?></span>
            </div>
            <div class="progress-bar-container">
                <div class="progress-bar" style="width: <?= $progress ?>%"></div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="tabs">
        <button class="tab-btn active" data-tab="browse">📚 Browse Ideas</button>
        <button class="tab-btn" data-tab="history">📜 History</button>
    </div>

    <!-- Tab Content: Browse -->
    <div id="tab-browse" class="tab-content active">
        <div class="browse-header">
            <div class="filters">
                <select id="categoryFilter" class="form-control">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="costFilter" class="form-control">
                    <option value="">Any Cost</option>
                    <option value="1">$</option>
                    <option value="2">$$</option>
                    <option value="3">$$$</option>
                </select>
            </div>
            <button class="btn btn-primary" id="addIdeaBtn">+ Add Idea</button>
        </div>

        <div class="ideas-grid" id="ideasGrid">
            <?php foreach ($ideas as $idea): ?>
                <div class="date-card" 
                     data-category="<?= $idea['category_id'] ?>" 
                     data-cost="<?= $idea['cost_level'] ?>"
                     data-completed="<?= !empty($idea['completed_at']) ? 'true' : 'false' ?>">
                    
                    <div class="card-header" style="background-color: <?= $idea['category_color'] ?>20; color: <?= $idea['category_color'] ?>">
                        <span class="category-icon"><?= $idea['category_icon'] ?? '❤️' ?></span>
                        <span class="category-name"><?= htmlspecialchars($idea['category_name'] ?? 'General') ?></span>
                        <span class="cost-indicator"><?= str_repeat('$', (int)$idea['cost_level']) ?></span>
                    </div>
                    
                    <div class="card-body">
                        <h3 class="card-title"><?= htmlspecialchars($idea['title']) ?></h3>
                        <p class="card-desc"><?= htmlspecialchars($idea['description'] ?? '') ?></p>
                        <?php if (!empty($idea['url'])): ?>
                            <a href="<?= htmlspecialchars($idea['url']) ?>" target="_blank" class="card-link">View Link ↗</a>
                        <?php endif; ?>
                    </div>

                    <div class="card-footer">
                        <?php if (!empty($idea['completed_at'])): ?>
                            <div class="completed-badge">
                                ✅ Done on <?= date('M j', strtotime($idea['completed_at'])) ?>
                            </div>
                            <button class="btn btn-sm btn-secondary schedule-again-btn" data-idea='<?= htmlspecialchars(json_encode($idea), ENT_QUOTES) ?>'>📅 Again</button>
                        <?php else: ?>
                            <button class="btn btn-sm btn-secondary schedule-btn" data-idea='<?= htmlspecialchars(json_encode($idea), ENT_QUOTES) ?>'>📅 Schedule</button>
                            <button class="btn btn-sm btn-success complete-btn" data-id="<?= $idea['id'] ?>">✅ Complete</button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Tab Content: History -->
    <div id="tab-history" class="tab-content">
        <div class="history-list">
            <?php if (empty($history)): ?>
                <p class="empty-state">No dates completed yet. Go have some fun!</p>
            <?php else: ?>
                <?php foreach ($history as $item): ?>
                    <div class="history-item">
                        <div class="history-date"><?= date('M j, Y', strtotime($item['completed_at'])) ?></div>
                        <div class="history-details">
                            <h4><?= htmlspecialchars($item['title']) ?></h4>
                            <div class="rating">
                                <?= str_repeat('⭐', (int)$item['rating']) ?>
                            </div>
                            <?php if (!empty($item['notes'])): ?>
                                <p class="notes">"<?= htmlspecialchars($item['notes']) ?>"</p>
                            <?php endif; ?>
                        </div>
                        <div class="points-badge">+<?= $item['points_awarded'] ?> pts</div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Idea Modal -->
<div class="modal" id="addIdeaModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Add Date Idea</h2>
            <button class="modal-close" data-close-modal="addIdeaModal">&times;</button>
        </div>
        <form id="addIdeaForm" class="modal-body">
            <div class="form-group">
                <label>Title</label>
                <input type="text" name="title" required class="form-control">
            </div>
            <div class="form-group">
                <label>Category</label>
                <select name="category_id" class="form-control">
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Cost</label>
                <select name="cost_level" class="form-control">
                    <option value="1">$ (Cheap)</option>
                    <option value="2">$$ (Moderate)</option>
                    <option value="3">$$$ (Expensive)</option>
                </select>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control"></textarea>
            </div>
            <div class="form-group">
                <label>Link (Optional)</label>
                <input type="url" name="url" class="form-control">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal="addIdeaModal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Idea</button>
            </div>
        </form>
    </div>
</div>

<!-- Complete Date Modal -->
<div class="modal" id="completeDateModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Complete Date</h2>
            <button class="modal-close" data-close-modal="completeDateModal">&times;</button>
        </div>
        <form id="completeDateForm" class="modal-body">
            <input type="hidden" name="date_idea_id" id="completeIdeaId">
            <div class="form-group">
                <label>How was it?</label>
                <div class="star-rating">
                    <input type="radio" name="rating" value="5" id="star5"><label for="star5">⭐</label>
                    <input type="radio" name="rating" value="4" id="star4"><label for="star4">⭐</label>
                    <input type="radio" name="rating" value="3" id="star3"><label for="star3">⭐</label>
                    <input type="radio" name="rating" value="2" id="star2"><label for="star2">⭐</label>
                    <input type="radio" name="rating" value="1" id="star1"><label for="star1">⭐</label>
                </div>
            </div>
            <div class="form-group">
                <label>Notes / Memories</label>
                <textarea name="notes" class="form-control" rows="3"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal="completeDateModal">Cancel</button>
                <button type="submit" class="btn btn-success">Complete & Collect Points!</button>
            </div>
        </form>
    </div>
</div>

<style>
    .date-night-page { padding: 1rem; max-width: 1200px; margin: 0 auto; }
    
    /* Gamification Hero */
    .gamification-hero {
        background: linear-gradient(135deg, #6366f1, #ec4899);
        border-radius: 1rem;
        padding: 2rem;
        color: white;
        margin-bottom: 2rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
    }
    .points-value { font-size: 3rem; font-weight: bold; }
    .level-info { display: flex; justify-content: space-between; margin-bottom: 0.5rem; }
    .level-badge { background: rgba(255,255,255,0.2); padding: 0.25rem 0.75rem; border-radius: 1rem; font-weight: bold; }
    .progress-bar-container { width: 300px; height: 10px; background: rgba(0,0,0,0.2); border-radius: 5px; overflow: hidden; }
    .progress-bar { height: 100%; background: #fbbf24; transition: width 0.5s; }

    /* Tabs */
    .tabs { display: flex; gap: 1rem; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem; }
    .tab-btn { background: none; border: none; padding: 0.5rem 1rem; cursor: pointer; font-size: 1.1rem; color: var(--text-secondary); border-bottom: 2px solid transparent; }
    .tab-btn.active { color: var(--primary); border-bottom-color: var(--primary); font-weight: bold; }
    .tab-content { display: none; }
    .tab-content.active { display: block; }

    /* Grid */
    .ideas-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.5rem; }
    .date-card { background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 0.75rem; overflow: hidden; transition: transform 0.2s; }
    .date-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
    .card-header { padding: 0.75rem; display: flex; align-items: center; gap: 0.5rem; font-weight: bold; }
    .card-body { padding: 1rem; }
    .card-title { margin: 0 0 0.5rem 0; font-size: 1.2rem; }
    .card-desc { color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 0.5rem; }
    .card-footer { padding: 1rem; border-top: 1px solid var(--border-color); display: flex; gap: 0.5rem; }
    
    /* Star Rating */
    .star-rating { display: flex; flex-direction: row-reverse; justify-content: flex-end; gap: 0.5rem; }
    .star-rating input { display: none; }
    .star-rating label { cursor: pointer; font-size: 1.5rem; color: #ddd; }
    .star-rating input:checked ~ label, .star-rating label:hover, .star-rating label:hover ~ label { color: #fbbf24; }

    /* History */
    .history-item { display: flex; gap: 1rem; padding: 1rem; border-bottom: 1px solid var(--border-color); align-items: center; }
    .history-date { font-weight: bold; color: var(--text-secondary); width: 100px; }
    .history-details { flex-grow: 1; }
    .points-badge { background: #10b981; color: white; padding: 0.25rem 0.5rem; border-radius: 0.5rem; font-weight: bold; }

    @media (max-width: 768px) {
        .gamification-hero { flex-direction: column; align-items: flex-start; }
        .progress-bar-container { width: 100%; }
    }
</style>

<script nonce="<?= cspNonce() ?>">
document.addEventListener('DOMContentLoaded', function() {
    // Tab Switching
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const tabId = this.dataset.tab;
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            document.getElementById('tab-' + tabId).classList.add('active');
            this.classList.add('active');
        });
    });

    // Filtering
    const categoryFilter = document.getElementById('categoryFilter');
    const costFilter = document.getElementById('costFilter');
    
    function filterIdeas() {
        const cat = categoryFilter.value;
        const cost = costFilter.value;
        
        document.querySelectorAll('.date-card').forEach(card => {
            const matchCat = !cat || card.dataset.category === cat;
            const matchCost = !cost || card.dataset.cost === cost;
            card.style.display = (matchCat && matchCost) ? 'block' : 'none';
        });
    }

    if (categoryFilter) categoryFilter.addEventListener('change', filterIdeas);
    if (costFilter) costFilter.addEventListener('change', filterIdeas);

    // Add Idea Button
    const addIdeaBtn = document.getElementById('addIdeaBtn');
    if (addIdeaBtn) {
        addIdeaBtn.addEventListener('click', function() {
            openModal('addIdeaModal');
        });
    }

    // Add Idea Form
    const addIdeaForm = document.getElementById('addIdeaForm');
    if (addIdeaForm) {
        addIdeaForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            try {
                const res = await fetch('/date-night/create', {
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
                alert('Failed to create idea');
            }
        });
    }

    // Complete Date Buttons
    document.querySelectorAll('.complete-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            document.getElementById('completeIdeaId').value = id;
            openModal('completeDateModal');
        });
    });

    // Complete Date Form
    const completeDateForm = document.getElementById('completeDateForm');
    if (completeDateForm) {
        completeDateForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            try {
                const res = await fetch('/date-night/complete', {
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
                alert('Failed to complete date');
            }
        });
    }

    // Schedule Date Buttons
    function handleScheduleClick() {
        const ideaData = this.dataset.idea;
        if (!ideaData) return;
        
        try {
            const idea = JSON.parse(ideaData);
            scheduleDate(idea);
        } catch (e) {
            console.error('Error parsing idea data', e);
        }
    }

    document.querySelectorAll('.schedule-btn, .schedule-again-btn').forEach(btn => {
        btn.addEventListener('click', handleScheduleClick);
    });

    // Schedule Date Logic
    function scheduleDate(idea) {
        if (!idea) return;
        
        // Redirect to calendar with params to open modal
        const title = encodeURIComponent('Date Night: ' + (idea.title || ''));
        const descText = (idea.description || '') + (idea.url ? '\n\nLink: ' + idea.url : '');
        const desc = encodeURIComponent(descText);
        window.location.href = `/calendar?action=add&title=${title}&description=${desc}`;
    }
});
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
?>
