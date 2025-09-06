// bridge.js — map ESM exports from ui.js to a global for main.js
(async () => {
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
  } catch (e) {
    console.error("[secure] bridge: failed to import ui.js", e);
  }
})();
