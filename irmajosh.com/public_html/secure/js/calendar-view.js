// /secure/js/calendar-view.js — simple month grid (no external libs)
// Renders into a container id you pass; prev/next month controls
// (This is a read-only visual calendar; scheduling still uses openScheduleModal via list buttons)

function startOfMonth(d){ return new Date(d.getFullYear(), d.getMonth(), 1); }
function endOfMonth(d){ return new Date(d.getFullYear(), d.getMonth()+1, 0); }
function addMonths(d, n){ return new Date(d.getFullYear(), d.getMonth()+n, 1); }
function fmtMonth(d){ return d.toLocaleString(undefined, { month:'long', year:'numeric' }); }

function buildGrid(container, viewDate){
  container.innerHTML = '';
  const header = document.createElement('div');
  header.className = 'cal-header';
  const prevBtn = document.createElement('button'); prevBtn.textContent = '‹';
  const nextBtn = document.createElement('button'); nextBtn.textContent = '›';
  const title = document.createElement('div'); title.className = 'cal-title'; title.textContent = fmtMonth(viewDate);
  header.appendChild(prevBtn); header.appendChild(title); header.appendChild(nextBtn);

  const weekdays = document.createElement('div');
  weekdays.className = 'cal-weekdays';
  const names = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
  for (const n of names){ const c=document.createElement('div'); c.textContent=n; weekdays.appendChild(c); }

  const grid = document.createElement('div');
  grid.className = 'cal-grid';

  const first = startOfMonth(viewDate);
  const last  = endOfMonth(viewDate);
  const padStart = first.getDay();
  const daysInMonth = last.getDate();
  const totalCells = Math.ceil((padStart + daysInMonth) / 7) * 7;

  const today = new Date(); today.setHours(0,0,0,0);
  for (let i=0; i<totalCells; i++){
    const cell = document.createElement('div');
    cell.className = 'cal-day';
    const dayNum = i - padStart + 1;
    const inMonth = dayNum >= 1 && dayNum <= daysInMonth;
    if (!inMonth) cell.classList.add('muted');

    let displayDay = '';
    let cellDate = null;
    if (inMonth){
      displayDay = String(dayNum);
      cellDate = new Date(viewDate.getFullYear(), viewDate.getMonth(), dayNum);
      const isToday = cellDate.getTime() === today.getTime();
      if (isToday) cell.classList.add('today');
    }
    cell.textContent = displayDay;
    grid.appendChild(cell);
  }

  container.appendChild(header);
  container.appendChild(weekdays);
  container.appendChild(grid);

  prevBtn.addEventListener('click', ()=> { buildGrid(container, addMonths(viewDate, -1)); });
  nextBtn.addEventListener('click', ()=> { buildGrid(container, addMonths(viewDate,  1)); });
}

export function mountCalendarView(containerId='calendar-view'){
  const host = (typeof containerId === 'string') ? document.getElementById(containerId) : containerId;
  if (!host) return;
  const initial = new Date(); initial.setDate(1);
  buildGrid(host, initial);
}
