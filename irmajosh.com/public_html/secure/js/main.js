// main bootstrap for secure area — single consolidated loader
console.log('[secure] main.js (consolidated) loaded');
import { api } from './api.js';
import { silentLogin } from './auth-silent.js';

async function performAuthCheck() {
  
  // Try common auth entrypoints exposed by api.js
  try {
    if (api && api.auth) {
      if (typeof api.auth.getUser === 'function') {
        const u = await api.auth.getUser();
        
        return !!(u && (u.id || u.userId || u.email));
      }
      if (typeof api.auth.me === 'function') {
        const r = await api.auth.me();
        
        return !!(r && (r.user || r.id));
      }
    }
  } catch (e) {
    console.warn('[secure] performAuthCheck failed', e);
  }
  // Session not confirmed; attempt silent login
  try {
    const ok = await silentLogin();
    return ok;
  } catch(e) {
    console.warn('[secure] silent login failed', e);
  }
  return false;
}

async function loadUiModule() {
  
  // Prefer dynamic import from ui.js; fall back to window.loadUi if present
  if (typeof window.initApp === 'function') return window.initApp;
  if (typeof window.loadUi === 'function') {
  await window.loadUi();
    if (typeof window.initApp === 'function') return window.initApp;
  }
  const mod = await import('./ui.js');
  
  if (typeof mod.initApp === 'function') return mod.initApp;
  if (typeof mod.default === 'function') return mod.default;
  return null;
}

async function boot() {
  try {
    
    const ok = await performAuthCheck();
    if (!ok) {
      console.log('[secure] not authenticated, redirecting to /');
      
      window.location.href = '/';
      return;
    }

    const entry = await loadUiModule();

    if (typeof entry === 'function') {
      try {
        entry();
      } catch (e) {
        console.error('[secure] error running UI entrypoint', e);
      }
    } else {
      console.warn('[secure] no UI entrypoint found');
      const el = document.getElementById('app');
      if (el) el.textContent = 'Signed in, but UI not available';
    }
  } catch (e) {
    console.error('[secure] boot failed', e);
    const el = document.getElementById('app');
    if (el) el.textContent = 'Error during boot (see console)';
  }
}

if (document.readyState === 'complete' || document.readyState === 'interactive') {
  boot();
} else {
  document.addEventListener('DOMContentLoaded', boot);
}

// small friendly fallback while heavy scripts load
document.addEventListener('DOMContentLoaded', ()=>{
  const el = document.getElementById('app');
  if (el && !el.textContent) el.textContent = 'Loading secure UI...';
});
