// This is the main UI generation script.
// It creates the entire dashboard, calendar, and modals, and handles all user interactions.

import { api } from './api.js';

const calendarState = { current: new Date('2025-09-01T12:00:00Z') };

async function updateTabCounts() {
    try {
        const [irmaReqs, irmaJobs, joshJobs] = await Promise.all([
            api.irma.getScheduleRequests(),
            api.irma.getJobStatuses(),
            api.josh.getPendingJobs()
        ]);

        const irmaCount = (irmaReqs.items?.length || 0) + (irmaJobs.items?.length || 0);
        const joshCount = joshJobs.items?.length || 0;

        const irmaDot = document.querySelector('[data-tab="irma-pane"] .tab-dot');
        const joshDot = document.querySelector('[data-tab="josh-pane"] .tab-dot');

        if (irmaDot) {
            irmaDot.textContent = irmaCount;
            irmaDot.style.display = irmaCount > 0 ? 'inline-flex' : 'none';
        }

        if (joshDot) {
            joshDot.textContent = joshCount;
            joshDot.style.display = joshCount > 0 ? 'inline-flex' : 'none';
        }
    } catch (error) {
        console.error("Failed to update tab counts:", error);
    }
}

function initTabs() {
    const tabs = document.querySelectorAll('#tabs-root .tabs button');
    const panes = document.querySelectorAll('#tabs-root [data-pane]');
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            
            const targetPaneId = tab.dataset.tab;
            panes.forEach(pane => {
                pane.style.display = pane.id === targetPaneId ? 'grid' : 'none';
            });

            if (targetPaneId === 'josh-pane') {
                renderJoshsDashboard();
            } else {
                refreshIrmaLists();
            }
        });
    });
    document.querySelector('#tabs-root .tabs button').click();
}

// --- Irma's Dashboard Logic ---
function initIrmaForms(){
    const form = document.getElementById("irma-form");
    if (!form) return;
    const titleInput = form.querySelector('#irma-title');
    const notesInput = form.querySelector('#irma-notes');
    const submitButton = form.querySelector('button[type="submit"]');

    function validateForm() {
        const isTitleValid = titleInput.value.trim() !== '';
        const isNotesValid = notesInput.value.trim() !== '';
        submitButton.disabled = !(isTitleValid && isNotesValid);
    }

    form.addEventListener("submit", async (e) => {
        e.preventDefault();
        await api.irma.sendJobToJosh({ title: titleInput.value, notes: notesInput.value });
        alert("Request sent to Josh!");
        form.reset();
        validateForm();
        updateTabCounts();
    });

    titleInput.addEventListener('input', validateForm);
    notesInput.addEventListener('input', validateForm);
    validateForm(); // Initial check
}

async function refreshIrmaLists(){
    const [reqRes, jobRes] = await Promise.all([
        api.irma.getScheduleRequests(),
        api.irma.getJobStatuses()
    ]);
    renderIrmaScheduleRequests(document.getElementById("pending-requests-container"), reqRes.items);
    renderIrmaJobStatuses(document.getElementById("job-status-list"), jobRes.items);
    updateTabCounts();
}

function renderIrmaScheduleRequests(container, items){
    if(!container) return;
    const listEl = container.querySelector('#pending-requests');

    if (!items || items.length === 0) {
        container.innerHTML = `<h3>Pending Scheduling Requests</h3><p class="placeholder-text">You're THE Best Babe! XOXO</p>`;
        return;
    }
    
    if (!listEl) {
        container.innerHTML = `<h3>Pending Scheduling Requests</h3><div id="pending-requests"></div>`;
    }
    
    const freshListEl = container.querySelector('#pending-requests');
    freshListEl.innerHTML = "";

    items.forEach(item => {
        const card = document.createElement("div");
        card.className="request-card";
        card.innerHTML=`<div class="title">${item.title}</div><div class="notes">${item.notes}</div>`;
        const scheduleButton = document.createElement('button');
        scheduleButton.className = 'btn-schedule';
        scheduleButton.textContent = 'Schedule';
        scheduleButton.onclick = () => openScheduleModal({ sourceId: item.id, sourceType: 'scheduleRequest', title: item.title, notes: item.notes });
        card.appendChild(scheduleButton);
        freshListEl.appendChild(card);
    });
}

