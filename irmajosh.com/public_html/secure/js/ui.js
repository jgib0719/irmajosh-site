
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

export function initTabs(){
  const irmaBtn = document.querySelector('[data-tab="irma"]');
  const joshBtn = document.querySelector('[data-tab="josh"]');
  const irmaTab = document.getElementById('irma-tab');
  const joshTab = document.getElementById('josh-tab');

  function setTab(which){
    if (which==='irma'){ irmaBtn.classList.add('active'); joshBtn.classList.remove('active'); irmaTab.classList.remove('hidden'); joshTab.classList.add('hidden'); }
    else { joshBtn.classList.add('active'); irmaBtn.classList.remove('active'); joshTab.classList.remove('hidden'); irmaTab.classList.add('hidden'); }
  }

  irmaBtn.addEventListener('click', ()=>setTab('irma'));
  joshBtn.addEventListener('click', ()=>setTab('josh'));
  setTab('irma');
}

export function initForms(){
  const irmaForm = document.getElementById('irma-form');
  const joshForm = document.getElementById('josh-form');

  irmaForm.addEventListener('submit', async (e)=>{
    e.preventDefault();
    if (!getUser()) return showToast('Sign in first');
    const title = document.getElementById('irma-title').value.trim();
    const notes = document.getElementById('irma-notes').value.trim();
    if (!title) return;
    try { await jobsApi.create({ title, notes }); document.getElementById('irma-title').value=''; document.getElementById('irma-notes').value=''; await refreshLists(); }
    catch(err){ showToast('Failed to post job: '+err.message); }
  });

  joshForm.addEventListener('submit', async (e)=>{
    e.preventDefault();
    if (!getUser()) return showToast('Sign in first');
    const title = document.getElementById('josh-title').value.trim();
    const notes = document.getElementById('josh-notes').value.trim();
    if (!title) return;
    try { await requestsApi.create({ title, notes }); document.getElementById('josh-title').value=''; document.getElementById('josh-notes').value=''; await refreshLists(); }
    catch(err){ showToast('Failed to submit request: '+err.message); }
  });
}

export async function refreshLists(){
  const irmaBadge = document.getElementById('irma-badge');
  const joshBadge = document.getElementById('josh-badge');
  const available = await jobsApi.list(STATUSES.JOB.AVAILABLE);
  const accepted  = await jobsApi.list(STATUSES.JOB.ACCEPTED);
  const pending   = await requestsApi.list(STATUSES.REQUEST.PENDING);

  if (available.items.length){ irmaBadge.textContent = available.items.length; irmaBadge.classList.remove('hidden'); } else irmaBadge.classList.add('hidden');
  if (pending.items.length){ joshBadge.textContent = pending.items.length; joshBadge.classList.remove('hidden'); } else joshBadge.classList.add('hidden');

  renderList(document.getElementById('available-jobs'), available.items, 'job');
  renderList(document.getElementById('accepted-jobs'), accepted.items, 'accepted-job');
  renderList(document.getElementById('pending-requests'), pending.items, 'request');
}

function el(tag, cls, text){ const e=document.createElement(tag); if(cls) e.className=cls; if(text!=null) e.textContent=text; return e; }

function renderList(root, items, type){
  root.innerHTML='';
  if (!items.length){ root.appendChild(el('p','text-muted','Nothing here right now.')); return; }
  for (const item of items){
    const box = el('div','card');
    const title = el('p','title',item.title||'');
    const notes = el('p','notes',item.notes||'');
    const btn = el('button','','');

    if (type==='job'){
      btn.textContent='Accept Job';
      btn.addEventListener('click', async () => {
        if (!getUser()) return showToast('Sign in first');
        try { await jobsApi.accept(item.id); await refreshLists(); } catch(e){ showToast(e.message); }
      });
    } else if (type==='accepted-job'){
      btn.textContent='Schedule';
      btn.addEventListener('click', ()=>openScheduleModal(String(item.id),'job',item.title||''));
    } else if (type==='request'){
      btn.textContent='Schedule';
      btn.addEventListener('click', ()=>openScheduleModal(String(item.id),'request',item.title||''));
    }

    box.appendChild(title); box.appendChild(notes); box.appendChild(btn);
    root.appendChild(box);
  }
}
