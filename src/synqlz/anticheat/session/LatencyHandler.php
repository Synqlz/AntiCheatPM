<?php

declare(strict_types=1);

namespace synqlz\anticheat\session;

use pocketmine\entity\Entity;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\MoveActorAbsolutePacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\network\mcpe\protocol\NetworkStackLatencyPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\SetActorMotionPacket;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemOnEntityTransactionData;
use pocketmine\player\Player;

class LatencyHandler{
	/**
	 * @var null|Entity This is the entity
	 * that the player is last attacked.
	 */
	private ?Entity $entity = null;
	/**
	 * @var array<int, Vector3> A list of positions
	 * rceived from the entity the player last attacked.
	 */
	private array $entityPositions = [];

	/**
	 * @var Vector3|null The current up-to-date
	 * position received by their PlayerAuthInputPacket.
	 */
	private ?Vector3 $playerPosition = null;
	/**
	 * @var Vector3|null The current up-to-date
	 * motion received by the PlayerAuthInputPacket.
	 */
	private ?Vector3 $playerMotion = null;
	/**
	 * @var int The current up-to-date tick
	 * received by the PlayerAuthInputPacket.
	 */
	private int $currentTick = 0;

	/** @var array<int, SetActorMotionPacket> */
	private array $pendingMotions = [];
	/** @var array<int, MoveActorAbsolutePacket|MovePlayerPacket> */
	private array $pendingMovements = [];
	/** @var array<int, NetworkStackLatencyPacket> */
	private array $pendingRequests = [];

	public function __construct(private Player $player){
	}

	public function getEntity() : ?Entity{
		return $this->entity;
	}

	public function getEntityPositions() : array{
		return $this->entityPositions;
	}

	public function getPlayerPosition() : ?Vector3{
		return $this->playerPosition;
	}

	public function getPlayerMotion() : ?Vector3{
		return $this->playerMotion;
	}

	public function getCurrentTick() : int{
		return $this->currentTick;
	}

	protected function sendNetworkStackLatencyPacket() : int{
		//Time stamp received always ends with 0
		$this->player->getNetworkSession()->sendDataPacket($pk = NetworkStackLatencyPacket::request(mt_rand(1, 10000) * 1000));
		$this->pendingRequests[$pk->timestamp] = [$pk, time()];
		return $pk->timestamp;
	}

	public function handleNetworkStackLatencyPacket(NetworkStackLatencyPacket $packet) : void{
		if(isset($this->pendingMotions[$packet->timestamp])){
			$this->playerMotion = $this->pendingMotions[$packet->timestamp]->motion;
			unset($this->pendingMotions[$packet->timestamp]);
		}

		if(isset($this->pendingMovements[$packet->timestamp])){
			$this->entityPositions[] = [$this->currentTick, $this->pendingMovements[$packet->timestamp]->position];
			unset($this->pendingMovements[$packet->timestamp]);
		}

		if(isset($this->pendingRequests[$packet->timestamp])){
			unset($this->pendingRequests[$packet->timestamp]);
		}
	}

	public function handleInventoryTransactionPacket(InventoryTransactionPacket $packet) : void{
		if($packet->trData instanceof UseItemOnEntityTransactionData && $packet->trData->getActionType() === UseItemOnEntityTransactionData::ACTION_ATTACK){
			$entity = $this->player->getWorld()->getEntity($packet->trData->getActorRuntimeId());
			if($entity === null || $entity === $this->entity){
				return;
			}

			$this->entity = $entity;
			$this->entityPositions = [];
			$this->pendingMovements = [];
		}
	}

	public function handlePlayerAuthInputPacket(PlayerAuthInputPacket $packet) : void{
		$this->playerPosition = $packet->getPosition();
		$this->currentTick = $packet->getTick();

		foreach($this->entityPositions as $i => [$tick, $position]){
			if($this->currentTick - $tick > 10){
				unset($this->entityPositions[$i]);
			}
		}
	}

	public function handleSetActorMotion(SetActorMotionPacket $packet) : void{
		if($packet->actorRuntimeId === $this->player->getId()){
			$this->pendingMotions[$this->sendNetworkStackLatencyPacket()] = $packet;
		}
	}

	public function handleMoveActorAbsolute(MoveActorAbsolutePacket $packet) : void{
		if($this->entity !== null && $packet->actorRuntimeId === $this->entity->getId()){
			$this->pendingMovements[$this->sendNetworkStackLatencyPacket()] = $packet;
		}
	}

	public function handleMovePlayer(MovePlayerPacket $packet) : void{
		if($this->entity !== null && $packet->actorRuntimeId === $this->entity->getId()){
			$this->pendingMovements[$this->sendNetworkStackLatencyPacket()] = $packet;
		}
	}
}