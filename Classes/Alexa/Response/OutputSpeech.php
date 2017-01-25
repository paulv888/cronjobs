<?php

namespace Alexa\Response;

class OutputSpeech {
	public $type = 'PlainText';
	public $text = '';

	public function render() {
		if(strlen($this->text) != strlen(strip_tags($this->text))) {
			return [
				'type' => 'SSML',
				'ssml' => $this->text
			];
			$this->type = 'SSML'; 
		} else {
			return [
				'type' => 'PlainText',
				'text' => $this->text
			];
		}
	}
}