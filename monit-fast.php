#!/usr/bin/php
<?php
require_once 'includes.php';

define("MY_DEVICE_ID", 211);

$runonce = (isset($_GET['runonce']) && $_GET['runonce'] == 1 ? true : false);

if (!$runonce) {
	class console{
			public static function log($msg, $arr=array()){
					$str = vsprintf($msg, $arr);
					fprintf(STDERR, "$str\n");
			}
	}

	if(version_compare(PHP_VERSION, "5.3.0", '<')){
			// tick use required as of PHP 4.3.0
			declare(ticks = 1);
	}


	pcntl_signal(SIGTERM, "signal_handler");
	pcntl_signal(SIGHUP, "signal_handler");
	pcntl_signal(SIGINT, "signal_handler");

	if(version_compare(PHP_VERSION, "5.3.0", '>=')){
			pcntl_signal_dispatch();
			console::log("Signal dispatched");
	}
}

while (true) {
	$feedback = monitorDevices('"POLL2"');
	$feedback['logTime'] = date("Y-m-d H:i:s");
	logEvent(array(
			'inout' => COMMAND_IO_SEND, 
			'callerID' => MY_DEVICE_ID, 
			'commandID' => 464, 
			'result' => $feedback,  
			'commandstr' => $feedback['commandstr'], 
			'data' => "monit-fast"));
	updateDLink(MY_DEVICE_ID);
 	sleep(5);
	if ($runonce) break;
}

function cleanup(){
	echo "Cleaning up\n";
	exit (1);
}


function signal_handler($signo){
        console::log("Caught a signal %d", array($signo));
        switch ($signo) {
         case SIGINT:
                // handle restart tasks
                cleanup();
                break;
         case SIGTERM:
                // handle shutdown tasks
                cleanup();
                break;
         case SIGHUP:
                // handle restart tasks
                cleanup();
                break;
         default:
                fprintf(STDERR, "Unknown signal ". $signo);
        }
}

?>
