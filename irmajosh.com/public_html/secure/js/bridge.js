// bridge loader: dynamically import ui.js and expose initApp on window
export async function loadUi(){
  try{
    const mod = await import('./ui.js?v=' + Date.now());
    if (typeof mod.initApp === 'function') window.initApp = mod.initApp;
    else if (typeof mod.default === 'function') window.initApp = mod.default;
    return window.initApp || null;
  }catch(e){
    console.error('[secure] bridge loadUi failed', e);
    return null;
  }
}
// bridge stub while UI is rebuilt
window.loadUi = async function(){ console.warn('[secure] loadUi stub called - UI rebuild in progress'); return null; };
// bridge.js — map ESM exports from ui.js to a global for main.js
// Provide a lazy loader so main.js can ask for the UI after auth completes.
window.loadUi = async function loadUi(){
  try {
    const mod = await import("./ui.js?v=" + Date.now());
    if (typeof mod.initApp === "function") {
      console.log("[secure] bridge: found export initApp()");
      window.initApp = mod.initApp;
    } else if (typeof mod.default === "function") {
      console.log("[secure] bridge: found default export()");
      window.initApp = mod.default;
    } else {
      console.warn("[secure] bridge: ui.js has no initApp/default export");
    }
    return window.initApp || null;
  } catch (e) {
    console.error("[secure] bridge: failed to import ui.js", e);
    return null;
  }
};
