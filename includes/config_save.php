<?php
// I am using params in my code
$saveParams = $params;
if (!array_key_exists('dexaId',$_GET)) {
    $formModel->setFormErrorMsg("No dexaId in PATH_PARAMS");
	$params = $saveParams;
    return false;
} 
$dexaId = $_GET['dexaId'];
if (array_key_exists('DEBUG',$_GET)) { 
    define( 'DEBUG', TRUE );
} 
if (!defined('DEBUG')) define( 'DEBUG', FALSE );
require_once $_SERVER['DOCUMENT_ROOT'].'/cronjobs/process.php';
//$origData = $formModel->getOrigData()[0]; 

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

$parent = array();
foreach ($putelementsByGroup['526'] as $fieldName) {
    if ($putelements[$fieldName][type]=="integer") {
        //echo "0**".$fieldName.'_raw'.' '.$data[$fieldName.'_raw']."</br>"; 
        if (!empty($data[$fieldName.'_raw'])) $parent[$putelements[$fieldName][name]] = (float)$data[$fieldName.'_raw'];
    } elseif (!is_array($data[$fieldName.'_raw'])) {
        //echo "1**".$fieldName.'_raw'.' '.$data[$fieldName.'_raw']."</br>";
        if (!empty($data[$fieldName.'_raw'])) $parent[$putelements[$fieldName][name]] = $data[$fieldName.'_raw'];
    } else {
        //echo "20**".$fieldName.'_raw'.' '.$data[$fieldName.'_raw'][0]."</br>";
        //echo "21**".$fieldName.'_raw'.' '.$data[$fieldName.'_raw']['0']."</br>";
        if (!empty($data[$fieldName.'_raw'][0]))$parent[$putelements[$fieldName][name]] = $data[$fieldName.'_raw'][0];
    }
}


$parent['dexaId'] = $dexaId;
$parent['configurationId'] = $data['pg_configurations___configurationId'];
$feedback['result']['Dexa PUT'] = executeCommand(Array('callerID'=>326,'messagetypeID'=>"MESS_TYPE_COMMAND",'deviceID' => 326, 'commandID'=>11,'commandvalue' => $dexaId."|".$parent['configurationId'], 'mess_text'=>json_encode($parent)));

if (count($data[pg_configurations_details___id])>0) { // We have some config details
    foreach ($data[pg_configurations_details___id] as $repeat=>$detailId) {
      $children = Array();
      $children['configurationId'] = $parent['configurationId'];
      foreach ($putelementsByGroup['527'] as $fieldName) {
//          echo "</br>".$fieldName."</br>";
//          var_dump($data[$fieldName.'_raw'][$repeat])."</br>";
        if ($putelements[$fieldName][type]=="integer") {
        //echo "0**".$fieldName.'_raw'.' '.$data[$fieldName.'_raw']."</br>"; 
          if (!empty($data[$fieldName.'_raw'])) $children[$putelements[$fieldName][name]] = (float)$data[$fieldName.'_raw'][$repeat];
        } elseif (!is_array($data[$fieldName.'_raw'][$repeat])) {  
//          echo "NOT ARRAY".$repeat."</br>";
//          var_dump($data[$fieldName.'_raw'][$repeat])."</br>";
          if (!empty($data[$fieldName.'_raw'][$repeat])) $children[$putelements[$fieldName][name]] = $data[$fieldName.'_raw'][$repeat];
        } else {
//          echo "ARRAY".$repeat."</br>";
//          var_dump($data[$fieldName.'_raw'][$repeat][0])."</br>";
          if (!empty($data[$fieldName.'_raw'][$repeat][0])) $children[$putelements[$fieldName][name]] = $data[$fieldName.'_raw'][$repeat][0];
        }
      }
      if ($parent['statusCode'] == "DESIGN") {
        if (!empty($detailId))  { // Update exsisting PUT 
        //echo "put";
            $feedback['result']['Config PUT'.$repeat]  = executeCommand(Array('callerID'=>326,'messagetypeID'=>"MESS_TYPE_COMMAND",'deviceID' => 326, 'commandID'=>16,'commandvalue' =>$dexaId."|".$parent['configurationId']."|".$children['configurationDetailId'], 'mess_text'=>json_encode($children)));
        } else {    // post new one
          //echo "POST"
          $feedback['result']['Config POST'.$repeat]  = executeCommand(Array('callerID'=>327,'messagetypeID'=>"MESS_TYPE_COMMAND",'deviceID' => 327, 'commandID'=>15,'commandvalue' =>$dexaId."|".$parent['configurationId'], 'mess_text'=>json_encode($children)));
        }
      }
    }
}

  $result = RemoteKeys($feedback, $callerparams);
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