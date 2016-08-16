<?php

// No direct access
defined('_JEXEC') or die('Restricted access');
$url_send ='https://'.$_SERVER['SERVER_NAME'].'/cronjobs/70D455DC-ACB4-4525-8A85-E6009AE93AF4/process.php';


$messages = Array();

switch (implode($data['smk_run___phase_raw'])) {
    case 0:	// Off
		$messages['control'] = array(
			'callerID'       => 164,
			'messagetypeID'  => 'MESS_TYPE_COMMAND',
			'commandID'      =>  20,
			'deviceID'       =>  implode($data['smk_run___deviceid_raw'])
			);

        $messages['smoker'] = array(
			'callerID'       => 164,
			'messagetypeID'  => 'MESS_TYPE_COMMAND',
			'commandID'      =>  314,
			'deviceID'       =>  implode($data['smk_run___deviceid_raw']),
			'commandvalue'   =>  "Setpoint Smoker___0"
			);

        $messages['phase'] = array(
			'callerID'       => 164,
			'messagetypeID'  => 'MESS_TYPE_COMMAND',
			'commandID'      =>  314,
			'deviceID'       =>  implode($data['smk_run___deviceid_raw']),
			'commandvalue'   =>  "Phase____".trim($data['smk_run___phase']).'_'
			);

        $messages['phase_raw'] = array(
			'callerID'       => 164,
			'messagetypeID'  => 'MESS_TYPE_COMMAND',
			'commandID'      =>  314,
			'deviceID'       =>  implode($data['smk_run___deviceid_raw']),
			'commandvalue'   =>  "Phase Raw___".trim(implode($data['smk_run___phase_raw']))
			);

		$messages['smoke'] = array(
			'callerID'       => 164,
			'messagetypeID'  => 'MESS_TYPE_COMMAND',
			'commandID'      =>  314,
			'deviceID'       =>  implode($data['smk_run___deviceid_raw']),
			'commandvalue'   =>  "Setpoint Smoke___0"
			);

		break;
    case 1:	// Pre-Heat
		$messages['control'] = array(
			'callerID'       => 164,
			'messagetypeID'  => 'MESS_TYPE_COMMAND',
			'commandID'      =>  17,
			'deviceID'       =>  implode($data['smk_run___deviceid_raw'])
			);

        $messages['smoker'] = array(
			'callerID'       => 164,
			'messagetypeID'  => 'MESS_TYPE_COMMAND',
			'commandID'      =>  314,
			'deviceID'       =>  implode($data['smk_run___deviceid_raw']),
			'commandvalue'   =>  "Setpoint Smoker___".trim($data['smk_run___smoker_temperature_C_raw'])
			);

        $messages['meat1'] = array(
			'callerID'       => 164,
			'messagetypeID'  => 'MESS_TYPE_COMMAND',
			'commandID'      =>  314,
			'deviceID'       =>  implode($data['smk_run___deviceid_raw']),
			'commandvalue'   =>  "Setpoint Meat 1___0"
			);

        $messages['meat2'] = array(
			'callerID'       => 164,
			'messagetypeID'  => 'MESS_TYPE_COMMAND',
			'commandID'      =>  314,
			'deviceID'       =>  implode($data['smk_run___deviceid_raw']),
			'commandvalue'   =>  "Setpoint Meat 2___0"
			);

        $messages['smoke'] = array(
			'callerID'       => 164,
			'messagetypeID'  => 'MESS_TYPE_COMMAND',
			'commandID'      =>  314,
			'deviceID'       =>  implode($data['smk_run___deviceid_raw']),
			'commandvalue'   =>  "Setpoint Smoke___300"
			);

        $messages['phase'] = array(
			'callerID'       => 164,
			'messagetypeID'  => 'MESS_TYPE_COMMAND',
			'commandID'      =>  314,
			'deviceID'       =>  implode($data['smk_run___deviceid_raw']),
			'commandvalue'   =>  "Phase___".trim($data['smk_run___phase'])
		);

        $messages['phase_raw'] = array(
			'callerID'       => 164,
			'messagetypeID'  => 'MESS_TYPE_COMMAND',
			'commandID'      =>  314,
			'deviceID'       =>  implode($data['smk_run___deviceid_raw']),
			'commandvalue'   =>  "Phase Raw___".trim(implode($data['smk_run___phase_raw']))
		);
		
        break;
    case 2:	// Running
		$messages['control'] = array(
			'callerID'       => 164,
			'messagetypeID'  => 'MESS_TYPE_COMMAND',
			'commandID'      =>  17,
			'deviceID'       =>  implode($data['smk_run___deviceid_raw'])
		);

        $messages['smoker'] = array(
			'callerID'       => 164,
			'messagetypeID'  => 'MESS_TYPE_COMMAND',
			'commandID'      =>  314,
			'deviceID'       =>  implode($data['smk_run___deviceid_raw']),
			'commandvalue'   =>  "Setpoint Smoker___".trim($data['smk_run___smoker_temperature_C_raw'])
			);

        $messages['meat1'] = array(
			'callerID'       => 164,
			'messagetypeID'  => 'MESS_TYPE_COMMAND',
			'commandID'      =>  314,
			'deviceID'       =>  implode($data['smk_run___deviceid_raw']),
			'commandvalue'   =>  "Setpoint Meat 1___0"
			);

        $messages['meat2'] = array(
			'callerID'       => 164,
			'messagetypeID'  => 'MESS_TYPE_COMMAND',
			'commandID'      =>  314,
			'deviceID'       =>  implode($data['smk_run___deviceid_raw']),
			'commandvalue'   =>  "Setpoint Meat 2___0"
			);

        $messages['smoke'] = array(
			'callerID'       => 164,
			'messagetypeID'  => 'MESS_TYPE_COMMAND',
			'commandID'      =>  314,
			'deviceID'       =>  implode($data['smk_run___deviceid_raw']),
			'commandvalue'   =>  "Setpoint Smoke___300"
			);

		foreach ($data['smk_run_meat___probe_raw'] as $key => $probe) {
			if ($probe[0] == 1 || $probe[0] == 2) {
					$messages['meat'.$probe[0]] = array(
							'callerID'       => 164,
							'messagetypeID'  => 'MESS_TYPE_COMMAND',
							'commandID'      =>  314,
							'deviceID'       =>  implode($data['smk_run___deviceid_raw']),
							'commandvalue'   =>  "Setpoint Meat ".trim($probe[0])."___".trim($data['smk_run_meat___meat_temperature_C_raw'][$key])
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
				'callerID'       => 164,
				'messagetypeID'  => 'MESS_TYPE_COMMAND',
				'commandID'      =>  287,
				'deviceID'       =>  implode($data['smk_run___deviceid_raw']),
				'commandvalue'   =>  $time_seconds
				);
		}

		$messages['phase'] = array(
			'callerID'       => 164,
			'messagetypeID'  => 'MESS_TYPE_COMMAND',
			'commandID'      =>  314,
			'deviceID'       =>  implode($data['smk_run___deviceid_raw']),
			'commandvalue'   =>  "Phase___".trim($data['smk_run___phase'])
		);

		$messages['phase_raw'] = array(
			'callerID'       => 164,
			'messagetypeID'  => 'MESS_TYPE_COMMAND',
			'commandID'      =>  314,
			'deviceID'       =>  implode($data['smk_run___deviceid_raw']),
			'commandvalue'   =>  "Phase Raw___".trim(implode($data['smk_run___phase_raw']))
		);
        break;
    case 3:
		$messages['control'] = array(
			'callerID'       => 164,
			'messagetypeID'  => 'MESS_TYPE_COMMAND',
			'commandID'      =>  20,
			'deviceID'       =>  implode($data['smk_run___deviceid_raw'])
			);

        $messages['smoker'] = array(
			'callerID'       => 164,
			'messagetypeID'  => 'MESS_TYPE_COMMAND',
			'commandID'      =>  314,
			'deviceID'       =>  implode($data['smk_run___deviceid_raw']),
			'commandvalue'   =>  "Setpoint Smoker___1"
			);

        $messages['meat1'] = array(
			'callerID'       => 164,
			'messagetypeID'  => 'MESS_TYPE_COMMAND',
			'commandID'      =>  314,
			'deviceID'       =>  implode($data['smk_run___deviceid_raw']),
			'commandvalue'   =>  "Setpoint Meat 1___500"
			);

        $messages['meat2'] = array(
			'callerID'       => 164,
			'messagetypeID'  => 'MESS_TYPE_COMMAND',
			'commandID'      =>  314,
			'deviceID'       =>  implode($data['smk_run___deviceid_raw']),
			'commandvalue'   =>  "Setpoint Meat 2___500"
			);

        $messages['smoke'] = array(
			'callerID'       => 164,
			'messagetypeID'  => 'MESS_TYPE_COMMAND',
			'commandID'      =>  314,
			'deviceID'       =>  implode($data['smk_run___deviceid_raw']),
			'commandvalue'   =>  "Setpoint Smoke___1"
			);

		$messages['control'] = array(
			'callerID'       => 164,
			'messagetypeID'  => 'MESS_TYPE_COMMAND',
			'commandID'      =>  20,
			'deviceID'       =>  implode($data['smk_run___control_deviceid_raw'])
			);

        $messages['phase'] = array(
			'callerID'       => 164,
			'messagetypeID'  => 'MESS_TYPE_COMMAND',
			'commandID'      =>  314,
			'deviceID'       =>  implode($data['smk_run___deviceid_raw']),
			'commandvalue'   =>  "Phase___".trim($data['smk_run___phase'])
			);

		$messages['phase_raw'] = array(
			'callerID'       => 164,
			'messagetypeID'  => 'MESS_TYPE_COMMAND',
			'commandID'      =>  314,
			'deviceID'       =>  implode($data['smk_run___deviceid_raw']),
			'commandvalue'   =>  "Phase Raw___".trim(implode($data['smk_run___phase_raw']))
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