function renderIrmaJobStatuses(container,items){
    if(!container)return;
    container.innerHTML="";
    if (!items || items.length === 0) {
        container.innerHTML = `<p class="placeholder-text">All Caught Up &lt;3</p>`;
        return;
    }
    items.forEach(item => {
        const card = document.createElement("div");
        card.className="status-card";
        if (item.status === 'accepted') {
            card.innerHTML=`<div class="title">${item.title}</div><div class="notes">${item.notes}</div>`;
            const scheduleButton = document.createElement('button');
            scheduleButton.className = 'btn-schedule';
            scheduleButton.textContent = 'Schedule';
            scheduleButton.onclick = () => openScheduleModal({ sourceId: item.id, sourceType: 'job', title: item.title, notes: item.notes });
            card.appendChild(scheduleButton);
        } else if (item.status === 'denied') {
            card.innerHTML=`<div class="title denied-title">Request Denied</div><div class="notes">${item.title}</div>`;
            const acknowledgeButton = document.createElement('button');
            acknowledgeButton.className = 'btn-acknowledge';
            acknowledgeButton.textContent = 'Acknowledge';
            acknowledgeButton.onclick = () => api.irma.acknowledgeJob(item.id).then(refreshIrmaLists);
            card.appendChild(acknowledgeButton);
        }
        container.appendChild(card);
    });
}

// --- Josh's Dashboard Logic ---
function initJoshForms() {
    const form = document.getElementById("josh-request-form");
    if (!form) return;
    const titleInput = form.querySelector('#josh-request-title');
    const notesInput = form.querySelector('#josh-request-notes');
    const submitButton = form.querySelector('button[type="submit"]');

    function validateForm() {
        const isTitleValid = titleInput.value.trim() !== '';
        const isNotesValid = notesInput.value.trim() !== '';
        submitButton.disabled = !(isTitleValid && isNotesValid);
    }
    
    form.addEventListener("submit", async (e) => {
        e.preventDefault();
        await api.josh.sendScheduleRequest({ title: titleInput.value, notes: notesInput.value });
        alert("Scheduling request sent to Irma!");
        form.reset();
        validateForm();
        updateTabCounts();
    });

    titleInput.addEventListener('input', validateForm);
    notesInput.addEventListener('input', validateForm);
    validateForm(); // Initial check
}

async function renderJoshsDashboard() {
    const res = await api.josh.getPendingJobs();
    const jobs = res.items || [];
    const cardContainer = document.getElementById('josh-pending-requests-card');
    
    if (!jobs || jobs.length === 0) {
        cardContainer.innerHTML = `<h3>Pending Job Requests from Irma</h3><p class="placeholder-text">Nothing Pending ~ Randy Time ;)</p>`;
    } else {
        let listEl = cardContainer.querySelector('#josh-pending-requests');
        if (!listEl) {
            cardContainer.innerHTML = `<h3>Pending Job Requests from Irma</h3><div id="josh-pending-requests"></div>`;
            listEl = cardContainer.querySelector('#josh-pending-requests');
        }
        listEl.innerHTML = '';
        jobs.forEach(job => {
            const card = document.createElement('div');
            card.className = 'request-card';
            card.innerHTML = `
                <div class="title">${job.title}</div>
                <div class="notes">${job.notes}</div>
                <div class="josh-card-actions">
                    <button class="btn-accept" data-id="${job.id}">Accept</button>
                    <button class="btn-deny" data-id="${job.id}">Deny</button>
                </div>
            `;
            listEl.appendChild(card);
        });
    }

    document.querySelectorAll('.btn-accept').forEach(btn => btn.onclick = () => { api.josh.updateJobStatus(parseInt(btn.dataset.id), 'accepted').then(renderJoshsDashboard).then(refreshIrmaLists); });
    document.querySelectorAll('.btn-deny').forEach(btn => btn.onclick = () => { api.josh.updateJobStatus(parseInt(btn.dataset.id), 'denied').then(renderJoshsDashboard).then(refreshIrmaLists); });
    updateTabCounts();
}

