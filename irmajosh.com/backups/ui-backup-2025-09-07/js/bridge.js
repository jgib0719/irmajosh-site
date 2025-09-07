window.loadUi = async function loadUi(){
  try {
    const mod = await import("./ui.js?v=" + Date.now());
    if (typeof mod.initApp === "function") window.initApp = mod.initApp;
    else if (typeof mod.default === "function") window.initApp = mod.default;
    else console.warn("[secure] bridge: ui.js has no initApp/default export");
    return window.initApp || null;
  } catch (e) { console.error("[secure] bridge: failed to import ui.js", e); return null; }
};
