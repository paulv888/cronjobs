<?php
function sendmail($to, $subject, $message, $fromname) {
	$headers = 'MIME-Version: 1.0' . "\r\n".
    'From: '.$fromname. "\r\n" .
    'Reply-To: '.$fromname. "\r\n" .
    'X-Mailer: PHP/' . phpversion();
	
	if(!mail($to, $subject, $message, $headers)) {
	    $feedback['error'] = "Mailer - error";
	    return $feedback;
	}
	else {
		return array();
	}
}
?>
