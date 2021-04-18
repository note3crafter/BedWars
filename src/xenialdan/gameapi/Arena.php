<?php

namespace xenialdan\gameapi;

use pocketmine\entity\Attribute;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\network\mcpe\protocol\GameRulesChangedPacket;
use pocketmine\Player;
use pocketmine\scheduler\Task;
use pocketmine\scheduler\TaskHandler;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use xenialdan\gameapi\libs\xenialdan\apibossbar\DiverseBossBar;
use xenialdan\gameapi\event\UpdateSignsEvent;
use xenialdan\gameapi\event\WinEvent;
use xenialdan\gameapi\gamerule\BoolGameRule;
use xenialdan\gameapi\gamerule\GameRuleList;
use xenialdan\gameapi\task\StartTickerTask;

define('DS', DIRECTORY_SEPARATOR);

class Arena
{
    const IDLE = 0;
    const WAITING = 1;
    const STARTING = 2;
    const INGAME = 3;
    const STOP = 4;
    const SETUP = 5;

    const TIMER_MAX = 30;
    private $owningGame;
    private $timer;
    private static $tasks;
    public $state = self::IDLE;
    private $levelName;
    private $level = null;
    private $teams = [];
    public $bossbar = null;
    public $settings;
    public $forcedStart = false;

    public function __construct(string $levelName, Game $game, DefaultSettings $settings = null)
    {
        $this->owningGame = $game;
        $this->levelName = $levelName;
        try {
            API::$generator->generateLevel($levelName);
            //reset world
            $path1 = $this->owningGame->getDataFolder() . "worlds" . DS;
            @mkdir($path1);

            if (!API::copyr($this->owningGame->getServer()->getDataPath() . "worlds" . DS . $levelName, $path1 . $levelName)) {
                throw new MiniGameException('Could not copy level to plugin..');
            }
            Server::getInstance()->loadLevel($levelName);
            $this->level = Server::getInstance()->getLevelByName($levelName);
            $this->setSettings($settings);
            //Prevents changing the level
            #$this->getLevel()->setAutoSave(false);
        } catch (MiniGameException $exception) {
            Server::getInstance()->getLogger()->logException($exception);
        }
    }

    public function getOwningGame(): Game
    {
        return $this->owningGame;
    }

    public function addTeam(Team $team)
    {
        $this->teams[] = $team;
    }

    public function getLevel()
    {
        return $this->level;
    }

    public function setLevel(Level $level)
    {
        $this->level = $level;
    }

    public function getLevelName()
    {
        return $this->levelName;
    }

    public function inArena($player): bool
    {
        return !is_null($this->getTeamByPlayer($player));
    }

    public function getTeamByPlayer(Player $player): ?Team
    {
        foreach ($this->getTeams() as $team) {
            if ($team->inTeam($player)) return $team;
        }
        return null;
    }

    public function getTeamByColor(string $color): ?Team
    {
        foreach ($this->getTeams() as $team) {
            if ($team->getColor() === $color) return $team;
        }
        return null;
    }

    public function getTeams()
    {
        return $this->teams;
    }

