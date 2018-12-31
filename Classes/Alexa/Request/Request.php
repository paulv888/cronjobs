<?php

namespace Alexa\Request;

use RuntimeException;
use InvalidArgumentException;
use DateTime;

abstract class Request {
	const TIMESTAMP_VALID_TOLERANCE_SECONDS = 30;

	public $requestId;
	public $timestamp;
	// public $user;
	// public $applicationId;
	// public $session;

	public function __construct($data) {
		$this->requestId = $data->request->requestId;
		$this->timestamp = new DateTime($data->request->timestamp);
		$this->user = new User($data->session->user);
		$this->applicationId = $data->session->application->applicationId;
		$this->session = $data->session;
	}

	public static function fromData($data) {
		$requestType = $data->request->type;

		if (!class_exists('\\Alexa\\Request\\' . $requestType)) {
			throw new RuntimeException('Unknown request type: ' . $requestType);
		}

		$className = '\\Alexa\\Request\\' . $requestType;

		$request = new $className($data);

		return $request;
	}

	public function validate($appIDs) {
		if (DEBUG_ALX) echo "Validate".CRLF;
		//$this->validateTimestamp();
		$this->validateApplicationID($appIDs);
	}

	private function validateTimestamp() {
		$now = new DateTime;
		$differenceInSeconds = $now->getTimestamp() - $this->timestamp->getTimestamp();

		if ($differenceInSeconds > self::TIMESTAMP_VALID_TOLERANCE_SECONDS) {
			throw new InvalidArgumentException('Request timestamp was too old. Possible replay attack.');
		}
	}

	private function validateApplicationID($appIDs) {
		foreach ($appIDs as $appID) {
			$found = true;
			if ($appID == $this->applicationId) {
				$found = true;
			}
		}
		if (!$found) throw new InvalidArgumentException('Application ID not matching');
	}
}
