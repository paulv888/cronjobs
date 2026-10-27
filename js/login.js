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
		var mode = 'signin';

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
				button.value = state ? 'Working...' : (mode === 'signup' ? 'Create account' : 'Sign in');
			}
			var google = el('login-google');
			if (google) google.disabled = state;
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
			if (reason === 'session_expired' || reason === 'user_disabled' || reason === 'invalid_token'
				|| reason === 'reauth_required') {
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
				if (result.status === 403 && result.body.reason === 'reauth_required') {
					setStatus('Your session was ended. Enter your password to continue.', true);
				} else if (result.status === 403) {
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

		// Once Firebase has a user, the only thing left is the exchange. Shared by the
		// password form, the Google button and the silent session restore.
		function finishSignIn(user) {
			setBusy(true);
			return exchange(user, true).then(function (result) {
				if (result.status === 200) {
					reloadIntoRemote();
					return;
				}
				setBusy(false);
				handleRejection(result.body.reason);
				if (result.status === 403 && result.body.reason === 'pending_approval') {
					setStatus('Your account is waiting for approval.', false);
				} else if (result.status === 403 && result.body.reason === 'reauth_required') {
					setStatus('Enter your password again to continue.', true);
				} else if (result.status === 403) {
					setStatus('This account does not have access to the remote.', true);
				} else if (result.status === 429) {
					setStatus('Too many attempts. Try again in a few minutes.', true);
				} else {
					setStatus(result.body.message || 'Sign-in was refused.', true);
				}
			}).catch(function () {
				setBusy(false);
				setStatus('Could not reach the server. Check the connection.', true);
			});
		}

		function signIn(email, password) {
			setBusy(true);
			setStatus('', false);
			auth.signInWithEmailAndPassword(email, password).then(function (credential) {
				return finishSignIn(credential.user);
			}).catch(function (error) {
				setBusy(false);
				setStatus(explain(error), true);
			});
		}

		// Self registration. The account is created and a verification link is sent;
		// nothing is granted until the address is verified and the request approved.
		// Signing out immediately keeps an unverified session from looking like progress.
		function signUp(email, password) {
			setBusy(true);
			setStatus('', false);
			auth.createUserWithEmailAndPassword(email, password).then(function (credential) {
				return credential.user.sendEmailVerification();
			}).then(function () {
				return auth.signOut();
			}).then(function () {
				setBusy(false);
				setMode('signin');
				setStatus('Account created. Check ' + email + ' for the verification link, then sign in.', false);
			}).catch(function (error) {
				setBusy(false);
				setStatus(explainSignUp(error), true);
			});
		}

		function explainSignUp(error) {
			var code = (error && error.code) || '';
			if (code === 'auth/email-already-in-use') return 'That email already has an account. Sign in instead.';
			if (code === 'auth/weak-password') return 'Choose a password of at least 6 characters.';
			if (code === 'auth/invalid-email') return 'That email address is not valid.';
			if (code === 'auth/too-many-requests') return 'Too many attempts. Try again in a few minutes.';
			if (code === 'auth/network-request-failed') return 'Cannot reach the sign-in service.';
			return 'Could not create the account' + (code ? ' (' + code + ')' : '.');
		}

		function setMode(next) {
			mode = next;
			var signup = next === 'signup';
			var title = el('login-title');
			var lead = el('login-lead');
			var submit = el('login-submit');
			var toggle = el('login-toggle');
			var google = el('login-google');
			var or = el('login-or');
			if (title) title.textContent = signup ? 'Create account' : 'Sign in';
			if (lead) lead.textContent = signup
				? 'Register, verify your email, then wait to be approved.'
				: 'Sign in once and this remote works from anywhere.';
			if (submit) submit.value = signup ? 'Create account' : 'Sign in';
			if (toggle) toggle.textContent = signup ? 'I already have an account' : 'Create an account';
			var confirmRow = el('login-confirm-row');
			if (confirmRow) confirmRow.style.display = signup ? 'block' : 'none';
			if (google) google.style.display = signup ? 'none' : '';
			if (or) or.style.display = signup ? 'none' : '';
			// Leaving sign-up mode must not carry a half-typed confirmation along.
			var confirm = el('login-password2');
			if (confirm && !signup) confirm.value = '';
			setStatus('', false);
		}

		// Google, with a redirect fallback. Popups are blocked in iframes and are
		// unreliable in an iOS standalone window, so a blocked popup becomes a full
		// redirect; when the browser comes back the restore path finishes the job.
		function signInWithGoogle() {
			if (!auth) return;
			setBusy(true);
			setStatus('', false);

			var provider = new firebase.auth.GoogleAuthProvider();
			provider.setCustomParameters({ prompt: 'select_account' });

			auth.signInWithPopup(provider).then(function (credential) {
				return finishSignIn(credential.user);
			}).catch(function (error) {
				var code = (error && error.code) || '';
				if (code === 'auth/popup-blocked' || code === 'auth/popup-closed-by-user'
					|| code === 'auth/operation-not-supported-in-this-environment'
					|| code === 'auth/cancelled-popup-request') {
					if (code === 'auth/popup-closed-by-user') {
						setBusy(false);
						setStatus('Sign-in was cancelled.', false);
						return;
					}
					return auth.signInWithRedirect(provider);
				}
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

		// Anything that goes wrong before the form is usable has to be visible. The
		// worst failure mode for this page is a blank card with no explanation.
		window.addEventListener('error', function (event) {
			if (event && event.message) setStatus('Page error: ' + event.message, true);
		});

		document.addEventListener('DOMContentLoaded', function () {
			// The form is rendered visible by the server, so a script failure leaves
			// something on screen instead of an empty card.
			if (!config.apiKey) {
				setStatus('Sign-in is not configured.', true);
				return;
			}
			if (typeof window.firebase === 'undefined' || typeof firebase.auth !== 'function') {
				setStatus('The sign-in library did not load. Reload the page.', true);
				return;
			}

			try {
				firebase.initializeApp(config);
				auth = firebase.auth();
			} catch (error) {
				setStatus('Sign-in could not start: ' + ((error && error.message) || error), true);
				return;
			}

			// Arriving from the Sign out button: the server session is already gone,
			// but Firebase still holds a login, and the restore path below would
			// silently sign the person straight back in. Clear it, then drop the flag
			// from the URL so a later sign-in does not trip over it.
			if (/[?&]signedout=1(&|$)/.test(window.location.search)) {
				auth.signOut();
				setStatus('You have signed out.', false);
				if (window.history && window.history.replaceState) {
					var kept = window.location.search.replace(/^\?/, '').split('&').filter(function (part) {
						return part && part.indexOf('signedout=') !== 0;
					});
					window.history.replaceState({}, document.title,
						window.location.pathname + (kept.length ? '?' + kept.join('&') : ''));
				}
				return;
			}

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
					if (mode === 'signup') {
						var confirm = (el('login-password2') || {}).value || '';
						if (password !== confirm) {
							setStatus('The two passwords do not match.', true);
							return;
						}
						signUp(email.trim(), password);
					} else {
						signIn(email.trim(), password);
					}
				});
			}

			var reveal = el('login-reveal');
			if (reveal) {
				reveal.addEventListener('click', function (event) {
					event.preventDefault();
					var showing = reveal.getAttribute('aria-pressed') === 'true';
					var next = showing ? 'password' : 'text';
					['login-password', 'login-password2'].forEach(function (id) {
						var input = el(id);
						if (input) input.setAttribute('type', next);
					});
					reveal.textContent = showing ? 'Show' : 'Hide';
					reveal.setAttribute('aria-pressed', showing ? 'false' : 'true');
					reveal.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
				});
			}

			var toggleLink = el('login-toggle');
			if (toggleLink) {
				toggleLink.addEventListener('click', function (event) {
					event.preventDefault();
					if (busy) return;
					setMode(mode === 'signup' ? 'signin' : 'signup');
				});
			}

			var googleButton = el('login-google');
			if (googleButton) {
				googleButton.addEventListener('click', function (event) {
					event.preventDefault();
					if (busy) return;
					signInWithGoogle();
				});
			}

			// A returning user with a live server session should never see the form.
			if (auth.currentUser) {
				if (form) form.style.display = 'none';
				var restoring = el('login-restoring');
				if (restoring) restoring.style.display = 'block';
				attemptRestore();
			}
		});
	})();
}
