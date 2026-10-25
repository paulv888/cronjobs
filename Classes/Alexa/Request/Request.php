<?php

namespace Alexa\Request;

use RuntimeException;
use InvalidArgumentException;
use DateTime;

abstract class Request {
	const TIMESTAMP_VALID_TOLERANCE_SECONDS = 150;

	public $requestId;
	public $timestamp;
	public $user;
	public $applicationId;
	public $session;

	public function __construct($data) {
		$this->requestId = isset($data->request->requestId) ? $data->request->requestId : null;

		// A missing or unparsable timestamp must not quietly become "now", or the
		// replay window in validateTimestamp() would never trip.
		$this->timestamp = null;
		if (isset($data->request->timestamp) && is_string($data->request->timestamp)) {
			try {
				$this->timestamp = new DateTime($data->request->timestamp);
			} catch (\Exception $e) {
				$this->timestamp = null;
			}
		}

		$this->user = new User(isset($data->session->user) ? $data->session->user : null);
		$this->applicationId = isset($data->session->application->applicationId)
			? (string)$data->session->application->applicationId
			: '';
		$this->session = isset($data->session) ? $data->session : null;
	}

	public static function fromData($data) {
		$requestType = isset($data->request->type) ? $data->request->type : '';

		if (!class_exists('\\Alexa\\Request\\' . $requestType)) {
			throw new RuntimeException('Unknown request type: ' . $requestType);
		}

		$className = '\\Alexa\\Request\\' . $requestType;

		$request = new $className($data);

		return $request;
	}

	public function validate($appIDs) {
		$this->validateTimestamp();
		$this->validateApplicationID($appIDs);
	}

	private function validateTimestamp() {
		if (!($this->timestamp instanceof DateTime)) {
			throw new InvalidArgumentException('Request timestamp is missing or invalid');
		}

		$now = new DateTime;
		$differenceInSeconds = abs($now->getTimestamp() - $this->timestamp->getTimestamp());

		if ($differenceInSeconds > self::TIMESTAMP_VALID_TOLERANCE_SECONDS) {
			throw new InvalidArgumentException('Request timestamp is outside the allowed window. Possible replay attack.');
		}
	}

	private function validateApplicationID($appIDs) {
		foreach ($appIDs as $appID) {
			if ((string)$appID === $this->applicationId) {
				return;
			}
		}
		throw new InvalidArgumentException('Application ID not matching');
	}
}
