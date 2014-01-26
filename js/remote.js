window.addEvent('domready', function(){

	$$('.rem-button').addEvent('mousedown', function(event){
		event.stop();

		var myHTMLRequest = new Request({

			url: 	'/cronjobs/70D455DC-ACB4-4525-8A85-E6009AE93AF4/process.php',
			method: 'post',
			data: { 'callsource':'3', 'remotekey':this.get("remotekey"), 'mouse':'down'},
		
			onRequest: function(){
				$$('#message').set('html', 'executing...');
			},

			onComplete: function(response){
				$$('#message').set('html',response);
			},
		}).send();
	});	
});

window.addEvent('domready', function(){

	$$('.rem-button').addEvent('mouseup', function(event){
		event.stop();

		var myHTMLRequest = new Request({

			url: 	'/cronjobs/70D455DC-ACB4-4525-8A85-E6009AE93AF4/process.php',
			method: 'post',
			data: { 'callsource':'3', 'remotekey':this.get("remotekey"), 'mouse':'up'},
		
			onRequest: function(){
				$$('#message').set('html', 'executing...');
			},

			onComplete: function(response){
				$$('#message').set('html',response);
			},
		}).send();
	});	
});


window.addEvent('domready', function(){

	//this is the function that dropdown
	$$('.controlselect').addEvent('change', function(event){
		// event = new Event(event).stop(); 
		event.stop();

		var myHTMLRequest = new Request({

			url: 	'/cronjobs/70D455DC-ACB4-4525-8A85-E6009AE93AF4/process.php',
			method: 'post',
			data: { 'callsource':'3', 'remotekey':this.get('remotekey'), 'command':this.get('value')},

			onRequest: function(){
				$$('#message').set('html', 'executing...');
			},

			onComplete: function(response){
				$$('#message').set('html',response);
			},
		}).send();
	});	
});


window.addEvent('domready', function(){

	//this is the function that dropdown
	$$('.controlselect-button').addEvent('change', function(event){
		// event = new Event(event).stop(); 
		event.stop();

		var myHTMLRequest = new Request({

			url: 	'/cronjobs/70D455DC-ACB4-4525-8A85-E6009AE93AF4/process.php',
			method: 'post',
			data: { 'callsource':'3', 'remotekey':this.get('remotekey'), 'command':this.get('value')},

			onRequest: function(){
				$$('#message').set('html', 'executing...');
			},

			onComplete: function(response){
				$$('#message').set('html',response);
			},
		}).send();
	});	
});

window.addEvent('domready', function(){

	//this is the function that dropdown
	$$('.jump-button').addEvent('click', function(event){
		// event = new Event(event).stop(); 
		event.stop();

		var myHTMLRequest = new Request({

			url: 	'/cronjobs/70D455DC-ACB4-4525-8A85-E6009AE93AF4/process.php',
			method: 'post',
			data: { 'callsource':'3', 'remotekey':this.get('remotekey'), 'command':this.getPrevious('.controlselect-button').value},
		
			onRequest: function(){
				$$('#message').set('html', 'executing...');
			},

			onComplete: function(response){
				$$('#message').set('html',response);
			},
		}).send();
	});	
});



