export function initApp(){ console.warn('[secure] initApp stub - UI is under rebuild'); const el=document.getElementById('app'); if (el) el.textContent='Secure UI temporarily offline for rebuild'; }

function showToast(msg) {
  let div = document.createElement('div');
  div.className = 'toast';
  div.textContent = msg;
  Object.assign(div.style, {
    position: 'fixed', bottom: '1rem', right: '1rem',
    background: '#333', color: '#fff', padding: '0.5rem 1rem',
    borderRadius: '4px', zIndex: 10000
  });
  document.body.appendChild(div);
  setTimeout(() => div.remove(), 3000);
}

import { requestsApi, jobsApi } from './api.js';
import { STATUSES } from './constants.js';
import { openScheduleModal } from './calendar.js';
import { getUser } from './auth.js';

export function initTabs() {
  const tabsRoot = document.getElementById('tabs-root');
  if (!tabsRoot) {
    console.warn('[ui] tabs-root not present; skipping tab wiring');
    return;
  }
  const btns = tabsRoot.querySelectorAll('[data-tab]');
  const panes = tabsRoot.querySelectorAll('[data-pane]');
  if (!btns.length || !panes.length) {
    console.warn('[ui] no tabs/panes found; skipping');
    return;
  }
  btns.forEach(btn => {
    btn.addEventListener('click', () => {
      const t = btn.getAttribute('data-tab');
      // set active class
      btns.forEach(b=>b.classList.toggle('active', b===btn));
      // show/hide panes
      panes.forEach(p => p.hidden = (p.getAttribute('data-pane') !== t));
      // show/hide form controls: only show Irma's Post Job on Irma tab
      const irmaCard = document.querySelector('#irma-form') ? document.getElementById('irma-form').closest('.card') : null;
      if (irmaCard) irmaCard.style.display = (t==='jobs') ? '' : 'none';
      // refresh calendar/render if needed
      document.dispatchEvent(new CustomEvent('ui:tab-change',{detail:{tab:t}}));
    });
  });
  // Ensure a default active tab is visible on init
  if (btns.length) {
    // prefer the jobs tab if present
    const preferred = Array.from(btns).find(b=>b.getAttribute('data-tab')==='jobs') || btns[0];
    preferred.click();
  }
}

export function initForms(){
  const irmaForm = document.getElementById('irma-form');
  const joshForm = document.getElementById('josh-form');

  // Guard: forms may not be present in every UI variant (avoid TypeError)
  if (irmaForm) {
    irmaForm.addEventListener('submit', async (e)=>{
      e.preventDefault();
      if (!getUser()) return showToast('Sign in first');
      const titleEl = document.getElementById('irma-title');
      const notesEl = document.getElementById('irma-notes');
      const title = titleEl ? titleEl.value.trim() : '';
      const notes = notesEl ? notesEl.value.trim() : '';
      if (!title) return;
      try {
        await jobsApi.create({ title, notes });
        if (titleEl) titleEl.value = '';
        if (notesEl) notesEl.value = '';
        await refreshLists();
      }
      catch(err){ showToast('Failed to post job: '+err.message); }
    });
  } else {
    console.warn('[ui] irma-form not present; skipping irma form wiring');
  }

  if (joshForm) {
    joshForm.addEventListener('submit', async (e)=>{
      e.preventDefault();
      if (!getUser()) return showToast('Sign in first');
      const titleEl = document.getElementById('josh-title');
      const notesEl = document.getElementById('josh-notes');
      const title = titleEl ? titleEl.value.trim() : '';
      const notes = notesEl ? notesEl.value.trim() : '';
      if (!title) return;
      try {
        await requestsApi.create({ title, notes });
        if (titleEl) titleEl.value = '';
        if (notesEl) notesEl.value = '';
        await refreshLists();
      }
      catch(err){ showToast('Failed to submit request: '+err.message); }
    });
  } else {
    console.warn('[ui] josh-form not present; skipping josh form wiring');
  }
}

