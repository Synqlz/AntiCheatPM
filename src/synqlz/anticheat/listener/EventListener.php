<?php

declare(strict_types=1);

namespace synqlz\anticheat\listener;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\MoveActorAbsolutePacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\network\mcpe\protocol\NetworkStackLatencyPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\SetActorMotionPacket;
use synqlz\anticheat\detection\DetectionHandler;
use synqlz\anticheat\session\SessionCache;

class EventListener implements Listener{
	/**
	 * @priority NORMAL
	 */
	public function onDataPacketSend(DataPacketSendEvent $event) : void{
		$packets = $event->getPackets();
		foreach($event->getTargets() as $target){
			$player = $target->getPlayer();
			if($player === null || !$player->isConnected()){
				continue;
			}

			$session = SessionCache::get($player);
			DetectionHandler::getInstance()->doCheck($session, $packets, $event);

			foreach($packets as $packet){
				switch($packet::class){
					case SetActorMotionPacket::class:
						$session->getLatencyHandler()?->handleSetActorMotion($packet);
						break;
					case MovePlayerPacket::class:
						$session->getLatencyHandler()?->handleMovePlayer($packet);
						break;
					case MoveActorAbsolutePacket::class:
						$session->getLatencyHandler()?->handleMoveActorAbsolute($packet);
						break;
				}
			}
		}
	}

	/**
	 * @priority NORMAL
	 */
	public function onDataPacketReceive(DataPacketReceiveEvent $event) : void{
		$pk = $event->getPacket();
		$player = $event->getOrigin()->getPlayer();
		if($player === null || !$player->isConnected()){
			return;
		}

		$session = SessionCache::get($player);
		DetectionHandler::getInstance()->doCheck($session, [$pk], $event);

		switch($pk::class){
			case NetworkStackLatencyPacket::class:
				$session->getLatencyHandler()?->handleNetworkStackLatencyPacket($pk);
				break;
			case InventoryTransactionPacket::class:
				$session->getLatencyHandler()?->handleInventoryTransactionPacket($pk);
				break;
			case PlayerAuthInputPacket::class:
				$session->getLatencyHandler()?->handlePlayerAuthInputPacket($pk);
				break;
		}
	}

	/**
	 * @priority NORMAL
	 */
	public function onQuit(PlayerQuitEvent $event) : void{
		SessionCache::destroy($event->getPlayer());
	}
}