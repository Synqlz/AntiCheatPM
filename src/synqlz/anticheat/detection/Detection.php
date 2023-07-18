<?php

declare(strict_types=1);

namespace synqlz\anticheat\detection;

use pocketmine\event\Event;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\protocol\Packet;
use synqlz\anticheat\detection\result\DetectionCheckResult;
use synqlz\anticheat\session\Session;

abstract class Detection{
	const IDENTIFIER = "";

	private bool $enabled;
	private bool $logging;

	private int $minimumPointsToLog;
	private int $minimumPointsToKick;

	public function __construct(protected array $config){
		if($this::IDENTIFIER === ""){
			throw new \RuntimeException("Identifier cannot be empty");
		}

		$this->enabled = $config["enabled"] ?? true;
		$this->logging = $config["logging"] ?? true;

		$this->minimumPointsToLog = $config["minimum_points_to_log"] ?? 10;
	}

	public function getId() : string{
		return $this::IDENTIFIER;
	}

	public function isEnabled() : bool{
		return $this->enabled;
	}

	public function isLogging() : bool{
		return $this->logging;
	}

	public function getMinimumPointsToLog() : int{
		return $this->minimumPointsToLog;
	}

	public function getMinimumPointsToKick() : int{
		return $this->minimumPointsToKick;
	}

	/** @return class-string<Packet>[] */
	public abstract function getPackets() : array;

	/** @return class-string<Event>[] */
	public function getEvents() : array{
		return [];
	}

	public function check(Session $session, Packet $packet, DataPacketSendEvent|DataPacketReceiveEvent $event) : DetectionCheckResult{
		return static::onCheck($session, $packet, $event);
	}

	/**
	 * Returns null on success, or a string on failure.
	 */
	public abstract static function onCheck(Session $session, Packet $packet, DataPacketSendEvent|DataPacketReceiveEvent $event) : DetectionCheckResult;

	public function onEvent(Event $event) : void{ }
}