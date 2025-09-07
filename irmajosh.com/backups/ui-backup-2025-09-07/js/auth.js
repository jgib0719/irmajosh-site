import { GSI_CLIENT_ID } from "/secure/api/config.js.php";
import { authApi } from "./api.js";

window.onGoogleCredential = async (response) => {
  try {
    if (!response || !response.credential) return;
    await authApi.login(response.credential);
    document.dispatchEvent(new CustomEvent("auth:login", { detail: { ok: true } }));
  } catch (err) { console.error("Login failed", err); }
};

export function renderGoogleButton() {
  const el = document.getElementById("gsiBtn");
  if (!el) return;
  if (!window.google || !google.accounts || !google.accounts.id) { setTimeout(renderGoogleButton, 300); return; }
  google.accounts.id.initialize({ client_id: GSI_CLIENT_ID, callback: window.onGoogleCredential, auto_select: false });
  google.accounts.id.renderButton(el, { type: "standard", theme: "outline", size: "large" });
}

export async function getUser() {
  try { const data = await authApi.me(); return data.user || null; } catch { return null; }
}

export async function initAuthUI() { renderGoogleButton(); const user = await getUser(); document.dispatchEvent(new CustomEvent("auth:ready", { detail: { user } })); }
