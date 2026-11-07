/**
 * ChurchTools Suite – Admin UI behaviour (no jQuery, no build step).
 *
 * Responsibilities are deliberately small: the page shell (rail collapse, colour scheme), the
 * global "Jetzt synchronisieren" action of the top bar, and small helpers (confirm before
 * destructive actions, copy to clipboard). Everything else stays where it is: the existing
 * `churchtools-suite-admin.js` keeps driving the sync/selection/log interactions of the views.
 *
 * Exposes `window.ctsAdmin` with `theme`, `toggleTheme()`, `setRailCollapsed()`, `syncNow()` and
 * `notify()` so views and addons can reuse it instead of duplicating fetch/confirm code.
 *
 * @package ChurchTools_Suite
 * @since   1.4.0.0
 */
(function () {
	'use strict';

	function bindDiagnosticsExport() {
		var button = document.getElementById('cts-diagnostics-export');
		var data = document.getElementById('cts-diagnostics-data');
		if (!button || !data) return;
		button.addEventListener('click', function () {
			var report = JSON.parse(data.textContent);
			var url = URL.createObjectURL(new Blob([JSON.stringify(report, null, 2)], { type: 'application/json' }));
			var link = document.createElement('a');
			link.href = url;
			link.download = 'churchtools-suite-diagnostics.json';
			document.body.appendChild(link);
			link.click();
			link.remove();
			window.setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
		});
	}

	var STORAGE_THEME = 'ctsAdminTheme';
	var STORAGE_RAIL = 'ctsAdminRailCollapsed';
	var syncInFlight = null;

	function store(key, value) {
		try {
			window.localStorage.setItem(key, value);
		} catch (e) {
			/* private mode: preferences simply are not persisted */
		}
	}

	function read(key) {
		try {
			return window.localStorage.getItem(key);
		} catch (e) {
			return null;
		}
	}

	function wrap() {
		return document.querySelector('.cts-wrap');
	}

	/* ---------- colour scheme ---------- */
	function applyTheme(theme) {
		var root = document.querySelector('.cts-wrap');
		var dark = theme === 'dark';
		if (root) {
			root.classList.toggle('cts-dark', dark);
			root.classList.toggle('dark', dark);
		}
		document.body.classList.toggle('cts-dark', dark);
		document.querySelectorAll('[data-cts-action="toggle-theme"]').forEach(function (btn) {
			btn.setAttribute('aria-pressed', dark ? 'true' : 'false');
		});
	}

	function currentTheme() {
		var saved = read(STORAGE_THEME);
		if (saved === 'dark' || saved === 'light') {
			return saved;
		}
		return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
	}

	function toggleTheme() {
		var next = (read(STORAGE_THEME) === 'dark' || document.body.classList.contains('cts-dark')) ? 'light' : 'dark';
		store(STORAGE_THEME, next);
		applyTheme(next);
		return next;
	}

	/* ---------- rail ---------- */
	function setRailCollapsed(collapsed) {
		var root = wrap();
		if (!root) {
			return collapsed;
		}
		root.classList.toggle('cts-rail-collapsed', collapsed);
		document.querySelectorAll('[data-cts-action="toggle-rail"]').forEach(function (btn) {
			btn.setAttribute('aria-pressed', collapsed ? 'true' : 'false');
		});
		store(STORAGE_RAIL, collapsed ? '1' : '0');
		return collapsed;
	}

	function setNavigationOpen(open) {
		var root = wrap();
		if (!root) {
			return;
		}
		root.classList.toggle('cts-navigation-open', open);
		document.querySelectorAll('[data-cts-action="toggle-navigation"]').forEach(function (btn) {
			btn.setAttribute('aria-expanded', open ? 'true' : 'false');
		});
	}

	/* ---------- notices ---------- */
	function notify(message, kind) {
		var region = document.querySelector('.cts-notices');
		if (!region) {
			var main = document.querySelector('.cts-main');
			if (!main) {
				window.alert(message);
				return null;
			}
			region = document.createElement('div');
			region.className = 'cts-notices';
			main.insertBefore(region, main.firstChild);
		}
		var el = document.createElement('div');
		el.className = 'cts-callout is-' + (kind || 'info');
		el.setAttribute('role', kind === 'danger' ? 'alert' : 'status');
		el.textContent = message;
		region.appendChild(el);
		window.setTimeout(function () {
			el.remove();
		}, 8000);
		return el;
	}

	/* ---------- global sync ---------- */
	function syncNow(button) {
		if (syncInFlight) {
			return syncInFlight;
		}
		var config = window.churchtoolsSuite || {};
		var url = config.ajaxUrl || window.ajaxurl;
		var status = document.getElementById('cts-sync-result');
		var buttons = Array.prototype.slice.call(document.querySelectorAll('[data-cts-action="sync-now"]'));
		if (button && buttons.indexOf(button) === -1) {
			buttons.push(button);
		}
		if (!url) {
			notify('AJAX-URL fehlt.', 'danger');
			return Promise.resolve(false);
		}

		buttons.forEach(function (btn) {
			btn.classList.add('is-loading');
			btn.disabled = true;
			btn.setAttribute('aria-busy', 'true');
		});
		if (status) {
			status.hidden = false;
			status.textContent = 'Synchronisiere…';
			status.removeAttribute('data-state');
		}

		syncInFlight = fetch(url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: new URLSearchParams({ action: 'cts_trigger_manual_sync', nonce: config.nonce || '' })
		})
			.then(function (response) {
				return response.json().catch(function () {
					return { success: false, data: { message: 'Ungültige Antwort (' + response.status + ').' } };
				});
			})
			.then(function (payload) {
				var ok = !!(payload && payload.success);
				var data = payload && payload.data;
				var message = (data && data.message) || (typeof data === 'string' && data) || (data && Array.isArray(data.errors) && data.errors.join(', ')) || (ok ? 'Synchronisation abgeschlossen.' : 'Synchronisierung fehlgeschlagen.');
				if (status) {
					status.textContent = message;
					status.setAttribute('data-state', ok ? 'success' : 'danger');
				} else {
					notify(message, ok ? 'success' : 'danger');
				}
				return ok;
			})
			.catch(function () {
				if (status) {
					status.textContent = 'Synchronisierung fehlgeschlagen.';
					status.setAttribute('data-state', 'danger');
				} else {
					notify('Synchronisierung fehlgeschlagen.', 'danger');
				}
				return false;
			})
			.then(function (ok) {
				buttons.forEach(function (btn) {
					btn.classList.remove('is-loading');
					btn.disabled = false;
					btn.removeAttribute('aria-busy');
				});
				syncInFlight = null;
				var event = new CustomEvent('cts:sync-finished', { detail: { success: ok } });
				document.dispatchEvent(event);
				return ok;
			});
		return syncInFlight;
	}

	/* ---------- wiring ---------- */
	function init() {
		bindDiagnosticsExport();
		applyTheme(currentTheme());
		if (read(STORAGE_RAIL) === '1') {
			setRailCollapsed(true);
		}

		document.addEventListener('click', function (event) {
			var trigger = event.target.closest('[data-cts-action]');
			if (!trigger) {
				return;
			}
			var action = trigger.getAttribute('data-cts-action');

			if (action === 'toggle-theme') {
				toggleTheme();
			} else if (action === 'toggle-rail') {
				setRailCollapsed(!wrap().classList.contains('cts-rail-collapsed'));
			} else if (action === 'toggle-navigation') {
				setNavigationOpen(!wrap().classList.contains('cts-navigation-open'));
			} else if (action === 'sync-now') {
				event.preventDefault();
				syncNow(trigger);
			} else if (action === 'copy') {
				var value = trigger.getAttribute('data-cts-copy') || '';
				if (navigator.clipboard) {
					navigator.clipboard.writeText(value).then(function () {
						notify('In die Zwischenablage kopiert.', 'success');
					});
				}
			}
		});

		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape' && wrap() && wrap().classList.contains('cts-navigation-open')) {
				setNavigationOpen(false);
				document.querySelector('[data-cts-action="toggle-navigation"]').focus();
			}
		});

		// Destructive actions ask for confirmation before submitting.
		document.addEventListener('submit', function (event) {
			var form = event.target;
			var message = form.getAttribute && form.getAttribute('data-cts-confirm');
			if (message && !window.confirm(message)) {
				event.preventDefault();
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

	window.ctsAdmin = {
		theme: currentTheme,
		toggleTheme: toggleTheme,
		setRailCollapsed: setRailCollapsed,
		syncNow: syncNow,
		notify: notify
	};
})();
