<?php

declare(strict_types=1);

namespace synqlz\anticheat\detection\defaults;

use pocketmine\entity\effect\VanillaEffects;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\network\mcpe\protocol\Packet;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use synqlz\anticheat\detection\Detection;
use synqlz\anticheat\detection\result\DetectionCheckResult;
use synqlz\anticheat\session\Session;

class JetpackDetection extends Detection{
	public const MAXIMUM_SPEED = 1;
	public const MAXIMUM_SPEED_EFFECT = 5;

	public const IDENTIFIER = "jetpack";

	public function getPackets() : array{
		return [PlayerAuthInputPacket::NETWORK_ID, MovePlayerPacket::NETWORK_ID];
	}

	public static function onCheck(Session $session, Packet $packet, DataPacketReceiveEvent|DataPacketSendEvent $event) : DetectionCheckResult{
		$player = $session->getPlayer();
		if($player === null){
			return DetectionCheckResult::passed($session);
		}

		$data = $session->getTemporaryData(self::IDENTIFIER);
		if($packet instanceof MovePlayerPacket && $packet->actorRuntimeId === $player->getId() && $packet->mode === MovePlayerPacket::MODE_TELEPORT){
			$data["teleported"] = $session->getLatencyHandler()->getCurrentTick();
		}

		if($packet instanceof PlayerAuthInputPacket && !$player->isFlying()){
			$pos = $packet->getPosition();
			$current = $session->getLatencyHandler()->getPlayerPosition() ?? $pos;

			$yDiff = $packet->getPosition()->y - $current->y;
			$distance = $pos->distance($current);

			//Check if the player is teleporting to avoid false positives
			if(isset($data["teleported"]) && $packet->getTick() - $data["teleported"] <= 5){
				return DetectionCheckResult::passed($session);
			}else{
				unset($data["teleported"]);
			}

			if ($player->getEffects()->has(VanillaEffects::SPEED()) && $player->getEffects()->get(VanillaEffects::SPEED())->getEffectLevel() > self::MAXIMUM_SPEED_EFFECT) {
				return DetectionCheckResult::passed($session);
			}

			if($yDiff > 0 && $distance > self::MAXIMUM_SPEED){
				if(!isset($data["jetpackTick"])){
					$data["jetpackTick"] = $packet->getTick();
				}

				if(($packet->getTick() - $data["jetpackTick"]) % 20 === 0){
					$result = DetectionCheckResult::failed($session,
						"{$player->getName()} could be using jetpack.",
						1
					);
				}
			}else{
				unset($data["jetpackTick"]);

				if(!isset($data["nonJetpackTick"])){
					$data["nonJetpackTick"] = $packet->getTick();
				}

				if(($packet->getTick() - $data["nonJetpackTick"]) % 20 === 0){
					$result = DetectionCheckResult::passed($session, 0.1);
				}
			}

		}

		$session->setTemporaryData(self::IDENTIFIER, $data);
		return $result ?? DetectionCheckResult::passed($session);
	}
}