<?php
//error_reporting(E_ALL);
//$log_file_name = 'tcpclient.txt';
//$data = "TCP/IP Connection\n";
//file_put_contents("tcpclient.txt", $data, FILE_APPEND);


function OpenTCP($host,$service_port, $for) {

	global $socket;

	$address = gethostbyname($host);
	
	/* Create a TCP/IP socket. */
	//set_time_limit(0);
	$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
	if ($socket !== false) {
		$result = socket_connect($socket, $address, $service_port);
		if ($result != false) {
			if ($for == "X10")	{
				$line = "Waiting for Connection...";
				$result = socket_write($socket, $line, strlen ($line));
				$data = socket_read($socket, 2048, PHP_NORMAL_READ);
			} 
			if ($for == "Insteon")	{
				$line = "\n";
				$result = socket_write($socket, $line, strlen ($line));
				$data = socket_read($socket, 2048, PHP_BINARY_READ);
			} 
		} 
	} else {
		$errorcode = socket_last_error();
		$errormsg = socket_strerror($errorcode);
		echo $errorcode.$errormsg."</br>";
	}
	return $socket;
}

function WriteTCP($command) {

	global $socket;
	$line = "$command;";
	socket_write($socket, $line, strlen ($line));
}

function ReadTCP() {

	global $socket;
	$resp = socket_read($socket, 100, PHP_BINARY_READ);
	return $resp;
}

function CloseTCP($for){

	global $socket;
	
	if ($for == "X10") {	
		$line = "?Close?";
		socket_write($socket, $line, strlen ($line));
	}
	socket_close($socket);
}
?>