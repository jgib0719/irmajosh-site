/*
 * DEPRECATED: This legacy calendar implementation is no longer invoked by the
 * current secure UI (`ui.js` now owns calendar rendering inside #calendar-grid).
 * Keep temporarily for reference while new mobile badge calendar stabilizes.
 * Safe to delete once new calendar requirements are final.
 */
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
const weekA = [1,2,5,6,0]; // Mon,Tue,Fri,Sat,Sun (Irma/JS work rotation)
const weekB = [3,4];      // Wed,Thu
// Reference date controls parity of the alternating rotation. Change as needed
let referenceDate = new Date('2025-08-25T00:00:00'); // adjust if rotation offset differs

export function setReferenceDate(d){ try { referenceDate = new Date(d); } catch(e){ console.warn('Invalid reference date', d); } }

export function getReferenceDate(){ return referenceDate.toISOString().slice(0,10); }

export function shiftReferenceDate(days){
  try{ referenceDate.setDate(referenceDate.getDate()+Number(days)); }
  catch(e){ console.warn('shiftReferenceDate failed', e); }
}

// Worker pattern controls: 'parity' means worker works on isWorkDay(date),
// 'inverse' means worker works on !isWorkDay(date). Defaults below.
const workerPatterns = { irma: 'parity', josh: 'inverse' };
export function setWorkerPattern(worker, pattern){ if (worker && (pattern==='parity' || pattern==='inverse')) workerPatterns[worker]=pattern; }
export function getWorkerPatterns(){ return Object.assign({}, workerPatterns); }

// Explicit repeating schedules: specify a startDate and an array of booleans
// representing consecutive days. The pattern repeats indefinitely.
const workerSchedules = {
  // Default Josh schedule provided by user starting 2025-09-07 (Sunday) - OFF
  // Pattern (14 days): 0=off,1=work
  // 9/7 OFF, 9/8 work, 9/9 work, 9/10 off, 9/11 off, 9/12 work, 9/13 work, 9/14 work,
  // 9/15 off, 9/16 off, 9/17 work, 9/18 work, 9/19 off, 9/20 off
  josh: {
    startDate: new Date('2025-09-07T00:00:00'),
    pattern: [0,1,1,0,0,1,1,1,0,0,1,1,0,0]
  }
};

export function setWorkerSchedule(worker, startDateISO, patternArray){
  try{
    const sd = new Date(startDateISO);
    const p = Array.isArray(patternArray) ? patternArray.map(v=>v?1:0) : [];
    if (!isFinite(sd.getTime()) || p.length===0) throw new Error('invalid schedule');
    workerSchedules[worker] = { startDate: sd, pattern: p };
  } catch(e){ console.warn('setWorkerSchedule failed', e); }
}

export function getWorkerSchedule(worker){ return workerSchedules[worker] ? { startDate: workerSchedules[worker].startDate.toISOString().slice(0,10), pattern: workerSchedules[worker].pattern.slice() } : null }

function daysDiff(a,b){ const A=new Date(a.getFullYear(),a.getMonth(),a.getDate()); const B=new Date(b.getFullYear(),b.getMonth(),b.getDate()); A.setHours(0,0,0,0); B.setHours(0,0,0,0); const diff = Math.round((A-B)/(1000*60*60*24)); return diff; }

function workerWorksOn(worker, date){
  // If explicit schedule exists, use it
  if (workerSchedules[worker]){
    const sched = workerSchedules[worker];
    const offset = daysDiff(date, sched.startDate);
    // support dates before startDate by modulo as well
    const idx = ((offset % sched.pattern.length) + sched.pattern.length) % sched.pattern.length;
    return Boolean(sched.pattern[idx]);
  }
  // Fallback to parity patterns
  const pattern = workerPatterns[worker] || 'parity';
  return (pattern === 'parity') ? isWorkDay(date) : !isWorkDay(date);
}

function diffWeeks(a,b){ const ms=1000*60*60*24*7; const A=new Date(a.getFullYear(),a.getMonth(),a.getDate()); const B=new Date(b.getFullYear(),b.getMonth(),b.getDate()); A.setHours(0,0,0,0); B.setHours(0,0,0,0); return Math.floor((A-B)/ms); }
function isWorkDay(date){ const parity = Math.abs(diffWeeks(date, referenceDate)) % 2 === 0; return (parity?weekA:weekB).includes(date.getDay()); }

