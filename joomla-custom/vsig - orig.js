// "Very Simple Image Gallery" Plugin for Joomla 3.1 - Version 1.6.8
// License: GNU General Public License version 2 or later; see LICENSE.txt
// Author: Andreas Berger - andreas_berger@bretteleben.de
// Copyright (C) 2013 Andreas Berger - http://www.bretteleben.de. All rights reserved.
// Project page and Demo at http://www.bretteleben.de
// ***Last update: 2013-08-15***

//dom
function vsig_dom(obj) {return document.getElementById(obj); }

//switch image without reload
function switchimg(t_ident, t_ident_b) {
	//topimage
	var topimg = "topimg" + t_ident_b[2];
	t_ident[6] = t_ident[6].replace(/&#39;/g, "'"); //replace &#39; with ' in alt-title
	t_ident[4] = t_ident[4].replace(/&#39;/g, "'"); //replace &#39; with ' in link-title
	t_ident[6] = t_ident[6].replace(/&amp;/g, "&"); //replace &amp;amp; with &amp; in alt-title
	//switch caption
	var t_cap = (typeof (vsig_dom(topimg).parentNode.href) !== "undefined") ? (vsig_dom(topimg).parentNode.parentNode.getElementsByTagName("div")) : (vsig_dom(topimg).parentNode.getElementsByTagName("div"));
	if (t_cap.length >= 1) {
		t_cap[0].innerHTML = (t_ident[1] !== "" || t_ident[2] !== "") ? ("<span>" + t_ident[1] + "</span><span>" + t_ident[2] + "</span>") : "";
	}
	//switch link
	if (typeof (vsig_dom(topimg).parentNode.href) !== "undefined") {
		vsig_dom(topimg).parentNode.href = t_ident[3];
		vsig_dom(topimg).parentNode.title = t_ident[4];
		vsig_dom(topimg).parentNode.target = t_ident[5];
	}
	//switch image
	vsig_dom(topimg).src = t_ident_b[0] + "vsig_images/" + t_ident[0];
	vsig_dom(topimg).alt = t_ident[6];
	vsig_dom(topimg).title = t_ident[6];
}

//switch set
function switchset(s_ident, s_start, s_number) {
	var ev_ident = window[s_ident];
	var ev_identb = window[s_ident + "_b"];
	var sets_total = Math.ceil(ev_ident.length / s_number);
	var sets_current = s_start / s_number + 1;
	//button back
	if (sets_current >= 2) {
		vsig_dom('bback' + s_ident).href = ev_identb[3].replace(/&amp;/g, "&") + parseInt(s_start - s_number, 10);
		vsig_dom('bback' + s_ident).onclick = function () {switchset(s_ident, parseInt(s_start - s_number, 10), s_number); return false; };
	} else {
		vsig_dom('bback' + s_ident).href = ev_identb[3].replace(/&amp;/g, "&") + ((sets_total - 1) * s_number);
		vsig_dom('bback' + s_ident).onclick = function () {switchset(s_ident, ((sets_total - 1) * s_number), s_number); return false; };
	}
	//button forward
	if (sets_current <= sets_total - 1) {
		vsig_dom('bfwd' + s_ident).href = ev_identb[3].replace(/&amp;/g, "&") + parseInt(s_start + s_number, 10);
		vsig_dom('bfwd' + s_ident).onclick = function () {switchset(s_ident, parseInt(s_start + s_number, 10), s_number); return false; };
	} else {
		vsig_dom('bfwd' + s_ident).href = ev_identb[3].replace(/&amp;/g, "&") + parseInt(0, 10);
		vsig_dom('bfwd' + s_ident).onclick = function () {switchset(s_ident, parseInt(0, 10), s_number); return false; };
	}
	//set counter
	vsig_dom('counter' + s_ident).innerHTML = "&nbsp;" + sets_current + "/" + sets_total;
	//switch main image
	if (s_start <= ev_ident.length && s_start >= 0) {
		switchimg(ev_ident[s_start], ev_identb);
	}
	if (s_number >= 2) {
		//thumb ändern
		var a;
		for (a = 1; a <= s_number; a++) {
			if (ev_ident[s_start + a - 1]) {
				var b = parseInt(s_start + a - 1, 10);
				var obj = vsig_dom('thb' + s_ident + '_' + a);
				obj.style.visibility = "visible";
				obj.getElementsByTagName("img")[0].src = ev_identb[0] + ev_identb[1] + ev_ident[b][7];
				obj.getElementsByTagName("img")[0].alt = ev_ident[b][6];
				obj.getElementsByTagName("a")[0].title = ev_ident[b][6];
				obj.getElementsByTagName("a")[0].href = ev_identb[3].replace(/&amp;/g, "&") + b;
				obj.getElementsByTagName("a")[0].b = b;
				obj.getElementsByTagName("a")[0].onclick = function () {switchimg(ev_ident[this.b], ev_identb); return false; };
				if (obj.getElementsByTagName("a")[0].onmouseover) {
					obj.getElementsByTagName("a")[0].onmouseover = function () {switchimg(ev_ident[this.b], ev_identb); return false; };
				}
			} else {
				vsig_dom('thb' + s_ident + '_' + a).style.visibility = "hidden";
			}
		}
	}
}

//daisychain preload
function vsig_daisychain(s_ident, s_identb) {
	var sl = function () {
		var ev_ident = window[s_ident];
		var ev_identb = window[s_identb];
		var img_total = ev_ident.length;
		var c;
		var prld_img;
		var prld_imges = [];
		var prld_thbs = [];
		for (c = 0; c < img_total; c++) {
			prld_img = new Image();
			prld_img.src = ev_identb[0] + "vsig_images/" + ev_ident[c][0];
			prld_imges.push(prld_img);
			prld_img.src = ev_identb[0] + "vsig_thumbs/" + ev_ident[c][7];
			prld_thbs.push(prld_img);
		}
	};
	if (window.addEventListener) {
		window.addEventListener('load', sl, false);
	} else if (window.attachEvent) {
		window.attachEvent('onload', sl);
	} else {
		if (window.onload) {
			var ld = window.onload;
			window.onload = function () {ld(); sl(); };
		} else {
			window.onload = sl;
		}
	}
}