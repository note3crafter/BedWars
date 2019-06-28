<?php

namespace xenialdan\BedWars\libs\xenialdan\gameapi\event;

use pocketmine\event\plugin\PluginEvent;
use pocketmine\level\Level;
use pocketmine\tile\Sign as SignTile;
use pocketmine\tile\Tile;
use pocketmine\utils\TextFormat;
use xenialdan\BedWars\libs\xenialdan\gameapi\API;
use xenialdan\BedWars\libs\xenialdan\gameapi\Arena;
use xenialdan\BedWars\libs\xenialdan\gameapi\Game;

class UpdateSignsEvent extends PluginEvent
{
    public static $handlerList = null;
    /** @var Level[] */
    private $levels;
    /** @var Arena */
    private $arena;

    /**
     * UpdateSignsEvent constructor.
     * @param Game $plugin
     * @param Level[] $levels
     * @param Arena $arena
     */
    public function __construct(Game $plugin, array $levels, Arena $arena)
    {
        parent::__construct($plugin);
        $this->levels = $levels;
        $this->arena = $arena;
    }

    public function call(): void
    {
        foreach (array_filter($this->levels, function (Level $level) {
            return !API::isArena($level);
        }) as $level) {
            if (!$level instanceof Level) continue;
            foreach (array_filter($level->getTiles(), function (Tile $tile) {
                return $tile instanceof SignTile;
            }) as $tile) {
                /** @var SignTile $tile */
                $lines = $tile->getText();
                if (strtolower(TextFormat::clean($lines[0])) === strtolower(TextFormat::clean($this->arena->getOwningGame()->getPrefix()))) {
                    if (TextFormat::clean($lines[1]) === $this->arena->getLevelName()) {
                        $state = $this->arena->getState();
                        switch ($state) {
                            case Arena::IDLE:
                                {
                                    $status = TextFormat::GREEN . "Bereit";
                                    break;
                                }
                            case Arena::WAITING:
                                {
                                    $status = TextFormat::GREEN . "Warte auf Spieler";
                                    break;
                                }
                            case Arena::STARTING:
                                {
                                    $status = TextFormat::GOLD . "Startet";
                                    break;
                                }
                            case Arena::INGAME:
                                {
                                    $status = TextFormat::RED . "Spiel läuft";
                                    break;
                                }
                            case Arena::STOP:
                                {
                                    $status = TextFormat::RED . "Neu erstellt";
                                    break;
                                }
                            case Arena::SETUP:
                                {
                                    $status = TextFormat::DARK_RED . "Gesperrt";
                                    break;
                                }
                            default:
                                {
                                    $status = "Unbekannt";
                                }
                        }
                        $color = count($this->arena->getPlayers()) === $this->arena->getMaxPlayers() ? TextFormat::RED : TextFormat::GREEN;
                        $playerline = TextFormat::AQUA . "Spieler: [" . $color . count($this->arena->getPlayers()) . TextFormat::AQUA . "/" . $color . $this->arena->getMaxPlayers() . TextFormat::AQUA . "]";
                        $tile->setText(null, null, $status, $playerline);
                    }
                }
            }
        }
        parent::call();
    }
}