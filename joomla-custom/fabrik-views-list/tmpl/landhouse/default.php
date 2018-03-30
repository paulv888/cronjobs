<?php
/**
 * Fabrik List Template: Div
 *
 * @package     Joomla
 * @subpackage  Fabrik
 * @copyright   Copyright (C) 2005-2016  Media A-Team, Inc. - All rights reserved.
 * @license     GNU/GPL http://www.gnu.org/copyleft/gpl.html
 */

// No direct access
defined('_JEXEC') or die('Restricted access');

// The number of columns to split the list rows into
$columns = 1;

// Show the labels next to the data:
$this->showLabels = false;

// Show empty data
$this->showEmpty = true;


$pageClass = $this->params->get('pageclass_sfx', '');

if ($pageClass !== '') :
	echo '<div class="' . $pageClass . '">';
endif;

?>
<?php if ($this->tablePicker != '') { ?>
	<div style="text-align:right"><?php echo FText::_('COM_FABRIK_LIST') ?>: <?php echo $this->tablePicker; ?></div>
<?php }
if ($this->showTitle == 1) { ?>
	<h1><?php //echo $this->table->label;?></h1>
<?php }?>
<?php echo $this->table->intro;?>
<form class="fabrikForm" action="<?php echo $this->table->action;?>" method="post" id="<?php echo $this->formid;?>" name="fabrikList">

<?php
if ($this->hasButtons):
	echo $this->loadTemplate('buttons');
endif;

if ($this->showFilters) {
	echo $this->layoutFilters();
}
?>

<div class="fabrikDataContainer" data-cols="<?php echo $columns;?>">
<?php foreach ($this->pluginBeforeList as $c) {
	echo $c;
}?>
<div class="fabrikList items-row cols-6 row-fluid clearfix" id="list_<?php echo $this->table->renderid;?>" >

	<?php
	$gCounter = 0;
	foreach ($this->rows as $groupedBy => $group) :?>
	<?php
	if ($this->isGrouped) :
		//$imgProps = array('alt' => FText::_('COM_FABRIK_TOGGLE'), 'data-role' => 'toggle', 'data-expand-icon' => 'fa fa-arrow-down', 'data-collapse-icon' => 'fa fa-arrow-right');
		// echo "<pre>";
	//var_dump($group[0]->{'data'}->{'projectlist___phase_raw'});
	// echo count($this->rows);
	// print_r($group);
	// echo "</pre>";
		$numGroups = count($this->rows);
		$spanColumns = 12/$numGroups;
		$spanSubColumns = (int)(12/$spanColumns*2);
	?>
		<div class="fabrik_groupheading_grid span<?php echo (int)$spanColumns?>">
			<h2 id="phase_<?php echo $group[0]->{'data'}->{'projectlist___phase_raw'};?>" class="groupTitle_grid" data-phase_raw="<?php echo $group[0]->{'data'}->{'projectlist___phase_raw'};?>">
		<?php

		echo strip_tags($this->layoutGroupHeading($groupedBy, $group)); ?></h2>
	<?php
	endif;
	?>
		<div class="fabrik_groupdata">
			<div class="groupdataMsg">
				<div class="emptyDataMessage" style="<?php echo $this->emptyStyle?>">
					<?php echo $this->emptyDataMessage; ?>
				</div>
			</div>

	<?php

	foreach ($group as $this->_row) :
		$this->spanSubColumns = $spanSubColumns;
		echo $this->loadTemplate('row');
		
	endforeach;
	?>
		</div>
	</div>
	<?php
	endforeach // group;
?>
</div>
<?php
echo $this->nav;
print_r($this->hiddenFields);?>
</div> 

</form>
<?php
echo $this->table->outro;

if ($pageClass !== '') :
	echo '</div>';
endif;
?>

