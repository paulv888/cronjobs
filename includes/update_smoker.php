<?php

// No direct access
defined('_JEXEC') or die('Restricted access');
$url_send ='https://'.$_SERVER['SERVER_NAME'].'/cronjobs/70D455DC-ACB4-4525-8A85-E6009AE93AF4/process.php';

define("COMMAND_TOGGLE", 19);
define("COMMAND_DIM", 13);
define("COMMAND_BRIGHTEN", 15);
define("COMMAND_ON", 17);
define("COMMAND_OFF", 20);
define("COMMAND_RUN_SCHEME", 154);
define("COMMAND_SET_VALUE", 145);
define("COMMAND_SET_RESULT", 285);
define("COMMAND_SET_PROPERTY_VALUE", 314);
define("COMMAND_VOICE", 324);
define("COMMAND_GET_PROPERTIES", 325);
define("COMMAND_SET_VALUE_NO_AUTO_ON", 398);
define("COMMAND_SET_EXTGPIO", 397);
define("DEVICE_REMOTE", 164);
define("PROPERTY_SETPOINT_SMOKER", 275 );
define("PROPERTY_SETPOINT_MEAT_1", 276 );
define("PROPERTY_SETPOINT_MEAT_2", 277 );
define("PROPERTY_SETPOINT_SMOKE", 285 );
define("PROPERTY_PHASE_RAW", 303 );

$messages = Array();

