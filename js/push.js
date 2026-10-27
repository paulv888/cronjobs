// Push registration for the remote PWA.
//
// The browser asks Firebase for a token, and hands it to push.php, which stores it
// against the signed-in user and the device row their account points at. That token
// is what sendAlertToDevice() ends up posting to.
//
// Nothing here runs unless there is a session: the remote page only loads this file
// for a logged-in user, because a LAN caller has no user to register against.
if (!window.scriptPushHasRun) {
	window.scriptPushHasRun = true;

	(function () {
		'use strict';

		var config = window.VLO_FIREBASE || {};
		var vapidKey = window.VLO_VAPID || '';
		var messaging = null;
		var busy = false;

		function el(id) { return document.getElementById(id); }

		function setStatus(message, isError) {
			var box = el('push-status');
			if (!box) return;
			box.textContent = message || '';
			box.className = 'push-status' + (isError ? ' push-error' : '');
		}

		// One id per browser, kept forever. It is what makes a token update in place
		// instead of accumulating a new row every time the page loads.
		function machineId() {
			var key = 'vlo-machine-id';
			try {
				var existing = window.localStorage.getItem(key);
				if (existing) return existing;
				var fresh = (window.crypto && window.crypto.randomUUID)
					? window.crypto.randomUUID()
					: 'm-' + Date.now() + '-' + Math.random().toString(16).slice(2, 10);
				window.localStorage.setItem(key, fresh);
				return fresh;
			} catch (error) {
				return '';   // private mode with storage blocked
			}
		}

		function post(body) {
			return fetch('/ha/push.php', {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify(body)
			}).then(function (response) {
				return response.json().catch(function () { return {}; });
			});
		}

		function registration() {
			if (!('serviceWorker' in navigator)) return Promise.reject(new Error('no service worker'));
			return navigator.serviceWorker.ready;
		}

		function saveToken() {
			return registration().then(function (reg) {
				return messaging.getToken({ vapidKey: vapidKey, serviceWorkerRegistration: reg });
			}).then(function (token) {
				if (!token) throw new Error('no token');
				return post({ token: token, machineId: machineId() }).then(function (result) {
					if (!result || result.ok !== true) {
						throw new Error((result && result.reason) || 'refused');
					}
					setStatus('Alerts are on for this phone.', false);
					hideButton();
					return true;
				});
			});
		}

		function hideButton() {
			var button = el('push-enable');
			if (button) button.style.display = 'none';
			var row = el('push-row');
			if (row) row.style.display = 'block';
		}

		function showRow() {
			var row = el('push-row');
			if (row) row.style.display = 'block';
		}

		function enable() {
			if (busy) return;
			busy = true;
			setStatus('Asking for permission...', false);

			var request = (window.Notification && window.Notification.permission === 'granted')
				? Promise.resolve('granted')
				: window.Notification.requestPermission();

			request.then(function (permission) {
				if (permission !== 'granted') {
					busy = false;
					setStatus('Notifications are blocked for this site. Allow them in the browser settings.', true);
					return null;
				}
				return saveToken();
			}).catch(function (error) {
				setStatus('Could not enable alerts: ' + ((error && error.message) || error), true);
			}).then(function () {
				busy = false;
			});
		}

		// Taking the token away with the session: the next person on this phone must
		// not inherit the previous user's alerts.
		function unregister() {
			var id = machineId();
			if (!id) return Promise.resolve();
			return post({ action: 'unregister', machineId: id }).catch(function () { return null; });
		}

		window.VloPush = { enable: enable, unregister: unregister };

		document.addEventListener('DOMContentLoaded', function () {
			if (!config.apiKey || !vapidKey) return;
			if (typeof window.firebase === 'undefined' || !firebase.messaging) return;
			if (typeof firebase.messaging.isSupported === 'function' && !firebase.messaging.isSupported()) {
				return;   // Safari in a tab, for instance
			}

			try {
				if (!firebase.apps || !firebase.apps.length) firebase.initializeApp(config);
				messaging = firebase.messaging();
			} catch (error) {
				return;
			}

			showRow();

			// Foreground: the tab is open, so show it in the page instead of the tray.
			try {
				messaging.onMessage(function (payload) {
					var note = payload.notification || {};
					var data = payload.data || {};
					var text = data.body || note.body || '';
					var title = data.title || note.title || 'Home';
					var box = el('system-message-container');
					if (box && text) {
						box.innerHTML = '<div class="alert alert-success">' + title + ': ' + text + '</div>';
					}
				});
			} catch (error) { /* older SDK, ignore */ }

			var button = el('push-enable');
			if (button) {
				button.addEventListener('click', function (event) {
					event.preventDefault();
					enable();
				});
			}

			if (window.Notification && window.Notification.permission === 'granted') {
				// Already allowed: re-register quietly, in case the token rotated.
				saveToken().catch(function () {
					setStatus('', false);
					var b = el('push-enable');
					if (b) b.style.display = '';
				});
			} else if (window.Notification && window.Notification.permission === 'denied') {
				setStatus('Notifications are blocked for this site. Allow them in the browser settings.', true);
			}
		});
	})();
}
