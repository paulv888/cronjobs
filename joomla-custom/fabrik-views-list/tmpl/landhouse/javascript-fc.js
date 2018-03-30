/**
 * List helper
 *
 * @copyright: Copyright (C) 2005-2016  Media A-Team, Inc. - All rights reserved.
 * @license:   GNU/GPL http://www.gnu.org/copyleft/gpl.html
 */

// function get(){

	// if ( jQuery('.fabrikDataContainer').offset().top - jQuery(this).scrollTop() > 100) {
        // jQuery('.groupTitle_grid').removeClass('groupheading_grid-fixed');
	// } else {
		// jQuery('.groupTitle_grid').addClass('groupheading_grid-fixed');
	// }
// }

// get();
// jQuery(window).scroll(get);

// function allowDrop(ev) {
    // ev.preventDefault();
// }

// function drag(ev) {
    // ev.dataTransfer.setData("rowid", ev.target.getAttribute("data-rowid"));
    // ev.dataTransfer.setData("phase_raw", ev.target.getAttribute("data-phase_raw"));
    // ev.dataTransfer.setData("objid", ev.target.getAttribute("data-list")+'_row_'+ev.target.getAttribute("data-rowid"));
// }

// function drop(ev) {
    // ev.preventDefault();
    // var rowid = ev.dataTransfer.getData("rowid");
    // var old_phaseid = ev.dataTransfer.getData("phase_raw");
    // var new_phaseid = ev.target.getAttribute("data-phase_raw");
    // var objid = ev.dataTransfer.getData("objid");
	// var trg = ev.target.nextSibling;
	// var insert = trg.getElementsByTagName('div')[2]
	

	// if (new_phaseid != old_phaseid) {
		// var myurl='/index.php/falcon-crest-grid';
		// var params = {'projectlist___id':rowid, 'projectlist___project':2, 'projectlist___phase[0]':new_phaseid, 'listid':289, 
					   // 'listref':289, 'rowid':rowid, 'Itemid':656, 'option':'com_fabrik' ,
					   // 'task':'form.process', 'formid':276, 'returntoform':0, 'fabrik_ajax':1,
					   // 'package':'fabrik', 'format':'raw', 'Submit':''};

		// var keysRequest = jQuery.ajax({
				// dataType: "json",
				// url: 	myurl,
				// method: 'post',
				// data: params,
				// timeout: 10000,
				// success: function(data)
				// {
				    // trg.insertBefore(document.getElementById(objid), insert);
					// ev.target.setAttribute("data-phase_raw", new_phaseid); 
				// },
				// error: function(xhr, textStatus, error)
				// {
					// alert(textStatus+' '+error+'</br>'+xhr.responseText);
				// },
			// }
        // );
	// }
// }