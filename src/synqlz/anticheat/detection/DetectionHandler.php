<?php

declare(strict_types=1);

namespace synqlz\anticheat\detection;

use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\protocol\Packet;
use pocketmine\Server;
use pocketmine\utils\SingletonTrait;
use synqlz\anticheat\AntiCheat;
use synqlz\anticheat\command\AntiCheatCommand;
use synqlz\anticheat\detection\defaults\JetpackDetection;
use synqlz\anticheat\detection\defaults\NoPacketDetection;
use synqlz\anticheat\detection\defaults\ReachDetection;
use synqlz\anticheat\detection\defaults\TimerDetection;
use synqlz\anticheat\log\DetectionLog;
use synqlz\anticheat\session\Session;
use synqlz\anticheat\session\SessionCache;
use synqlz\anticheat\task\KickTask;

class DetectionHandler{
	use SingletonTrait;

	public const DEFAULT_CONFIG = [
		"enabled" => true,
		"logging" => true,
		"kick" => true,
	];

	private array $config;

	/** @var array<string, Detection> */
	private array $detections = [];

	/** @var array<string, Detection[]> */
	private array $packetToDetection = [];


	public function __construct(){
		$plugin = AntiCheat::getInstance();
		if(!file_exists($plugin->getDataFolder() . "config.yml")){
			$plugin->saveResource("config.yml");
		}

		$this->config = yaml_parse_file($plugin->getDataFolder() . "config.yml");
		$this->defaults();
	}

	private function getConfig(string $detection) : array{
		return $this->config["detections"][$detection] ?? self::DEFAULT_CONFIG;
	}

	private function defaults() : void{
		$this->register(new JetpackDetection($this->getConfig(JetpackDetection::IDENTIFIER)));
		$this->register(new NoPacketDetection($this->getConfig(NoPacketDetection::IDENTIFIER)));
		$this->register(new ReachDetection($this->getConfig(ReachDetection::IDENTIFIER)));
		$this->register(new TimerDetection($this->getConfig(TimerDetection::IDENTIFIER)));
	}

	public function fromPacket(Packet $packet) : array{
		return $this->packetToDetection[$packet->pid()] ?? [];
	}

	public function get(string $id) : ?Detection{
		return $this->detections[$id] ?? null;
	}

	public function register(Detection $detection) : void{
		$this->detections[$detection->getId()] = $detection;

		if($detection->isEnabled()){
			foreach($detection->getPackets() as $packetId){
				$this->packetToDetection[$packetId][] = $detection;
			}

			foreach($detection->getEvents() as $event => $priority){
				AntiCheat::getInstance()->getServer()->getPluginManager()->registerEvent(
					$event,
					\Closure::fromCallable([$detection, "onEvent"]),
					(int) $priority,
					AntiCheat::getInstance(),
				);
			}
		}
	}

	/**
	 * @param Packet[] $packets
	 */
	public function doCheck(Session $session, array $packets, DataPacketSendEvent|DataPacketReceiveEvent $event) : void{
		foreach($packets as $pk){
			foreach($this->fromPacket($pk) as $detection){
				$result = $detection->check($session, $pk, $event);

				if($result->getPoints() > 0){
					if($result->hasFailed()){
						$session->increaseDetectionPoints($detection->getId(), $result->getPoints());
					}else{
						$session->decreaseDetectionPoints($detection->getId(), $result->getPoints());
					}
				}

				if(!$result->hasFailed()){
					continue;
				}

				$pts = $session->getDetectionPoints($detection->getId());
				if($pts >= $detection->getMinimumPointsToLog()){
					$msg = AntiCheat::prefix() . "Failed " . $detection->getId() . " detection check [Points: " . number_format($pts, 2) . "]: " . $result->getMessage();
					$session->saveLog(new DetectionLog(
						$detection,
						$msg,
						$pts,
						microtime(true)
					));

					foreach(AntiCheatCommand::getEnabled() as $username){
						$player = Server::getInstance()->getPlayerExact($username);
						if($player === null){
							continue;
						}

						$playerSession = SessionCache::get($username);
						if($playerSession->getMessageCooldown($detection, $session->getName()) > 0){
							continue;
						}

						$playerSession->setMessageCooldown($detection, $session->getName(), 3);
						$player->sendMessage($msg);
					}
				}

				if($detection->getMinimumpointsToKick() !== -1 && $pts >= $detection->getMinimumpointsToKick()){
					$player = Server::getInstance()->getPlayerExact($session->getName());
					$session->setDetectionPoints($detection->getId(), 0);
					if($player !== null){
                        //We use "An unknown error has occurred" kick message so the player does not know they were kicked for cheating
						KickTask::kick(
							$player,
							"An unknown error has occurred",
							20
						);
					}
				}
			}
		}
	}
}