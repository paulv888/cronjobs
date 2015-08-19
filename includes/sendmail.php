<?php
function sendmail($to, $subject, $message, $fromname) {

	$mailer = new PHPMailer();
	$mailer->IsSMTP();
	$mailer->Host = 'ssl://smtp.gmail.com:465';
	$mailer->SMTPAuth = true;
	
	$mailer->Username = GMAIL_USER;
	$mailer->Password = GMAIL_PASSWORD;
	
	$mailer->From = GMAIL_USER;
	$mailer->FromName = $fromname;
	$mailer->Body = $message;
	$mailer->Subject = $subject;
	
	$mailer->AddAddress($to);
	$mailer->AddCustomHeader("Content-Type: text/html; charset=UTF-8\r\n");
	$send = 0;
	
	if(!$mailer->Send()) {
	    error_log("Mailer :  error ".$mailer->ErrorInfo)." : $to";
	    return false;
	}
	else {
		return true;
	}
}
?>
