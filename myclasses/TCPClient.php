<?php
//error_reporting(E_ALL);
//$log_file_name = 'tcpclient.txt';
//$data = "TCP/IP Connection\n";
//file_put_contents("tcpclient.txt", $data, FILE_APPEND);


function OpenTCP($host,$service_port) {

	global $socket;

	$address = gethostbyname($host);
	
	/* Create a TCP/IP socket. */
	$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
	if ($socket !== false) {

		$result = socket_connect($socket, $address, $service_port);
		if ($result != false) {
			$line = "Waiting for Connection...";
			$result = socket_write($socket, $line, strlen ($line));
			$data = socket_read($socket, 2048, PHP_NORMAL_READ); 
			$out = ''; 
 		} 
	}
}

function WriteTCP($command) {

	global $socket;
	$line = "$command;";
	socket_write($socket, $line, strlen ($line));
}

function CloseTCP(){

	global $socket;
	
	$line = "?Close?";
	socket_write($socket, $line, strlen ($line));
	socket_close($socket);
}
?>