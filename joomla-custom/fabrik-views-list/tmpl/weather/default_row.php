<?php
/**
 * Fabrik List Template: Div Row
 *
 * @package     Joomla
 * @subpackage  Fabrik
 * @copyright   Copyright (C) 2005-2015 fabrikar.com - All rights reserved.
 * @license     GNU/GPL http://www.gnu.org/copyleft/gpl.html
 */

// No direct access
defined('_JEXEC') or die('Restricted access');

?>
<tr id="<?php echo $this->_row->id;?>" class="<?php echo $this->_row->class;?>">
	<td>
<div class="row-fluid fabrik_row <?php echo $this->_row->class;?>" >
	<?php foreach ($this->headings as $heading => $label) :
		$d = @$this->_row->data->$heading;
		if (isset($this->showEmpty) && $this->showEmpty === false  && trim(strip_tags($d)) !== '') :
			continue;
		endif;?>
		<?php if (strpos($this->cellClass[$heading]['class'],'fabrik_select') !== false) continue; ?>
		<div class="span6 <?php echo $this->cellClass[$heading]['class']?>">
			<?php if (isset($this->showLabels) && $this->showLabels) :
			echo '<span class="muted">' . $label . ': </span>';
			endif;?>

			<?php echo $d?>
		</div>
	<?php
	endforeach;
	?>
</div>
	</td>
</tr>
