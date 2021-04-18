<?php

namespace xenialdan\BedWars\task;

use pocketmine\scheduler\Task;
use xenialdan\BedWars\Loader;
use xenialdan\gameapi\Arena;

class SpawnItemsTask extends Task
{
    private $arena;

    public function __construct(Arena $arena)
    {
        $this->arena = $arena;
    }

    public function onRun(int $currentTick)
    {
        if ($this->arena->getState() === Arena::INGAME) {
            if ($currentTick % 50 === 0) Loader::getInstance()->spawnBronze($this->arena);
            if ($currentTick % 250 === 0) Loader::getInstance()->spawnSilver($this->arena);
            if ($currentTick % 750 === 0) Loader::getInstance()->spawnGold($this->arena);
        } else {
            $this->getHandler()->cancel();
        }
    }
}
