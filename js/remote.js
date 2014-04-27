if(!window.scriptHasRun) { 
	window.scriptHasRun = true; 
	var COMMAND_TOGGLE = 19;
	var SIGNAL_SOURCE_COMMAND = 16;
	var SIGNAL_SOURCE_SCHEME = 20;
	var SIGNAL_SOURCE_REMOTE = 3;
	var lastKey = null;
	window.addEvent('domready', function(){

		//launchFullScreen(document.documentElement) // the whole page	
		//toggleFullScreen(); does not allow auto :)
		
		//checkInstalled();

		$$('.rem-button-down').removeEvents('mousedown');
		$$('.rem-button-down').addEvent('mousedown', function(event){
			event.stop();
			var params = {callsource: SIGNAL_SOURCE_REMOTE, remotekey: this.get("remotekey"), mouse: 'down'};
			callAjax (params) ;
		});	

		$$('.rem-button-down').removeEvents('mouseup');
		$$('.rem-button-down').addEvent('mouseup', function(event){
			event.stop();
			var params = {callsource: SIGNAL_SOURCE_REMOTE, remotekey: this.get("remotekey"), mouse: 'up'};
			callAjax (params) ;
		});	

		$$('.rem-button').removeEvents('click');
		$$('.rem-button').addEvent('click', function(event){
			event.stop();
			var params = {callsource: SIGNAL_SOURCE_REMOTE, remotekey: this.get("remotekey")};
			callAjax (params) ;
		});	

		//Dropdowns, either be command or scheme, if scheme Scommand 
		$$('.controlselect-button').removeEvents('change');
		$$('.controlselect-button').addEvent('change', function(event){
			event.stop();
			var params = {callsource: SIGNAL_SOURCE_REMOTE, remotekey: this.get("remotekey"), 'command':this.get('value')};
			callAjax (params) ;
		});	

		//Run scheme button (
		$$('.scheme-button').removeEvents('click');
		$$('.scheme-button').addEvent('click', function(event){
			event.stop();
			var params = {callsource: SIGNAL_SOURCE_SCHEME, 'scheme':this.get('value')};
			callAjax (params) ;
		});	

		//Run command button (
		$$('.command-button').removeEvents('click');
		$$('.command-button').addEvent('click', function(event){
			event.stop();
			var params = {callsource: SIGNAL_SOURCE_COMMAND, 'command':this.get('value')};
			callAjax (params) ;
		});	

		//this is the function that dropdown's button
		$$('.jump-button').removeEvents('click');
		$$('.jump-button').addEvent('click', function(event){
			event.stop();
			var params = {callsource: SIGNAL_SOURCE_REMOTE, remotekey: this.get("remotekey"), 'command':this.getPrevious('.controlselect-button').value};
			callAjax (params) ;
		});	
		
		//this is the function that handle switch applications (go button)
		$$('#myTab a').removeEvents('click');
		$$('#myTab a').addEvent('click', function(event){
			$$('.message').removeClass('alert');
			$$('.message').set('html', '');
		})
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
		
	function callAjax (params) {

		var myHTMLRequest = new Request({
			url: 	'/cronjobs/70D455DC-ACB4-4525-8A85-E6009AE93AF4/process.php',
			method: 'post',
			data: params,
			onRequest: function(){
				$$('.message').removeClass('alert');
				document.getElementById('spinner').style.display = 'block';
			},

			onComplete: function(data){
				processData(data);
				document.getElementById('spinner').style.display = 'none';
			},
		}).send();
	};
		
	function callAjaxSync (params) {

		var myHTMLRequest = new Request({
			url: 	'/cronjobs/70D455DC-ACB4-4525-8A85-E6009AE93AF4/process.php',
			method: 'post',
			async: false,
			data: params,
			onSucces: function(){
				$$('.message').removeClass('alert');
				document.getElementById('spinner').style.display = 'block';
			},

			onComplete: function(data){
				processData(data);
				document.getElementById('spinner').style.display = 'none';
			},
		}).send();
	};
		
	function processData(data) {
		$$('.message').set('html', '');
		var temp = new Array();
		var pos = data.indexOf("OK;");

		if (pos > -1) {
			$$('.message').addClass('alert');
			$$('.message').set('html',data.substring(1,pos));
			data = data.substring(pos);
		}
		temp = data.split(';');
		if (temp[0]) {
			if (temp[0] != 'OK') 
			{
				$$('.message').addClass('alert');
				$$('.message').set('html',data);
				return;
			}
			Array.each(temp, function(arr) {
				var temp1 = new Array();
				temp1 = arr.split(' ');
				temp1.push(null);
				if (temp1[0].indexOf('OK') > -1) return;
				if (temp1[2] != null) 
				{
					//$('[remotekey=' + temp1[0] + ']').val(temp1[2]);
					$$('[remotekey=' + temp1[0] + ']').each(function(index){
							$(index).set('html',temp1[2]);
						});
				} else {
					$$('[remotekey=' + temp1[0] + ']').each(function(index){
							$(index).removeClass("off");
							$(index).removeClass("on");
							$(index).removeClass("error");
							$(index).removeClass("undefined");
							$(index).removeClass("unknown");
							$(index).addClass(temp1[1]);
						});
				}
				return (arr.length !== 0); // will stop running after "three"
			});
		};
	}
}