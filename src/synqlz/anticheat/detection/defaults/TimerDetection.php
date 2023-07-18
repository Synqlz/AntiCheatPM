<?php

declare(strict_types=1);

namespace synqlz\anticheat\detection\defaults;

use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\protocol\Packet;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use synqlz\anticheat\detection\Detection;
use synqlz\anticheat\detection\result\DetectionCheckResult;
use synqlz\anticheat\session\Session;

class TimerDetection extends Detection{
	public const IDENTIFIER = "timer";

	public function getPackets() : array{
		return [PlayerAuthInputPacket::NETWORK_ID];
	}

	public static function onCheck(Session $session, Packet $packet, DataPacketSendEvent|DataPacketReceiveEvent $event) : DetectionCheckResult{
		assert($packet instanceof PlayerAuthInputPacket);

		$player = $session->getPlayer();
		if($player === null || $packet->getTick() <= $session->getLatencyHandler()->getCurrentTick()){
			return DetectionCheckResult::passed($session);
		}

		$data = $session->getTemporaryData(self::IDENTIFIER);

		$time = $data["time"] ?? 0;
		/* @var $ticks int This is the amount of ticks the player has sent this seconds. */
		$ticks = $data["ticks"] ?? 0;
		/* @var $timerSeconds int this is that amount of time that the player has been sending packets with a tick higher than 20TPS */
		$timerSeconds = $data["timer_seconds"] ?? 0;

		if(microtime(true) - $time > 1){
			if($ticks > 20){
				$timerSeconds++;

				//Sometimes, the client will naturally send more than 20 packets per second, so we need to account for that.
				if($timerSeconds % 10 === 0){
					$result = DetectionCheckResult::failed($session,
						"{$player->getName()} is likely to be using timer (TPS: $ticks) (Seconds: $timerSeconds).",
						0.5
					);
				}
			}else{
				$timerSeconds = 0;
				$result = DetectionCheckResult::passed($session, 0.05);
			}

			$ticks = 0;
			$time = microtime(true);
		}

		$session->setTemporaryData(self::IDENTIFIER, [
			"time" => $time,
			"ticks" => ++$ticks,
			"timer_seconds" => $timerSeconds
		]);

		return $result ?? DetectionCheckResult::passed($session);
	}
}