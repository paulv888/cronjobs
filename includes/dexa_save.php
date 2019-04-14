<?php
if (array_key_exists('DEBUG',$_GET)) { 
    define( 'DEBUG', TRUE );
} 
if (!defined('DEBUG')) define( 'DEBUG', FALSE );
// I am using params in my code
$saveParams = $params;
require_once $_SERVER['DOCUMENT_ROOT'].'/cronjobs/process.php';

$groups = $formModel->getGroupsHiarachy();
foreach ($groups as $group) {
	$elements = $group->getPublishedElements();
    foreach ($elements as $elementModel) {
		if ($elementModel->canView() || $elementModel->canUse())
		{
			$element = $elementModel->getElement();
			if ($elementModel->getParams()->get('tablecss_cell_class') == "putthis") {
				$putelements[$elementModel->getFullName(true, false)]['name'] =  $element->name;
				$putelements[$elementModel->getFullName(true, false)]['type'] =  $elementModel->getParams()->get('text_format');
				$putelementsByGroup[$group->getId()][] = $elementModel->getFullName(true, false);
			}
		}
	}
}

// Process Dexa
$dexa = array();
foreach ($putelementsByGroup['524'] as $fieldName) {
    if ($putelements[$fieldName][type]=="integer") {
        if (!empty($data[$fieldName.'_raw'])) $dexa[$putelements[$fieldName][name]] = (float)$data[$fieldName.'_raw'];
    } elseif (!is_array($data[$fieldName.'_raw'])) {
        if (!empty($data[$fieldName.'_raw'])) $dexa[$putelements[$fieldName][name]] = $data[$fieldName.'_raw'];
    } else {
        if (!empty($data[$fieldName.'_raw'][0]))$dexa[$putelements[$fieldName][name]] = $data[$fieldName.'_raw'][0];
    }
}

if (!empty($data['pg_dexas___dexaId'])) {   // Update
	$dexa['dexaId'] = $data['pg_dexas___dexaId'];
	$feedback['result'] = executeCommand(Array('callerID'=>326,'messagetypeID'=>"MESS_TYPE_COMMAND",'deviceID' => 326, 'commandID'=>7,'commandvalue' =>$data['pg_dexas___dexaId'], 'mess_text'=>json_encode($dexa)));
} else {  // post new one
	$feedback['result'] = executeCommand(Array('callerID'=>327,'messagetypeID'=>"MESS_TYPE_COMMAND",'deviceID' => 327, 'commandID'=>17, 'mess_text'=>json_encode($dexa)));
	if (array_key_exists('result',$temp)) {		// Doing this post/put
		$dexa['dexaId'] = $temp['result']['data']['dexaId'];
		$formModel->updateFormData('pg_dexas___dexaId', $dexaId, true);
	}
}
$temp = $feedback['result']['SendCommand']['0'];
if (array_key_exists('error',$temp)) {
    JFactory::getApplication()->enqueueMessage($temp['error'], 'error');
    $formModel->setFormErrorMsg("Error during update to AWS.");
	$params = $saveParams;
    return false;
}

if (!empty($data['pg_configurations___configurationId'])) { 	// We have some configs
    foreach ($data['pg_configurations___configurationId'] as $repeat=>$configId) {
		$config = Array();
		foreach ($putelementsByGroup['525'] as $fieldName) {
			if (!is_array($data[$fieldName.'_raw'][$repeat])) {
				if (!empty($data[$fieldName.'_raw'][$repeat])) $config[$putelements[$fieldName][name]] = $data[$fieldName.'_raw'][$repeat];
			} else {
				if (!empty($data[$fieldName.'_raw'][$repeat][0])) $config[$putelements[$fieldName][name]] = $data[$fieldName.'_raw'][$repeat][0];
			}
		}

		$config['dexaId'] = $dexa['dexaId'];
		if (!empty($configId))  { // Update exsisting PUT
			if ($config['statusCode'] == "DESIGN") {
				$feedback['result']['Config PUT'.$repeat]  = executeCommand(Array('callerID'=>326,'messagetypeID'=>"MESS_TYPE_COMMAND",'deviceID' => 326, 'commandID'=>11,'commandvalue' => $dexa['dexaId']."|".$config['configurationId'] , 'mess_text'=>json_encode($config)));
			}
		} else {    // post new one
			$config['dexaId'] = $dexa['dexaId'];
			$feedback['result']['Config PUT'.$repeat]  = executeCommand(Array('callerID'=>327,'messagetypeID'=>"MESS_TYPE_COMMAND",'deviceID' => 327, 'commandID'=>12,'commandvalue' =>$dexa['dexaId'], 'mess_text'=>json_encode($config)));
		}
	}
}

$result = RemoteKeys($feedback, $callerparams);
if (DEBUG) echo "</pre>";
if (DEBUG) exit;

if (array_key_exists('message',$result)) {
    JFactory::getApplication()->enqueueMessage($result['message']);
}
if (array_key_exists('error',$result)) {
	JFactory::getApplication()->enqueueMessage($result['error'], 'error');
    $formModel->setFormErrorMsg("Error during update to AWS.");
	$params = $saveParams;
    return false;
}

$params = $saveParams;
return;
?>