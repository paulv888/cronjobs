if(!window.scriptRemoteHasRun) { 
	window.scriptRemoteHasRun = true; 


   var VloRemote = {
			'COMMAND_SET_VALUE' : 145,
			'COMMAND_GET_VALUE' : 136,
			'COMMAND_TOGGLE' : 19,
			'MY_DEVICE_ID' : 164,
			'GROUP_NO_SELECTED' : 0,
			'DIM_NO_SELECTED' : 19,
			'url' : '/ha/process.php',
			'timer' : false,
			'timerDelay' : 0,
			'repeattimer' : false,
			'pollInFlight' : false,
			'darkStartHour' : 19,
			'darkEndHour' : 7
	};

	var isMobile = {
		Android: function() {
			return /Android/i.test(navigator.userAgent);
		},
		iOS: function() {
			return /iPhone|iPad|iPod/i.test(navigator.userAgent);
		},
		any: function() {
			return (isMobile.Android() || isMobile.iOS());
		}
	};


	jQuery(document).on('click', '.myDebugOutputTitle', function(event){
		jQuery(this).next().toggleClass('myDebugHidden')
	});	

	jQuery(document).ready(function(){

		var eventname;
		var activeDownKey = null;

		// Build the request for a dropdown/jump option: "S<id>" scheme, "C<id>" command, "V<value>" value
		function buildParams (selected, selectedtext, key) {
			if (typeof selected !== 'string' || selected.length === 0) return null;
			var prefix = selected.charAt(0);
			var id = selected.substring(1);
			var keys = key ? [key] : [];
			if (prefix == 'S') {
				return {callerID: VloRemote.MY_DEVICE_ID, messagetypeID: 'MESS_TYPE_SCHEME', schemeID: id, commandvalue: selectedtext};
			}
			if (prefix == 'C') {
				return {callerID: VloRemote.MY_DEVICE_ID, messagetypeID: 'MESS_TYPE_REMOTE_KEY', keys: keys, commandID: id};
			}
			if (prefix == 'V') {
				return {callerID: VloRemote.MY_DEVICE_ID, messagetypeID: 'MESS_TYPE_REMOTE_KEY', keys: keys, commandvalue: id};
			}
			return null;
		}

		// Send a single remote key press/release
		function sendKey (key, mouse) {
			if (!key) return;
			callAjax({callerID: VloRemote.MY_DEVICE_ID, messagetypeID: 'MESS_TYPE_REMOTE_KEY', keys: [key], mouse: mouse}, false);
		}

		// Repeat a key (volume, cursor up/down) while the control is held
		function startRepeat (control) {
			var key = control.attr('data-remotekey');
			var repeattime = parseInt(control.attr('data-repeat-time'), 10);
			if (!key || isNaN(repeattime) || repeattime <= 0) return;
			stopRepeat();
			control.addClass('sending');
			VloRemote.repeattimer = window.setInterval(function () { sendKey(key, 'down'); }, repeattime);
		}

		function stopRepeat () {
			if (VloRemote.repeattimer) window.clearInterval(VloRemote.repeattimer);
			VloRemote.repeattimer = false;
			jQuery('.repeat-click-down').removeClass('sending');
		}

		// Stop repeating and release a key that is still held down
		function releaseHeld () {
			stopRepeat();
			if (activeDownKey !== null) {
				var key = activeDownKey;
				activeDownKey = null;
				sendKey(key, 'up');
			}
		}

		checkAndApplyTheme();
		refreshDiv(true, true);

		// regular down when up as well (cam move)
		eventname = isMobile.any() ? "touchstart" : "mousedown";
		jQuery('.click-down').off(eventname + '.vloremote').on(eventname + '.vloremote', function(event){
			event.preventDefault();
			activeDownKey = jQuery(this).attr("data-remotekey");
			sendKey(activeDownKey, 'down');
		});	

		// regular up from down (cam move), the window handler below is the safety net
		eventname = isMobile.any() ? "touchend" : "mouseup";
		jQuery('.click-down').off(eventname + '.vloremote').on(eventname + '.vloremote', function(event){
			event.preventDefault();
			var key = activeDownKey || jQuery(this).attr("data-remotekey");
			activeDownKey = null;
			sendKey(key, 'up');
		});	

		// repeat down when up as well (volume)
		eventname = isMobile.any() ? "touchstart" : "mousedown";
		jQuery('.repeat-click-down').off(eventname + '.vloremote').on(eventname + '.vloremote', function(event){
			event.preventDefault();
			startRepeat(jQuery(this));
		});	

		// repeat down form up (stop repeating)
		eventname = isMobile.any() ? "touchend" : "mouseup";
		jQuery('.repeat-click-down').off(eventname + '.vloremote').on(eventname + '.vloremote', function(event){
			event.preventDefault();
			stopRepeat();
		});

		// Safety net: a pointer/finger released outside the control must not leave a key held down
		jQuery(window).off('mouseup.vloremote touchend.vloremote touchcancel.vloremote')
			.on('mouseup.vloremote touchend.vloremote touchcancel.vloremote', function () {
				releaseHeld();
			});

		// regular up
		eventname = isMobile.any() ? "touchend" : "click";
		jQuery('.click-up').off(eventname + '.vloremote').on(eventname + '.vloremote', function(event){
			event.preventDefault();
			resetSelection();
			jQuery(this).addClass('group-select');
			callAjax({callerID: VloRemote.MY_DEVICE_ID, messagetypeID: 'MESS_TYPE_REMOTE_KEY', keys: [jQuery(this).attr("data-remotekey")]});
		});	

		// Generic dropdown button
		eventname = isMobile.any() ? "touchend" : "click";
		jQuery('.btndropdown a').off(eventname + '.vloremote').on(eventname + '.vloremote', function(event){
			event.preventDefault();
			event.stopImmediatePropagation();
			var $link = jQuery(this);
			var $menu = $link.closest('.btndropdown');
			var $button = $menu.closest('.dropdown').find('.rem-button');
			var selected = $link.attr('data-value');
			var selectedtext = $link.text();
			var $buttontext = $button.find('.buttontext');
			if ($buttontext.length > 0) $buttontext.text(selectedtext + ' ');
			$menu.attr('data-myvalue', selected);
			var params = buildParams(selected, selectedtext, $button.attr('data-remotekey'));
			if (params) callAjax(params);
		});

		//Dropdowns, either be command or scheme, if scheme Scommand, if with command then key needed as well 
		// Update same as latest button dropdown, allow S C or Value
		jQuery('.controlselect-button').off('change.vloremote').on('change.vloremote', function(event){
			event.preventDefault();
			var option = this.selectedOptions && this.selectedOptions.length ? this.selectedOptions[0] : this.options[this.selectedIndex];
			if (!option) return;
			var params = buildParams(option.value, jQuery(option).text(), jQuery(this).attr("data-remotekey"));
			if (params) callAjax(params);
		});	

		//this is the function that dropdown's button either schemes or commands
		// Update same as latest button dropdown, allow S C or Value
		eventname = isMobile.any() ? "touchend" : "click";
		jQuery('.jump-button').off(eventname + '.vloremote').on(eventname + '.vloremote', function(event){
			event.preventDefault();
			var select = jQuery(this).prev('.controlselect-button')[0];
			var option = select ? (select.selectedOptions && select.selectedOptions.length ? select.selectedOptions[0] : select.options[select.selectedIndex]) : null;
			if (!option) return;
			var params = buildParams(option.value, jQuery(option).text(), jQuery(this).attr("data-remotekey"));
			if (params) callAjax(params);
		});	

		// switching tabs
		eventname = isMobile.any() ? "touchend" : "click";
		jQuery('#myTab a').off(eventname + '.vloremote').on(eventname + '.vloremote', function(event){
			jQuery('#system-message-container').html('');
			//resetSelection();
		});

		// Auto refresh switch: checked = keep polling
		if (jQuery("#autorefresh").length > 0) {
			jQuery("#autorefresh").off('change.vloremote').on('change.vloremote', function(event){
				if (this.checked) {
					startTimer();
				} else {
					stopTimer();
				}
			});
		};

		// Loosing focus stop polling and release anything still held
		jQuery(window).off('blur.vloremote').on('blur.vloremote', function(){
			stopTimer();
			releaseHeld();
			console.log('Not Active, polling paused');
		});

		jQuery(window).off('focus.vloremote').on('focus.vloremote', function(){
			checkAndApplyTheme();
			refreshDiv(true, false);
			startTimer();
			console.log('JQuery Re-Activate');
		});

		// Mobile browsers do not always fire blur/focus, so watch visibility as well
		jQuery(document).off('visibilitychange.vloremote').on('visibilitychange.vloremote', function () {
			if (document.hidden) {
				stopTimer();
				releaseHeld();
			} else if (document.hasFocus()) {
				checkAndApplyTheme();
				refreshDiv(true, false);
				startTimer();
			}
		});

		// Page going away: stop the timers, do not leave a key held down
		jQuery(window).off('pagehide.vloremote').on('pagehide.vloremote', function () {
			stopTimer();
			releaseHeld();
		});

		startTimer();

	});

	function refreshDiv (showSpin, force) {

		checkAndApplyTheme();

		if (force || jQuery("#autorefresh").length == 0 || jQuery("#autorefresh").is(":checked")) {
			var keys = [];
			jQuery('.rem-button, .display').each(function() {
				var key = jQuery(this).attr('data-remotekey');
				if (key) keys.push(key);
			}) ;
			if (keys.length > 0) {
				var params = {callerID: VloRemote.MY_DEVICE_ID, messagetypeID: 'MESS_TYPE_COMMAND', keys: keys, commandID: VloRemote.COMMAND_GET_VALUE};
				callAjax(params, showSpin, true) ;
			}
		}
	};

	function callAjax (params, showSpin, isPoll) {

		if (!params) return;
		if (isPoll && VloRemote.pollInFlight) return;		// never stack polls, commands still go out

		if (showSpin === undefined) showSpin = true;
		if (showSpin) {
			//	jQuery('#system-message-container').html ('');
				jQuery('#spinner').show();
		}

		var debug = getUrlParameter('debug');
		if (typeof(debug) != "undefined") params.debug = debug;

		if (isPoll) VloRemote.pollInFlight = true;

		jQuery.ajax({
				dataType: "json",
				url: 	VloRemote.url,
				method: 'post',
				data: params,
				timeout: 360000,
				success: function(data)
				{
					processData(data);
				},
				error: function(xhr, textStatus, error)
				{
					// 401 means the session is gone - revoked, expired or never
					// established. Reload so the page can show the sign-in form; the
					// guard stops a reload loop if the server keeps refusing.
					if (xhr && xhr.status === 401) {
						stopTimer();
						if (!VloRemote.authRedirected) {
							VloRemote.authRedirected = true;
							window.location.reload();
							return;
						}
					}
					showError(textStatus+' '+error ,xhr.responseText);
				},
				complete: function()
				{
					if (isPoll) VloRemote.pollInFlight = false;
					if (showSpin) jQuery('#spinner').hide();
				}
			}
        );
	};

	function showMessage(message) {
		if ((typeof(message) != "undefined") && message.length > 0) {
			if (message.substring(0,6) != '<HTML>') {
				// console.log(message.substring(0,6));
				jQuery('#system-message-container').html('<div class="alert alert-success"><a data-dismiss="alert" class="close" href="#">&times</a>'+message+'</div>');
			} else {
				jQuery('#html-container').html('<div>'+message.substring(6)+'</div>');
			} 
		}
	}

	function showError(error, message) {
		if ((typeof(message) != "undefined") && message.length > 0) {
			if (message.indexOf('myDebugOutputTitle') > 0) {
				jQuery('#myDebug').html(message);
			} else {
				jQuery('#system-message-container').html('<div class="alert alert-error"><a data-dismiss="alert" class="close" href="#">&times</a>'+error+'<br />'+message+'</div>');
			}
		}
	}

	function showErrorMessage(message) {
		if (message.length > 0) {
			jQuery('#system-message-container').html('<div class="alert alert-error"><a data-dismiss="alert" class="close" href="#">&times</a>'+message+'</div>');
		}
	}

	function processData(data) {

		if (!data) return;

		jQuery.each(data, function(index, item){
			if (index == 'message') {
				showMessage(item);
				return;
			}
			if (index == 'error') {
				showErrorMessage(item);
				return;
			}
			if (!item || typeof item.remotekey === 'undefined') return;

			jQuery('[data-remotekey="' + item.remotekey + '"]').each(function(index){
				jQuery(this).removeClass("link-warning");
				jQuery(this).removeClass("link-down");
				if (typeof item.status !== 'undefined') {
					jQuery(this).removeClass("off");
					jQuery(this).removeClass("on");
					jQuery(this).removeClass("error");
					jQuery(this).removeClass("undefined");
					jQuery(this).removeClass("unknown");
					jQuery(this).addClass(item.status);
				} 

				if (typeof item.text !== 'undefined' && item.text !== null) {
					var text = String(item.text);
					if (jQuery(this).find('.buttontext').length > 0) {  // User this for dropdowns where text is in buttontext
						jQuery(this).find('.buttontext').html(text);
						// console.log ("found"+this);
					} else {
						// console.log ("not"+this);
						this.innerHTML = text;
					}
				} 
				if (typeof item.groupselect !== 'undefined') {
					jQuery(this).addClass('group-select');
				} 
				if (typeof item.link !== 'undefined') {
					jQuery(this).addClass(item.link);
				} 
			});
		});
	};

	function resetSelection() {
		jQuery('.group-select').each(function() {
			jQuery(this).removeClass('group-select');
		});
	}

	// Delay is 6s while the page is focused, 60s otherwise
	function startTimer (delay) {
		var wanted = delay;
		if (!wanted) wanted = (document.hasFocus() ? 6000 : 60000);
		if (VloRemote.timer && VloRemote.timerDelay === wanted) return VloRemote.timer;

		stopTimer();
		VloRemote.timerDelay = wanted;
		VloRemote.timer = window.setInterval(function(){
			refreshDiv(false, false);
		}, wanted);
		return VloRemote.timer;
	}

	function stopTimer () {
		if (VloRemote.timer) window.clearInterval(VloRemote.timer);
		VloRemote.timer = false;
		VloRemote.timerDelay = 0;
	}

	function getUrlParameter(sParam) {
		var sPageURL = window.location.search.substring(1),
			sURLVariables = sPageURL.split('&'),
			sParameterName,
			i;

		for (i = 0; i < sURLVariables.length; i++) {
			sParameterName = sURLVariables[i].split('=');

			if (sParameterName[0] === sParam) {
				return sParameterName[1] === undefined ? true : decodeURIComponent(sParameterName[1]);
			}
		}
	}

	// Dark mode belongs to remote.php opened on its own. The main Joomla site
	// loads this same script for the remotes embedded in its pages, so applying
	// the theme there would turn the whole website dark at night.
	function isDirectRemotePage() {
		if (window.VLO_REMOTE_HOST !== 'pwa') return false;
		try {
			return window.self === window.top;
		} catch (e) {
			return false;
		}
	}

	function isDarkModeTime() {
		var themeParam = getUrlParameter('theme');
		if (themeParam === 'dark') return true;
		if (themeParam === 'light') return false;

		var currentHour = new Date().getHours();
		if (VloRemote.darkStartHour > VloRemote.darkEndHour) {
			// Night spans midnight (e.g. 19:00 to 07:00)
			return (currentHour >= VloRemote.darkStartHour || currentHour < VloRemote.darkEndHour);
		} else {
			return (currentHour >= VloRemote.darkStartHour && currentHour < VloRemote.darkEndHour);
		}
	}

	function applyDarkMode(isDark) {
		var styleId = 'vlo-dark-mode-styles';
		var themeMeta = jQuery('meta[name="theme-color"]');

		if (isDark) {
			jQuery('html').attr('data-bs-theme', 'dark');
			jQuery('body').addClass('dark-mode');
			if (themeMeta.length) {
				themeMeta.attr('content', '#18191a');
			}

			if (!document.getElementById(styleId)) {
				var css = [
					'body.dark-mode { background-color: #18191a !important; color: #e4e6eb !important; }',
					'body.dark-mode .keyscell { background-color: transparent !important; }',
					'body.dark-mode td.keyscellempty, body.dark-mode .keyscellempty { background-color: #18191a !important; box-shadow: none !important; border-color: transparent !important; }',
					'body.dark-mode #myTabContent, body.dark-mode .nav-tabs + #myTabContent, body.dark-mode .nav-tabs + .tab-content { background: #18191a !important; background-color: #18191a !important; border-color: #3e4042 !important; box-shadow: none !important; }',
					'body.dark-mode #myTabContent .tab-pane { background-color: #18191a !important; }',
					'body.dark-mode #myTabContent .table, body.dark-mode #myTabContent .table-rem-condensed { --bs-table-bg: transparent !important; --bs-table-accent-bg: transparent !important; background-color: transparent !important; color: #e4e6eb !important; }',
					'body.dark-mode #myTab { border-bottom-color: #3e4042 !important; background-color: transparent !important; }',
					'body.dark-mode #myTab > li:not(.active) { background-color: #242526 !important; }',
					'body.dark-mode #myTab > li:not(.active) > a { background-color: #242526 !important; color: #adb5bd !important; }',
					'body.dark-mode #myTab > li:not(.active) > a:hover { background-color: #3a3b3c !important; color: #ffffff !important; }',
					'body.dark-mode .rem-button.button:not(.on):not(.off):not(.unknown):not(.btn-warning):not(.link-down):not(.link-warning) { background-color: #2e3033 !important; color: #e4e6eb !important; border-color: #44474d !important; }',
					'body.dark-mode .rem-button.button:not(.on):not(.off):not(.unknown):not(.btn-warning):hover { background-color: #3e4247 !important; color: #ffffff !important; }',
					'body.dark-mode .dropdown-menu, body.dark-mode ul.btndropdown { background-color: #242526 !important; border: 1px solid #3e4042 !important; }',
					'body.dark-mode .dropdown-menu a, body.dark-mode .dropdown-item, body.dark-mode ul.btndropdown li a { background-color: #242526 !important; color: #e4e6eb !important; }',
					'body.dark-mode .dropdown-menu a:hover, body.dark-mode .dropdown-item:hover, body.dark-mode ul.btndropdown li a:hover { background-color: #3a3b3c !important; color: #ffffff !important; }',
					'body.dark-mode .controlselect, body.dark-mode .controlselect-button { background-color: #242526 !important; color: #e4e6eb !important; border: 1px solid #44474d !important; }',
					'body.dark-mode .jump-button { background-color: #2e3033 !important; color: #e4e6eb !important; border-color: #44474d !important; }',
					'body.dark-mode .form-check-label { color: #e4e6eb !important; }',
					'body.dark-mode div#spinner { background-color: #242526 !important; border-color: #444 !important; color: #fff !important; }',
					'body.dark-mode .myDebugOutputTitle, body.dark-mode .myDebugOutput, body.dark-mode .myDebugOutput pre { background-color: #242526 !important; color: #ccc !important; border-color: #444 !important; }'
				].join('\n');

				var style = document.createElement('style');
				style.id = styleId;
				style.type = 'text/css';
				style.appendChild(document.createTextNode(css));
				document.head.appendChild(style);
			}
		} else {
			jQuery('html').removeAttr('data-bs-theme');
			jQuery('body').removeClass('dark-mode');
			if (themeMeta.length) {
				themeMeta.attr('content', '#E9ECE5');
			}
			var existingStyle = document.getElementById(styleId);
			if (existingStyle && existingStyle.parentNode) {
				existingStyle.parentNode.removeChild(existingStyle);
			}
		}
	}

	function checkAndApplyTheme() {
		if (!isDirectRemotePage()) return;
		var isDark = isDarkModeTime();
		applyDarkMode(isDark);
	}

	// Signing out has to clear both halves: the server row and the browser's
	// Firebase state. Without the second, the login page would silently restore
	// the session on the next load and the button would look broken.
	function signOut() {
		stopTimer();

		// Hand the push token back while the session still works, so the next person
		// on this phone does not inherit these alerts. push.php needs the session, so
		// this has to finish before the logout call.
		var unregister = (window.VloPush && window.VloPush.unregister)
			? window.VloPush.unregister()
			: Promise.resolve();

		unregister.catch(function () { return null; }).then(function () {
			return fetch('/ha/auth.php', {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ action: 'logout' })
			});
		}).catch(function () { return null; }).then(function () {
			window.location.href = '/ha/remote.php?signedout=1';
		});
	}

	window.VloRemoteSignOut = signOut;
}