// --- Calendar and Modal Logic (Shared) ---
function isWorkDay(date) { const weekA=[1,2,5,6,0],weekB=[3,4],ref=new Date("2025-08-25T00:00:00"),diff=t=>Math.floor((new Date(t.getFullYear(),t.getMonth(),t.getDate())-new Date(ref.getFullYear(),ref.getMonth(),ref.getDate()))/6048e5);return(Math.abs(diff(date))%2==0?weekA:weekB).includes(date.getDay()) }
async function renderCalendar() {
    const grid = document.getElementById('calendar-grid');
    const header = document.getElementById('month-year');
    if (!grid || !header) return;
    header.textContent = calendarState.current.toLocaleString('default', { month: 'long', year: 'numeric' });
    const eventsRes = await api.events.list();
    const eventsByDate = (eventsRes.items || []).reduce((acc, ev) => { (acc[ev.date] = acc[ev.date] || []).push(ev); return acc; }, {});
    grid.innerHTML = '';
    ['S', 'M', 'T', 'W', 'T', 'F', 'S'].forEach(day => { grid.appendChild(document.createElement('div')).textContent = day; grid.lastChild.className = 'weekday-header'; });
    const year = calendarState.current.getFullYear(), month = calendarState.current.getMonth();
    const firstDay = new Date(year, month, 1), lastDay = new Date(year, month + 1, 0);
    const startDayOfWeek = firstDay.getDay();
    for (let i = 0; i < startDayOfWeek; i++) { grid.appendChild(document.createElement('div')).className = 'calendar-day not-in-month'; }
    const today = new Date(); today.setHours(0, 0, 0, 0);
    for (let day = 1; day <= lastDay.getDate(); day++) {
        const cell = document.createElement('div'), date = new Date(year, month, day);
        cell.className = 'calendar-day';
        const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        const dayNum = document.createElement('div');
        dayNum.className = 'day-num';
        dayNum.textContent = day;
        if (date.getTime() === today.getTime()) dayNum.classList.add('today');
        if (isWorkDay(date)) dayNum.classList.add('work');
        if (date < today) dayNum.classList.add('past-day');
        cell.appendChild(dayNum);
        if (eventsByDate[dateStr]) {
            eventsByDate[dateStr].forEach(event => {
                const chip = document.createElement('div');
                chip.className = 'event-chip';
                if (event.is_done) chip.classList.add('done');
                if (new Date(event.date) < today && !event.is_done) chip.classList.add('past-event');
                chip.textContent = event.title;
                chip.addEventListener('click', (e) => { e.stopPropagation(); openDetailsModal(event); });
                cell.appendChild(chip);
            });
        }
        cell.addEventListener('click', () => openScheduleModal({ date: dateStr }));
        grid.appendChild(cell);
    }
}
function initCalendarNav() { document.getElementById('prev-month').addEventListener('click', () => { calendarState.current.setMonth(calendarState.current.getMonth() - 1); renderCalendar(); }); document.getElementById('next-month').addEventListener('click', () => { calendarState.current.setMonth(calendarState.current.getMonth() + 1); renderCalendar(); }); }
function openScheduleModal(event = {}) { 
    const modal = document.getElementById('scheduling-modal'); 
    if (modal) { 
        modal.querySelector('#modal-event-id').value = event.id || ''; 
        modal.querySelector('#modal-source-id').value = event.sourceId || '';
        modal.querySelector('#modal-source-type').value = event.sourceType || '';
        modal.querySelector('#modal-title').value = event.title || ''; 
        modal.querySelector('#modal-date').value = event.date || ''; 
        modal.querySelector('#modal-time').value = event.time || '09:00'; 
        modal.querySelector('#modal-notes').value = event.notes || ''; 
        modal.classList.add('visible'); 
    } 
}
function openDetailsModal(event) { const modal = document.getElementById('details-modal'); if (modal) { modal.dataset.eventId = event.id; modal.querySelector('#details-title').textContent = event.title; const date = new Date(`${event.date}T${event.time||"00:00"}`); modal.querySelector('#details-datetime').textContent = date.toLocaleString('en-US', { dateStyle: 'full', timeStyle: 'short' }); modal.querySelector('#details-notes').textContent = event.notes || 'No notes.'; modal.classList.add('visible'); } }

