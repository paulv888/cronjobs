<?php
/**
 * Fabrik List Template: Admin Row
 *
 * @package     Joomla
 * @subpackage  Fabrik
 * @copyright   Copyright (C) 2005-2016  Media A-Team, Inc. - All rights reserved.
 * @license     GNU/GPL http://www.gnu.org/copyleft/gpl.html
 */

// No direct access
defined('_JEXEC') or die('Restricted access');

?>
<?php
// include_once('print_rr.php');
 // if (isset($this->spanSubColumns)) echo $this->spanSubColumns."</br>";
 // echo($this->_spanSubColumns);
 // var_debug($this);
 // var_debug($this->{'spanSubColumns'});
 ?>
<div id="<?php echo $this->_row->id;?>" class="span<?php if (isset($this->spanSubColumns)) echo $this->spanSubColumns.' '; echo $this->_row->class;?> lh_card">
	<?php foreach ($this->headings as $heading => $label) {
		$style = empty($this->cellClass[$heading]['style']) ? '' : 'style="'.$this->cellClass[$heading]['style'].'"';
		?>
		<div class="row-fluid <?php echo $this->cellClass[$heading]['class']?>" <?php echo $style?>>
			<?php echo isset($this->_row->data) ? $this->_row->data->$heading : '';?>
		</div>
	<?php }?>
</div>
