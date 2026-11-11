/* Backend-only deferred release checks. Installation remains in WordPress core. */
(function () {
  'use strict';
  const config = window.ctsVerifiedUpdates;
  if (!config) return;
  function start() {
    let pending = null;
    const controls = () => document.querySelectorAll('[data-cts-update-check]');
    const results = () => document.querySelectorAll('[data-cts-update-result]');
    const reloads = () => document.querySelectorAll('[data-cts-update-reload]');
    function check(manual) {
      if (pending) return pending;
      if (manual) {
        reloads().forEach(link => { link.hidden = true; });
        results().forEach(result => { result.hidden = false; result.textContent = config.checking; });
      }
      controls().forEach(button => { button.disabled = true; });
      const controller = new AbortController();
      const timer = window.setTimeout(() => controller.abort(), 90000);
      pending = fetch(config.url, {signal:controller.signal, method:'POST', credentials:'same-origin', body:new URLSearchParams({action:'cts_check_github_updates', nonce:config.nonce, manual:manual?'1':'0'})})
        .then(async response => {
          const body = await response.json().catch(() => null);
          if (!response.ok || !body || body.success !== true) {
            throw new Error(body?.data?.message || (response.status === 403 ? config.forbidden : config.failed));
          }
          return body;
        })
        .then(response => {
          if (manual) {
            results().forEach(result => { result.textContent = response.data?.message || config.completed; });
            document.querySelectorAll('[data-cts-update-status]').forEach(status => { status.hidden = true; });
            reloads().forEach(link => { link.hidden = false; });
          }
        })
        .catch(error => {
          if (manual) results().forEach(result => { result.textContent = error.message || config.failed; });
        })
        .finally(() => {
          window.clearTimeout(timer);
          pending = null;
          controls().forEach(button => { button.disabled = !config.ready; });
        });
      return pending;
    }
    controls().forEach(button => button.addEventListener('click', () => check(true)));
    if (config.due) {
      if ('requestIdleCallback' in window) window.requestIdleCallback(() => check(false), {timeout:5000});
      else window.setTimeout(() => check(false), 1500);
    }
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, {once:true});
  else start();
})();
