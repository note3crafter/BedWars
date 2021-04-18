<?php

namespace xenialdan\gameapi\event;

use pocketmine\event\plugin\PluginEvent;
use pocketmine\plugin\Plugin;

class StartGameEvent extends PluginEvent{
	public static $handlerList = null;

	private $game;

	public function __construct(Plugin $plugin, Plugin $game){
		parent::__construct($plugin);
		$this->game = $game;
	}

	public function getPlugin(): Plugin{
		return $this->game;
	}

	public function getGame(){
		return $this->game;
	}

	public function getName(){
		$this->getGame()->getName();
	}
}