// Sign-in for the remote PWA.
//
// The browser talks to Firebase to get an ID token, and then hands that token to
// auth.php once. Everything after that is authorised by the session cookie, so this
// file has two jobs: get a token, and exchange it. It never keeps a token around.
//
// The tokenFirebase keeps in local storage is what makes a reload painless - on load
// we exchange whatever it already has, and only show the form if that fails.
if (!window.scriptLoginHasRun) {
	window.scriptLoginHasRun = true;

	(function () {
		'use strict';

		var config = window.VLO_FIREBASE || {};
		var auth = null;
		var busy = false;

		function el(id) { return document.getElementById(id); }

		function setStatus(message, isError) {
			var box = el('login-status');
			if (!box) return;
			box.textContent = message || '';
			box.className = 'login-status' + (isError ? ' login-error' : '');
			box.style.display = message ? 'block' : 'none';
		}

		function setBusy(state) {
			busy = state;
			var button = el('login-submit');
			if (button) {
				button.disabled = state;
				button.value = state ? 'Signing in...' : 'Sign in';
			}
		}

		// POST the token, and report both the HTTP status and the JSON body so the
		// caller can tell "wrong password" from "not provisioned".
		function exchange(idToken, forceRefresh) {
			return idToken.getIdToken(forceRefresh).then(function (token) {
				return fetch('/ha/auth.php', {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({ idToken: token })
				});
			}).then(function (response) {
				return response.json().catch(function () { return {}; }).then(function (body) {
					return { status: response.status, body: body };
				});
			});
		}

		// Firebase's own signed-in state is not authority here: the server decides.
		// If the server says the session is gone, drop Firebase's copy too, or it
		// would silently sign the user straight back in and defeat the 30 day rule.
		function handleRejection(reason) {
			if (reason === 'session_expired' || reason === 'user_disabled' || reason === 'invalid_token') {
				if (auth) auth.signOut();
			}
		}

		function reloadIntoRemote() {
			window.location.reload();
		}

		function attemptRestore() {
			if (!auth || !auth.currentUser) return false;
			setBusy(true);
			setStatus('Restoring session...', false);
			exchange(auth.currentUser, false).then(function (result) {
				if (result.status === 200) {
					reloadIntoRemote();
					return;
				}
				setBusy(false);
				handleRejection(result.body.reason);
				if (result.status === 403) {
					setStatus('This account does not have access to the remote.', true);
				} else {
					setStatus('Please sign in.', false);
				}
				showForm();
			}).catch(function () {
				setBusy(false);
				showForm();
			});
			return true;
		}

		function showForm() {
			var form = el('login-form');
			var restoring = el('login-restoring');
			if (form) form.style.display = 'block';
			if (restoring) restoring.style.display = 'none';
		}

		function explain(error) {
			var code = (error && error.code) || '';
			if (code === 'auth/wrong-password' || code === 'auth/invalid-credential'
				|| code === 'auth/user-not-found' || code === 'auth/invalid-login-credentials') {
				return 'Wrong email or password.';
			}
			if (code === 'auth/too-many-requests') return 'Too many attempts. Try again in a few minutes.';
			if (code === 'auth/network-request-failed') return 'Cannot reach the sign-in service. Check the connection.';
			if (code === 'auth/user-disabled') return 'This account has been disabled.';
			return 'Sign-in failed' + (code ? ' (' + code + ')' : '.');
		}

		function signIn(email, password) {
			setBusy(true);
			setStatus('', false);
			auth.signInWithEmailAndPassword(email, password).then(function (credential) {
				return exchange(credential.user, true);
			}).then(function (result) {
				if (result.status === 200) {
					reloadIntoRemote();
					return;
				}
				setBusy(false);
				handleRejection(result.body.reason);
				if (result.status === 403) {
					setStatus('This account does not have access to the remote.', true);
				} else if (result.status === 429) {
					setStatus('Too many attempts. Try again in a few minutes.', true);
				} else {
					setStatus(result.body.message || 'Sign-in was refused.', true);
				}
			}).catch(function (error) {
				setBusy(false);
				setStatus(explain(error), true);
			});
		}

		function signOut() {
			return fetch('/ha/auth.php', {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ action: 'logout' })
			}).then(function () {
				if (auth) return auth.signOut();
			}).then(function () {
				window.location.reload();
			});
		}

		window.VloAuth = { signOut: signOut };

		document.addEventListener('DOMContentLoaded', function () {
			if (!config.apiKey) {
				setStatus('Sign-in is not configured.', true);
				showForm();
				return;
			}
			if (!window.firebase) {
				setStatus('The sign-in library did not load.', true);
				showForm();
				return;
			}

			firebase.initializeApp(config);
			auth = firebase.auth();

			// A returning user with a live server session should never see this form.
			if (!attemptRestore()) showForm();

			var form = el('login-form');
			if (form) {
				form.addEventListener('submit', function (event) {
					event.preventDefault();
					if (busy) return;
					var email = (el('login-email') || {}).value || '';
					var password = (el('login-password') || {}).value || '';
					if (!email || !password) {
						setStatus('Enter your email and password.', true);
						return;
					}
					signIn(email.trim(), password);
				});
			}
		});
	})();
}