export function openScheduleModal(sourceId, sourceType, title, date, activeTab){
  document.getElementById('modal-source-id').value = sourceId || '';
  document.getElementById('modal-source-type').value = sourceType || '';
  document.getElementById('modal-title').value = title || '';
  document.getElementById('modal-notes').value = '';
  try{
    if (date) {
      // date expected as YYYY-MM-DD or Date-like
      document.getElementById('modal-date').value = (new Date(date)).toISOString().slice(0,10);
    } else {
      document.getElementById('modal-date').valueAsDate = new Date();
    }
  } catch(e){ document.getElementById('modal-date').valueAsDate = new Date(); }
  document.getElementById('scheduling-modal').classList.remove('hidden');
  const mt = document.getElementById('modal-title'); if (mt) mt.focus();
  // Populate time slots. If the activeTab corresponds to a worker who is NOT
  // scheduled to work that date, allow full-day slots (no shift limit).
  try{
    const selDate = (function(){ try{ return new Date((new Date(date)).toDateString()); } catch(e){ const d=new Date(); d.setHours(0,0,0,0); return d; } })();
    // Determine worker and whether they work that date. By default, assume
    // 'jobs' tab = Irma, 'requests' tab = Josh. Irma works on isWorkDay(date).
  const worker = (activeTab === 'requests') ? 'josh' : 'irma';
  const workerWorks = workerWorksOn(worker, selDate);
  // Always allow full-day scheduling in 30-minute increments
  populateTimeSlots(0,24,30);
    const sel = document.getElementById('modal-time');
    // If scheduling for today, pick nearest upcoming slot
  const pickedDate = new Date((new Date(date)).toDateString());
    const today = new Date(); today.setHours(0,0,0,0);
    if (pickedDate.getTime() === today.getTime()){
  // choose nearest 30-min slot >= now
  const now = new Date();
  const hh = now.getHours();
  const mm = now.getMinutes();
  const round = Math.ceil(mm/30)*30;
      let candidateH = hh;
      let candidateM = round;
      if (candidateM >= 60){ candidateH += 1; candidateM = 0; }
      const cand = `${String(candidateH).padStart(2,'0')}:${String(candidateM).padStart(2,'0')}`;
      // Find first option >= cand
      let found = false;
      for (const opt of sel.options){ if (opt.value >= cand){ sel.value = opt.value; found = true; break; } }
      if (!found && sel.options.length) sel.value = sel.options[sel.options.length-1].value;
    }
  } catch(e){ console.warn('[calendar] populateTimeSlots failed', e); }
}
export function closeScheduleModal(){ document.getElementById('scheduling-modal').classList.add('hidden'); }

export function showEventDetails(evt){
  document.getElementById('details-id').value = String(evt.id);
  // store event id for potential reschedule
  const existingEventIdInput = document.getElementById('modal-event-id');
  if (existingEventIdInput) existingEventIdInput.value = String(evt.id);
  document.getElementById('details-title').textContent = evt.title || '';
  let dateStr = new Date(evt.date+'T00:00:00').toLocaleDateString('en-US',{weekday:'long',year:'numeric',month:'long',day:'numeric'});
  if (evt.time){ const t = new Date('1970-01-01T'+evt.time+':00').toLocaleTimeString('en-US',{hour:'numeric',minute:'2-digit',hour12:true}); dateStr += ' at '+t; }
  // Append an invisible ISO date to help reschedule parsing
  document.getElementById('details-date').innerHTML = dateStr + ' <span class="iso-date" style="display:none">' + evt.date + '</span>';
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
      if (e.is_done && Number(e.is_done)) {
        chip.classList.add('done');
      }
  chip.addEventListener('click',(ev)=>{ ev.stopPropagation(); showEventDetails(e); });
  chip.setAttribute('role','button');
  chip.tabIndex = 0;
  chip.addEventListener('keydown',(ev)=>{ if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); showEventDetails(e); } });
      cell.appendChild(chip);
    }
    // If no events / even if there are, make the cell clickable to schedule
    cell.addEventListener('click',(ev)=>{
      // Avoid triggering when clicking an event chip
      if (ev.target && ev.target.classList && ev.target.classList.contains('event')) return;
      // Determine active tab to know which worker is scheduling
      const active = document.querySelector('#tabs-root .tabs button.active');
      const activeTab = active ? active.getAttribute('data-tab') : 'jobs';
      openScheduleModal('', 'manual', '', dateStr, activeTab);
    });
    out.appendChild(cell);
  }
}

export function initCalendarNav(){
  document.getElementById('prev-month').addEventListener('click', async ()=>{ state.current.setMonth(state.current.getMonth()-1); await renderCalendar(); });
  document.getElementById('next-month').addEventListener('click', async ()=>{ state.current.setMonth(state.current.getMonth()+1); await renderCalendar(); });
  document.addEventListener('calendar:refresh', renderCalendar);
}

export function populateTimeSlots(startH=0, endH=24, step=30){
  // startH/endH are hour integers (0-24). This will create options from
  // startH:00 up to endH:00 in `step` minute increments. If endH===24 the
  // last option will be 23:45.
  const sel = document.getElementById('modal-time');
  if (!sel) return;
  sel.innerHTML='';
  for (let h = startH; h < endH; h++){
    for (let m = 0; m < 60; m += step){
      const v = `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}`;
      const label = new Date('1970-01-01T'+v+':00').toLocaleTimeString('en-US',{hour:'numeric',minute:'2-digit',hour12:true});
      const opt = document.createElement('option'); opt.value=v; opt.textContent=label; sel.appendChild(opt);
    }
  }
}
