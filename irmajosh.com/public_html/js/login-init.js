// Externalized from inline module in index.html to comply with CSP
import { GSI_CLIENT_ID } from "/secure/api/config.js.php";

// Use the global callback defined in /js/login-exchange.js
window.addEventListener('load', function () {
  try {
    if (!window.google || !google.accounts || !google.accounts.id) {
      console.warn('Google Identity Services not available yet');
      return;
    }
    // NOTE: disable FedCM prompt for debugging COOP/postMessage issues.
    // If you prefer FedCM flows, set this back to true after troubleshooting.
    google.accounts.id.initialize({
      client_id: GSI_CLIENT_ID,
      callback: window.handleCredentialResponse,
      use_fedcm_for_prompt: false
    });
    google.accounts.id.renderButton(
      document.getElementById('g_id_signin'),
      { theme: 'outline', size: 'medium' }
    );
    console.log('[gsi] initialized with client id', GSI_CLIENT_ID);
  } catch (e) {
    console.error('[gsi] initialization error', e);
  }
});
