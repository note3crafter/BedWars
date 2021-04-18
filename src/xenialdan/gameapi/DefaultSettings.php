<?php


namespace xenialdan\gameapi;

use pocketmine\level\Level;
use pocketmine\Player;
use pocketmine\utils\Config;

class DefaultSettings extends Config
{
    public $immutableWorld = true;
    public $gamemode = Player::SURVIVAL;
    public $startNoWalk = true;
    public $stopTime = true;
    public $time = Level::TIME_DAY;
    public $noBed = true;
    public $noBuild = false;
    public $noBreak = false;
    public $noPickup = false;
    public $noEntityDrops = true;
    public $noDropItem = true;
    public $noBlockDrops = true;
    public $keepInventory = false;
    public $clearInventory = false;
    public $noArrowPickup = false;
    public $noDamageEntities = true;
    public $noDamageTeam = true;
    public $noDamageEnemies = false;
    public $noEnvironmentDamage = false;
    public $noFallDamage = false;
    public $noExplosionDamage = false;
    public $noDrowningDamage = false;
    public $noInventoryEditing = false;
    public $allowFlight = false;
    public $breakBlockIds = [];
    public $placeBlockIds = [];
    public $teams = [];

    public function __construct(string $path)
    {
        parent::__construct($path, Config::JSON, array_filter((array)$this, function ($k): bool {
            return strpos($k, Config::class) === false;
        }, ARRAY_FILTER_USE_KEY));
        foreach ($this->getAll(true) as $key) {
            if (isset($this->$key)) $this->$key = $this->get($key);
        }
    }

    public function __set($k, $v)
    {
        $this->$k = $v;
        $this->set($k, $v);
    }

    public function __get($k)
    {
        return $this->get($k);
    }

    public function save(): bool
    {
        $this->setAll(
            array_filter((array)$this, function ($k): bool {
                return strpos($k, Config::class) === false;
            }, ARRAY_FILTER_USE_KEY)
        );
        return parent::save();
    }
}