    public function joinTeam(Player $player, string $teamname = "")
    {
        if ($this->getState() === self::SETUP) {
            $player->sendMessage(TextFormat::RED . TextFormat::BOLD . "This arena is currently not available due to setup process");
            return false;
        }
        if (($this->getState() === self::INGAME && count($this->getPlayers()) <= 0) || $this->getState() === self::STOP) {
            $player->sendMessage(TextFormat::RED . TextFormat::BOLD . "This arena did not stop properly");
            $player->sendMessage(TextFormat::RED . TextFormat::BOLD . "An issue occurred, trying to stop the arena. Please try again in a few seconds.");
            API::resetArena($this);//also automatically stops the arena and removes players
            return false;
        }
        if (($this->getState() === self::INGAME) && empty($teamname) && is_null(API::getArenaOfPlayer($player))) {
            $player->sendMessage(TextFormat::RED . TextFormat::BOLD . "This arena is already running");
            return false;
        }
        if (!is_null(($oldteam = $this->getTeamByPlayer($player)))) {
            $oldteam->removePlayer($player);
        }
        $team = $this->getTeamByName($teamname);
        if (empty($teamname)) {
            $count = [];
            foreach ($this->getTeams() as $team) {
                if (count($team->getPlayers()) < $team->getMaxPlayers())
                    $count[$team->getName()] = count($team->getPlayers());
            }
            if (!empty($count)) $team = $this->getTeamByName($teamname = array_keys($count, min($count))[0]);
        }
        if (!$team instanceof Team) {
            $player->sendMessage(TextFormat::RED . TextFormat::BOLD . "Could not join team $teamname, Reason: A team with this name does not exist");
            return false;
        }
        if (count($team->getPlayers()) >= $team->getMaxPlayers()) {
            $player->sendMessage(TextFormat::RED . TextFormat::BOLD . "Could not join team $teamname, Reason: This team is full");
            return false;
        }
        $team->addPlayer($player);
        if ($this->getState() === self::WAITING || $this->getState() === self::STARTING || $this->getState() === self::IDLE) {
            var_dump(__FILE__ . __LINE__, (string)$this->getLevel()->getSafeSpawn());
            $player->teleport(Position::fromObject($this->getLevel()->getSafeSpawn($this->getLevel()->getSpawnLocation()), $this->getLevel()));//TODO check if ->add(0, 1)
            Server::getInstance()->getLogger()->notice($player->getName() . ' added to ' . $teamname);
            $gamename = $this->owningGame->getPrefix();
            if (!isset($this->bossbar)) {
                $this->bossbar = new DiverseBossBar();
            }
            $this->bossbar->addPlayer($player);
            if (count($this->getPlayers()) < $this->getMinPlayers()) Server::getInstance()->broadcastMessage(TextFormat::RED . TextFormat::BOLD . "The game " . $gamename . " needs players!", Server::getInstance()->getDefaultLevel()->getPlayers());
            elseif (count($this->getPlayers()) < $this->getMaxPlayers()) Server::getInstance()->broadcastMessage(TextFormat::DARK_GRAY . TextFormat::BOLD . "The game " . $gamename . " is not full, you can still join it!", Server::getInstance()->getDefaultLevel()->getPlayers());
            $this->bossbar->setPercentage(1)->setTitle($gamename . " " . strval($this->getMinPlayers() - count($this->getPlayers())) . " more players needed");

            $this->setState(self::WAITING);
            $player->setGamemode(Player::ADVENTURE);
        }
        $this->resetPlayer($player);
        $this->owningGame->onPlayerJoinTeam($player);
        $player->sendMessage($team->getColor() . TextFormat::BOLD . "You joined the team " . $team->getName());
        if (($this->getState() === self::WAITING || $this->getState() === self::IDLE || $this->getState() === self::STARTING) && count($this->getPlayers()) >= $this->getMinPlayers()) {
            $this->setState(self::STARTING);
            if (isset(self::$tasks['ticker'])) $this->resetTimer();
            $this->startTimer($this->owningGame);
        }
        return true;
    }

    public function getPlayers()
    {
        $players = [];
        foreach ($this->getTeams() as $team) {
            $players = array_merge($players, $team->getPlayers());
        }
        return $players;
    }

    public function getTeamByName(string $teamname)
    {
        foreach ($this->getTeams() as $team) {
            if ($team->getName() === $teamname) return $team;
        }
        return null;
    }

    public function setTimer(int $timer): void
    {
        $this->timer = $timer;
    }

    public function startTimer(Game $game)
    {
        $this->resetTimer();
        $this->bossbar->showToAll();
        $this->setState(self::STARTING);
        self::$tasks['ticker'] = $game->getScheduler()->scheduleRepeatingTask(new StartTickerTask($game, $this), 20);
    }

    public function sendTimer(Game $game)
    {
        if (!$this->forcedStart && count($this->getPlayers()) < $this->getMinPlayers()) {
            Server::getInstance()->broadcastTitle(TextFormat::DARK_RED . "Too less players", "for " . $game->getPrefix() . ', ' . $this->getMinPlayers() . ' players are needed!');
            $this->resetTimer();
            return;
        }
        $this->bossbar->setTitle('Game ' . str_replace('[', '', str_replace(']', '', $game->getPrefix())) . ' starts in ' . $this->timer . ' seconds')->setPercentage($this->timer / self::TIMER_MAX);
        $this->timer--;
        if ($this->timer < 0) {
            $this->resetTimer();
            $this->startArena();
        }
    }

    public function resetTimer()
    {
        $this->bossbar->hideFromAll();
        $this->forcedStart = false;
        $this->setState(self::WAITING);
        if (isset(self::$tasks['ticker'])) $this->getOwningGame()->getScheduler()->cancelTask(self::$tasks['ticker']->getTaskId());
        unset(self::$tasks['ticker']);
        $this->timer = self::TIMER_MAX;
    }