function initModals() {
    const scheduleModal = document.getElementById('scheduling-modal');
    const detailsModal = document.getElementById('details-modal');
    const confirmModal = document.getElementById('confirm-modal');
    let actionToConfirm = null;

    const showConfirmModal = (message, action) => {
        confirmModal.querySelector('#confirm-message').textContent = message;
        actionToConfirm = action;
        confirmModal.classList.add('visible');
    };

    confirmModal.querySelector('#confirm-no').addEventListener('click', () => {
        actionToConfirm = null;
        confirmModal.classList.remove('visible');
    });

    confirmModal.querySelector('#confirm-yes').addEventListener('click', async () => {
        if (typeof actionToConfirm === 'function') {
            await actionToConfirm();
        }
        actionToConfirm = null;
        confirmModal.classList.remove('visible');
    });

    scheduleModal?.querySelector('.btn-cancel').addEventListener('click', () => scheduleModal.classList.remove('visible'));
    detailsModal?.querySelector('.btn-cancel').addEventListener('click', () => detailsModal.classList.remove('visible'));
    
    scheduleModal?.querySelector('.btn-save').addEventListener('click', async () => { 
        const id = parseInt(scheduleModal.querySelector('#modal-event-id').value);
        const sourceId = parseInt(scheduleModal.querySelector('#modal-source-id').value);
        const sourceType = scheduleModal.querySelector('#modal-source-type').value;

        const payload = { 
            title: scheduleModal.querySelector('#modal-title').value, 
            date: scheduleModal.querySelector('#modal-date').value, 
            time: scheduleModal.querySelector('#modal-time').value, 
            notes: scheduleModal.querySelector('#modal-notes').value, 
        }; 

        if (!payload.date) {
            alert("Please select a date.");
            return;
        }

        if (id) {
            await api.events.update({ id, ...payload });
        } else {
            await api.events.create(payload);
        }

        if (sourceId && sourceType) {
            if (sourceType === 'scheduleRequest') {
                await api.irma.deleteScheduleRequest(sourceId);
            } else if (sourceType === 'job') {
                await api.irma.acknowledgeJob(sourceId); 
            }
        }

        scheduleModal.classList.remove('visible'); 
        renderCalendar(); 
        refreshIrmaLists(); 
    });
    
    detailsModal?.querySelector('.btn-complete').addEventListener('click', () => {
        const eventId = parseInt(detailsModal.dataset.eventId);
        showConfirmModal('Are you sure you want to mark this as complete?', async () => {
            await api.events.update({ id: eventId, is_done: 1 });
            detailsModal.classList.remove('visible');
            renderCalendar();
        });
    });
    
    detailsModal?.querySelector('.btn-reschedule').addEventListener('click', () => {
        const eventId = parseInt(detailsModal.dataset.eventId);
        showConfirmModal('Are you sure you want to reschedule?', async () => {
            const res = await api.events.list();
            const event = res.items.find(e => e.id === eventId);
            detailsModal.classList.remove('visible');
            openScheduleModal(event);
        });
    });

    detailsModal?.querySelector('.btn-delete').addEventListener('click', () => {
        const eventId = parseInt(detailsModal.dataset.eventId);
        showConfirmModal('Are you sure you want to delete this event?', async () => {
            await api.events.delete(eventId);
            detailsModal.classList.remove('visible');
            renderCalendar();
        });
    });
}

