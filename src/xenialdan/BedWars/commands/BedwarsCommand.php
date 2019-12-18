<?php

declare(strict_types=1);

namespace xenialdan\BedWars\commands;

use pocketmine\command\CommandSender;
use pocketmine\command\PluginCommand;
use pocketmine\Player;
use pocketmine\plugin\Plugin;
use pocketmine\utils\TextFormat;
use xenialdan\BedWars\Loader;
use xenialdan\gameapi\API;
use xenialdan\gameapi\Game;

class BedwarsCommand extends PluginCommand
{
    public function __construct(Plugin $plugin)
    {
        parent::__construct("bw", $plugin);
        $this->setAliases(["bedwars"]);
        $this->setPermission("bedwars.command");
        $this->setDescription(Loader::$prefix . "Einstellung");
        $this->setUsage("/bw | /bw setup | /bw endsetup | /join| /leave | /start | /bw stop | /bw status | /bw info");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args)
    {
        /** @var Player $sender */
        $return = $sender->hasPermission($this->getPermission());
        if (!$return) {
            $sender->sendMessage(Loader::$prefix . "§cDu hast keine Berechtigung um diesen Befehl auszuführen");
            return true;
        }
        if (!$sender instanceof Player) {
            $sender->sendMessage(Loader::$prefix . "§cDu kannst diesen Befehl hier nicht ausführen!");
            return false;
        }
        try {
            $return = true;
            switch ($args[0] ?? "help") {
                case "setup":
                    {
                        if (!$sender->hasPermission("bedwars.command.setup")) {
                            $sender->sendMessage(Loader::$prefix . "§cDu hast keine Berechtigung um diesen Befehl auszuführen");
                            return true;
                        }
                        /** @var Game $p */
                        $p = $this->getPlugin();
                        $p->setupArena($sender);
                        break;
                    }
                case "join":
                {
                    if (!$sender->hasPermission("bedwars.command.join")) {
                        $sender->sendMessage(Loader::$prefix . "§cDu hast keine Berechtigung um diesen Befehl auszuführen");
                        return true;
                    }
                    if (API::getArenaOfPlayer($sender) !== null) {
                        $sender->sendMessage(Loader::$prefix . "§cDu kannst dieser Arena nicht Beitreten wenn sie derzeit Läuft");
                        return true;
                    }
                    if (is_null($arena = Loader::getInstance()->getArenas()[$args[1]]??null)) {
                        $sender->sendMessage(Loader::$prefix . "§cDie Arena §f: §e" . $args[1] . " §cwurde nicht gefunden!");
                        return true;
                    }
                    if (!$arena->joinTeam($sender)) {
                        $sender->sendMessage(Loader::$prefix . "§cDu kannst der Arena nicht Beitreten.");
                        return true;
                    }
                    break;
                }
                case "leave":
                    {
                        if (!$sender->hasPermission("bedwars.command.leave")) {
                            $sender->sendMessage(Loader::$prefix . "§cDu hast keine Berechtigung um diesen Befehl auszuführen");
                            return true;
                        }
                        $arena = API::getArenaOfPlayer($sender);
                        if(is_null($arena) || !API::isArenaOf($this->getPlugin(), $arena->getLevel())){
                            /** @var Game $plugin */
                            $plugin = $this->getPlugin();
                            $sender->sendMessage(Loader::$prefix . "§cDu bist derzeit in keiner Runde ". $plugin->getPrefix());
                            return true;
                        }
                        if (API::isPlaying($sender, $this->getPlugin())) $arena->removePlayer($sender);
                        break;
                    }
                case "endsetup":
                    {
                        if (!$sender->hasPermission("bedwars.command.endsetup")) {//TODO only when setup
                            $sender->sendMessage(Loader::$prefix . "§cDu hast keine Berechtigung um diesen Befehl auszuführen");
                            return true;
                        }
                        /** @var Game $p */
                        $p = $this->getPlugin();
                        $p->endSetupArena($sender);
                        break;
                    }
                case "stop":
                    {
                        if (!$sender->hasPermission("bedwars.command.stop")) {
                            $sender->sendMessage(Loader::$prefix . "§cDu hast keine Berechtigung um diesen Befehl auszuführen");
                            return true;
                        }
                        API::getArenaByLevel(Loader::getInstance(), $sender->getLevel())->stopArena();
                        break;
                    }
                case "forcestart":
                    {
                        if (!$sender->hasPermission("bedwars.command.forcestart")) {
                            $sender->sendMessage(Loader::$prefix . "§cDu hast keine Berechtigung um diesen Befehl auszuführen");
                            return true;
                        }
                        $arena = API::getArenaOfPlayer($sender);
                        if(is_null($arena) || !API::isArenaOf($this->getPlugin(), $arena->getLevel())){
                            /** @var Game $plugin */
                            $plugin = $this->getPlugin();
                            $sender->sendMessage(Loader::$prefix . "§6Du bist derzeit in keiner Runde ". $plugin->getPrefix());
                            return true;
                        }
                        $arena->startTimer($arena->getOwningGame());
                        $arena->forcedStart = true;
                        $arena->setTimer(5);
                        $sender->getServer()->broadcastMessage(Loader::$prefix . "Das Spiel wurde gestartet von " . $sender->getDisplayName(), $arena->getPlayers());
                        break;
                    }
                case "help":
                    {
                        if (!$sender->hasPermission("bedwars.command.help")) {
                            $sender->sendMessage(Loader::$prefix . "§cDu hast keine Berechtigung um diesen Befehl auszuführen");
                            return true;
                        }
                        $sender->sendMessage($this->getUsage());
                        $return = true;
                        break;
                    }
                default:
                    {
                        $return = false;
                        throw new \InvalidArgumentException("Unbekanntes Argument: " . $args[0]);
                    }
            }
        } catch (\Throwable $error) {
            $this->getPlugin()->getLogger()->logException($error);
            $return = false;
        } finally {
            return $return;
        }
    }
}