    public function startArena()
    {
        $this->getLevel()->setTime($this->getSettings()->time);
        if ($this->getSettings()->stopTime) {
            $this->getLevel()->stopTime();
            $pk = new GameRulesChangedPacket();
            $gamerulelist = new GameRuleList();
            $gamerulelist->setRule(new BoolGameRule(GameRuleList::DODAYLIGHTCYCLE, !$this->getSettings()->stopTime));
            $pk->gameRules = $gamerulelist->getRules();
            $this->getLevel()->broadcastGlobalPacket($pk);
        } else {
            $this->getLevel()->startTime();
            $pk = new GameRulesChangedPacket();
            $gamerulelist = new GameRuleList();
            $gamerulelist->setRule(new BoolGameRule(GameRuleList::DODAYLIGHTCYCLE, !$this->getSettings()->stopTime));
            $pk->gameRules = $gamerulelist->getRules();
            $this->getLevel()->broadcastGlobalPacket($pk);
        }
        foreach ($this->getTeams() as $team) {
            $team->resetInitialPlayers();
            $team->updateInitialPlayers();
            foreach ($team->getPlayers() as $player) {
                $this->resetPlayer($player);
            }
        }
        $this->setState(self::INGAME);
        $this->getOwningGame()->startArena($this);
    }

    public function stopArena()
    {
        foreach ($this->getPlayers() as $player) {
            try {
                $this->removePlayer($player);
            } catch (\ReflectionException $e) {
            }
        }
        $this->setState(self::STOP);
        foreach ($this->getTeams() as $team) {
            $team->resetInitialPlayers();
        }
        $this->getOwningGame()->stopArena($this);
    }

    public function setState(int $state)
    {
        $this->state = $state;
        $ev = new UpdateSignsEvent($this->getOwningGame(), [$this->getOwningGame()->getServer()->getDefaultLevel()], $this);
        try {
            $ev->call();
        } catch (\ReflectionException $e) {
            Server::getInstance()->getLogger()->logException($e);
        }
    }

    public function getState(): int
    {
        return $this->state;
    }

    public function resetPlayer(Player $player)
    {
        $player->setNameTag($player->getDisplayName());
        $player->setHealth($player->getMaxHealth());
        $player->setFood($player->getMaxFood());
        $player->setSaturation($player->getAttributeMap()->getAttribute(Attribute::SATURATION)->getMaxValue());
        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();
        $player->getEnderChestInventory()->clearAll();
        $player->getCursorInventory()->clearAll();
        $player->setGamemode($this->settings->gamemode);
        $player->setAllowFlight($this->settings->allowFlight);
        $player->removeAllEffects();
        $player->setDataFlag(Player::DATA_FLAGS, Player::DATA_FLAG_IMMOBILE, false);
        $player->sendSettings();
    }

    public function removePlayer(Player $player)
    {
        $team = $this->getTeamByPlayer($player);
        var_dump($team);
        if ($team instanceof Team) {
            $team->removePlayer($player);
        }
        $player->setSpawn(Server::getInstance()->getDefaultLevel()->getSafeSpawn());
        Server::getInstance()->getLogger()->notice($player->getName() . ' removed');
        if ($player->isOnline()) {
            if (isset($this->bossbar)) $this->bossbar->removePlayer($player);
            $this->resetPlayer($player);
            $player->setGamemode(Server::getInstance()->getDefaultGamemode());
            $player->teleport(Server::getInstance()->getDefaultLevel()->getSafeSpawn());
            $player->sendSettings();
        }
        $aliveTeams = array_filter($this->getTeams(), function (Team $team): bool {
            return count($team->getPlayers()) > 0;
        });
        $aliveTeamsCount = count($aliveTeams);
        if (($aliveTeamsCount === 1 && count($this->teams) > 1) || (count($this->teams) === 1 && count($team->getPlayers()) === 1) || ($this->getState() !== self::STOP && count($this->getPlayers()) === 0)) {
            if (count($this->getPlayers()) !== 0) {
                if (count($this->teams) > 1) {
                    $winner = array_values($aliveTeams)[0];
                } else {
                    $winner = array_values($team->getPlayers())[0];
                }
                $ev = new WinEvent($this->getOwningGame(), $this, $winner);
                $ev->call();
                $ev->announce();
            }
            if ($this->getState() === self::INGAME && count($this->getPlayers()) === 0) $this->setState(self::IDLE);
            if ($this->getState() === self::INGAME) $this->getOwningGame()->getScheduler()->scheduleDelayedTask(new class($this) extends Task
            {
                private $arena;

                public function __construct(Arena $arena)
                {
                    $this->arena = $arena;
                }

                public function onRun(int $currentTick)
                {
                    API::resetArena($this->arena);
                }
            }, 5 * 20);
        }
    }

    public function getMaxPlayers()
    {
        return array_sum(array_map(function (Team $team) {
            return $team->getMaxPlayers();
        }, $this->getTeams()));
    }

    public function getMinPlayers()
    {
        return array_sum(array_map(function (Team $team) {
            return $team->getMinPlayers();
        }, $this->getTeams()));
    }

    public function getSettings()
    {
        return $this->settings;
    }

    public function setSettings(?DefaultSettings $settings)
    {
        $this->settings = $settings;
    }
}
