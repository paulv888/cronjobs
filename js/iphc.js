//This Javascript hides the iPhones Navigation Bar and sets the window width
var isMSIE = /*@cc_on!@*/0;
var lastKey = null;
if (isMSIE) {
   attachEvent("onload", function() {setTimeout(updateLayout, 0);}, false);
} else {
  addEventListener("load", function() {setTimeout(updateLayout, 0);}, false);
}
$.ajaxSetup({
    beforeSend:function(){
        // show gif here, eg:
        $("div#spinner").show();
    },
    complete:function(){
        // hide gif here, eg:
        $("div#spinner").hide();
    }
});
var spinnerVisible = false;
function showProgress() {
	if (!spinnerVisible) {
		$("div#spinner").fadeIn("fast");
		spinnerVisible = true;
	}
};
function hideProgress() {
	if (spinnerVisible) {
		var spinner = $("div#spinner");
		spinner.stop();
		spinner.fadeOut("fast");
		spinnerVisible = false;
	}
};

function switchDiv(selDiv) {
		$(".message").html('');
		$("#body").children("div:visible").hide();
		$("#toolbar a").removeClass();
		$(selDiv).addClass("selected");
		var thelink = $(selDiv).attr('href');
		var thediv = thelink.split("#");
		$('#'+thediv[1]).show();
		return false;
}

var currentWidth = 0;
function updateLayout() {
	if (window.innerWidth != currentWidth) {currentWidth = window.innerWidth;
	var orient = currentWidth == 320 ? "profile" : "landscape";
	document.body.setAttribute("orient", orient);
	setTimeout(function() {window.scrollTo(0, 1);}, 100);}
}

setInterval(updateLayout, 400);

//jquery
$(document).ready(function(){

	$("#body").children().hide();

	$("#body div:first").show();

	//this is the function that handles switching divs
	$("#toolbar a").click(function(){
		switchDiv(this);
	});

	//this is the function that handles rest service
	$(".button").live("click", function(){
		$(".message").html('');
		lastKey= $(this).attr('remotekey');
		$.post("process.php", { remotekey:$(this).attr('remotekey'), setvalue:s = $('#setval').find(":selected").val() },
		function(data){
			processData(data);
		});
		return false;
	 });
//

	$(".remotedivimg").click(function () {
		$.get("refreshimage.php", 
			function(data){ 
				d = new Date();
				var new_str = "#f9f6ef no-repeat center top url(images/HIPScreenshot.jpg?" + d.getTime() + ")";
				$(".remotedivimg").css("background", new_str); 
				$(".message").html(data);
		 }); 
	});

	//this is the function that handle switch applications (go button)
	$(".jump").click(function(){
		$(".message").html('');
		var s = $(this).parent().find("option:selected").val();
		$.post("process.php", { remotekey:$(this).attr('value') , command:$(this).parent().find("option:selected").val() },
				function(data){
					processData(data);
				}); 
	return false;
	});

function processData(data) {
	var temp = new Array();
	temp = data.split(';');
	if (temp[0] != 'OK') $(".message").html(data);
	jQuery.each(temp, function() {
		var temp1 = new Array();
		temp1 = this.split(' ');
		if (temp1[0] == 'OK') return;
		if (temp1[2] != null) 
		{
			//$('[remotekey=' + temp1[0] + ']').val(temp1[2]);
			$('[remotekey=' + temp1[0] + ']').each(function(index){
					$(this).html(temp1[2]);
				});
		} else {
			$('[remotekey=' + temp1[0] + ']').each(function(index){
					$(this).removeClass("off on").addClass(temp1[1]);
				});
		}
		return (this.length !== 0); // will stop running after "three"
	});
}

	
	//this is the function that handle switch applications (go button)
	$("#UpLast").click(function(){
		$(".message").html('');
		if (lastKey != null) { 
			$.post("process.php", { remotekey:lastKey, setvalue:s = $('#setval').find(":selected").val() },
			function(data){
				processData(data);
			});
		};
		return false;
	});

	//this is the function that dropdown
	$(".formdropdownlist").live("change", function(){
		$(".message").html('');
		$.post("process.php", { remotekey:$(this).attr('value') , command:$(this).parent().find("option:selected").val() },
				function(data){
					processData(data);
				}); 
		return false;
	 });
	 
		//Enable swiping...
	$(".remotedivs").swipe( {
		//Generic swipe handler for all directions
		swipe:function(event, direction, distance, duration, fingerCount) {
			var prev;
			var next;
			var menu = $("#toolbar a");
			menu.each(function(i) {
				if ($(this).is('.selected')) {
					//found selected diff so take next one (if exist)
					prev = i>0?$(menu[i-1]):false
					next = i<menu.length-1?$(menu[i+1]):false; 
					return false;
				}
			});
			switch (direction) 
			{
			case 'left':
				if (next) switchDiv(next);
				break;
			case 'right':
				if (prev) switchDiv(prev);
				break;
			case 'down':
				// would be nice if Ajax
				$(".message").html("Refreshing...");
				window.location.reload( false );
			default:
			;
			}
		},
		//Default is 75px, set to 0 for demo so any distance triggers swipe
		allowPageScroll:"vertical",
		threshold:35
	});

	
});
