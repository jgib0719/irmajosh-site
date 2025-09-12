// This file defines the frontend API structure.
// These functions should be connected to the corresponding backend PHP scripts.

import { API as Endpoints } from './constants.js';

async function http(url, opts={}) {
  const res = await fetch(url, { credentials:'include', headers:{'Content-Type':'application/json', ...(opts.headers||{})}, ...opts });
  let data;
  try { data = await res.clone().json(); }
  catch (e) {
    const txt = await res.clone().text().catch(()=>null);
    data = { ok: false, error: txt || 'Invalid JSON', __raw: txt };
  }
  if (!res.ok || data.ok===false) throw new Error((url||'') + ' -> ' + (data.error || ('HTTP '+res.status)));
  return data;
}

export const api = {
  auth: {
    me: () => http(Endpoints.AUTH + '?action=me'),
    login: (idToken) => http(Endpoints.AUTH + '?action=login', { method: 'POST', body: JSON.stringify({ id_token: idToken }) }),
    logout: () => http(Endpoints.AUTH + '?action=logout', { method: 'POST' })
  },
    irma: {
        getScheduleRequests: () => http(Endpoints.REQUESTS + '?from=josh'),
        deleteScheduleRequest: (id) => http(Endpoints.REQUESTS, { method: 'DELETE', body: JSON.stringify({ id }) }),
        getJobStatuses: () => http(Endpoints.JOBS + '?status=non-pending'),
        sendJobToJosh: (payload) => http(Endpoints.JOBS, { method: 'POST', body: JSON.stringify(payload) }),
        acknowledgeJob: (id) => http(Endpoints.JOBS, { method: 'DELETE', body: JSON.stringify({ id }) })
    },
    josh: {
        getPendingJobs: () => http(Endpoints.JOBS + '?status=pending'),
        updateJobStatus: (id, status) => http(Endpoints.JOBS, { method: 'PATCH', body: JSON.stringify({ id, status }) }),
        sendScheduleRequest: (payload) => http(Endpoints.REQUESTS, { method: 'POST', body: JSON.stringify(payload) })
    },
    events: {
        list: () => http(Endpoints.EVENTS),
        create: (payload) => http(Endpoints.EVENTS, { method: 'POST', body: JSON.stringify(payload) }),
        update: (payload) => http(Endpoints.EVENTS, { method: 'PATCH', body: JSON.stringify(payload) }),
        delete: (id) => http(Endpoints.EVENTS, { method: 'DELETE', body: JSON.stringify({ id }) })
  },
  features: {
    list: () => http(Endpoints.FEATURES),
    create: (payload) => http(Endpoints.FEATURES, { method: 'POST', body: JSON.stringify(payload) }),
    update: (payload) => http(Endpoints.FEATURES, { method: 'PATCH', body: JSON.stringify(payload) })
  }
};
