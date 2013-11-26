<?php 
require 'connect-db.php';
include_once 'defines.php';
include_once 'myclasses/RestClient.class.php';
include_once 'myclasses/TCPClient.php';
include_once 'includes/shared_db.php';
include_once 'includes/shared_file.php';
include_once 'includes/shared_ha.php';
include_once 'includes/shared_gen.php';
include_once 'includes/mysendmail.php';
include_once 'thermo_process.php';

//define( 'MYDEBUG', FALSE );
define( 'MYDEBUG', TRUE );
// 
// Called with remotekey or 
//                with a dropdown selected remotekey and command = selected
//                no schemes supported with dropdowns 
//  
if (isset($_POST["remotekey"])) {			// Called from remote
	$callsource = CALL_SOURCE_REMOTE_BUTTON;
	$remotekeyid=$_POST["remotekey"];
	$commandid=$_POST["command"];
	$setvalue=$_POST["setvalue"];
	if (substr($commandid, 0,1)=="S") {
		$callsource = CALL_SOURCE_REMOTE_SCHEME;
		$commandid = substr($commandid, 1);								// **** Remote can send S12, Schemeid as well. in this case overloading $commandid 
		if (MYDEBUG) echo "REMOTE SCHEME ".$commandid."</p>";
	}	
	echo process($callsource, $remotekeyid, $commandid, $alertid, $setvalue);
	exit;
}
/*

	Process can execute following commands
		1) single X10 command to HTPC over TCPbridge
		2) post to a Rest service 
		3) send an eMail
		4) execute a scheme with any of the above commands
		
	Called from 
		1) inet Remote, Simple, $remotekeyid is the remotekeyid and command optionally is commandid direct,(used for dropdowns)
		2) trade Alerts, command = T12, Schemeid (source updatetrades.php)   (Not implemented)
		3) HA Alerts, command = NULL , Alerts has AlertId to process (source createalerts.php) (Simple emails are handled locally)
					Changed to direct call from createalerts.
	
	Old interface from Remote (Still in use, depricate)
		1) remotekey !& command = remotekeyid of button pressed											-> execute remotekey or scheme attached
		2) remotekey & command = dropdowns, key=dropdown field and command is selection of dropdown, 	-> execute command
		3) 
*/

