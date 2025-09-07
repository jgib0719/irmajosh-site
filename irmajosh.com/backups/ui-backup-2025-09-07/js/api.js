import { API } from './constants.js';

async function http(url, opts={}) {
  const res = await fetch(url, { credentials:'include', headers:{'Content-Type':'application/json', ...(opts.headers||{})}, ...opts });
  let data;
  try { data = await res.clone().json(); }
  catch (e) { const txt = await res.clone().text().catch(()=>null); data = { ok: false, error: txt || 'Invalid JSON', __raw: txt }; }
  if (!res.ok || data.ok===false) throw new Error(data.error || ('HTTP '+res.status));
  return data;
}

export const authApi = {
  me: () => http(API.AUTH+'?action=me', {method:'GET'}),
  login: (id_token) => http(API.AUTH+'?action=login', {method:'POST', body: JSON.stringify({id_token})}),
  logout: () => http(API.AUTH+'?action=logout', {method:'POST'})
};

export const requestsApi = {
  list: (status) => http(status ? API.REQUESTS+'?status='+encodeURIComponent(status) : API.REQUESTS, {method:'GET'}),
  create: (payload) => http(API.REQUESTS, {method:'POST', body: JSON.stringify(payload)}),
  setStatus: (id, status) => http(API.REQUESTS, {method:'PATCH', body: JSON.stringify({id, status})})
};

export const jobsApi = {
  list: (status) => http(status ? API.JOBS+'?status='+encodeURIComponent(status) : API.JOBS, {method:'GET'}),
  create: (payload) => http(API.JOBS, {method:'POST', body: JSON.stringify(payload)}),
  accept: (id) => http(API.JOBS, {method:'PATCH', body: JSON.stringify({id, status:'accepted'})}),
  schedule: (id) => http(API.JOBS, {method:'PATCH', body: JSON.stringify({id, status:'scheduled'})})
};

export const eventsApi = {
  list: () => http(API.EVENTS, {method:'GET'}),
  create: (payload) => http(API.EVENTS, {method:'POST', body: JSON.stringify(payload)}),
  update: (payload) => http(API.EVENTS, {method:'PATCH', body: JSON.stringify(payload)}),
  remove: (id) => http(API.EVENTS+'?id='+encodeURIComponent(id), {method:'DELETE'})
};
