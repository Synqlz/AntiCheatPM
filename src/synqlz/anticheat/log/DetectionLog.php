<?php

declare(strict_types=1);

namespace synqlz\anticheat\log;

use synqlz\anticheat\detection\Detection;

class DetectionLog implements \JsonSerializable{
	protected float $time;

	public function __construct(
		protected Detection $detection,
		protected string $message,
		protected float $points,
		?float $time = null
	){
		$this->time = $time ?? microtime(true);
	}

	public function getDetection() : Detection{
		return $this->detection;
	}

	public function getMessage() : string{
		return $this->message;
	}

	public function getPoints() : float{
		return $this->points;
	}

	public function getTime() : float{
		return $this->time;
	}

	public function jsonSerialize() : array{
		return [
			"detection" => $this->detection->getId(),
			"message" => $this->message,
			"points" => $this->points,
			"time" => $this->time
		];
	}
}