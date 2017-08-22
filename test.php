<?php
require_once 'includes.php';
define( 'DEBUG_INSTEON', FALSE );
if (!defined('DEBUG_INSTEON')) define( 'DEBUG_INSTEON', FALSE );

// $mysql = 'select * from ha_mf_devices WHERE id = 302';

// $row = FetchRow($mysql);
// var_dump($row);

// echo "sleep...".CRLF;
// sleep(10);

// $row = FetchRows($mysql);
// print_r($row);

// $row = FetchRows($mysql);
// print_r($row);

//require_once '/home/www/cronjobs/70D455DC-ACB4-4525-8A85-E6009AE93AF4/includes.php'; 
//loadRemote(20);

// $params['commandvalue'] = 'insert into ha_alerts (deviceID, description) VALUES (302, "hello")';
//print_r(executeQuery($params));

$deviceID = 115;
	if ($device = getDevice($deviceID)) {

		$page = 'buffstatus.xml';
		$device['connection']['page'] = $page;
		$url = setURL(array('device' => $device), $page);

		$page = '/1?XB=M=1';
		$device['connection']['page'] = $page;
		$clearurl = setURL(array('device' => $device), $page);
		
		// $this->inst_coder = new InsteonCoder();
	
		// $this->messages = new SplQueue();
		// $this->incompl_messages = new SplQueue();
	
		// $this->last = strtotime(date("Y-m-d H:i:s"));
		
	}

$inst_coder = new InsteonCoder();

$last_buff_len = 0;
$last_result = "0000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000";
$max_buff_len = strlen($last_result);
$processed = "";
$mybuffer = "";

$curl = restClient::get($clearurl,null, null, 1);

while (true){
	
	usleep(250000);
		// if (DEBUG_MODE) echo $this->url.CRLF;
	$curl = restClient::get($url,null, null, 1);
	if ($curl->getresponsecode() != 200 && $curl->getresponsecode() != 204) 
		// handle error?
		$feedback['error'] = $curl->getresponsecode().": ".$curl->getresponse();
	else {
		preg_match('#<BS>(.*?)</BS>#', $curl->getresponse(), $matches);
		$tmpresult = substr($matches[0],  4, strlen($matches[0]) -9 );
		$buff_len = hexdec(substr($tmpresult, -2));
		if ( $tmpresult != $last_result) {		// Something changed, content or length
			
			//
			// Sub what we already processed, cannot rely on counter bc auto empty
			//
			if (DEBUG_INSTEON) echo "tmpresult:".$tmpresult."\n";
			if (DEBUG_INSTEON) echo "left temp:".substr($tmpresult, 0, strlen($processed))."\n";
			if (DEBUG_INSTEON) echo "processed:".$processed."\n";
			if (substr($tmpresult, 0, strlen($processed)) == $processed) {							 // Start position or received additional 
				$mybuffer .= substr($tmpresult, strlen($processed), $buff_len - strlen($processed)); // Left part the same, cut off
				$end_buffer_len = 0;
			} else {
				// 2 Scenario's 
				//		Normal circle -> grab end and beginning
				//		A command was send and we want to ignore and since f.cking insteon reset our buffer
					
				// Normal 
				if (substr($tmpresult, strlen($processed)  , 2) == "02" ) {
					$mybuffer .= substr($tmpresult, strlen($processed) , $max_buff_len - strlen($processed) - 2 );
					$end_buffer_len = $max_buff_len - strlen($processed) - 2 ;

				}
				//= preg_replace('/^00+/', '02', $mybuffer);
				if (DEBUG_INSTEON) echo "end_buffer_len: $end_buffer_len\n";
				$mybuffer .= substr($tmpresult, 0 , $buff_len );
				$processed = "";

				// Was command send and do we only want to get the start
				//  Remove leading zero's
			}

			// Incoming commands buffer keeps growing.
			// On sending a command, buffer is auto cleared HOW TO RECOGNIZE?
			if (DEBUG_INSTEON) echo "Buffer Length:".$buff_len."\n";
			// $current .= date("Y-m-d H:i:s").": ".$tmpresult."\n";
			if (DEBUG_INSTEON) echo "->".$mybuffer."\n";
			$last_buff_len = $buff_len;
			$last_result = $tmpresult;
			
			//
			// Lets decode some of the result
			//
			do {
				$plm_decode_result = $inst_coder->plm_decode($mybuffer);
				
				// check for to short for PLM message, if so save result for rest
				// if (!array_key_exists("extdata", $plm_decode_result)) $plm_decode_result['extdata'] = "";
				echo date("Y-m-d H:i:s")." +++plm_decode_result\n";
				echo json_encode($plm_decode_result)."\n";
				echo date("Y-m-d H:i:s")." ===end plm_decode_result\n";
				if ($plm_decode_result['length'] == ERROR_MESSAGE_TO_SHORT) {  							// leave result and wait for more
					echo "ERROR_MESSAGE_TO_SHORT"." Empty mybuffer, refill from buffer"."\n";
					$mybuffer = "";
					// Need to make sure to not get stuck, retry and discard input buffer
					//exit;

				} elseif ($plm_decode_result['length'] == ERROR_STX_MISSING) {									// basically to short as well
					echo "ERROR_STX_MISSING"." Start nibbling to catch up"."\n";
					// $mybuffer = "";									// Clear result padding
					// Need to handle these as they arrise
					exit;
				} elseif ($plm_decode_result['length'] <= -3) {									// basically to short as well
					echo "ERROR_UNKNOWN_MESSAGE"." Do something :)"."\n";
					// $mybuffer = "";									// Clear result padding
					//
					//
					// Need to handle these as they arrise
					exit;
				} else {
					// if ($plm_decode_result['loglevel'] != LOGLEVEL_NONE)  $addMessage($plm_decode_result);
					echo "All good storing!!! ".$plm_decode_result['plm_message']." ".$plm_decode_result['insteon']['command']." Len: ".$plm_decode_result['length']."\n\n";
					// echo "%%%%%%%:".$mybuffer."->".$plm_decode_result['length'];
					$processed .= substr($mybuffer, $end_buffer_len, $plm_decode_result['length']);	
					$mybuffer = substr($mybuffer,$plm_decode_result['length']);	
					if (DEBUG_INSTEON) echo "%%%%%%%:".$mybuffer."\n";
				}
			} while ($plm_decode_result['length']>0 && strlen($mybuffer>0));
		}
	}
}
?>
