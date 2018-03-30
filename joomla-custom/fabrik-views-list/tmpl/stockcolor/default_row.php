<?php
/**
 * Fabrik List Template: Admin Row
 *
 * @package     Joomla
 * @subpackage  Fabrik
 * @copyright   Copyright (C) 2005-2013 fabrikar.com - All rights reserved.
 * @license     GNU/GPL http://www.gnu.org/copyleft/gpl.html
 */

// No direct access
defined('_JEXEC') or die('Restricted access');

?>
<tr id="<?php echo $this->_row->id;?>" class="<?php echo $this->_row->class;?>">



	<?php foreach ($this->headings as $heading => $label) {	?>
		<td class="<?php

		
		$thiscellClass=$this->cellClass[$heading]['class'];
		if (strpos ($thiscellClass, "colorthis")>0) {
			if (@$this->_row->data->$heading>0) {
				$thiscellClass=str_replace("colorthis", "stock_green",$thiscellClass);
			} 
			if (@$this->_row->data->$heading<0) {
				$thiscellClass=str_replace("colorthis","stock_red",$thiscellClass);
			}
		} 

			if (strpos ($thiscellClass, "colorback")>0) {
			if (@$this->_row->data->$heading>0) {
				$thiscellClass=str_replace("colorback", "stock_green_back",$thiscellClass);
			} 
			if (@$this->_row->data->$heading<0) {
				$thiscellClass=str_replace("colorback","stock_red_back",$thiscellClass);
			}
		} 

		if (strpos ($thiscellClass, "setdate")>0) {
			if (preg_match("/^(\d{4})-(\d{2})-(\d{2})/", @$this->_row->data->$heading, $matches)) {
				if (checkdate($matches[2], $matches[3], $matches[1])) {
					if (strtotime(date("Y-m-d"))==strtotime(date("Y-m-d", strtotime(@$this->_row->data->$heading)))) {
						$thiscellClass=str_replace("setdate","background_red",$thiscellClass);
					} elseif (strtotime(date("Y-m-d"))>strtotime(date("Y-m-d", strtotime(@$this->_row->data->$heading)))) {
						$thiscellClass=str_replace("setdate","greyout",$thiscellClass);
					} elseif (strtotime(date("Y-m-d"))>strtotime(date("Y-m-d", strtotime(@$this->_row->data->$heading)) . " -7 day")) {
							$thiscellClass=str_replace("setdate","background_highlight",$thiscellClass);
					}
				} 
			}
		}

		echo $thiscellClass;

		?>
		" >
			<?php echo @$this->_row->data->$heading;?>
		</td>

	<?php }?>
		<?php 
//echo "<pre>";
//print_r($this->_row);
//echo "</pre>";
		?>
</tr>
