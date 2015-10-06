
if(!window.scriptRemoteHasRun) { 
	window.scriptRemoteHasRun = true; 
	var COMMAND_TOGGLE = 19;
	var COMMAND_GET_GROUP = 282;
	var COMMAND_GET_VALUE = 136;
	var MY_DEVICE_ID = 164;
	var GROUP_NO_SELECTED = 0;
	var DIM_NO_SELECTED = 19;
	var COMMAND_SET_VALUE = 145;
	

	var myurl = '/cronjobs/70D455DC-ACB4-4525-8A85-E6009AE93AF4/process.php';

	var lastKey = null;
	window.addEvent('domready', function(){

		// regular down when up as well (cam move...)
		$$('.click-down').removeEvents('mousedown');
		$$('.click-down').addEvent('mousedown', function(event){
			event.stop();
			var keys = [];
			keys.push(this.get("data-remotekey"));
			var params = {callerID: MY_DEVICE_ID, messagetypeID: 'MESS_TYPE_REMOTE_KEY', keys: keys, mouse: 'down'};
			callAjaxNoSpin (params) ;
		});	

		// regular up for mouse down class
		$$('.click-down').removeEvents('mouseup');
		$$('.click-down').addEvent('mouseup', function(event){
			event.stop();
			var keys = [];
			keys.push(this.get("data-remotekey"));
			var params = {callerID: MY_DEVICE_ID, messagetypeID: 'MESS_TYPE_REMOTE_KEY', keys: keys, mouse: 'up'};
			callAjax (params) ;
		});	

		// regular up
		$$('.click-up').removeEvents('click');
		$$('.click-up').addEvent('click', function(event){
			event.stop();
			var commandvalue = 100;
			
			// check if in dim mode
			commandvalue = parseInt($$('.tab-pane.active .dimmer').get('data-myvalue'));
			if (commandvalue ==  DIM_NO_SELECTED || isNaN(commandvalue)) commandvalue = null;
			var keys = [];
			keys.push(this.get("data-remotekey"));
			var params = {callerID: MY_DEVICE_ID, messagetypeID: 'MESS_TYPE_REMOTE_KEY', keys: keys, commandvalue: commandvalue};
			resetSelection();
			this.addClass('group-select');
			callAjax (params) ;
		});	

		// Generic dropdown button
		$$('.btndropdown li a').removeEvents('click');
		$$('.btndropdown li a').addEvent('click', function(event){
			//event.stop();
			var mbut = this.parentNode.parentNode.parentNode.firstChild;
			mbut.getElementsByClassName("buttontext")[0].textContent = this.text+' ';
			var selected = this.getAttribute('data-value');
			this.parentNode.parentNode.setAttribute('data-myvalue', selected);
			var keys = [];
			keys.push(this.parentNode.parentNode.get("data-remotekey"));
			if (selected.charAt(0) == 'S') {
				var params = {callerID: MY_DEVICE_ID, messagetypeID: 'MESS_TYPE_SCHEME', keys: keys, schemeID:selected.substring(1)};
			} else if (selected.charAt(0) == 'C') {
				var params = {callerID: MY_DEVICE_ID, messagetypeID: 'MESS_TYPE_REMOTE_KEY', keys: keys , commandID:selected.substring(1)};
			} else {
				var params = {callerID: MY_DEVICE_ID, messagetypeID: 'MESS_TYPE_REMOTE_KEY', keys: keys , commandvalue:selected};
			}
			callAjax (params) ;
		});

		// Group drop downs
		$$('#group li a').removeEvents('click');
		$$('#group li a').addEvent('click', function(event){
//			event.stop();
			var mbut = this.parentNode.parentNode.parentNode.firstChild;
			mbut.firstChild.textContent = ' '+this.text;
			var selected = this.getAttribute('data-value');
			this.parentNode.parentNode.setAttribute('data-myvalue', selected);
			if (selected == GROUP_NO_SELECTED){
				mbut.removeClass('btn-info');
				mbut.addClass('btn-success');
				resetSelection();
			} else {
				mbut.removeClass('btn-info');
				mbut.addClass('btn-success');
				resetSelection();
				var params = {callerID: MY_DEVICE_ID, messagetypeID: 'MESS_TYPE_COMMAND', commandID: COMMAND_GET_GROUP, commandvalue: this.getAttribute('data-value').substring(1)};
				callAjax (params) ; 		// get group members here and set select
			}
		});

		
		// Dimmer dropdowns
		$$('.dimmer li a').removeEvents('click');
		$$('.dimmer li a').addEvent('click', function(event){
//			event.stop();
			var mbut = this.parentNode.parentNode.parentNode.firstChild;
//			mbut.firstChild.textContent = ' '+this.text;
			mbut.getElementsByClassName("buttontext")[0].textContent = this.text+' ';
			var selected = this.getAttribute('data-value');
			this.parentNode.parentNode.setAttribute('data-myvalue', selected);

			// now find all selected button and send dim value (Either selected over click or over group)
			var keys = [];
			var elArray = $$('.group-select');
			var arrayLength = elArray.length;
			if (arrayLength > 0 && selected != "19") {				// some selections
				for (var i = 0; i < arrayLength; i++) {
					keys.push(elArray[i].get('data-remotekey'));
				}
				if (selected.charAt(0) == 'S') {
					var params = {callerID: MY_DEVICE_ID, messagetypeID: 'MESS_TYPE_SCHEME', keys: keys, schemeID:selected.substring(1)};
				} else if (selected.charAt(0) == 'C') {
					var params = {callerID: MY_DEVICE_ID, messagetypeID: 'MESS_TYPE_REMOTE_KEY', keys: keys , commandID:selected.substring(1)};
				} else {
					var params = {callerID: MY_DEVICE_ID, messagetypeID: 'MESS_TYPE_REMOTE_KEY', keys: keys, commandID: COMMAND_SET_VALUE, commandvalue: parseInt(selected)};
				}
				callAjax (params) ;
			}
			if (selected == "19") {					// On/Off toggle
				mbut.removeClass('btn-info');
				mbut.addClass('btn-warning');
			} else {								// dim value
				if (arrayLength = 0) alert ('Please select light you want to dim or on/off together');
				mbut.addClass('btn-info');
				mbut.removeClass('btn-warning');
			}

			
			var d = this.getAttribute('data-value');
			var t = this.get('data-value');
		});
		
		//Dropdowns, either be command or scheme, if scheme Scommand, if with command then key needed as well 
		// Update same as latest button dropdown, allow S C or Value
		$$('.controlselect-button').removeEvents('change');
		$$('.controlselect-button').addEvent('change', function(event){
			event.stop();
			var selected = this.get('value');
			var keys = [];
			keys.push(this.get("data-remotekey"));
			if (selected.charAt(0) == 'S') {
				var params = {callerID: MY_DEVICE_ID, messagetypeID: 'MESS_TYPE_SCHEME', schemeID:selected.substring(1)};
			} else if (selected.charAt(0) == 'C') {
				var params = {callerID: MY_DEVICE_ID, messagetypeID: 'MESS_TYPE_REMOTE_KEY', keys: keys, commandID:selected.substring(1)};
			} else {
				var params = {callerID: MY_DEVICE_ID, messagetypeID: 'MESS_TYPE_REMOTE_KEY', keys: keys, commandvalue:selected};
			}
			callAjax (params) ;
		});	

		//this is the function that dropdown's button either schemes or commands
		// Update same as latest button dropdown, allow S C or Value
		$$('.jump-button').removeEvents('click');
		$$('.jump-button').addEvent('click', function(event){
			event.stop();
			var selected = this.getPrevious('.controlselect-button').value;
			var keys = [];
			keys.push(this.get("data-remotekey"));
			if (selected.charAt(0) == 'S') {
				var params = {callerID: MY_DEVICE_ID, messagetypeID: 'MESS_TYPE_SCHEME', schemeID:selected.substring(1)};
			} else if (selected.charAt(0) == 'C'){
				var params = {callerID: MY_DEVICE_ID, messagetypeID: 'MESS_TYPE_REMOTE_KEY', keys: keys, commandID:selected.substring(1)};
			} else {
				var params = {callerID: MY_DEVICE_ID, messagetypeID: 'MESS_TYPE_REMOTE_KEY', keys: keys, commandID:selected};
			}
			callAjax (params) ;

		//Run scheme button (
		$$('.scheme-button').removeEvents('click');
		$$('.scheme-button').addEvent('click', function(event){
			event.stop();
			var params = {callerID: MY_DEVICE_ID, messagetypeID: 'MESS_TYPE_SCHEME', schemeID:this.get('value')};
			callAjax (params) ;
		});	

		//Run command button (
		$$('.command-button').removeEvents('click');
		$$('.command-button').addEvent('click', function(event){
			event.stop();
			var params = {callerID: MY_DEVICE_ID, messagetypeID: 'MESS_TYPE_COMMAND', commandID:this.get('value')};
			callAjax (params) ;
		});	

		});	
		

		// switching tabs
		$$('#myTab a').removeEvents('click');
		$$('#myTab a').addEvent('click', function(event){
			$$('#system-message-container').set('html', '');
			$$('.dimmer li a[value='+DIM_NO_SELECTED+']').fireEvent('click');
			$$('#group li a[value='+GROUP_NO_SELECTED+']').fireEvent('click');
			resetSelection();
			//window.scrollTo(0,document.body.scrollHeight);
		})
		
		window.setInterval(function(){
			refreshDiv();
		}, 5000);
	});

	function launchFullScreen(element) {
		if(element.requestFullscreen) {
			element.requestFullscreen();
		} else if(element.mozRequestFullScreen) {
			element.mozRequestFullScreen();
		} else if(element.webkitRequestFullscreen) {
			element.webkitRequestFullscreen();
		} else if(element.msRequestFullscreen) {
			element.msRequestFullscreen();
		}
	}
	
	
	var currentDiv;
	
	function refreshDiv () {

		if ($('autorefresh') == null || $('autorefresh').hasClass('active')) {
			var keys = [];
//			var elArray = $$('.rem-button.on, .rem-button.off, , .rem-button.error, .rem-button.undefined, .rem-button.unknown, .field, .link-warning, .link-down');
			var elArray = $$('.rem-button, .display');
			var arrayLength = elArray.length;
			if (arrayLength > 0) {				// some selections
				for (var i = 0; i < arrayLength; i++) {
					keys.push(elArray[i].get('data-remotekey'));
				}
				var params = {callerID: MY_DEVICE_ID, messagetypeID: 'MESS_TYPE_COMMAND', keys: keys, commandID: COMMAND_GET_VALUE};
				callAjaxNoSpin (params) ;
			}
		}
	};

	function callAjax (params) {
	
       var keysRequest = new Request.JSON({
				url: 	myurl,
				method: 'post',
				data: params,
				timeout: 10000,
				onRequest: function(){
					$$('#system-message-container').set('html', '');
					document.getElementById('spinner').style.display = 'block';
				},
				onSuccess: function(data)
				{
					processData(data);
					document.getElementById('spinner').style.display = 'none';
				},
				onError: function(text, error)
				{
					$$('#system-message-container').set('html', text+'</br>'+error);
					document.getElementById('spinner').style.display = 'none';
				},
				onTimeout: function(text, error)
				{
					$$('#system-message-container').set('html', 'Connection Timed Out'+'</br>');
					document.getElementById('spinner').style.display = 'none';
				},
			}
        ).send();
	};

	function callAjaxNoSpin (params) {
	
       var keysRequest = new Request.JSON({
				url: 	myurl,
				method: 'post',
				data: params,
				timeout: 10000,
				onRequest: function(){
					//$$('#system-message-container').set('html', '');
				},
				onSuccess: function(data)
				{
					processData(data);
				},
				onError: function(text, error)
				{
					$$('#system-message-container').set('html', text+'</br>'+error);
				},
                                onTimeout: function(text, error)
                                {
                                        $$('#system-message-container').set('html', 'Connection Timed Out'+'</br>');
                                },
			}
        ).send();
	};
	
		
	function callAjaxSync (params) {
	
       var keysRequest = new Request.JSON({
				url: 	myurl,
				method: 'post',
				data: params,
				async: false,
				onRequest: function(){
					$$('#system-message-container').set('html', '');
					if (document.getElementById('spinner')) document.getElementById('spinner').style.display = 'block';
				},
				onSuccess: function(data)
				{
					processData(data);
					document.getElementById('spinner').style.display = 'none';
				},
				onError: function(text, error)
				{
					$$('#system-message-container').set('html', text);
					if (document.getElementById('spinner')) document.getElementById('spinner').style.display = 'none';
				},
			}
        ).send();
	};

	function showMessage(message) {
		if (message.length > 0) {
			$$('#system-message-container').set('html','<div class="alert alert-message"><a data-dismiss="alert" class="close" href="#">&times</a>'+message+'</div>');
		}
	}

	function processData(data) {
				
		Object.each(data, function(item, key){
			// check for message
			if (key == 'message') {
				showMessage(item);
			}
			$$('[data-remotekey=' + item.remotekey + ']').each(function(index){
				$(index).removeClass("link-warning");
				$(index).removeClass("link-down");
				if (typeof item.status !== 'undefined') {
					$(index).removeClass("off");
					$(index).removeClass("on");
					$(index).removeClass("error");
					$(index).removeClass("undefined");
					$(index).removeClass("unknown");
					$(index).addClass(item.status);
				} else if (typeof item.text !== 'undefined') {
					//$(index).set('html',item.text);
					if (typeof (index).getElementsByClassName("buttontext")[0] !== 'undefined') (index).getElementsByClassName("buttontext")[0].set('html',item.text);
				} else if (typeof item.groupselect !== 'undefined') {
					$(index).addClass('group-select');
				} 
				if (typeof item.link !== 'undefined') {
					$(index).addClass(item.link);
				} 
			});
		});
	};
	
	function resetSelection() {
		$$('.group-select').each(function(el) {
			el.removeClass('group-select');
		});
	}
}