export async function refreshLists(){
  // Only update tab badges; inline form badges removed for cleaner DOM
  const available = await jobsApi.list(STATUSES.JOB.AVAILABLE);
  const accepted  = await jobsApi.list(STATUSES.JOB.ACCEPTED);
  const pending   = await requestsApi.list(STATUSES.REQUEST.PENDING);

  // Tabs numeric dot indicators
  const irmaDot = document.getElementById('irma-dot');
  const joshDot = document.getElementById('josh-dot');
  if (irmaDot) {
    if (available.items.length) { irmaDot.textContent = available.items.length; irmaDot.classList.remove('hidden'); }
    else { irmaDot.classList.add('hidden'); }
  }
  if (joshDot) {
    if (pending.items.length) { joshDot.textContent = pending.items.length; joshDot.classList.remove('hidden'); }
    else { joshDot.classList.add('hidden'); }
  }

  renderList(document.getElementById('available-jobs'), available.items, 'job');
  renderList(document.getElementById('accepted-jobs'), accepted.items, 'accepted-job');
  renderList(document.getElementById('pending-requests'), pending.items, 'request');
}

function el(tag, cls, text){ const e=document.createElement(tag); if(cls) e.className=cls; if(text!=null) e.textContent=text; return e; }

function renderList(root, items, type){
  if (!root) return; // Guard: if the container is absent, skip rendering
  root.innerHTML='';
  if (!items.length){ root.appendChild(el('p','text-muted','Nothing here right now.')); return; }
  for (const item of items){
    const box = el('div','card');
    const title = el('p','title',item.title||'');
    const notes = el('p','notes',item.notes||'');
    const btn = el('button','','');

    const activeTab = () => {
      const active = document.querySelector('#tabs-root .tabs button.active');
      return active ? active.getAttribute('data-tab') : 'jobs';
    };

    if (type==='job'){
      btn.textContent='Accept Job';
      btn.addEventListener('click', async () => {
        if (!getUser()) return showToast('Sign in first');
        try { await jobsApi.accept(item.id); await refreshLists(); } catch(e){ showToast(e.message); }
      });
    } else if (type==='accepted-job'){
      btn.textContent='Schedule';
      btn.className = 'btn-schedule';
      btn.addEventListener('click', ()=>openScheduleModal(String(item.id),'job',item.title||'', null, activeTab()));
      // Make accepted-job cards have full-width schedule button
      box.style.display='flex'; box.style.flexDirection='column'; box.style.alignItems='stretch';
      btn.style.width='100%';
    } else if (type==='request'){
      // Pending request: title, notes, and a full-width schedule button stacked vertically
      btn.textContent='Schedule';
      btn.className = 'btn-schedule';
      btn.addEventListener('click', ()=>openScheduleModal(String(item.id),'request',item.title||'', null, activeTab()));
      box.style.display='flex'; box.style.flexDirection='column'; box.style.alignItems='stretch';
      title.style.textAlign = 'left'; notes.style.textAlign = 'left';
      btn.style.width = '100%'; btn.style.marginTop = '.5rem';
      box.innerHTML='';
      box.appendChild(title);
      box.appendChild(notes);
      box.appendChild(btn);
    }

    // Layout: title, notes, button on separate lines
    title.style.margin = '0 0 .25rem 0';
    notes.style.margin = '0 0 .5rem 0';
    btn.style.display = 'inline-block'; btn.style.width = 'auto';

    box.appendChild(title);
    box.appendChild(notes);
    box.appendChild(btn);
    root.appendChild(box);
  }
}