export function initApp() {
    const appRoot = document.getElementById('app');
    if (appRoot) {
        appRoot.innerHTML = `
          <div id="tabs-root">
            <div class="tabs">
              <button data-tab="irma-pane" class="active">Irma's Dashboard <span class="tab-dot"></span></button>
              <button data-tab="josh-pane">Josh's Corner <span class="tab-dot"></span></button>
            </div>
            
            <div id="main-content">
                <div id="irma-pane" data-pane="irma-pane">
                    <div id="left-col"></div>
                    <div id="right-col"></div>
                    <div id="job-status-container"></div>
                </div>

                <div id="josh-pane" data-pane="josh-pane">
                    <div id="josh-pending-requests-card" class="card">
                        
                    </div>
                    <div class="card">
                        <h3>Request Scheduling</h3>
                        <form id="josh-request-form">
                            <input id="josh-request-title" placeholder="Title: Dentist Appointment" required>
                            <textarea id="josh-request-notes" placeholder="Request: Please schedule me a dentist appointment." required></textarea>
                            <button class="btn-primary" type="submit" style="background:#3b82f6;">Submit Request</button>
                        </form>
                    </div>
                </div>
            </div>

            <div id="calendar-container"></div>
          </div>
          
          <div id="scheduling-modal" class="modal"><div class="modal-content"><h3>Schedule Event</h3><input type="hidden" id="modal-event-id"><input type="hidden" id="modal-source-id"><input type="hidden" id="modal-source-type"><input type="text" id="modal-title" placeholder="Event Title"><input type="date" id="modal-date"><select id="modal-time">${Array.from({length:12},(_,i)=>i+8).map(h=>{const t=h%12||12;return`<option value="${String(h).padStart(2,"0")}:00">${t}:00 ${h<12?"AM":"PM"}</option>`}).join("")}</select><textarea id="modal-notes" placeholder="Notes..."></textarea><div class="modal-actions"><button class="btn-cancel">Cancel</button><button class="btn-save">Save</button></div></div></div>
          <div id="details-modal" class="modal"><div class="modal-content"><h3 id="details-title"></h3><p id="details-datetime"></p><div id="details-notes"></div><div class="modal-actions"><button class="btn-complete">Mark Complete</button><button class="btn-reschedule">Reschedule</button><button class="btn-delete">Delete</button><button class="btn-cancel">Close</button></div></div></div>
          <div id="confirm-modal" class="modal"><div class="modal-content"><h3 id="confirm-title">Are you sure?</h3><p id="confirm-message"></p><div class="modal-actions"><button id="confirm-no" class="btn-cancel">Cancel</button><button id="confirm-yes" class="btn-save">Confirm</button></div></div></div>
        `;
        
        document.querySelector('#left-col').innerHTML = `<div class="card"><h3>Send Job to Josh</h3><form id="irma-form"><input id="irma-title" placeholder="Job Title (e.g., Mow Lawn)"><textarea id="irma-notes" placeholder="Notes for the job..."></textarea><button class="btn-primary" type="submit">Send Job</button></form></div>`;
        document.querySelector('#right-col').innerHTML = `<div id="pending-requests-container" class="card"></div>`;
        document.querySelector('#job-status-container').innerHTML = `<div class="card"><h3>Job Status from Josh</h3><div id="job-status-list"></div></div>`;
        document.querySelector('#calendar-container').innerHTML = `<div class="card"><div class="calendar-nav"><button id="prev-month">◄ Prev</button><h3 id="month-year"></h3><button id="next-month">Next ►</button></div><div id="calendar-grid"></div></div>`;

        initTabs();
        initIrmaForms();
        initJoshForms();
        initCalendarNav();
        renderCalendar();
        initModals();
    }
}
