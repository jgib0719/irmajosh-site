// main.js — secure area bootstrap
console.log("[secure] main.js loaded");
import { authApi } from "./api.js";
// Do not statically call UI before auth; UI will be dynamically imported in boot()
// import * as mod from './ui.js';

async function boot(){
  try{
    const me = await authApi.me();
    if (!me || !me.user){
      // Not signed in → go to root to sign in
      window.location.href = "/";
      return;
    }
    // Authenticated → load UI
    try {
      const mod = await import("./ui.js");
      if (typeof mod.initApp === "function"){
        console.log("[secure] calling mod.initApp()");
        mod.initApp();
      } else if (typeof mod.default === "function"){
        console.log("[secure] calling mod.default()");
        mod.default();
      } else if (typeof window !== "undefined"){
        // Try to lazily load the UI bridge which will populate window.initApp
        if (typeof window.loadUi === 'function') {
          try {
            await window.loadUi();
          } catch(e) {
            console.warn('[secure] window.loadUi() failed', e);
          }
        }
        if (typeof window.initApp === 'function'){
          console.log("[secure] calling window.initApp()");
          window.initApp();
        } else {
          console.warn("[secure] no UI entrypoint found in ui.js");
          const el = document.getElementById("app");
          if (el) el.textContent = "Signed in, but no UI entrypoint in ui.js";
        }
      } else {
        console.warn("[secure] no UI entrypoint found in ui.js");
        const el = document.getElementById("app");
        if (el) el.textContent = "Signed in, but no UI entrypoint in ui.js";
      }
    } catch(e) {
      console.error("[secure] failed importing ui.js", e);
      const el = document.getElementById("app");
      if (el) el.textContent = "Error loading UI (see console).";
    }
  }catch(e){
    console.error("[secure] auth check failed", e);
    window.location.href = "/";
  }
}
document.addEventListener('DOMContentLoaded', boot);
