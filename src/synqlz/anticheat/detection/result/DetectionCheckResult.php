<?php

declare(strict_types=1);

namespace synqlz\anticheat\detection\result;

use synqlz\anticheat\session\Session;

class DetectionCheckResult{
	public static function passed(Session $session, float $points = 0.0) : self{
		return new self($session, true, "", $points);
	}

	public static function failed(Session $session, string $message, float $points = 0.0) : self{
		return new self($session, false, $message, $points);
	}

	public function __construct(
		private Session $session,
		private bool $result,
		private string $message = "",
		private float $points = 0.0,
	){
	}

	public function getSession() : Session{
		return $this->session;
	}

	public function hasFailed() : bool{
		return !$this->result;
	}

	public function getMessage() : string{
		return $this->message;
	}

	public function getPoints() : float{
		return $this->points;
	}
}