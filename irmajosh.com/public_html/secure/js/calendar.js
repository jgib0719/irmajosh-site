
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

import { eventsApi } from './api.js';

const state = { current: new Date() };
const weekA = [1,2,5,6,0]; // Mon,Tue,Fri,Sat,Sun
const weekB = [3,4];      // Wed,Thu
const referenceDate = new Date('2025-08-25T00:00:00'); // change if your rotation changes

function diffWeeks(a,b){ const ms=1000*60*60*24*7; const A=new Date(a.getFullYear(),a.getMonth(),a.getDate()); const B=new Date(b.getFullYear(),b.getMonth(),b.getDate()); A.setHours(0,0,0,0); B.setHours(0,0,0,0); return Math.floor((A-B)/ms); }
function isWorkDay(date){ const parity = Math.abs(diffWeeks(date, referenceDate)) % 2 === 0; return (parity?weekA:weekB).includes(date.getDay()); }

export function openScheduleModal(sourceId, sourceType, title){
  document.getElementById('modal-source-id').value = sourceId || '';
  document.getElementById('modal-source-type').value = sourceType || '';
  document.getElementById('modal-title').value = title || '';
  document.getElementById('modal-notes').value = '';
  document.getElementById('modal-date').valueAsDate = new Date();
  document.getElementById('scheduling-modal').classList.remove('hidden');
  document.getElementById('modal-title').focus();
}
export function closeScheduleModal(){ document.getElementById('scheduling-modal').classList.add('hidden'); }

export function showEventDetails(evt){
  document.getElementById('details-id').value = String(evt.id);
  document.getElementById('details-title').textContent = evt.title || '';
  let dateStr = new Date(evt.date+'T00:00:00').toLocaleDateString('en-US',{weekday:'long',year:'numeric',month:'long',day:'numeric'});
  if (evt.time){ const t = new Date('1970-01-01T'+evt.time+':00').toLocaleTimeString('en-US',{hour:'numeric',minute:'2-digit',hour12:true}); dateStr += ' at '+t; }
  document.getElementById('details-date').textContent = dateStr;
  document.getElementById('details-notes').textContent = evt.notes || 'No notes for this event.';
  document.getElementById('details-modal').classList.remove('hidden');
}
export function closeEventDetails(){ document.getElementById('details-modal').classList.add('hidden'); }

export async function renderCalendar(){
  const out = document.getElementById('calendar');
  const header = document.getElementById('month-year');
  const m = state.current.getMonth(), y = state.current.getFullYear();
  header.textContent = state.current.toLocaleString('default',{month:'long',year:'numeric'});

  const res = await eventsApi.list();
  const events = res.items || [];
  const idx = new Map();
  for (const e of events){ if (!idx.has(e.date)) idx.set(e.date, []); idx.get(e.date).push(e); }

  out.innerHTML='';
  const first = new Date(y,m,1);
  const start = first.getDay();
  const days  = new Date(y,m+1,0).getDate();
  for (let i=0;i<start;i++) out.appendChild(document.createElement('div'));

  const today = new Date(); today.setHours(0,0,0,0);
  for (let d=1; d<=days; d++){
    const cell = document.createElement('div');
    const date = new Date(y,m,d);
    const dateStr = `${y}-${String(m+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;

    const dn = document.createElement('div');
    dn.className='day-num';
    dn.textContent=String(d);
    if (isWorkDay(date)) dn.classList.add('work');
    if (date.getTime()===today.getTime()) dn.classList.add('today');
    cell.appendChild(dn);

    const list = idx.get(dateStr) || [];
    for (const e of list){
      const chip = document.createElement('div');
      chip.className='event';
      chip.textContent=e.title||'(no title)';
      chip.addEventListener('click',()=>showEventDetails(e));
      cell.appendChild(chip);
    }
    out.appendChild(cell);
  }
}

export function initCalendarNav(){
  document.getElementById('prev-month').addEventListener('click', async ()=>{ state.current.setMonth(state.current.getMonth()-1); await renderCalendar(); });
  document.getElementById('next-month').addEventListener('click', async ()=>{ state.current.setMonth(state.current.getMonth()+1); await renderCalendar(); });
  document.addEventListener('calendar:refresh', renderCalendar);
}

export function populateTimeSlots(){
  const sel = document.getElementById('modal-time');
  sel.innerHTML='';
  for (let h=0; h<24; h++) for (let m=0; m<60; m+=15){
    const v = `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}`;
    const label = new Date('1970-01-01T'+v+':00').toLocaleTimeString('en-US',{hour:'numeric',minute:'2-digit',hour12:true});
    const opt = document.createElement('option'); opt.value=v; opt.textContent=label; sel.appendChild(opt);
  }
}