function process($callsource, $remotekeyid = NULL, $commandid = NULL, $alertid = NULL, $setvalue = NULL) {
	/* Get the Keys Schema or Device */
	
	switch ($callsource)
	{
	case CALL_SOURCE_REMOTE_BUTTON:    // Key pressed on remote
		$reskeys = mysql_query("SELECT * FROM ha_remote_keys where id =".$remotekeyid);
		$rowkeys = mysql_fetch_array($reskeys);
		$schemeid=$rowkeys['scheme'];
		
		if ($schemeid <=0) {  												// not a scheme, execute here now
			if ($commandid===NULL) { $commandid=$rowkeys['command'];}  		// for dropdowns getting command in dowhat, so take this.     
			if ($commandid==COMMAND_TOGGLE) {   // Special handling for toggle
				if ($setvalue==100) {
					$resmonitor = mysql_query("SELECT ha_mf_monitor_status.status FROM ha_mf_monitor_status WHERE ha_mf_monitor_status.deviceID =".$rowkeys['device']);
					$rowmonitor = mysql_fetch_array($resmonitor);
					if ($rowmonitor) {
						$commandid = ($rowmonitor['status'] == STATUS_ON ? STATUS_OFF : STATUS_ON); // toggle on/off
					}
				} else {
						$commandid = STATUS_ON;						
				}
			}
			if ($result=SendCommand($callsource, $rowkeys['device'], $commandid,  $setvalue)) {
				$feedback .= $result;
			} else {
				$feedback = FALSE;
			}
		} 
		break;
	case CALL_SOURCE_REMOTE_SCHEME:        // Received S12 
		$schemeid = $commandid;
		if (MYDEBUG) echo "CALL_SOURCE_REMOTE_SCHEME ".$schemeid."</p>";
		break;
	case CALL_SOURCE_HA_ALERT:       // process from alerts
		$sqlstr = "SELECT ha_alerts.id, ha_alerts_dd.schemeid FROM ha_alerts LEFT JOIN ha_alerts_dd ON ha_alerts.alertid = ha_alerts_dd.id WHERE (ha_alerts.id =".$alertid.")";
		if (MYDEBUG) echo $sqlstr."</p>";
		$resalerts = mysql_query($sqlstr);
		$rowalerts = mysql_fetch_array($resalerts);
		$schemeid = $rowalerts['schemeid'];
		break;
	case CALL_SOURCE_TRADE_ALERT:        // process from trade alerts
		break;
	}
	
	if ($schemeid>0) {      // its a scheme, process steps. Scheme setup by a) , b) derived from remotekey, c) derived from alerts

		$sqlstr = "SELECT ha_remote_scheme_steps.id, ha_remote_scheme_steps.deviceID, ha_remote_scheme_steps.commandID, ha_remote_scheme_steps.value,ha_remote_scheme_steps.sort,ha_remote_scheme_steps.alert_textID ";
		$sqlstr.= " FROM (ha_remote_schemes INNER JOIN ha_remote_scheme_steps ON ha_remote_schemes.id = ha_remote_scheme_steps.schemesID) ";
		$sqlstr.=  "WHERE(((ha_remote_schemes.id) =".$schemeid.")) ORDER BY ha_remote_scheme_steps.sort";
		$resschemesteps	= mysql_query($sqlstr);
		while ($rowshemesteps = mysql_fetch_array($resschemesteps)) {  // loop all steps
//			if ($callsource == CALL_SOURCE_REMOTE_SCHEME) // expecting scheme_step as ID 
//				{$schemeid = $rowshemesteps['id'];}
			if ($result=SendCommand($callsource, $rowshemesteps['deviceID'], $rowshemesteps['commandID'],  ($rowshemesteps['value']>0 ? $rowshemesteps['value'] : 0),$alertid,($rowshemesteps['alert_textID']>0 ? $rowshemesteps['alert_textID'] : 0))) {
							$feedback .= $result;
			} else {
				$feedback = FALSE;
			}
		}
	}

	
	if ($rowkeys) 
		if ($rowkeys['show_result']) 
			return $feedback;
	
	if (MYDEBUG) echo " feedback: ".$feedback."</p>";

	return ($feedback ? "OK;".$feedback : false);
			
}


