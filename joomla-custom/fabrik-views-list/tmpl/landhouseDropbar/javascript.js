/**
 * List helper
 *
 * @copyright: Copyright (C) 2005-2016  Media A-Team, Inc. - All rights reserved.
 * @license:   GNU/GPL http://www.gnu.org/copyleft/gpl.html
 */

function get(){

	var el = jQuery('.dropbar'); 
	// if (typeof el == 'undefined') el = jQuery('#list_292_mod_fabrik_list_168'); 
	var bottom = el.position().top + el.outerHeight(true) + 13;

//console.log(jQuery('#list_279_com_fabrik_279').offset().top - jQuery(this).scrollTop()+' '+bottom);
	if ( jQuery('#list_279_com_fabrik_279').offset().top - jQuery(this).scrollTop() > 150) {
        jQuery('.groupTitle_grid').removeClass('groupheading_grid-fixed');
	} else {
		jQuery('.groupTitle_grid').addClass('groupheading_grid-fixed');
		jQuery(".groupheading_grid-fixed").css({ top: bottom+'px' });
	}
}

get();
jQuery(window).scroll(get);

hideDropbar();

function allowDrop(ev) {
    ev.preventDefault();
}

function drag(ev) {
    ev.dataTransfer.setData("rowid", ev.target.getAttribute("data-rowid"));
    ev.dataTransfer.setData("phase_raw", ev.target.getAttribute("data-phase_raw"));
    ev.dataTransfer.setData("objid", ev.target.getAttribute("data-list")+'_row_'+ev.target.getAttribute("data-rowid"));
    ev.dataTransfer.setData("dragsourceid", ev.target.id);
	showDropbar();
}

function drop(ev) {
    ev.preventDefault();
    var rowid = ev.dataTransfer.getData("rowid");
    var old_phaseid = ev.dataTransfer.getData("phase_raw");
    var new_phaseid = ev.target.getAttribute("data-phase_raw");
    var objid = ev.dataTransfer.getData("objid");
    var dragsourceid = ev.dataTransfer.getData("dragsourceid");
	var trg = document.getElementById('phase_'+new_phaseid);
	if (trg != null) var insert = trg.nextSibling.getElementsByTagName('div')[2];
	

	if (new_phaseid != old_phaseid) {
		var myurl='/index.php/falcon-crest-grid';
		var params = {'projectlist___id':rowid, 'projectlist___project':2, 'projectlist___phase[0]':new_phaseid, 'listid':289, 
					   'listref':289, 'rowid':rowid, 'Itemid':656, 'option':'com_fabrik' ,
					   'task':'form.process', 'formid':276, 'returntoform':0, 'fabrik_ajax':1,
					   'package':'fabrik', 'format':'raw', 'Submit':''};

		var keysRequest = jQuery.ajax({
				dataType: "json",
				url: 	myurl,
				method: 'post',
				data: params,
				timeout: 10000,
				success: function(data)
				{
				    if (trg != null) {
						trg.nextSibling.insertBefore(document.getElementById(objid), insert)
					} else {
						document.getElementById(objid).remove();
					}
					document.getElementById(dragsourceid).setAttribute("data-phase_raw", new_phaseid); 
					hideDropbar();
				},
				error: function(xhr, textStatus, error)
				{
					alert(textStatus+' '+error+'</br>'+xhr.responseText);
					hideDropbar();
				},
			}
        );
	}
	hideDropbar();
}

function hideDropbar() {
	// document.getElementById('listform_292_mod_fabrik_list_168').style.display = 'none'
}

function showDropbar() {
	// document.getElementById('listform_292_mod_fabrik_list_168').style.display = 'block';
}


Element.prototype.remove = function() {
    this.parentElement.removeChild(this);
}
NodeList.prototype.remove = HTMLCollection.prototype.remove = function() {
    for(var i = this.length - 1; i >= 0; i--) {
        if(this[i] && this[i].parentElement) {
            this[i].parentElement.removeChild(this[i]);
        }
    }
}