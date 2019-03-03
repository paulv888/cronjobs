<?php

namespace Alexa\Request;

class IntentRequest extends Request {
	public $intentName;
	public $slots;

	public function __construct($data) {
		parent::__construct($data);

		$this->intentName = str_replace('AMAZON.', '', $data->request->intent->name);

		if (isset($data->request->intent->slots)) {
			foreach ($data->request->intent->slots as $slot) {
				if (isset($slot->value)) {
					if (!is_object($this->slots)) $this->slots = new \stdClass ;
					$name = $slot->name;
					$this->slots->$name = $slot->value;
				}
			}
		}
	}
}