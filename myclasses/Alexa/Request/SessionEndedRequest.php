<?php

namespace Alexa\Request;

class SessionEndedRequest extends Request {
	public $reason;

	public function __construct($data) {
		parent::__construct($data);

		// echo "SessionEndedRequest".CRLF;
		$this->reason = $data->request->reason;
	}
}