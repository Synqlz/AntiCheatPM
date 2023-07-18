<?php

declare(strict_types=1);

namespace synqlz\anticheat\session;

use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\Filesystem;
use synqlz\anticheat\AntiCheat;
use synqlz\anticheat\detection\Detection;
use synqlz\anticheat\detection\DetectionHandler;
use synqlz\anticheat\log\DetectionLog;

class Session{
	private string $path;

	/** @var array<string, array> */
	private array $detectionData;
	/** @var array<string, array> */
	private array $tempDetectionData = [];
	/** @var array<string, DetectionLog[]> */
	private array $logs;

	private array $messageCooldowns = [];

	private array $logCooldowns = [];

	private ?LatencyHandler $latencyHandler = null;

	public function __construct(protected string $username){
		$this->path = AntiCheat::getInstance()->getDataFolder() . "sessions" . DIRECTORY_SEPARATOR . $this->username . ".json";
		if(!file_exists(dirname($this->path))){
			mkdir(dirname($this->path), 0777, true);
		}

		if(!file_exists($this->path)){
			Filesystem::safeFilePutContents($this->path, "{}");
		}

		$data = json_decode(file_get_contents($this->path), true) ?? [];

		$this->detectionData = $data["detectionData"] ?? [];
		$this->logs = [];

		foreach($data["logs"] ?? [] as $detectionId => $logs){
			$detection = DetectionHandler::getInstance()->get($detectionId);
			if($detection === null){
				continue;
			}

			foreach($logs as $log){
				$log = new DetectionLog($detection, $log["message"], $log["time"]);
				if(microtime(true) - $log->getTime() > 86400 * 3){
					continue; //After 3 days, the logs will be cleared.
				}

				$this->logs[$detectionId][] = $log;
			}
		}

		$player = $this->getPlayer();
		if($player !== null){
			$this->latencyHandler = new LatencyHandler($player);
		}
	}

	public function getPlayer() : ?Player{
		return Server::getInstance()->getPlayerExact($this->username);
	}

	public function getName() : string{
		return $this->username;
	}

	public function getDetectionData(string $detection) : array{
		return $this->detectionData[$detection]["data"] ?? [];
	}

	public function setDetectionData(string $detection, array $data) : void{
		$this->detectionData[$detection]["data"] = $data;
	}

	public function getDetectionPoints(string $detection) : float{
		return $this->detectionData[$detection]["points"] ?? 0;
	}

	public function setDetectionPoints(string $detection, float $points) : void{
		$this->detectionData[$detection]["points"] = max(0, $points);
	}

	public function increaseDetectionPoints(string $detection, float $points) : void{
		$this->setDetectionPoints($detection, $this->getDetectionPoints($detection) + $points);
	}

	public function decreaseDetectionPoints(string $detection, float $points) : void{
		$this->setDetectionPoints($detection, $this->getDetectionPoints($detection) - $points);
	}

	public function getTemporaryData(string $id) : array{
		return $this->tempDetectionData[$id] ?? [];
	}

	public function setTemporaryData(string $id, array $data) : void{
		$this->tempDetectionData[$id] = $data;
	}

	public function getLogs() : array{
		return $this->logs;
	}

	public function getDetectionLogs(string $detection) : array{
		return $this->logs[$detection] ?? [];
	}

	public function saveLog(DetectionLog $log) : void{
		if (($this->logCooldowns[$log->getDetection()->getId()] ?? time()) - time()> 0) {
			return;
		}

		$this->logCooldowns[$log->getDetection()->getId()] = time() + 5;
		$this->logs[$log->getDetection()->getId()][] = $log;
	}

	public function getMessageCooldown(Detection $detection, string $player) : float{
		return ($this->messageCooldowns[$detection->getId()][$player] ?? microtime(true)) - microtime(true);
	}

	public function setMessageCooldown(Detection $detection, string $player, float $cooldown) : void{
		$this->messageCooldowns[$detection->getId()][$player] = microtime(true) + $cooldown;
	}

	public function getLatencyHandler() : ?LatencyHandler{
		return $this->latencyHandler;
	}

	public function getData() : array{
		return [
			"detectionData" => $this->detectionData,
			"logs" => $this->logs
		];
	}

	public function save() : void{
		Filesystem::safeFilePutContents($this->path, json_encode($this->getData(), JSON_PRETTY_PRINT));
	}
}