function SendCommand($callsource, $deviceid, $commandid,  $value = NULL, $alertid = NULL, $alert_textID = NULL) { 
//
//   Sends 1 single command to TCP, REST, EMAIL
//	

	// Handles 1 single Device
	$resdevices = mysql_query("SELECT * FROM ha_mf_devices where myid =".$deviceid);
	$rowdevices = mysql_fetch_array($resdevices);
	$rescommands = mysql_query("SELECT * FROM ha_mf_commands JOIN ha_mf_commands_detail ON ".
			"ha_mf_commands.myid=ha_mf_commands_detail.commandid WHERE ha_mf_commands.myid =".$commandid. " AND commandclassid = ".$rowdevices['commandclassid']);
			
	$rowcommands = mysql_fetch_array($rescommands);
	$resdevicelinks = mysql_query("SELECT * FROM ha_mf_device_links where id =".$rowdevices['devicelink']);
	$rowdevicelinks = mysql_fetch_array($resdevicelinks);
	
	if (MYDEBUG) echo "device ".$deviceid."</p>";
	if (MYDEBUG) echo "commandid ".$commandid."</p>";
	if (MYDEBUG) echo "commandclassid ".$rowdevices['commandclassid']."</p>";
	if (MYDEBUG) echo "value ".$value."</p>";
	if (MYDEBUG) echo "targettype ".$rowdevicelinks['targettype']."</p>";
	
	if ($rowcommands['commandclassid']==COMMAND_CLASS_PHP) {								//  Hardcoded PHP
			
		if (MYDEBUG) echo "COMMAND_CLASS_PHP deviceid ".$deviceid." commandid ".$commandid."</p>";
		
		$func = $rowcommands['command'];
		if (MYDEBUG) echo "COMMAND_CLASS_PHP deviceid ".$deviceid." command ". $rowcommands['command']."</p>";
		$result = $func($deviceid);
		$feedback = newStatus($deviceid, $result[0]);
		$feedback .= setValue($deviceid, $result[1]);
	} elseif ($rowcommands['commandclassid']==COMMAND_CLASS_EMAIL) {								// Email or SMS (Gateway/Device Hardcoded
				
				if (MYDEBUG) echo "COMMAND_CLASS_EMAIL alertid ".$alertsid." alert_textID ".$alert_textID."</p>";
				
				$restext = mysql_query("SELECT * FROM ha_alert_text where id =".$alert_textID);
				$rowtext = mysql_fetch_array($restext);
				
				$subject= $rowtext['description'];
				$message= $rowtext['message'];
				$myresult = createMail($callsource,$alertid,$subject,$message);
				$feedback= sendmail($rowcommands['command'], $subject, $message, 'VloHome');
	} else {
		if ($rowdevicelinks['targettype']=="GET"){
			echo "NOT TESTED</p>";
			
			$tcomm = str_replace("{commandid}",$commandid,$rowcommands['command']);
			$tcomm = str_replace("{deviceid}",$deviceid,$tcomm);
			$tcomm = str_replace("{unit}",$deviceid,$tcomm);

		
// /3?0262ABCDEF0F13FF=I=3
			


			$url= $rowdevicelinks['targetaddress'].":".$rowdevicelinks['targetport']."/";
			if (MYDEBUG) echo $url." ".$tcomm."</p>";
			$get = restClient::get($url, $tcomm);
			$feedback = ($get->getresponsecode()==200 ? $get->getresponse() : FALSE);
		}

		if ($rowdevicelinks['targettype']=="REST"){
			if ($rowcommands['commandclassid']==COMMAND_CLASS_INSTEON) {
				$tcomm = str_replace("{commandid}",$commandid,$rowcommands['command']);
				$tcomm = str_replace("{deviceid}",$deviceid,$tcomm);
				$tcomm = str_replace("{unit}",$rowdevices['unit'],$tcomm);
				if ($rowcommands['commandclassid']==COMMAND_CLASS_INSTEON) {
					if ($value>0) $value=255/100*$value;
					$value = dec2hex($value,2);
					if (MYDEBUG) echo "value ".$value."</p>";
				}
				$tcomm = str_replace("{value}",$value,$tcomm);
				if (MYDEBUG) echo "Rest deviceid ".$deviceid." commandid ".$commandid."</p>";
				$url=$rowdevicelinks['targetaddress'].":".$rowdevicelinks['targetport'].$rowdevicelinks['page'].$tcomm;
				if (MYDEBUG) echo $url."</p>";
				$post = restClient::post($url);
				$feedback = ($post->getresponsecode()==200 ? $post->getresponse() : FALSE);
				usleep(INSTEON_SLEEP_MICRO);
				if ($feedback) {
					$feedback = newStatus($deviceid,($commandid));
					UpdateStatus($deviceid,($commandid == STATUS_OFF ? STATUS_OFF : STATUS_ON));
				}
			} 
		}
		
		if ($rowdevicelinks['targettype']=="POST"){
			$tcomm = str_replace("{commandid}",$commandid,$rowcommands['command']);
			$tcomm = str_replace("{deviceid}",$deviceid,$tcomm);
			$tcomm = str_replace("{unit}",$rowdevices['unit'],$tcomm);
			$url= $rowdevicelinks['targetaddress'].":".$rowdevicelinks['targetport'].'/'.$rowdevicelinks['page'];
			if (MYDEBUG) echo $url.$tcomm."</p>";
			$post = restClient::post($url, $tcomm);
			$feedback = ($post->getresponsecode()==200 ? $post->getresponse() : FALSE);
		}

		if ($rowdevicelinks['targettype']=="TCP"){
			if ($rowcommands['commandclassid']==COMMAND_CLASS_X10) {
				$xmlfile="X10Command.xml";
				$x10 = simplexml_load_file($xmlfile);
				OpenTCP($rowdevicelinks['targetaddress'], $rowdevicelinks['targetport']);
				$x10[0]->CallerID = "web";
				$x10[0]->Operation = "send";
				$x10[0]->Sender = "plc";
				$x10[0]->HouseCode = $rowdevices['code'];
				$x10[0]->Unit = $rowdevices['unit'];
				if ($value!=100) {
					$x10[0]->Command = "On";
					$x10[0]->CmdData = NULL;
					$x10[0]->GMTTime = mygmdate("Y-m-d H:i:s");
					WriteTCP($x10[0]->asXML());
					$x10[0]->Command = "Bright";
					$x10[0]->CmdData = 100;
					$x10[0]->GMTTime = mygmdate("Y-m-d H:i:s");
					WriteTCP($x10[0]->asXML());
					$x10[0]->Command = "Dim";
					$x10[0]->CmdData = 100-$value;
					$x10[0]->GMTTime = mygmdate("Y-m-d H:i:s");
					WriteTCP($x10[0]->asXML());
				} else {
					$x10[0]->Command = $rowcommands['description'];
					$x10[0]->CmdData = $value;
					$x10[0]->GMTTime = mygmdate("Y-m-d H:i:s");
					WriteTCP($x10[0]->asXML());
				}
				CloseTCP();
				$feedback = newStatus($deviceid,$commandid);
			}
		}  
	} 
		
	return $feedback;
} 