switch (implode($data['smk_run___phase_raw'])) {
    case 0:	// Off
	$messages['control'] = array(
			'callerID'       => DEVICE_REMOTE,
			'messagetypeID'  => 'MESS_TYPE_COMMAND',
			'commandID'      => COMMAND_OFF,
			'deviceID'       => implode($data['smk_run___deviceid_raw'])
			);

        $messages['smoker'] = array(
			'callerID'       => DEVICE_REMOTE,
			'messagetypeID'  => 'MESS_TYPE_COMMAND',
			'commandID'      => COMMAND_SET_VALUE_NO_AUTO_ON,
			'deviceID'       => implode($data['smk_run___deviceid_raw']),
			'propertyID'     => PROPERTY_SETPOINT_SMOKER,
			'commandvalue'   => 0
			);

        $messages['phase'] = array(
			'callerID'       => DEVICE_REMOTE,
			'messagetypeID'  => 'MESS_TYPE_COMMAND',
			'commandID'      => COMMAND_SET_PROPERTY_VALUE,
			'deviceID'       => implode($data['smk_run___deviceid_raw']),
			'commandvalue'   => "Phase____".trim($data['smk_run___phase']).'_'
			);

        $messages['phase_raw'] = array(
			'callerID'       => DEVICE_REMOTE,
			'messagetypeID'  => 'MESS_TYPE_COMMAND',
			'commandID'      => COMMAND_SET_EXTGPIO,
			'deviceID'       => implode($data['smk_run___deviceid_raw']),
			'propertyID'     => PROPERTY_PHASE_RAW,
			'commandvalue'   => trim(implode($data['smk_run___phase_raw']))
			);

		$messages['smoke'] = array(
			'callerID'       => DEVICE_REMOTE,
			'messagetypeID'  => 'MESS_TYPE_COMMAND',
			'commandID'      => COMMAND_SET_VALUE_NO_AUTO_ON,
			'deviceID'       => implode($data['smk_run___deviceid_raw']),
			'propertyID'     => PROPERTY_SETPOINT_SMOKE,
			'commandvalue'   => 0
			);

		break;
    case 1:	// Pre-Heat
		$messages['control'] = array(
			'callerID'       => DEVICE_REMOTE,
			'messagetypeID'  => 'MESS_TYPE_COMMAND',
			'commandID'      => COMMAND_ON,
			'deviceID'       => implode($data['smk_run___deviceid_raw'])
			);

        $messages['smoker'] = array(
			'callerID'       => DEVICE_REMOTE,
			'messagetypeID'  => 'MESS_TYPE_COMMAND',
			'commandID'      => COMMAND_SET_VALUE_NO_AUTO_ON,
			'deviceID'       => implode($data['smk_run___deviceid_raw']),
			'propertyID'     => PROPERTY_SETPOINT_SMOKER,
			'commandvalue'   => trim($data['smk_run___smoker_temperature_C_raw'])
			);

        $messages['meat1'] = array(
			'callerID'       => DEVICE_REMOTE,
			'messagetypeID'  => 'MESS_TYPE_COMMAND',
			'commandID'      => COMMAND_SET_VALUE_NO_AUTO_ON,
			'deviceID'       => implode($data['smk_run___deviceid_raw']),
			'propertyID'     => PROPERTY_SETPOINT_MEAT_1,
			'commandvalue'   => 0
			);

        $messages['meat2'] = array(
			'callerID'       => DEVICE_REMOTE,
			'messagetypeID'  => 'MESS_TYPE_COMMAND',
			'commandID'      => COMMAND_SET_VALUE_NO_AUTO_ON,
			'deviceID'       => implode($data['smk_run___deviceid_raw']),
			'propertyID'     => PROPERTY_SETPOINT_MEAT_2,
			'commandvalue'   => 0
			);

        $messages['smoke'] = array(
			'callerID'       => DEVICE_REMOTE,
			'messagetypeID'  => 'MESS_TYPE_COMMAND',
			'commandID'      => COMMAND_SET_VALUE_NO_AUTO_ON,
			'deviceID'       => implode($data['smk_run___deviceid_raw']),
			'propertyID'     => PROPERTY_SETPOINT_SMOKE,
			'commandvalue'   => 300
			);

        $messages['phase'] = array(
			'callerID'       => DEVICE_REMOTE,
			'messagetypeID'  => 'MESS_TYPE_COMMAND',
			'commandID'      => COMMAND_SET_PROPERTY_VALUE,
			'deviceID'       => implode($data['smk_run___deviceid_raw']),
			'commandvalue'   => "Phase___".trim($data['smk_run___phase'])
		);

        $messages['phase_raw'] = array(
			'callerID'       => DEVICE_REMOTE,
			'messagetypeID'  => 'MESS_TYPE_COMMAND',
			'commandID'      => COMMAND_SET_EXTGPIO,
			'deviceID'       => implode($data['smk_run___deviceid_raw']),
			'propertyID'     => PROPERTY_PHASE_RAW,
			'commandvalue'   => trim(implode($data['smk_run___phase_raw']))
		);
		
        break;
    case 2:	// Running
		$messages['control'] = array(
			'callerID'       => DEVICE_REMOTE,
			'messagetypeID'  => 'MESS_TYPE_COMMAND',
			'commandID'      => COMMAND_ON,
			'deviceID'       => implode($data['smk_run___deviceid_raw'])
		);

        $messages['smoker'] = array(
			'callerID'       => DEVICE_REMOTE,
			'messagetypeID'  => 'MESS_TYPE_COMMAND',
			'commandID'      => COMMAND_SET_VALUE_NO_AUTO_ON,
			'deviceID'       => implode($data['smk_run___deviceid_raw']),
			'propertyID'     => PROPERTY_SETPOINT_SMOKER,
			'commandvalue'   => trim($data['smk_run___smoker_temperature_C_raw'])
			);

        $messages['meat1'] = array(
			'callerID'       => DEVICE_REMOTE,
			'messagetypeID'  => 'MESS_TYPE_COMMAND',
			'commandID'      => COMMAND_SET_VALUE_NO_AUTO_ON,
			'deviceID'       => implode($data['smk_run___deviceid_raw']),
			'propertyID'     => PROPERTY_SETPOINT_MEAT_1,
			'commandvalue'   => 0
			);

        $messages['meat2'] = array(
			'callerID'       => DEVICE_REMOTE,
			'messagetypeID'  => 'MESS_TYPE_COMMAND',
			'commandID'      => COMMAND_SET_VALUE_NO_AUTO_ON,
			'deviceID'       => implode($data['smk_run___deviceid_raw']),
			'propertyID'     => PROPERTY_SETPOINT_MEAT_2,
			'commandvalue'   => 0
			);

        $messages['smoke'] = array(
			'callerID'       => DEVICE_REMOTE,
			'messagetypeID'  => 'MESS_TYPE_COMMAND',

			'commandID'      => COMMAND_SET_VALUE_NO_AUTO_ON,
			'deviceID'       => implode($data['smk_run___deviceid_raw']),
			'propertyID'     => PROPERTY_SETPOINT_SMOKE,
			'commandvalue'   => 300
			);

		foreach ($data['smk_run_meat___probe_raw'] as $key => $probe) {
			if ($probe[0] == 1 || $probe[0] == 2) {
					$messages['meat'.$probe[0]] = array(
							'callerID'       => DEVICE_REMOTE,
							'messagetypeID'  => 'MESS_TYPE_COMMAND',
							'commandID'      => COMMAND_SET_VALUE_NO_AUTO_ON,
							'deviceID'       => implode($data['smk_run___deviceid_raw']),
							'propertyID'     => PROPERTY_SETPOINT_MEAT_1 + trim($probe[0]) - 1,	// 276 & 277
							'commandvalue'   => trim($data['smk_run_meat___meat_temperature_C_raw'][$key])
						);


			} 

			if (is_array($data['smk_run_meat___cooktime_raw'])) {
				$cooktime = max($data['smk_run_meat___cooktime_raw']);
			} else {
				$cooktime = $data['smk_run_meat___cooktime_raw'];
			}

			sscanf($cooktime, "%d:%d", $hours, $minutes);
			$time_seconds = $hours * 60 + $minutes;

			$messages['timer'] = array(
				'callerID'       => DEVICE_REMOTE,
				'messagetypeID'  => 'MESS_TYPE_COMMAND',
				'commandID'      => 287,
				'deviceID'       => implode($data['smk_run___deviceid_raw']),
				'commandvalue'   => $time_seconds
				);
		}

		$messages['phase'] = array(
			'callerID'       => DEVICE_REMOTE,
			'messagetypeID'  => 'MESS_TYPE_COMMAND',
			'commandID'      => COMMAND_SET_PROPERTY_VALUE,
			'deviceID'       => implode($data['smk_run___deviceid_raw']),
			'commandvalue'   => "Phase___".trim($data['smk_run___phase'])
		);

		$messages['phase_raw'] = array(
			'callerID'       => DEVICE_REMOTE,
			'messagetypeID'  => 'MESS_TYPE_COMMAND',
			'commandID'      => COMMAND_SET_EXTGPIO,
			'deviceID'       => implode($data['smk_run___deviceid_raw']),
			'propertyID'     => PROPERTY_PHASE_RAW,
			'commandvalue'   => trim(implode($data['smk_run___phase_raw']))
		);
        break;
    case 3:
		$messages['control'] = array(
			'callerID'       => DEVICE_REMOTE,
			'messagetypeID'  => 'MESS_TYPE_COMMAND',
			'commandID'      => COMMAND_OFF,
			'deviceID'       => implode($data['smk_run___deviceid_raw'])
			);

        $messages['smoker'] = array(
			'callerID'       => DEVICE_REMOTE,
			'messagetypeID'  => 'MESS_TYPE_COMMAND',
			'commandID'      => COMMAND_SET_VALUE_NO_AUTO_ON,
			'deviceID'       => implode($data['smk_run___deviceid_raw']),
			'propertyID'     => PROPERTY_SETPOINT_SMOKER,
			'commandvalue'   => 1
			);

        $messages['meat1'] = array(
			'callerID'       => DEVICE_REMOTE,
			'messagetypeID'  => 'MESS_TYPE_COMMAND',
			'commandID'      => COMMAND_SET_VALUE_NO_AUTO_ON,
			'deviceID'       => implode($data['smk_run___deviceid_raw']),
			'propertyID'     => PROPERTY_SETPOINT_MEAT_1,
			'commandvalue'   => 500
			);

        $messages['meat2'] = array(
			'callerID'       => DEVICE_REMOTE,
			'messagetypeID'  => 'MESS_TYPE_COMMAND',
			'commandID'      => COMMAND_SET_VALUE_NO_AUTO_ON,
			'deviceID'       => implode($data['smk_run___deviceid_raw']),
			'propertyID'     => PROPERTY_SETPOINT_MEAT_2,
			'commandvalue'   => 500
			);

        $messages['smoke'] = array(
			'callerID'       => DEVICE_REMOTE,
			'messagetypeID'  => 'MESS_TYPE_COMMAND',
			'commandID'      => COMMAND_SET_VALUE_NO_AUTO_ON,
			'deviceID'       => implode($data['smk_run___deviceid_raw']),
			'propertyID'     => PROPERTY_SETPOINT_SMOKE,
			'commandvalue'   => 1
			);

		$messages['control'] = array(
			'callerID'       => DEVICE_REMOTE,
			'messagetypeID'  => 'MESS_TYPE_COMMAND',
			'commandID'      => COMMAND_OFF,
			'deviceID'       => implode($data['smk_run___control_deviceid_raw'])
			);

        $messages['phase'] = array(
			'callerID'       => DEVICE_REMOTE,
			'messagetypeID'  => 'MESS_TYPE_COMMAND',
			'commandID'      => COMMAND_SET_PROPERTY_VALUE,
			'deviceID'       => implode($data['smk_run___deviceid_raw']),
			'commandvalue'   => "Phase___".trim($data['smk_run___phase'])
			);

		$messages['phase_raw'] = array(
			'callerID'       => DEVICE_REMOTE,
			'messagetypeID'  => 'MESS_TYPE_COMMAND',
			'commandID'      => COMMAND_SET_EXTGPIO,
			'deviceID'       => implode($data['smk_run___deviceid_raw']),
			'propertyID'     => PROPERTY_PHASE_RAW,
			'commandvalue'   => trim(implode($data['smk_run___phase_raw']))
			);

        break;
}

//echo "<pre>";
//print_r($messages);
//exit;

$application = JFactory::getApplication();
foreach ($messages as $data) {
	$result = sendPostData($url_send, $data);
	if (strpos($result,"error") === false)
		JFactory::getApplication()->enqueueMessage($result,'message');
	else
		JFactory::getApplication()->enqueueMessage(JText::_($result), 'error');
}

return false;

function sendPostData($url, $post){
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");  
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS,$post);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
  $result = curl_exec($ch);
  curl_close($ch);  // Seems like good practice
  return $result;
}

?>
