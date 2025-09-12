// auth-silent.js -- handles silent Google Identity Services re-auth
// Assumes Google Identity Services script is loaded globally on / (public page)
// This module is imported only when secure app loads and session is missing.
import { api } from './api.js';
import { GSI_CLIENT_ID } from '../api/config.js.php';

let tokenClient;

function ensureGsi() {
  return new Promise((resolve, reject) => {
    if (window.google && window.google.accounts && window.google.accounts.id) {
      return resolve();
    }
    // Attempt to load the GIS script dynamically if absent
    const existing = document.querySelector('script[data-gsi]');
    if (existing) {
      existing.addEventListener('load', () => resolve());
      existing.addEventListener('error', () => reject(new Error('GSI script failed')));
      return;
    }
    const s = document.createElement('script');
    s.src = 'https://accounts.google.com/gsi/client';
    s.async = true; s.defer = true; s.dataset.gsi = '1';
    s.onload = () => resolve();
    s.onerror = () => reject(new Error('GSI script load error'));
    document.head.appendChild(s);
  });
}

export async function silentLogin() {
  try {
    await ensureGsi();
    // First, try the accounts.id auto_select flow which is silent when the user
    // is already signed in and has previously granted consent. This avoids any
    // popup being opened (which browsers often block).
    if (window.google && window.google.accounts && window.google.accounts.id) {
      const idToken = await new Promise((resolve, reject) => {
        let settled = false;
        try {
          window.google.accounts.id.initialize({
            client_id: GSI_CLIENT_ID,
            callback: (credResp) => {
              if (settled) return;
              settled = true;
              if (credResp && credResp.credential) return resolve(credResp.credential);
              return reject(new Error('No credential from accounts.id'));
            },
            auto_select: true,
            cancel_on_tap_outside: false
          });
          // prompt() will be silent if auto_select succeeds, otherwise it may
          // show a one-tap prompt UI. We give it a short timeout and then fall
          // back to the token client approach which is more explicit.
          window.google.accounts.id.prompt();
          setTimeout(() => {
            if (!settled) {
              settled = true;
              reject(new Error('accounts.id prompt timed out'));
            }
          }, 2500);
        } catch (e) {
          if (!settled) {
            settled = true;
            reject(e);
          }
        }
      }).catch((err) => {
        console.warn('[silentLogin] accounts.id auto_select failed, will try token client', err);
        return null;
      });
      if (idToken) {
        const loginRes = await api.auth.login(idToken);
        return !!(loginRes && loginRes.user);
      }
    }

    // Fallback: use oauth2 token client. This may open a popup; handle popup
    // blocked scenarios gracefully and do not treat them as fatal for the app.
    if (!tokenClient) {
      tokenClient = window.google.accounts.oauth2.initTokenClient({
        client_id: GSI_CLIENT_ID,
        scope: 'openid email profile',
        callback: () => {},
      });
    }
    const tokenResp = await new Promise((resolve, reject) => {
      try {
        tokenClient.callback = (resp) => {
          if (resp && resp.access_token) return resolve(resp);
          return reject(new Error(resp && resp.error ? resp.error : 'No access token'));
        };
        tokenClient.requestAccessToken({ prompt: '' });
        // popup-block detection: if no response within short window, assume blocked
        setTimeout(() => reject(new Error('token client timed out (possible popup blocked)')), 2500);
      } catch (e) { reject(e); }
    }).catch((err) => {
      console.warn('[silentLogin] token client failed or blocked', err);
      return null;
    });

    if (!tokenResp) return false;
    // If we got an access token, try to retrieve an ID token via accounts.id
    if (window.google && window.google.accounts && window.google.accounts.id) {
      const idTok = await new Promise((resolve, reject) => {
        try {
          window.google.accounts.id.initialize({
            client_id: GSI_CLIENT_ID,
            callback: (credResp) => { if (credResp && credResp.credential) resolve(credResp.credential); else reject(new Error('No credential')) },
            auto_select: false
          });
          window.google.accounts.id.prompt();
          setTimeout(() => reject(new Error('No credential after token')), 3000);
        } catch (e) { reject(e); }
      }).catch(e => { console.warn('[silentLogin] failed to obtain id token after access token', e); return null; });
    
      if (idTok) {
        const loginRes = await api.auth.login(idTok);
        return !!(loginRes && loginRes.user);
      }
    }
    return false;
    if (!idToken) return false;
    const loginRes = await api.auth.login(idToken);
    return !!(loginRes && loginRes.user);
  } catch (e) {
    console.warn('[silentLogin] failed', e);
    return false;
  }
}
