<?php

namespace xenialdan\BedWars\libs\xenialdan\gameapi\event;

use pocketmine\event\plugin\PluginEvent;
use pocketmine\Player;
use pocketmine\plugin\Plugin;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use xenialdan\BedWars\libs\xenialdan\gameapi\API;
use xenialdan\BedWars\libs\xenialdan\gameapi\Arena;
use xenialdan\BedWars\libs\xenialdan\gameapi\Team;

class WinEvent extends PluginEvent{
	public static $handlerList = null;
	/** @var Arena */
	private $arena;
	private $winner;

	/**
	 * TeamWinEvent constructor.
	 * @param Plugin $plugin
	 * @param Arena $arena
	 * @param Team|Player $winner
	 */
	public function __construct(Plugin $plugin, Arena $arena, $winner){
		parent::__construct($plugin);
		$this->arena = $arena;
		$this->winner = $winner;
	}

	public function announce(){
		$prefix = $this->winner instanceof Player ? "Spieler " : "Team ";
        Server::getInstance()->broadcastTitle(TextFormat::GREEN . $prefix . $this->winner->getName(), TextFormat::GREEN . ' hat das Spiel gewonnen ' . $this->arena->getOwningGame()->getPrefix() . '!', -1, -1, -1, Server::getInstance()->getDefaultLevel()->getPlayers());
        Server::getInstance()->broadcastMessage(TextFormat::GREEN . $prefix . $this->winner->getName() . TextFormat::GREEN . ' hat das Spiel gewonnen ' . $this->arena->getOwningGame()->getPrefix() . '!', Server::getInstance()->getDefaultLevel()->getPlayers());
	}

	public function getGame(){
		return API::getGame($this->getPlugin()->getName());
	}

	/**
	 * @return Player[]
	 */
	public function getWinningPlayers(){
		if($this->winner instanceof Player) return [$this->winner];
		else return $this->winner->getInitialPlayers();
	}
}