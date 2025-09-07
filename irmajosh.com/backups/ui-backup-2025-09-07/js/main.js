console.log("[secure] main.js loaded");
import { authApi } from "./api.js";
async function boot(){
  try{
    const me = await authApi.me();
    if (!me || !me.user){ window.location.href = "/"; return; }
    try {
      const mod = await import("./ui.js");
      if (typeof mod.initApp === "function") mod.initApp();
      else if (typeof mod.default === "function") mod.default();
      else if (typeof window !== "undefined") {
        if (typeof window.loadUi === 'function') try { await window.loadUi(); } catch(e){}
        if (typeof window.initApp === 'function') window.initApp(); else { const el = document.getElementById("app"); if (el) el.textContent = "Signed in, but no UI entrypoint in ui.js"; }
      }
    } catch(e){ console.error("[secure] failed importing ui.js", e); const el = document.getElementById('app'); if (el) el.textContent = "Error loading UI (see console)."; }
  }catch(e){ console.error("[secure] auth check failed", e); window.location.href = "/"; }
}
document.addEventListener('DOMContentLoaded', boot);
