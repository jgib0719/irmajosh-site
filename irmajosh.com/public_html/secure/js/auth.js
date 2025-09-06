// /secure/js/auth.js — centralized GSI client id via PHP-exported module
import { GSI_CLIENT_ID } from "/secure/api/config.js.php";
import { authApi } from "./api.js";

/**
 * Global callback for Google Identity Services (GSI).
 * Used both by data-callback and programmatic flows.
 */
window.onGoogleCredential = async (response) => {
  try {
    if (!response || !response.credential) {
      console.warn("GSI response missing credential", response);
      return;
    }
    await authApi.login(response.credential);
    document.dispatchEvent(new CustomEvent("auth:login", { detail: { ok: true } }));
  } catch (err) {
    console.error("Login failed", err);
    alert("Login failed: " + err.message);
  }
};

/**
 * Renders the Google Sign-In button into #gsiBtn.
 * Requires the GSI script tag in the page HTML.
 */
export function renderGoogleButton() {
  const el = document.getElementById("gsiBtn");
  if (!el) return;

  if (!window.google || !google.accounts || !google.accounts.id) {
    // Retry shortly until GSI is ready (script is async/defer)
    setTimeout(renderGoogleButton, 300);
    return;
  }

  google.accounts.id.initialize({
    client_id: GSI_CLIENT_ID,
    callback: window.onGoogleCredential,
    // optional flags:
    auto_select: false,
    // use_fedcm_for_prompt: true, // enable if you want FedCM prompt behavior
  });

  google.accounts.id.renderButton(el, {
    type: "standard",
    theme: "outline",
    size: "large",
  });
}

/** Returns current session user or null */
export async function getUser() {
  try {
    const data = await authApi.me();
    return data.user || null;
  } catch {
    return null;
  }
}

/** Bootstraps auth UI and emits an auth:ready event with the current user (if any) */
export async function initAuthUI() {
  renderGoogleButton();
  const user = await getUser();
  document.dispatchEvent(new CustomEvent("auth:ready", { detail: { user } }));
}
