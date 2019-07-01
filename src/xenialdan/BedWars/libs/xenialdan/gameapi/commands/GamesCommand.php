<?php

declare(strict_types=1);

namespace xenialdan\BedWars\libs\xenialdan\gameapi\commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\permission\Permission;
use pocketmine\Player;
use pocketmine\utils\TextFormat;
use xenialdan\BedWars\libs\xenialdan\gameapi\API;
use xenialdan\BedWars\libs\xenialdan\gameapi\Game;

class GamesCommand extends Command
{
    public function __construct()
    {
        parent::__construct("games", "List games", "/games");
        $this->setPermission(Permission::DEFAULT_TRUE);
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args)
    {
        /** @var Player $sender */
        $return = $sender->hasPermission($this->getPermission());
        if (!$return) {
            $sender->sendMessage(TextFormat::RED . "§f[§4Bed§fWars] §eDu hast keine Berechtigung um diesen Command auszuführen.");
            return true;
        }
        $sender->sendMessage(TextFormat::GOLD . "=== Verfügbare Spiele ===");
        /** @var Game $game */
        foreach (API::getGames() as $game)
            $sender->sendMessage(($game->isEnabled() ? TextFormat::GREEN : TextFormat::RED) . $game->getPrefix() . ($game->isEnabled() ? TextFormat::GREEN : TextFormat::RED) . " v" . $game->getDescription()->getVersion() . " by " . TextFormat::AQUA . $game->getAuthors() . ($game->isEnabled() ? TextFormat::GREEN : TextFormat::RED) . ($game->getDescription()->getDescription() !== "" ?: ($game->isEnabled() ? TextFormat::GREEN : TextFormat::RED) . $game->getDescription()->getDescription()));
        return $return;
    }
}
