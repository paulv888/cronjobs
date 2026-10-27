// Service worker for the HA remote PWA.
//
// One worker for the whole scope: it caches the app shell so the remote starts
// instantly and survives a flaky connection, and it also receives push messages.
// There cannot be a second worker at the same scope, so push handling lives here
// rather than in a firebase-messaging-sw.js.
//
// The API is never cached. A stale status readout is worse than a slow one.
const VERSION = 'ha-remote-v1';
const SCOPE = '/ha/';

const SHELL = [
	'/ha/offline.html',
	'/ha/manifest.json',
	'/ha/images/icon-192.png',
	'/ha/images/icon-512.png',
	'/ha/images/apple-touch-icon.png',
	'/ha/images/favicon-32x32.png',
	'/ha/images/badge-72.png',
	'/ha/js/remote.js',
	'/ha/js/login.js',
	'/ha/js/vendor/firebase-app-compat.js',
	'/ha/js/vendor/firebase-auth-compat.js'
];

// Anything that answers a question about the house right now.
const NEVER_CACHE = ['/ha/process.php', '/ha/auth.php', '/ha/push.php'];

self.addEventListener('install', function (event) {
	event.waitUntil(
		caches.open(VERSION)
			.then(function (cache) {
				// addAll rejects the whole install if one URL 404s, so add them
				// individually and tolerate a miss.
				return Promise.all(SHELL.map(function (url) {
					return cache.add(url).catch(function () { return null; });
				}));
			})
			.then(function () { return self.skipWaiting(); })
	);
});

self.addEventListener('activate', function (event) {
	event.waitUntil(
		caches.keys().then(function (keys) {
			return Promise.all(keys.map(function (key) {
				return key === VERSION ? null : caches.delete(key);
			}));
		}).then(function () { return self.clients.claim(); })
	);
});

self.addEventListener('fetch', function (event) {
	var request = event.request;
	if (request.method !== 'GET') return;

	var url = new URL(request.url);
	if (url.origin !== self.location.origin) return;
	if (NEVER_CACHE.indexOf(url.pathname) !== -1) return;

	// Navigations: always try the network, because the page is rendered from the
	// database and a cached copy would show buttons that do nothing.
	if (request.mode === 'navigate') {
		event.respondWith(
			fetch(request).catch(function () {
				return caches.match(SCOPE + 'offline.html');
			})
		);
		return;
	}

	// Images: cache first, they never change within a version.
	if (/\.(png|jpg|jpeg|gif|svg|ico|webp)$/i.test(url.pathname)) {
		event.respondWith(
			caches.match(request).then(function (hit) {
				return hit || fetch(request).then(function (response) {
					if (response && response.ok) {
						var copy = response.clone();
						caches.open(VERSION).then(function (cache) { cache.put(request, copy); });
					}
					return response;
				});
			})
		);
		return;
	}

	// Everything else: network first, fall back to the cache. This is what stops a
	// deploy from leaving a phone running last week's JavaScript against today's
	// server.
	event.respondWith(
		fetch(request).then(function (response) {
			if (response && response.ok && response.type === 'basic') {
				var copy = response.clone();
				caches.open(VERSION).then(function (cache) { cache.put(request, copy); });
			}
			return response;
		}).catch(function () {
			return caches.match(request);
		})
	);
});

// ------------------------------------------------------------------- push
// Data-only messages: the sender must not include a `notification` block, or the
// browser would display one as well and the user would get two.
self.addEventListener('push', function (event) {
	var payload = {};
	try {
		payload = event.data ? event.data.json() : {};
	} catch (e) {
		payload = { title: 'Home', body: event.data ? event.data.text() : '' };
	}

	// Tolerate both our own shape and a raw FCM one.
	var data = payload.data || {};
	var note = payload.notification || {};
	var title = data.title || note.title || payload.title || 'Home';
	var body = data.body || note.body || payload.body || '';
	var link = data.link || payload.link || (payload.fcmOptions && payload.fcmOptions.link) || SCOPE + 'remote.php';

	// Absolute URLs. A relative icon is ignored by some platforms, which is what
	// leaves the notification showing the browser's own icon instead of ours.
	var origin = self.location.origin;

	var options = {
		body: body,
		icon: origin + '/ha/images/icon-192.png',
		badge: origin + '/ha/images/badge-72.png',
		tag: data.tag || 'ha-remote-' + Date.now(),
		// renotify is only honoured with a tag, and without it a repeat alert with the
		// same tag replaces the old one in silence.
		renotify: true,
		data: { link: link }
	};

	// An alert may carry a camera snapshot; the browser shows it large.
	var image = data.image || payload.image || '';
	if (image) options.image = image;

	event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function (event) {
	event.notification.close();
	var link = (event.notification.data && event.notification.data.link) || (SCOPE + 'remote.php');

	event.waitUntil(
		self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (list) {
			// Prefer an existing window on the same path: focus it rather than
			// piling up tabs every time a notification is tapped.
			for (var i = 0; i < list.length; i++) {
				var client = list[i];
				if (client.url.indexOf(link) !== -1) {
					return client.focus();
				}
			}
			for (var j = 0; j < list.length; j++) {
				var open = list[j];
				if (open.url.indexOf(SCOPE) !== -1 && 'navigate' in open) {
					return open.navigate(link).then(function (client) {
						return client && client.focus ? client.focus() : client;
					});
				}
			}
			if (self.clients.openWindow) return self.clients.openWindow(link);
		})
	);
});

// A refreshed push token arrives here if the browser rotates the subscription.
self.addEventListener('pushsubscriptionchange', function (event) {
	event.waitUntil(
		self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (list) {
			for (var i = 0; i < list.length; i++) {
				list[i].postMessage({ type: 'ha-push-resubscribe' });
			}
		})
	);
});
