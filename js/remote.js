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
			'timer' : false
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

		refreshDiv(true, true);
		
		// window.addEvent('domready', function() {
			// document.getElements('.myDebugOutputTitle').each(function (title) {
				// title.addEvent('click', function (e) {
					// title.getNext().toggleClass('myDebugHidden');
				// });
			// });
		// })
		// jQuery('.myDebugOutputTitle').bind('click', function(event){
			// // event.preventDefault()
			// // event.stopImmediatePropagation()
			// getNext().toggleClass('myDebugHidden');
		// });	
			// jQuery(this).on('click', function(event){
			// // event.preventDefault()
			// // event.stopImmediatePropagation()
				// console.log('clicked');
				// jQuery(this).next().toggleClass('myDebugHidden')
			// });	

		// regular down when up as well (cam move)
		eventname = isMobile.any() ? "touchstart" : "mousedown";
		jQuery('.click-down').unbind(eventname);
		jQuery('.click-down').bind(eventname, function(event){
			event.preventDefault()
			//event.stopImmediatePropagation()
			var keys = [];
			keys.push(jQuery(this).attr("data-remotekey"));
			var params = {callerID: VloRemote.MY_DEVICE_ID, messagetypeID: 'MESS_TYPE_REMOTE_KEY', keys: keys, mouse: 'down'};
			callAjax(params, false) ;
		});	

		// regular up from down (cam move)
		eventname = isMobile.any() ? "touchend" : "mouseup";
		jQuery('.click-down').unbind(eventname);
		jQuery('.click-down').bind(eventname, function(event){
			event.preventDefault()
			//event.stopImmediatePropagation()
			var keys = [];
			keys.push(jQuery(this).attr("data-remotekey"));
			var params = {callerID: VloRemote.MY_DEVICE_ID, messagetypeID: 'MESS_TYPE_REMOTE_KEY', keys: keys, mouse: 'up'};
			callAjax(params, false) ;
		});	

		// repeat down when up as well (volume)
		eventname = isMobile.any() ? "touchstart" : "mousedown";
		jQuery('.repeat-click-down').unbind(eventname);
		jQuery('.repeat-click-down').bind(eventname, function(event){
			event.preventDefault()
			//event.stopPropagation()
			//event.stopImmediatePropagation()
			// handle repeat sending for volume and cursor up/down
			var keys = [];
			keys.push(jQuery(this).attr("data-remotekey"));
			var repeattime = jQuery(this).attr("data-repeat-time")
			jQuery(this).addClass('sending');
			VloRemote.repeattimer = setInterval( function() { repeatSend(keys) }, repeattime );
			// console.log(VloRemote.repeattimer + " time: " + repeattime + "\n");
		});	

		function repeatSend(keys) {
			// console.log ("Sending " + keys);
			var params = {callerID: VloRemote.MY_DEVICE_ID, messagetypeID: 'MESS_TYPE_REMOTE_KEY', keys: keys, mouse: 'down'};
			callAjax(params, false);
			//jQuery(control).removeClass('sending');
		};
		
		// repeat down form up (stop repeating)
		eventname = isMobile.any() ? "touchend" : "mouseup";
		jQuery('.repeat-click-down').unbind(eventname);
		jQuery('.repeat-click-down').bind(eventname, function(event){
			event.preventDefault()
			//event.stopPropagation()
			// handle repeat stop
			jQuery(this).removeClass('sending');
			clearInterval(VloRemote.repeattimer);
		});

		// regular up
		eventname = isMobile.any() ? "touchend" : "click";
		jQuery('.click-up').unbind(eventname);
		jQuery('.click-up').on(eventname, function(event){
			event.preventDefault()
			//event.stopImmediatePropagation()
			var commandvalue = 100;
			// check if in dim mode
			commandvalue = parseInt(jQuery('.tab-pane.active .dimmer').attr('data-myvalue'));
			if (commandvalue ==  VloRemote.DIM_NO_SELECTED || isNaN(commandvalue)) commandvalue = null;
			var keys = [];
			keys.push(jQuery(this).attr("data-remotekey"));
			var params = {callerID: VloRemote.MY_DEVICE_ID, messagetypeID: 'MESS_TYPE_REMOTE_KEY', keys: keys, commandvalue: commandvalue};
			resetSelection();
			jQuery(this).addClass('group-select');
			callAjax (params) ;
		});	

		// Generic dropdown button
		eventname = isMobile.any() ? "touchend" : "click";
		jQuery('.btndropdown a').unbind(eventname);
		jQuery('.btndropdown a').bind(eventname, function(event){
			event.preventDefault()
			//event.stopImmediatePropagation()
			var mbut = this.parentNode.parentNode.parentNode;
			mbut.getElementsByClassName("buttontext")[0].textContent = this.text+' ';
			var selected = jQuery(this).attr('data-value');
			var selectedtext = jQuery(this).text();
			jQuery(this.parentNode.parentNode).attr('data-myvalue', selected);
			var keys = [];
			keys.push(jQuery(this.parentNode.parentNode).find('.rem-button').attr('data-remotekey'));
			if (selected.charAt(0) == 'S') {
				var params = {callerID: VloRemote.MY_DEVICE_ID, messagetypeID: 'MESS_TYPE_SCHEME', keys: keys, schemeID:selected.substring(1), commandvalue:selectedtext};
			} else if (selected.charAt(0) == 'C') {
				var params = {callerID: VloRemote.MY_DEVICE_ID, messagetypeID: 'MESS_TYPE_REMOTE_KEY', keys: keys , commandID:selected.substring(1)};
			} else if (selected.charAt(0) == 'V') {
				var params = {callerID: VloRemote.MY_DEVICE_ID, messagetypeID: 'MESS_TYPE_REMOTE_KEY', keys: keys , commandvalue:selected.substring(1)};
			}
			callAjax (params) ;
		});

		//Dropdowns, either be command or scheme, if scheme Scommand, if with command then key needed as well 
		// Update same as latest button dropdown, allow S C or Value
		jQuery('.controlselect-button').unbind("change");
		jQuery('.controlselect-button').change( function(event){
			event.preventDefault()
			//event.stopImmediatePropagation()
			var selected = jQuery(this.selectedOptions).attr('value');
			var selectedtext = jQuery(this.selectedOptions).text();
 			var keys = [];
			keys.push(jQuery(this).attr("data-remotekey"));
			if (selected.charAt(0) == 'S') {
				var params = {callerID: VloRemote.MY_DEVICE_ID, messagetypeID: 'MESS_TYPE_SCHEME', schemeID:selected.substring(1), commandvalue:selectedtext};
			} else if (selected.charAt(0) == 'C') {
				var params = {callerID: VloRemote.MY_DEVICE_ID, messagetypeID: 'MESS_TYPE_REMOTE_KEY', keys: keys, commandID:selected.substring(1)};
			} else if (selected.charAt(0) == 'V') {
				var params = {callerID: VloRemote.MY_DEVICE_ID, messagetypeID: 'MESS_TYPE_REMOTE_KEY', keys: keys, commandvalue:selected.substring(1)};
			}
			callAjax (params) ;
		});	

		//this is the function that dropdown's button either schemes or commands
		// Update same as latest button dropdown, allow S C or Value
		eventname = isMobile.any() ? "touchend" : "click";
		jQuery('.jump-button').unbind(eventname);
		jQuery('.jump-button').bind(eventname, function(event){
			event.preventDefault()
			//event.stopImmediatePropagation()
			var selected = jQuery(jQuery(this).prev('.controlselect-button')[0].selectedOptions).attr('value');
			var selectedtext = jQuery(jQuery(this).prev('.controlselect-button')[0].selectedOptions).text();
			var keys = [];
			keys.push(jQuery(this).attr("data-remotekey"));
			if (selected.charAt(0) == 'S') {
				var params = {callerID: VloRemote.MY_DEVICE_ID, messagetypeID: 'MESS_TYPE_SCHEME', schemeID:selected.substring(1), commandvalue:selectedtext};
			} else if (selected.charAt(0) == 'C'){
				var params = {callerID: VloRemote.MY_DEVICE_ID, messagetypeID: 'MESS_TYPE_REMOTE_KEY', keys: keys, commandID:selected.substring(1)};
			} else if (selected.charAt(0) == 'V'){
				var params = {callerID: VloRemote.MY_DEVICE_ID, messagetypeID: 'MESS_TYPE_REMOTE_KEY', keys: keys, commandvalue:selected.substring(1)};
			}
			callAjax (params) ;
		});	

		//Run scheme button (
		jQuery('.scheme-button').unbind('click');
		jQuery('.scheme-button').click( function(event){
			event.preventDefault()
			//event.stopImmediatePropagation()
			var params = {callerID: VloRemote.MY_DEVICE_ID, messagetypeID: 'MESS_TYPE_SCHEME', schemeID:jQuery(this).get('value')};
			callAjax (params) ;
		});	

		//Run command button (
		jQuery('.command-button').unbind('click');
		jQuery('.command-button').click( function(event){
			event.preventDefault()
			//event.stopImmediatePropagation()
			// event.stop();
			var params = {callerID: VloRemote.MY_DEVICE_ID, messagetypeID: 'MESS_TYPE_COMMAND', commandID:jQuery(this).get('value')};
			callAjax (params) ;
		});	

		// switching tabs
		eventname = isMobile.any() ? "touchend" : "click";
		jQuery('#myTab a').unbind(eventname);
		jQuery('#myTab a').bind(eventname, function(event){
			jQuery('#system-message-container').html('');
			//resetSelection();
		});

		if (jQuery("#autorefresh").length > 0) {
			eventname = isMobile.any() ? "touchend" : "click";
			jQuery("#autorefresh").bind(eventname, function(event){
				if (jQuery(this).is(":checked")) {
					clearInterval(VloRemote.timer);
					VloRemote.timer = false;
				} else {
					VloRemote.timer = startTimer();
				}
			});
		};

		// Loosing focus stop 
		jQuery(window).blur(function(){
			clearInterval(VloRemote.timer);
			VloRemote.timer = false;
			console.log('JQuery Hiding');
		});

		jQuery(window).focus(function(){
			refreshDiv(true, false);
			VloRemote.timer = startTimer();
			console.log('JQuery Re-Activate');
		});

		VloRemote.timer = startTimer();

	});

	function refreshDiv (showSpin, force) {

		if (force || jQuery("#autorefresh").length == 0 || jQuery("#autorefresh").is(":checked")) {
			console.log('refreshDiv');
			var keys = [];
			jQuery('.rem-button, .display').each(function() {
				keys.push(jQuery(jQuery(this)).attr('data-remotekey'));
			}) ;
			if (keys.length > 0) {
				var params = {callerID: VloRemote.MY_DEVICE_ID, messagetypeID: 'MESS_TYPE_COMMAND', keys: keys, commandID: VloRemote.COMMAND_GET_VALUE};
				callAjax(params, showSpin) ;
			}
		}
	};

	function callAjax (params, showSpin) {

		if (showSpin === undefined) showSpin = true;
		if (showSpin) {
			//	jQuery('#system-message-container').html ('');
				jQuery('#spinner').show();
		}

		var debug = getUrlParameter('debug');
		if (typeof(debug) != "undefined") params.debug = debug;

    	params.apikey = getUrlParameter('key');

		// console.log(params);

		// Start timer, incase we missed the unhide event
		VloRemote.timer = startTimer();


		var keysRequest = jQuery.ajax({
				dataType: "json",
				url: 	VloRemote.url,
				method: 'post',
				data: params,
				timeout: 60000,
				success: function(data)
				{
					processData(data);
					if (showSpin) jQuery('#spinner').hide();
				},
				error: function(xhr, textStatus, error)
				{
					showError(textStatus+' '+error ,xhr.responseText);
					if (showSpin) jQuery('#spinner').hide();
				}
			}
        );
	};

	function callAjaxSync(params) {

		jQuery('#system-message-container').html ('');
		jQuery('#spinner').show();

		var debug = getUrlParameter('debug');
		params.debug = debug;
    	params.apikey = getUrlParameter('key');
		
		var d = new Date();
		params.time = +d.getTime();
		
		var keysRequest = jQuery.ajax({
				dataType: "json",
				url: 	VloRemote.url,
				method: 'post',
				data: params,
				timeout: 0,
				async: false,
				success: function(data)
				{
					processData(data);
					jQuery('#spinner').hide();
				},
				error: function(xhr, textStatus, error)
				{
					showError(textStatus+' '+error ,xhr.responseText);
					jQuery('#spinner').hide();
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

		jQuery.each(data, function(index, item){
			if (index == 'message') {
				showMessage(item);
			}
			if (index == 'error') {
				showErrorMessage(item);
			}
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

				// if (typeof item.text !== 'undefined') {
					// if (item.text.search('_apikey_')>=0) {
						// item.text = item.text.replace('_apikey_', getUrlParameter('key') );
						// console.log(item.text);
					// }
					// jQuery(this).html(item.text);
				// }
				if (typeof item.text !== 'undefined') {
					if (item.text.search('_apikey_')>=0) {
						item.text = item.text.replace('_apikey_', getUrlParameter('key') );
						// console.log(item.text);
					}
					// if (typeof jQuery(this .getElementsByClassName("buttontext")[0]) !== 'undefined') {
						// jQuery(this .getElementsByClassName("buttontext")[0]).html(item.text);
					// }
				
					if (jQuery(this).find('.buttontext').length > 0) {  // User this for dropdowns where text is in buttontext
						jQuery(this).find('.buttontext').html(item.text);
						// jQuery(this .getElementsByClassName("buttontext")[0]).html(item.text);
						// console.log ("found"+this);
					} else {
						// console.log ("not"+this);
						this.innerHTML = item.text;
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

	function startTimer() {
		if (VloRemote.timer == false) {
			clearInterval(VloRemote.timer);
			timer = window.setInterval(function(){
				refreshDiv(false, false);
			}, 6000);
			return timer;
		} else {
			return VloRemote.timer;
		}
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
}
