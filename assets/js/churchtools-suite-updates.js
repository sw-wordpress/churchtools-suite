/* Backend-only deferred release checks. Installation remains in WordPress core. */
(function () {
  'use strict';
  const config = window.ctsVerifiedUpdates;
  if (!config) return;
  let pending = null;
  function check(manual) {
    if (pending) return pending;
    const button = document.getElementById('cts-verified-updates-check');
    const result = document.getElementById('cts-verified-updates-result');
    if (manual && result) { result.hidden = false; result.textContent = 'Signierte Releases werden geprüft …'; }
    if (button) button.disabled = true;
    const controller = new AbortController();
    const timer = window.setTimeout(() => controller.abort(),90000);
    pending = fetch(config.url, {signal:controller.signal,method:'POST', credentials:'same-origin', body:new URLSearchParams({action:'cts_check_github_updates', nonce:config.nonce, manual:manual?'1':'0'})})
      .then(response => { if (!response.ok) throw new Error('Update-Prüfung fehlgeschlagen.'); return response.json(); })
      .then(response => { if (manual && result) result.textContent = response.data?.message || 'Prüfung abgeschlossen.'; })
      .catch(() => { if (manual && result) result.textContent = 'GitHub konnte nicht erreicht werden. Bitte später erneut prüfen.'; })
      .finally(() => { window.clearTimeout(timer); pending = null; if (button) button.disabled = false; });
    return pending;
  }
  document.getElementById('cts-verified-updates-check')?.addEventListener('click', () => check(true));
  if (config.due) {
    if ('requestIdleCallback' in window) window.requestIdleCallback(() => check(false), {timeout:5000});
    else window.setTimeout(() => check(false), 1500);
  }
})();
