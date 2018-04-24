<?php
// I am using params in my code
$saveParams = $params;
if (array_key_exists('DEBUG',$_GET)) { 
    define( 'DEBUG', TRUE );
} 
if (!defined('DEBUG')) define( 'DEBUG', FALSE );
//require_once $_SERVER['DOCUMENT_ROOT'].'/cronjobs/process.php';
//$origData = $formModel->getOrigData()[0]; 
if (DEBUG) echo "<pre>";
if (DEBUG) echo "Data:";
if (DEBUG) print_r($data);
//print_r($formModel->formData); 
//print_r($formModel->getElementIds());

$groups = $formModel->getGroupsHiarachy();
foreach ($groups as $group) {
	$elements = $group->getPublishedElements();
    foreach ($elements as $elementModel) {
		if ($elementModel->canView() || $elementModel->canUse())
		{
			$element = $elementModel->getElement();
			if ($elementModel->getParams()->get('tablecss_cell_class') == "putthis") {
				$fieldMap[$elementModel->getFullName(true, false)]['name'] =  $element->name;
				$fieldMap[$elementModel->getFullName(true, false)]['type'] =  $elementModel->getParams()->get('text_format');
				$fields[$group->getId()][] = $elementModel->getFullName(true, false);
			}
		}
	}
}
if (DEBUG) echo "fieldMap:";
if (DEBUG) print_r($fieldMap);
if (DEBUG) echo "fields:";
if (DEBUG) print_r($fields);

function loadFields($fields, $fieldMap, $data, $repeat = null) {

	$output = array();
	foreach ($fields as $fieldName) {
		if (is_null($repeat)) {
			if ($fieldMap[$fieldName][type]=="integer") {
				if (!empty($data[$fieldName.'_raw'])) $output[$fieldMap[$fieldName][name]] = (float)$data[$fieldName.'_raw'];
			} elseif (!is_array($data[$fieldName.'_raw'])) {
				if (!empty($data[$fieldName.'_raw'])) $output[$fieldMap[$fieldName][name]] = $data[$fieldName.'_raw'];
			} else {
				if (!empty($data[$fieldName.'_raw'][0])) $output[$fieldMap[$fieldName][name]] = $data[$fieldName.'_raw'][0];
			}
		} else {
			if ($fieldMap[$fieldName][type]=="integer") {
			if (!empty($data[$fieldName.'_raw'][$repeat])) $output[$fieldMap[$fieldName][name]] = (float)$data[$fieldName.'_raw'][$repeat];
			} elseif (!is_array($data[$fieldName.'_raw'][$repeat])) {  
				if (!empty($data[$fieldName.'_raw'][$repeat])) $output[$fieldMap[$fieldName][name]] = $data[$fieldName.'_raw'][$repeat];
			} else {
				if (!empty($data[$fieldName.'_raw'][$repeat][0])) $output[$fieldMap[$fieldName][name]] = $data[$fieldName.'_raw'][$repeat][0];
			}
		}
	}
	return $output;
}

function checkForErrors($formModel, $feedback) {
	$result = RemoteKeys($feedback, $callerparams);
	if (array_key_exists('message',$result)) {
		JFactory::getApplication()->enqueueMessage($result['message']);
	}
	if (array_key_exists('error',$result)) {
		//JFactory::getApplication()->enqueueMessage($result['error'], 'error');
		$formModel->setFormErrorMsg($result['error']);
		return false;
	}
	return true;
}

?>