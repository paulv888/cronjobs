<?php
/********************************************************************
Product		: Simple Responsive Menu
Date		: 09 October 2013
Copyright	: Les Arbres Design 2010-2013
Contact		: http://extensions.lesarbresdesign.info
Licence		: GNU General Public License
*********************************************************************/
defined('_JEXEC') or die;

	echo "\n".'<div class="'.$class_sfx.'srm_position" style="'.$div_styles.'">';
	$onchange = 'onchange="var e=document.getElementById(\'srm_select_list\'); window.location.href=e.options[e.selectedIndex].value"';
	echo "\n".'<select id="srm_select_list" size="1" style="'.$select_styles.'" '.$onchange.'>';

	if ($fixedText != '')
		echo "\n".'<option value="#" selected="selected">'.$fixedText.'</option>';
	
	$depth = 0;
	foreach ($list as $i => &$item)
		{
		if ($item->id == $active_id)
			$selected = ' selected="selected"';
		else
			$selected = '';
			
		if ($fixedText != '')
			$selected = '';

		switch ($item->type)
			{
			case 'separator':
			case 'heading':
				continue 2; break;		// don't create a list item for these types
			case 'url':
			case 'component':
			default:
				$link = $item->flink;
				if ($link == '#') continue 2;
				break;
			}
		
		if (strpos($item->anchor_css, 'hidden-phone') !== FALSE) continue;
		
		/*echo "<pre>";
		print_r ($item);
		echo "</pre>";*/
		
		echo "\n".'<option value="'.$link.'"'.$selected.'>';
//		echo "\n".'<option value="'.$link.'"'.' class="'.$item->anchor_css.'"'.$selected.'>';
//		for ($i=0; $i < $item->level-1; $i++)
//		for ($i=0; $i < $depth; $i++)
//			echo '-';
		echo $item->title;
		echo '</option>';
		
		if ($item->deeper)
			$depth ++;
		if ($item->shallower)
			$depth --;

		}
		
	echo '</select>';
	echo '</div>';
