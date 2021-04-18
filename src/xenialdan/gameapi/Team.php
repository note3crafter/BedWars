<?php

namespace xenialdan\gameapi;

use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

class Team{

	private $players = [];
	private $initialplayers = [];
	private $max = 1;
	private $min = 1;
	private $name = "unnamed";
	private $color = TextFormat::RESET;
	private $spawn;
	public function __construct(string $color = TextFormat::RESET, string $name = "", array $players = []){
		$this->setColor($color);
		$this->setName($name);
		foreach ($players as $player){
			$this->addPlayer($player);
		}
	}

	public function addPlayer(Player $player){
		$this->players[$player->getLowerCaseName()] = $player;
	}

	public function removePlayer(Player $player){
		unset($this->players[$player->getLowerCaseName()]);
	}

	public function inTeam(Player ...$players){
		foreach ($players as $player){
			if (!isset($this->players[$player->getLowerCaseName()])) return false;
		}
		return true;
	}

	public function __toString(){
		return "Team " . $this->getColor() . $this->getName() . TextFormat::RESET . ", players: " . (implode(", ", array_keys($this->getPlayers())));
	}

	public function getColor(){
		return $this->color;
	}

	public function setColor(string $color){
		$this->color = $color;
	}

	public function getName(){
		return $this->name;
	}

	public function setName(string $name){
		$this->name = $name;
	}

	public function getPlayers(){
		return $this->players;
	}

	public function getInitialPlayers(){
		return $this->initialplayers;
	}

	public function resetInitialPlayers(){
		$this->initialplayers = [];
	}

	public function updateInitialPlayers(){
		$this->initialplayers = $this->getPlayers();
	}

	public function setMaxPlayers(int $max){
		$this->max = $max;
	}

	public function getMaxPlayers(){
		return $this->max;
	}

	public function setMinPlayers(int $min){
		$this->min = $min;
	}

	public function getMinPlayers(){
		return $this->min;
	}

	public function setSpawn(Vector3 $vector3){
		$this->spawn = $vector3;
	}

	public function getSpawn(){
		return $this->spawn??new Vector3();
	}
}