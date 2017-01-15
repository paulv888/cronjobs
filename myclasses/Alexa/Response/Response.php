<?php

namespace Alexa\Response;

class Response {
	public $version = '1.0';
	public $sessionAttrib = [];

	public $outputSpeech = null;
	public $card = null;
	public $reprompt = null;
	public $shouldEndSession = true;

	public function __construct() {
		$this->outputSpeech = new OutputSpeech;
	}

	public function respond($text) {
		$responses = array (" What do you want to know?", " Anything I can help you with?", " Sana nasil yardim edebilirim?", " Wassup?");
		$append = $responses[rand(0,count($responses)-1)];

		$this->outputSpeech = new OutputSpeech;
		$this->outputSpeech->text = $text;
		if (isset($this->sessionAttrib['Launched'])) {
			if (strlen($this->outputSpeech->text) == strlen(strip_tags($this->outputSpeech->text))) {
				$this->outputSpeech->text = "<speak>".$this->outputSpeech->text."<break time=\"0.3s\" />".$append."</speak>";
			} else {
				$this->outputSpeech->text = str_replace("</speak>","",$this->outputSpeech->text);
				$this->outputSpeech->text .= "<break time=\"0.3s\" />".$append."</speak>";
			}
		}
		return $this;
	}

	public function reprompt($text) {
		$this->reprompt = new Reprompt;
		$this->reprompt->outputSpeech->text = $text;

		return $this;
	}

	public function sessionAttributes($attr) {
		$this->sessionAttrib = array_merge($this->sessionAttrib,$attr);
		return $this;
	}

	public function withCard($title, $content = '') {
		$this->card = new Card;
		$this->card->title = $title;
		$this->card->content = $content;
		
		return $this;
	}

	public function endSession($shouldEndSession = true) {
		$this->shouldEndSession = $shouldEndSession;
		return $this;
	}

	public function ask() {
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode(
		[
			'version' => $this->version,
			'sessionAttributes' => $this->sessionAttrib,
			'response' => [
				'outputSpeech' => $this->outputSpeech ? $this->outputSpeech->render() : null,
				'card' => $this->card ? $this->card->render() : null,
				'reprompt' => $this->reprompt ? $this->reprompt->render() : null,
				'shouldEndSession' => false
			]
		] );
		return;
	}
		
	public function tell() {
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode(
		[
			'version' => $this->version,
			'sessionAttributes' => $this->sessionAttrib,
			'response' => [
				'outputSpeech' => $this->outputSpeech ? $this->outputSpeech->render() : null,
				'card' => $this->card ? $this->card->render() : null,
				'reprompt' => $this->reprompt ? $this->reprompt->render() : null,
				'shouldEndSession' => $this->shouldEndSession ? true : false
			]
		] );
		return;
	}
		
	public function render() {
		return [
			'version' => $this->version,
			'sessionAttributes' => $this->sessionAttrib,
			'response' => [
				'outputSpeech' => $this->outputSpeech ? $this->outputSpeech->render() : null,
				'card' => $this->card ? $this->card->render() : null,
				'reprompt' => $this->reprompt ? $this->reprompt->render() : null,
				'shouldEndSession' => $this->shouldEndSession ? true : false
			]
		];
	}
}