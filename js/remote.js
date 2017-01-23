
if(!window.scriptRemoteHasRun) { 
	window.scriptRemoteHasRun = true; 

   var VloRemote = {
			'COMMAND_SET_VALUE' : 145,
			'COMMAND_GET_VALUE' : 136,
			'COMMAND_TOGGLE' : 19,
			'MY_DEVICE_ID' : 164,
			'GROUP_NO_SELECTED' : 0,
			'DIM_NO_SELECTED' : 19,
			'url' : '/cronjobs/70D455DC-ACB4-4525-8A85-E6009AE93AF4/process.php',
			'timer' : 0
	};

	var isMobile = {
		Android: function() {
			return /Android/i.test(navigator.userAgent);
		},
		BlackBerry: function() {
			return /BlackBerry/i.test(navigator.userAgent);
		},
		iOS: function() {
			return /iPhone|iPad|iPod/i.test(navigator.userAgent);
		},
		Windows: function() {
			return /IEMobile/i.test(navigator.userAgent);
		},
		any: function() {
			return (isMobile.Android() || isMobile.BlackBerry() || isMobile.iOS() || isMobile.Windows());
		}
	};

	jQuery(document).ready(function(){

		if (isMobile.Android() == true) {

			console = {
				"_log" : [],
				"log" : function() {
				  var arr = [];
				  for ( var i = 0; i < arguments.length; i++ ) {
					arr.push( arguments[ i ] );
				  }
				  this._log.push( arr.join( ", ") );
				},
				"trace" : function() {
				  var stack;
				  try {
					throw new Error();
				  } catch( ex ) {
					stack = ex.stack;
				  }
				  console.log( "console.trace()\n" + stack.split( "\n" ).slice( 2 ).join( "  \n" ) );
				},
				"dir" : function( obj ) {
				  console.log( "Content of " + obj );
				  for ( var key in obj ) {
					var value = typeof obj[ key ] === "function" ? "function" : obj[ key ];
					console.log( " -\"" + key + "\" -> \"" + value + "\"" );
				  }
				},
				"show" : function() {
				  alert( this._log.join( "\n" ) );
				  this._log = [];
				}
			};
		}
	
		// window.onerror = function( msg, url, line ) {
			// console.log("ERROR: \"" + msg + "\" at \"" + "\", line " + line);
		// }

		// Android 3 fingers
		window.addEventListener( "touchstart", function( e ) {
			if( e.touches.length === 3 ) {
			  console.show();
			}
		});

		
		// regular down when up as well (cam move)
		eventname = isMobile.any() ? "touchstart" : "mousedown";
		jQuery('.click-down').unbind(eventname);
		jQuery('.click-down').bind(eventname, function(event){
			event.preventDefault()
			event.stopImmediatePropagation()
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
			event.stopImmediatePropagation()
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
			event.stopPropagation()
			//event.stopImmediatePropagation()
			// handle repeat sending for volume and cursor up/down
			var keys = [];
			keys.push(jQuery(this).attr("data-remotekey"));
			var repeattime = jQuery(this).attr("data-repeat-time")
			jQuery(this).addClass('sending');
			VloRemote.repeattimer = setInterval( function() { repeatSend(keys) }, repeattime );
			console.log(VloRemote.repeattimer + " time: " + repeattime + "\n");
		});	

		function repeatSend(keys) {
			console.log ("Sending " + keys);
			var params = {callerID: VloRemote.MY_DEVICE_ID, messagetypeID: 'MESS_TYPE_REMOTE_KEY', keys: keys, mouse: 'down'};
			callAjax(params, false);
			//jQuery(control).removeClass('sending');
		};
		
		// repeat down form up (stop repeating)
		eventname = isMobile.any() ? "touchend" : "mouseup";
		jQuery('.repeat-click-down').unbind(eventname);
		jQuery('.repeat-click-down').bind(eventname, function(event){
			event.preventDefault()
			event.stopPropagation()
			// handle repeat stop
			jQuery(this).removeClass('sending');
			clearInterval(VloRemote.repeattimer);
		});

		// regular up
		//jQuery('.click-up').unbind('click');
		eventname = isMobile.any() ? "touchend" : "click";
		jQuery('.click-up').unbind(eventname);
		jQuery('.click-up').bind(eventname, function(event){
			event.preventDefault()
			event.stopImmediatePropagation()
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
		jQuery('.btndropdown li a').unbind(eventname);
		jQuery('.btndropdown li a').bind(eventname, function(event){
			//event.preventDefault()
			// event.stopImmediatePropagation()
			var mbut = this.parentNode.parentNode.parentNode;
			mbut.getElementsByClassName("buttontext")[0].textContent = this.text+' ';
			var selected = jQuery(this).attr('data-value');
			var selectedtext = jQuery(this).text();
			jQuery(this.parentNode.parentNode).attr('data-myvalue', selected);
			var keys = [];
			keys.push(jQuery(this.parentNode.parentNode).attr("data-remotekey"));
			if (selected.charAt(0) == 'S') {
				var params = {callerID: VloRemote.MY_DEVICE_ID, messagetypeID: 'MESS_TYPE_SCHEME', keys: keys, schemeID:selected.substring(1), commandvalue:selectedtext};
			} else if (selected.charAt(0) == 'C') {
				var params = {callerID: VloRemote.MY_DEVICE_ID, messagetypeID: 'MESS_TYPE_REMOTE_KEY', keys: keys , commandID:selected.substring(1)};
			} else if (selected.charAt(0) == 'V') {
				var params = {callerID: VloRemote.MY_DEVICE_ID, messagetypeID: 'MESS_TYPE_REMOTE_KEY', keys: keys , commandvalue:selected.substring(1)};
			}
			callAjax (params) ;
		});

		// Dimmer dropdowns
		eventname = isMobile.any() ? "touchend" : "click";
		jQuery('.dimmer li a').unbind(eventname);
		jQuery('.dimmer li a').bind(eventname, function(event){
			event.preventDefault()
			// event.stopImmediatePropagation()
			var mbut = this.parentNode.parentNode.parentNode;
			mbut.getElementsByClassName("buttontext")[0].textContent = this.text+' ';
			var selected = jQuery(this).attr('data-value');
			var selectedtext = jQuery(this).text();
			jQuery(this.parentNode.parentNode).attr('data-myvalue', selected);

			// now find all selected button and send dim value (Either selected over click or over group)
			var keys = [];
			jQuery('.group-select').each(function() {
				keys.push(jQuery(jQuery(this)).attr('data-remotekey'));
			}) ;
			if (keys.length > 0) {
				if (selected.charAt(0) == 'S') {
					var params = {callerID: VloRemote.MY_DEVICE_ID, messagetypeID: 'MESS_TYPE_SCHEME', keys: keys, schemeID:selected.substring(1), commandvalue:selectedtext};
				} else if (selected.charAt(0) == 'C') {
					var params = {callerID: VloRemote.MY_DEVICE_ID, messagetypeID: 'MESS_TYPE_REMOTE_KEY', keys: keys , commandID:selected.substring(1)};
				} else if (selected.charAt(0) == 'V') {
					var params = {callerID: VloRemote.MY_DEVICE_ID, messagetypeID: 'MESS_TYPE_REMOTE_KEY', keys: keys, commandID: VloRemote.COMMAND_SET_VALUE, commandvalue: parseInt(selected.substring(1))};
				}
				callAjax (params) ;
			}
			if (selected == "19") {					// On/Off toggle
				jQuery(mbut).removeClass('btn-info');
				jQuery(mbut).addClass('btn-warning');
			} else {								// dim value
				if (keys.length == 0) showMessage('Please select lights you want to dim or on/off together');
			}
		});
		
		//Dropdowns, either be command or scheme, if scheme Scommand, if with command then key needed as well 
		// Update same as latest button dropdown, allow S C or Value
		jQuery('.controlselect-button').unbind("change");
		jQuery('.controlselect-button').change( function(event){
			event.preventDefault()
			event.stopImmediatePropagation()
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
			event.stopImmediatePropagation()
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
			event.stopImmediatePropagation()
			var params = {callerID: VloRemote.MY_DEVICE_ID, messagetypeID: 'MESS_TYPE_SCHEME', schemeID:jQuery(this).get('value')};
			callAjax (params) ;
		});	

		//Run command button (
		jQuery('.command-button').unbind('click');
		jQuery('.command-button').click( function(event){
			event.preventDefault()
			event.stopImmediatePropagation()
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
				if (jQuery(this).hasClass('active')) {
					clearInterval(VloRemote.timer);
				} else {
					VloRemote.timer = startTimer();
				}
			});
		};
	
		// Loosing focus stop 
		(function() {
		  var hidden = "hidden";

		  // Standards:
		  if (hidden in document)
			document.addEventListener("visibilitychange", onchange);
		  else if ((hidden = "mozHidden") in document)
			document.addEventListener("mozvisibilitychange", onchange);
		  else if ((hidden = "webkitHidden") in document)
			document.addEventListener("webkitvisibilitychange", onchange);
		  else if ((hidden = "msHidden") in document)
			document.addEventListener("msvisibilitychange", onchange);
		  // IE 9 and lower:
		  else if ("onfocusin" in document)
			document.onfocusin = document.onfocusout = onchange;
		  // All others:
		  else
			window.onpageshow = window.onpagehide
			= window.onfocus = window.onblur = onchange;

		  function onchange (evt) {
			var v = "visible", h = "hidden",
				evtMap = {
				  focus:v, focusin:v, pageshow:v, blur:h, focusout:h, pagehide:h
				};

			evt = evt || window.event;
			if (evt.type in evtMap)
			  document.body.className = evtMap[evt.type];
			else {
			  document.body.className = this[hidden] ? "hidden" : "visible";
			  if (this[hidden]) {
				clearInterval(VloRemote.timer);
			} else {
				// Show user we are refreshing
				refreshDiv(true);
				VloRemote.timer = startTimer();
			}
			}
		  }

		  // set the initial state (but only if browser supports the Page Visibility API)
		  if( document[hidden] !== undefined )
			onchange({type: document[hidden] ? "blur" : "focus"});
		})();
		
		VloRemote.timer = startTimer();
		
	});

	function refreshDiv (showSpin) {

		if (jQuery("#autorefresh").length == 0 || jQuery("#autorefresh").hasClass('active')) {
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
				jQuery('#system-message-container').html ('');
				jQuery('#spinner').show();
		}
		var keysRequest = jQuery.ajax({
				dataType: "json",
				url: 	VloRemote.url,
				method: 'post',
				data: params,
				timeout: 10000,
				success: function(data)
				{
					processData(data);
					if (showSpin) jQuery('#spinner').hide();
				},
				error: function(xhr, textStatus, error)
				{
					showError(textStatus+' '+error+'</br>'+xhr.responseText);
					if (showSpin) jQuery('#spinner').hide();
				},
			}
        );
	};

	function callAjaxSync(params) {
	
		jQuery('#system-message-container').html ('');
		jQuery('#spinner').show();
       var keysRequest = jQuery.ajax({
				dataType: "json",
				url: 	VloRemote.url,
				method: 'post',
				data: params,
				timeout: 10000,
				async: false,
				success: function(data)
				{
					processData(data);
					jQuery('#spinner').hide();
				},
				error: function(xhr, textStatus, error)
				{
					showError(textStatus+' '+error+'</br>'+xhr.responseText);
					jQuery('#spinner').hide();
				},
			}
        );
	};

	function showMessage(message) {
		if (message.length > 0) {
			jQuery('#system-message-container').html('<div class="alert alert-success"><a data-dismiss="alert" class="close" href="#">&times</a>'+message+'</div>');
		}
	}

	function showError(message) {
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
				showError(item);
			}
			jQuery('[data-remotekey=' + item.remotekey + ']').each(function(index){
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
				if (typeof item.text !== 'undefined') {
					//jQuery(index).set('html',item.text);
					if (typeof jQuery(this .getElementsByClassName("buttontext")[0]) !== 'undefined') {
						jQuery(this .getElementsByClassName("buttontext")[0]).html(item.text);
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
		timer = window.setInterval(function(){
			refreshDiv(false);
		}, 5000);
		return timer;
	}
}
