<?php

namespace xenialdan\BedWars;

use pocketmine\block\SignPost;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\tile\Sign;
use pocketmine\utils\TextFormat;

class JoinGameListener implements Listener
{

    public function onInteract(PlayerInteractEvent $event)
    {
        $action = $event->getAction();
        $block = $event->getBlock();
        if ($action === PlayerInteractEvent::RIGHT_CLICK_BLOCK && $block instanceof SignPost) {
            if (($tile = $block->getLevel()->getTile($block)) instanceof Sign) {
                $this->onClickSign($event, $tile->getText());
            }
        }
    }

    public function onClickSign($event, array $text)
    {
        if (strpos(strtolower(TextFormat::clean($text[0])), strtolower(TextFormat::clean(Loader::getInstance()->getPrefix()))) !== false) {
            $player = $event->getPlayer();
            if (is_null($arena = Loader::getInstance()->getArenas()[TextFormat::clean($text[1])]??null)) {
                $player->sendMessage(Loader::$prefix . '§cDie Arena wurde nicht gefunden.');
                return;
            }
            if(!$arena->joinTeam($player)) {
                $player->sendMessage(Loader::$prefix . '§cDu Konntest nicht Joinen.');
            }
        }
    }

}
