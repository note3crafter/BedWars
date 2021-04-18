<?php

namespace xenialdan\gameapi;

use pocketmine\entity\Entity;
use pocketmine\item\Item;
use pocketmine\level\format\io\LevelProvider;
use pocketmine\level\format\io\LevelProviderManager;
use pocketmine\level\Level;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\Player;
use pocketmine\plugin\Plugin;
use pocketmine\Server;
use pocketmine\utils\Color;
use pocketmine\utils\TextFormat;
use xenialdan\gameapi\commands\GamesCommand;
use xenialdan\gameapi\commands\GameStatusCommand;
use xenialdan\gameapi\event\DefaultSettingsListener;
use xenialdan\gameapi\event\RegisterGameEvent;
use xenialdan\gameapi\event\StopGameEvent;
use xenialdan\gameapi\task\ArenaAsyncCopyTask;

class API
{
    private static $games;
    public static $generator;

    public static function getAllWorlds(): array
    {
        $worldNames = [];
        $glob = glob(Server::getInstance()->getDataPath() . "worlds/*", GLOB_ONLYDIR);
        if ($glob === false) return $worldNames;
        foreach ($glob as $path) {
            $path .= DIRECTORY_SEPARATOR;
            $provider = LevelProviderManager::getProvider($path);
            if ($provider !== \null) {
                $c = (new $provider($path));
                $worldNames[] = $c->getName();
            }
        }
        sort($worldNames);
        return $worldNames;
    }

    public static function stopAll()
    {
        $server = Server::getInstance();
        foreach ($server->getPluginManager()->getPlugins() as $game) {
            self::stop($game);
        }
        DefaultSettingsListener::unregister();
        $server->broadcastMessage(TextFormat::GREEN . "Stopped all games.");
    }

    public static function stop($plugin)
    {
        $server = Server::getInstance();
        if (is_string($plugin)) {
            $plugin = $server->getPluginManager()->getPlugin($plugin);
        }
        if (!$plugin instanceof Game) {
            $server->broadcastMessage(TextFormat::RED . "There is no such plugin/minigame");
            return false;
        }
        $ev = new StopGameEvent($plugin);
        $ev->call();
        foreach ($plugin->getArenas() as $arena) {
            $arena->stopArena();
        }
        if (DefaultSettingsListener::getRegistrant() === $plugin) {
            DefaultSettingsListener::unregister();
            foreach (self::getGames() as $otherGame) {
                if (!is_null($otherGame) && $otherGame->isEnabled()) {
                    if (!DefaultSettingsListener::isRegistered())
                        DefaultSettingsListener::register($otherGame);
                }
            }
        }
        $server->broadcastMessage(TextFormat::GREEN . "Stopped " . ($ev->getName() ?? "nameless game"));
        return true;
    }

    public static function resetArena(Arena $arena)
    {
        $level = $arena->getLevel();
        $levelname = $arena->getLevelName();
        $server = Server::getInstance();

        if ($arena->getState() !== Arena::STOP) $arena->stopArena();

        if ($server->isLevelLoaded($levelname)) {
            if (method_exists($arena->getOwningGame(), "removeEntityOnReset"))
                foreach (array_filter($level->getEntities(), function (Entity $entity) use ($arena) {
                    return $arena->getOwningGame()->removeEntityOnArenaReset($entity);
                }) as $entity) {
                    $level->removeEntity($entity);
                }
            $server->getLogger()->notice('Level ' . $levelname . ($server->unloadLevel($server->getLevelByName($levelname)) ? ' successfully' : ' NOT') . ' unloaded!');
            $path1 = $arena->getOwningGame()->getDataFolder();

            $server->getAsyncPool()->submitTask(new ArenaAsyncCopyTask($path1, $server->getDataPath(), $levelname, $arena->getOwningGame()->getName()));
        }
    }

    public static function copyr($source, $dest)
    {
        // Check for symlinks
        if (is_link($source)) {
            return symlink(readlink($source), $dest);
        }

        // Simple copy for a file
        if (is_file($source)) {
            return copy($source, $dest);
        }

        // Make destination directory
        if (!is_dir($dest)) {
            @mkdir($dest, 0777, true);
        }

        // Loop through the folder
        $dir = dir($source);
        while (false !== $entry = $dir->read()) {
            // Skip pointers
            if ($entry == '.' || $entry == '..') {
                continue;
            }

            // Deep copy directories
            self::copyr("$source/$entry", "$dest/$entry");
        }

        // Clean up
        $dir->close();
        return true;
    }

    public static function getTeamOfPlayer(Player $player): ?Team
    {
        $arena = self::getArenaOfPlayer($player);
        if (is_null($arena)) return null;
        return $arena->getTeamByPlayer($player);
    }

    public static function getTeamByColor(Game $game, Level $level, string $color): ?Team
    {
        $arena = self::getArenaByLevel($game, $level);
        if (is_null($arena)) return null;
        return $arena->getTeamByColor($color);
    }

    public static function getArenaOfPlayer(Player $player): ?Arena
    {
        foreach (self::getGames() as $game) {
            #if (!self::isPlaying($player, $game)) continue;
            foreach ($game->getArenas() as $arena) {
                if ($arena->inArena($player)) return $arena;
            }
        }
        return null;
    }

