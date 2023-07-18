<?php

declare(strict_types=1);

namespace synqlz\anticheat\detection\defaults;

use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\network\mcpe\protocol\AddPlayerPacket;
use pocketmine\network\mcpe\protocol\MoveActorAbsolutePacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\network\mcpe\protocol\NetworkStackLatencyPacket;
use pocketmine\network\mcpe\protocol\Packet;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\SetActorMotionPacket;
use synqlz\anticheat\detection\Detection;
use synqlz\anticheat\detection\result\DetectionCheckResult;
use synqlz\anticheat\session\Session;

class NoPacketDetection extends Detection{
	const IDENTIFIER = "no-packet";

	public function getPackets() : array{
        //Player sends this packet 20 times per second, so we can use this to detect NoPacket
        //This could also false flag if they are lagging
		return [PlayerAuthInputPacket::NETWORK_ID];
	}

	public static function onCheck(Session $session, Packet $packet, DataPacketSendEvent|DataPacketReceiveEvent $event) : DetectionCheckResult{
		$last = $session->getTemporaryData(self::IDENTIFIER)[0] ?? microtime(true);
		if($packet instanceof PlayerAuthInputPacket){
			$session->setTemporaryData(self::IDENTIFIER, [microtime(true)]);
		}

		$elapsed = microtime(true) - $last;

		if($elapsed > 3){
			return DetectionCheckResult::failed(
				$session,
				"{$session->getName()} could be using NoPacket or Lagging (Packet Difference: " . number_format($elapsed, 2) . " seconds).",
				0.02
			);
		}


		return DetectionCheckResult::passed($session, 0.05);
	}
}