function newStatus ($deviceid, $status) 
// breaks if multiple keys for same device only 1 will be udated
{
//	$resmonitor = mysql_query("SELECT ha_mf_monitor_status.status FROM ha_mf_monitor_status WHERE ha_mf_monitor_status.deviceID =".$deviceid);
//	$rowmonitor = mysql_fetch_array($resmonitor);
//	if ($rowmonitor) {
		$reskeys = mysql_query("SELECT * FROM ha_remote_keys where device =".$deviceid);
		while ($rowkeys = mysql_fetch_array($reskeys)) {
			if ($rowkeys['inputtype']== "button") {
				if ($status == STATUS_OFF) {    			// if monitoring status and command not off then new status is on (dim/bright)
					$feedback[$rowkeys['id']]=$rowkeys['id']." off";
				} elseif ($status == STATUS_UNKNOWN) {
					$feedback[]=$rowkeys['id']." unknown";
				} elseif ($status == STATUS_ON) {
					$feedback[]=$rowkeys['id']." on";				
				} else { 										// else assume a value
					$feedback[]=$rowkeys['id']." on";
				}				
			}
		}
	if (!empty($feedback))
		return implode(";", $feedback).";";
	else 
		return;
}


function setValue ($deviceid, $value) 
// breaks if multiple keys for same device only 1 will be udated
{
//	$resmonitor = mysql_query("SELECT ha_mf_monitor_status.status FROM ha_mf_monitor_status WHERE ha_mf_monitor_status.deviceID =".$deviceid);
//	$rowmonitor = mysql_fetch_array($resmonitor);
//	if ($rowmonitor) {
		$reskeys = mysql_query("SELECT * FROM ha_remote_keys where device =".$deviceid);
		while ($rowkeys = mysql_fetch_array($reskeys)) {
			if ($rowkeys['inputtype']== "field") {
				$feedback[]=$rowkeys['id']." val: $value";
			}
		}
	if (!empty($feedback))
		return implode(";", $feedback).";";
	else 
		return;
}

?>