    public static function isPlaying(Player $gamer, ?Plugin $game = null)
    {
        return /*!is_null(self::getTeamOfPlayer($gamer)) && */
            !is_null($arena = self::getArenaByLevel($game, $gamer->getLevel())) && $arena->inArena($gamer);
    }

    public static function registerGame(Game $game)
    {
        //game command
        if ($game->getServer()->getCommandMap()->register("gameapi", new GamesCommand()))
            $game->getServer()->getLogger()->notice('Registered /games command');
        if ($game->getServer()->getCommandMap()->register("gameapi", new GameStatusCommand()))
            $game->getServer()->getLogger()->notice('Registered /gamestatus command');
        //Generic handler for the DefaultSettings
        if (!DefaultSettingsListener::isRegistered())
            DefaultSettingsListener::register($game);
        try {
            self::$generator = new GeneratorGameVoid();
        } catch (\InvalidArgumentException $e) {
        };
        self::$games[$game->getName()] = $game;
        $ev = new RegisterGameEvent($game);
        $ev->call();
    }

    public static function getGames()
    {
        return self::$games;
    }

    public static function getGame(string $name)
    {
        return self::$games[$name] ?? null;
    }

    public static function getArenas(Plugin $game)
    {
        return $game->getArenas();
    }

    public static function isArena(?Level $level)
    {
        if (is_null($level)) return false;
        return self::getArenaByLevel(null, $level) instanceof Arena;
    }

    public static function isArenaOf(?Plugin $game, ?Level $level)
    {
        if (is_null($level)) return false;
        return ($arena = self::getArenaByLevel($game, $level)) instanceof Arena && $arena->getOwningGame() === $game;
    }

    public static function getArenaByLevel(?Plugin $game, Level $level): ?Arena
    {
        if (is_null($game))
            foreach (API::getGames() as $game)//Else all arenas check
                foreach ($game->getArenas() as $arena) {
                    if ($arena->getLevel()->getName() === $level->getName()) return $arena;
                }
        foreach ($game->getArenas() as $arena) {
            if ($arena->getLevel()->getName() === $level->getName()) return $arena;
        }
        return null;
    }

    public static function getArenaByLevelName(?Plugin $game, string $levelname): ?Arena
    {
        $level = Server::getInstance()->getLevelByName($levelname);
        if (is_null($level)) return null;
        return self::getArenaByLevel($game, $level);
    }

    public static function colorFromTextFormat($color): Color
    {
        [$r, $g, $b] = str_split(ltrim(str_replace('>', '', str_replace('<span style=color:#', '', TextFormat::toHTML($color))), '#'));
        return new Color(...array_map('hexdec', [$r . $r, $g . $g, $b . $b]));
    }

    public static function getMetaByColor(string $color)
    {
        switch ($color) {
            case TextFormat::BLACK:
                return 15;
            case TextFormat::DARK_BLUE:
                return 11;
            case TextFormat::DARK_GREEN :
                return 13;
            case TextFormat::DARK_AQUA :
            case TextFormat::AQUA :
                return 9;
            case TextFormat::DARK_RED :
            case TextFormat::RED :
                return 14;
            case TextFormat::DARK_PURPLE :
                return 10;
            case TextFormat::GOLD :
                return 1;
            case TextFormat::GRAY :
                return 8;
            case TextFormat::DARK_GRAY :
                return 7;
            case TextFormat::BLUE :
                return 3;
            case TextFormat::GREEN :
                return 5;
            case TextFormat::LIGHT_PURPLE :
                return 2;
            case TextFormat::YELLOW :
                return 4;
            case TextFormat::WHITE :
                return 0;
            default:
                return -1;
        }
    }

    public static function getColorByMeta(int $meta)
    {
        switch ($meta) {
            case 0:
            default:
                return TextFormat::WHITE;
            case 1:
                return TextFormat::GOLD;
            case 2:
                return TextFormat::LIGHT_PURPLE;
            case 3:
                return TextFormat::BLUE;
            case 4:
                return TextFormat::YELLOW;
            case 5:
                return TextFormat::GREEN;
            case 7:
                return TextFormat::DARK_GRAY;
            case 8:
                return TextFormat::GRAY;
            case 9:
                return TextFormat::AQUA;
            case 10:
                return TextFormat::DARK_PURPLE;
            case 11:
                return TextFormat::DARK_BLUE;
            case 13:
                return TextFormat::DARK_GREEN;
            case 14:
                return TextFormat::RED;
            case 15:
                return TextFormat::BLACK;
        }
    }

    public static function setCustomColor(Item $item, Color $color)
    {
        if (($hasTag = $item->hasCompoundTag())) {
            $tag = $item->getNamedTag();
        } else {
            $tag = new CompoundTag("", []);
        }
        $tag->setInt("customColor", self::toRGB($color));
        $item->setCompoundTag($tag);
        return $item;
    }

    public static function toRGB(Color $color): int
    {
        return ($color->getR() << 16) | ($color->getG() << 8) | $color->getB() & 0xffffff;
    }

    public static function getGameByLevel(?Level $level): ?Game
    {
        if (is_null($level)) return null;
        foreach (self::getGames() as $game) {
            if (API::isArenaOf($game, $level)) return $game;
        }
        return null;
    }
}