export function initApp() {
  try {
  // Add secure-dark class so the secure area can present a dark shell while
  // keeping internal cards/calendars light for contrast.
  try { document.body.classList.add('secure-dark'); } catch(e){}
    // Ensure a minimal UI skeleton exists so the secure area can render even
    // when `secure/index.html` is a minimal shell with only `#app`.
    const appRoot = document.getElementById('app');
    if (appRoot) {
      // Inject the mock CSS and markup by default (the backup is in ui.js.bak)
      try {
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = '/secure/css/edit-dashboard.css';
        document.head.appendChild(link);
        // replace app with mock markup
        appRoot.innerHTML = ''; // clear
        const ui = document.createElement('div'); ui.id = 'tabs-root';
        ui.innerHTML = `
          <div class="tabs">
            <button data-tab="irma" class="active">Irma's Dashboard <span class="tab-dot">2</span></button>
            <button data-tab="josh">Josh's Corner <span class="tab-dot">1</span></button>
          </div>

          <div id="pane-jobs">
            <div id="left-col">
              <div class="card">
                <h3>Send Request to Josh</h3>
                <form id="irma-form">
                  <input id="irma-title" placeholder="Job Title (e.g., Mow Lawn)">
                  <textarea id="irma-notes" placeholder="Notes for the job..."></textarea>
                  <button class="btn-primary" type="submit">Send Request</button>
                </form>
              </div>
            </div>
            <div id="right-col">
              <div id="pending-requests-container" class="card">
                  <h3>Pending Requests from Josh</h3>
                  <div id="pending-requests"></div>
              </div>
            </div>
            <div id="accepted-jobs-container">
                <div class="card">
                    <h3>Accepted Jobs to Schedule</h3>
                    <div id="accepted-jobs"></div>
                </div>
            </div>
          </div>
        `;
        appRoot.appendChild(ui);
        // wire the same inits
        initTabs(); initForms(); refreshLists();
        // continue with default flow as needed
      } catch(err) { console.warn('[ui] mock injection failed', err); }
      // If essential containers are missing, create a simple skeleton.
      if (!document.getElementById('tabs-root')) {
  console.log('[ui] creating tabs-root skeleton');
        const tabs = document.createElement('div');
        tabs.id = 'tabs-root';
        tabs.innerHTML = `
          <div class="tabs">
            <button data-tab="jobs">Irma's Dashboard <span id="irma-dot" class="tab-dot hidden" aria-hidden="true"></span></button>
            <button data-tab="requests">Josh's Corner <span id="josh-dot" class="tab-dot hidden" aria-hidden="true"></span></button>
          </div>

          <div data-pane="jobs" id="pane-jobs">
            <div id="left-col">
              <div id="forms-top"></div>
            </div>
            <div id="center-col">
              <div id="available-jobs"></div>
              <h3 style="text-align:center;margin:1rem 0">Accepted Jobs to Schedule</h3>
              <div id="accepted-jobs"></div>
            </div>
            <div id="right-col">
              <h3 style="text-align:center;margin:0 0 .5rem 0">Pending Requests from Josh</h3>
              <div id="pending-requests"></div>
            </div>
          </div>

          <div id="calendar-wrap">
            <h3 id="month-year"></h3>
            <div id="calendar" style="display:grid;grid-template-columns:repeat(7,1fr);gap:.25rem;margin-top:.5rem"></div>
            <div class="nav-row"><button id="prev-month">◀</button><button id="next-month">▶</button></div>
          </div>
        `;
        appRoot.appendChild(tabs);
      }

      if (!document.getElementById('irma-form') && !document.getElementById('josh-form')) {
  console.log('[ui] creating forms skeleton');
  const forms = document.createElement('div');
        forms.id = 'forms-root';
        forms.innerHTML = `
          <div style="display:flex;flex-direction:column;gap:.75rem;align-items:stretch">
            <div class="card">
              <div class="title">Post a Job for Josh</div>
              <form id="irma-form" style="display:flex;flex-direction:column;gap:.5rem;">
                <input id="irma-title" placeholder="Job Title (e.g., Mow Lawn)">
                <textarea id="irma-notes" placeholder="Notes for the job..." rows="3"></textarea>
                <button class="btn-primary" type="submit">Post Job</button>
              </form>
            </div>
            <div class="card">
              <div class="title">Post a Request</div>
              <form id="josh-form" style="display:flex;flex-direction:column;gap:.5rem;">
                <input id="josh-title" placeholder="Request title">
                <textarea id="josh-notes" placeholder="Notes for the request..." rows="3"></textarea>
                <button class="btn-primary" type="submit">Submit Request</button>
              </form>
            </div>
          </div>
            <!-- Scheduling modal (hidden until used) -->
            <div id="scheduling-modal" class="modal hidden" role="dialog" aria-modal="true" aria-labelledby="scheduling-heading">
              <div class="modal-content">
                <h3 id="scheduling-heading">Schedule</h3>
                <input id="modal-event-id" type="hidden">
                <input id="modal-source-id" type="hidden">
                <input id="modal-source-type" type="hidden">
                <div style="display:flex;align-items:center;gap:.5rem;justify-content:center">
                  <label for="modal-title">Title</label>
                  <input id="modal-title">
                  <label for="modal-date">Date</label>
                  <input id="modal-date" type="date">
                  <label for="modal-time">Time</label>
                  <select id="modal-time"></select>
                </div>
                <div style="display:flex;justify-content:center;margin-top:.5rem">
                  <label for="modal-notes">Notes</label>
                  <textarea id="modal-notes"></textarea>
                </div>
                <div class="modal-actions"><button id="modal-save">Save</button><button id="modal-cancel">Cancel</button></div>
              </div>
            </div>

            <!-- Details modal -->
            <div id="details-modal" class="modal hidden" role="dialog" aria-modal="true" aria-labelledby="details-heading">
              <div class="modal-content">
                <h3 id="details-heading">Event details</h3>
                <input id="details-id" type="hidden">
                <h4 id="details-title"></h4>
                <p id="details-date"></p>
                <p id="details-notes"></p>
                <div class="modal-actions"><button id="details-complete">Completed</button><button id="details-reschedule">Reschedule</button><button id="details-delete">Delete</button><button id="details-close">Close</button></div>
              </div>
            </div>
        `;
        // Insert forms into the top of the jobs pane (above calendar)
        const jobsPaneTop = document.getElementById('forms-top');
        if (jobsPaneTop) jobsPaneTop.appendChild(forms);
        else appRoot.appendChild(forms);
      }
      // If calendar functions are available, initialize nav and render
      try{
        (async ()=>{
          const cal = await import('./calendar.js');
          if (typeof cal.initCalendarNav === 'function') cal.initCalendarNav();
          if (typeof cal.populateTimeSlots === 'function') cal.populateTimeSlots();
          if (typeof cal.renderCalendar === 'function') await cal.renderCalendar();
          console.log('[ui] calendar render complete');

          // Wire modal actions to create events and refresh UI
          const save = document.getElementById('modal-save');
          const cancel = document.getElementById('modal-cancel');
              if (save) save.addEventListener('click', async (e)=>{
            e.preventDefault();
            try {
              const modalEventId = document.getElementById('modal-event-id').value;
              const sourceId = document.getElementById('modal-source-id').value;
              const sourceType = document.getElementById('modal-source-type').value;
              const title = document.getElementById('modal-title').value.trim();
              const date = document.getElementById('modal-date').value;
              const time = document.getElementById('modal-time').value;
              const notes = document.getElementById('modal-notes').value.trim();
              if (!title || !date) return showToast('Please provide title and date');
              const { eventsApi } = await import('./api.js');
              // If modalEventId present, update existing event instead of creating
              if (modalEventId && modalEventId.length) {
                const upd = { id: modalEventId, title, date, time: time ? time : null, notes: notes ? notes : null };
                console.log('[ui] updating event', JSON.stringify(upd));
                await eventsApi.update(upd);
                document.getElementById('modal-event-id').value = '';
              } else {
                // Sanitize payload: only allow valid source_type values and convert empty strings to null
                const payload = {};
                payload.title = title;
                payload.date = date;
                payload.time = time ? time : null;
                payload.notes = notes ? notes : null;
                payload.source_id = sourceId && sourceId.length ? sourceId : null;
                // source_type must be 'job' or 'request' per DB enum; treat others as null
                payload.source_type = (sourceType === 'job' || sourceType === 'request') ? sourceType : null;
                console.log('[ui] scheduling payload (sanitized)', JSON.stringify(payload));
                await eventsApi.create(payload);
              }
              // close modal, refresh calendar and lists
              document.getElementById('scheduling-modal').classList.add('hidden');
              document.dispatchEvent(new Event('calendar:refresh'));
              await refreshLists();
            } catch(err){ showToast('Failed to schedule: '+(err.message||err)); }
          });
          if (cancel) cancel.addEventListener('click', (e)=>{ e.preventDefault(); document.getElementById('scheduling-modal').classList.add('hidden'); });

          const detailsClose = document.getElementById('details-close');
          if (detailsClose) detailsClose.addEventListener('click', ()=>document.getElementById('details-modal').classList.add('hidden'));

          // Details modal actions: complete / reschedule / delete
          const detailsComplete = document.getElementById('details-complete');
          const detailsReschedule = document.getElementById('details-reschedule');
          const detailsDelete = document.getElementById('details-delete');
          if (detailsComplete) detailsComplete.addEventListener('click', async ()=>{
            const btn = detailsComplete;
            try {
              btn.disabled = true;
              const id = document.getElementById('details-id').value;
              const { eventsApi } = await import('./api.js');
              await eventsApi.update({ id, is_done: 1 });
              document.getElementById('details-modal').classList.add('hidden');
              document.dispatchEvent(new Event('calendar:refresh'));
              await refreshLists();
            } catch(e){ showToast('Failed to mark complete: '+(e.message||e)); }
            finally { btn.disabled = false; }
          });
          if (detailsReschedule) detailsReschedule.addEventListener('click', async ()=>{
            // open schedule modal pre-filled with event data
            try {
              const id = document.getElementById('details-id').value;
              const title = document.getElementById('details-title').textContent;
              const dateText = document.getElementById('details-date').textContent;
              // Find ISO date in the displayed date text (YYYY-MM-DD)
              const iso = dateText.match(/(\d{4}-\d{2}-\d{2})/); // may be present
              const date = iso ? iso[1] : null;
              document.getElementById('details-modal').classList.add('hidden');
              // set the modal-event-id so Save knows to update rather than create
              const modalEventIdInput = document.getElementById('modal-event-id');
              if (modalEventIdInput) modalEventIdInput.value = id;
              const active = document.querySelector('#tabs-root .tabs button.active');
              const activeTab = active ? active.getAttribute('data-tab') : 'jobs';
              openScheduleModal(id, null, title, date, activeTab);
            } catch(e){ showToast('Failed to reschedule: '+(e.message||e)); }
          });
          if (detailsDelete) detailsDelete.addEventListener('click', async ()=>{
            const btn = detailsDelete;
            try {
              if (!confirm('Delete this event? This cannot be undone.')) return;
              btn.disabled = true;
              const id = document.getElementById('details-id').value;
              const { eventsApi } = await import('./api.js');
              await eventsApi.remove(id);
              document.getElementById('details-modal').classList.add('hidden');
              document.dispatchEvent(new Event('calendar:refresh'));
              await refreshLists();
            } catch(e){ showToast('Failed to delete: '+(e.message||e)); }
            finally { btn.disabled = false; }
          });

          // Accessibility: add ARIA labels and keyboard shortcuts
          try {
            const setAria = () => {
              if (save) save.setAttribute('aria-label','Save event');
              if (cancel) cancel.setAttribute('aria-label','Cancel scheduling');
              if (detailsClose) detailsClose.setAttribute('aria-label','Close details');
              if (detailsComplete) detailsComplete.setAttribute('aria-label','Mark completed');
              if (detailsReschedule) detailsReschedule.setAttribute('aria-label','Reschedule event');
              if (detailsDelete) detailsDelete.setAttribute('aria-label','Delete event');
            };
            setAria();

            // Close modals with Escape key
            document.addEventListener('keydown', (ev)=>{
              if (ev.key === 'Escape'){
                const sched = document.getElementById('scheduling-modal');
                const det = document.getElementById('details-modal');
                let handled = false;
                if (sched && !sched.classList.contains('hidden')){ sched.classList.add('hidden'); handled = true; }
                if (det && !det.classList.contains('hidden')){ det.classList.add('hidden'); handled = true; }
                if (handled) ev.preventDefault();
              }
            });
          } catch(e) { console.warn('[ui] aria setup failed', e); }

        })();
      } catch(e) {
        console.warn('[ui] calendar module unavailable', e);
      }
    }

    initTabs();
    initForms();
    refreshLists();
  } catch (e) {
    console.error('[ui] initApp failed', e);
  }
}