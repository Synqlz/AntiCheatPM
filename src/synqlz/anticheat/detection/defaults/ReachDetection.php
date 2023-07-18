<?php

declare(strict_types=1);

namespace synqlz\anticheat\detection\defaults;

use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\Packet;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemOnEntityTransactionData;
use pocketmine\player\Player;
use synqlz\anticheat\detection\Detection;
use synqlz\anticheat\detection\result\DetectionCheckResult;
use synqlz\anticheat\session\Session;

class ReachDetection extends Detection{
	public const IDENTIFIER = "reach";

	public function getPackets() : array{
		return [InventoryTransactionPacket::NETWORK_ID];
	}

	public static function onCheck(Session $session, Packet $packet, DataPacketSendEvent|DataPacketReceiveEvent $event) : DetectionCheckResult{
		assert($packet instanceof InventoryTransactionPacket);
		if(!$packet->trData instanceof UseItemOnEntityTransactionData || $packet->trData->getActionType() !== UseItemOnEntityTransactionData::ACTION_ATTACK){
			return DetectionCheckResult::passed($session);
		}

		$player = $session->getPlayer();
		$entityId = $packet->trData->getActorRuntimeId();
		$entity = $player->getWorld()->getEntity($entityId);
		if(!$entity instanceof Player){
			return DetectionCheckResult::passed($session);
		}

		$distance = null;

		if($session->getLatencyHandler()->getPlayerPosition() === null){
			return DetectionCheckResult::passed($session);
		}

		$playerPos = $session->getLatencyHandler()->getPlayerPosition()->add(0, $player->getEyeHeight(), 0);
		foreach($session->getLatencyHandler()->getEntityPositions() as [$tick, $pos]){
			$bb = new AxisAlignedBB($pos->x - 0.3, $pos->y, $pos->z - 0.3, $pos->x + 0.3, $pos->y + 1.8, $pos->z + 0.3);
			$dist = self::distance($bb, $playerPos);
			if($distance === null || $dist < $distance){
				$distance = $dist;
			}
		}

		//Players even when using cheats cannot hit from more than 7 blocks away.
		if($distance !== null && $distance >= 3.1 && $distance < 7 && ($player->isSurvival() || $player->isAdventure())){
			return DetectionCheckResult::failed(
				$session,
				"{$player->getName()} has been hitting from a galaxy far, far away (Distance: " . number_format($distance, 2) . ").",
				0.3
			);
		}

		return DetectionCheckResult::passed($session, 0.01);
	}

	public static function distance(AxisAlignedBB $bb, Vector3 $pos) : float{
		$distX = max(0, $bb->minX - $pos->x, $pos->x - $bb->maxX);
		$distY = max(0, $bb->minY - $pos->y, $pos->y - $bb->maxY);
		$distZ = max(0, $bb->minZ - $pos->z, $pos->z - $bb->maxZ);

		return sqrt(($distX ** 2) + ($distY ** 2) + ($distZ ** 2));
	}
}