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
//include_once('print_rr.php');
 //var_debug($this->spanSubColumns);
	// echo "<pre>";
	// print_r($this->_row->data);
	// if (isset($this->_row->data->{'ha_mi_phases___id_raw'})) echo $this->_row->data->{'ha_mi_phases___id_raw'};
	// echo $this->_row->data->{'ha_mi_phases___id_raw'};
	// echo "</pre>";
	?>
<div id="<?php echo $this->_row->id;?>" class="span1 <?php echo $this->_row->class;?>">
	<?php foreach ($this->headings as $heading => $label) {
		$style = empty($this->cellClass[$heading]['style']) ? '' : 'style="'.$this->cellClass[$heading]['style'].'"';
		?>
		<div class="row-fluid <?php echo $this->cellClass[$heading]['class']?>" <?php echo $style?>><h3 id="phase_db_<?php if (isset($this->_row->data)) echo $this->_row->data->{'ha_mi_phases___id_raw'};?>" class="dropbar_grid" ondrop="drop(event)" ondragover="allowDrop(event)" data-phase_raw="<?php if (isset($this->_row->data)) echo $this->_row->data->{'ha_mi_phases___id_raw'};?>">

			<?php echo isset($this->_row->data) ? $this->_row->data->$heading : '';?></h3>
		</div>
	<?php }?>
</div>