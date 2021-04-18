<?php


namespace xenialdan\BedWars;


use pocketmine\block\Block;
use pocketmine\entity\Villager;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntitySpawnEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\utils\TextFormat;
use xenialdan\gameapi\API;
use xenialdan\gameapi\Arena;

class SetupEventListener implements Listener
{

    public function spawnShop(EntitySpawnEvent $e){
        if(!$e->getEntity() instanceof Villager) return;
        if (!API::isArenaOf(Loader::getInstance(), ($level = ($entity = $e->getEntity())->getLevel()))) return;
        if (!($arena = API::getArenaByLevel(Loader::getInstance(), $level)) instanceof Arena) return;
        if ($arena->getState() !== Arena::SETUP) {
            return;
        }
        $entity->setRotation(0,0);
        $entity->setNameTagVisible(true);
        $entity->setNameTagAlwaysVisible(false);
        $entity->setImmobile();
        $entity->setMotion(new Vector3());
        $entity->respawnToAll();
    }

    public function removeOrRotateShop(EntityDamageEvent $e){
        if(!$e->getEntity() instanceof Villager || !$e instanceof EntityDamageByEntityEvent) return;
        if(!$e->getDamager() instanceof Player) return;
        if (!API::isArenaOf(Loader::getInstance(), ($level = ($entity = $e->getEntity())->getLevel()))) return;
        if (!($arena = API::getArenaByLevel(Loader::getInstance(), $level)) instanceof Arena) return;
        if ($arena->getState() !== Arena::SETUP) {
            return;
        }
        $e->setCancelled();
        if($e->getDamager()->isSneaking()){
            $entity->close();
            return;
        }
        $newYaw = ($entity->getYaw() + 45) % 360;
        $entity->setRotation($newYaw, 0);
        $entity->respawnToAll();
    }

    public function setSpawns(BlockPlaceEvent $e)
    {
        if (!API::isArenaOf(Loader::getInstance(), $e->getBlock()->getLevel())) return;
        if (!($arena = API::getArenaByLevel(Loader::getInstance(), $e->getBlock()->getLevel())) instanceof Arena) return;
        if ($arena->getState() !== Arena::SETUP) {
            return;
        }
        if ($e->getBlock()->getId() !== Block::CONCRETE) return;
        $e->setCancelled();
        $color = API::getColorByMeta($e->getBlock()->getDamage());
        $team = API::getTeamByColor(Loader::getInstance(), $e->getPlayer()->getLevel(), $color);
        if (is_null($team)) return;
        $team->setSpawn($e->getBlock()->asVector3());
        /** @var BedwarsSettings $settings */
        $settings = $arena->getSettings();
        $settings->teams[$team->getName()]["spawn"] = (array)$e->getBlock()->asVector3();
        $arena->getSettings()->save();
		$e->getPlayer()->sendMessage(Loader::$prefix . "Der Spawn wurde erfolgreich gesetzt" . $team->getColor() . $team->getName() . TextFormat::RESET . " zu [" . (join(", ", (array)$e->getBlock()->asVector3())) . "]");
    }

    public function removeItemSpawns(BlockBreakEvent $e)
    {
        if (!API::isArenaOf(Loader::getInstance(), $e->getBlock()->getLevel())) return;
        if (!($arena = API::getArenaByLevel(Loader::getInstance(), $e->getBlock()->getLevel())) instanceof Arena) return;
        if ($arena->getState() !== Arena::SETUP) {
            return;
        }
        if ($e->getBlock()->getId() !== Block::GOLD_BLOCK && $e->getBlock()->getId() !== Block::IRON_BLOCK && $e->getBlock()->getId() !== Block::HARDENED_CLAY) return;
        $e->setCancelled();
        $settings = $arena->getSettings();
        $vector3 = (array)$e->getBlock()->asVector3();
        $removed = false;
        if ($e->getBlock()->getId() === Block::GOLD_BLOCK) {
            foreach ($settings->gold as $i => $gold) {
                if ($gold["x"] === $vector3["x"] && $gold["y"] === $vector3["y"] && $gold["z"] === $vector3["z"]) {
                    $s = $settings->gold;
                    unset($s[$i]);
                    $settings->gold = $s;
                    $e->getPlayer()->sendMessage(Loader::$prefix . "Der §eGoldSpawner§6 wurde entfernt bei §f[§e" . (join(", ", (array)$e->getBlock()->asVector3())) . "§f]");
                    $removed = true;
                }
            }
        }
        if ($e->getBlock()->getId() === Block::IRON_BLOCK) {
            foreach ($settings->silver as $i => $silver) {
                if ($silver["x"] === $vector3["x"] && $silver["y"] === $vector3["y"] && $silver["z"] === $vector3["z"]) {
                    $s = $settings->silver;
                    unset($s[$i]);
                    $settings->silver = $s;
                    $e->getPlayer()->sendMessage(Loader::$prefix . "Der §7SilberSpawner§6 wurde entfernt bei §f[§e" . (join(", ", (array)$e->getBlock()->asVector3())) . "§f]");
                    $removed = true;
                }
            }
        }
        if ($e->getBlock()->getId() === Block::HARDENED_CLAY) {
            foreach ($settings->bronze as $i => $bronze) {
                if ($bronze["x"] === $vector3["x"] && $bronze["y"] === $vector3["y"] && $bronze["z"] === $vector3["z"]) {
                    $s = $settings->bronze;
                    unset($s[$i]);
                    $settings->bronze = $s;
                   $e->getPlayer()->sendMessage(Loader::$prefix . "Der §cBronzeSpawner§6 wurde entfernt bei §f[§e" . (join(", ", (array)$e->getBlock()->asVector3())) . "§f]");
                    $removed = true;
                }
            }
        }
        if ($removed) $arena->getSettings()->save();
        else $e->setCancelled(false);
    }

    public function setItemSpawns(PlayerInteractEvent $e)
    {
        if (!$e->getAction() === PlayerInteractEvent::RIGHT_CLICK_BLOCK) return;
        if (!API::isArenaOf(Loader::getInstance(), $e->getBlock()->getLevel())) return;
        if (!($arena = API::getArenaByLevel(Loader::getInstance(), $e->getBlock()->getLevel())) instanceof Arena) return;
        if ($arena->getState() !== Arena::SETUP) {
            return;
        }
        if ($e->getBlock()->getId() !== Block::GOLD_BLOCK && $e->getBlock()->getId() !== Block::IRON_BLOCK && $e->getBlock()->getId() !== Block::HARDENED_CLAY) return;
        $e->setCancelled();
        $settings = $arena->getSettings();
        $vector3 = (array)$e->getBlock()->asVector3();
        if ($e->getBlock()->getId() === Block::GOLD_BLOCK) {
            foreach ($settings->gold as $i => $v3) {
                if ($v3["x"] === $vector3["x"] && $v3["y"] === $vector3["y"] && $v3["z"] === $vector3["z"]) {
                    $e->getPlayer()->sendMessage(Loader::$prefix . "§cDer §eGoldSpawner§6 wurde bereits ausgewählt. Zerstöre ihn um ihn zurückzusetzen.");
                    return;
                }
            }
            $settings->gold[] = (array)$e->getBlock()->asVector3();
            $e->getPlayer()->sendMessage(Loader::$prefix . "Der §eGoldSpawner§6 wurde gesetzt §f[§e" . (join(", ", (array)$e->getBlock()->asVector3())) . "§f]");
        }
        if ($e->getBlock()->getId() === Block::IRON_BLOCK) {
            foreach ($settings->silver as $i => $v3) {
                if ($v3["x"] === $vector3["x"] && $v3["y"] === $vector3["y"] && $v3["z"] === $vector3["z"]) {
                    $e->getPlayer()->sendMessage(Loader::$prefix . "§cDer §7SilberSpawner§6 wurde bereits ausgewählt. Zerstöre ihn um ihn zurückzusetzen.");
                    return;
                }
            }
            $settings->silver[] = (array)$e->getBlock()->asVector3();
            $e->getPlayer()->sendMessage(Loader::$prefix . "Der §7SilberSpawner§6 wurde gesetzt §f[§e" . (join(", ", (array)$e->getBlock()->asVector3())) . "§f]");
        }
        if ($e->getBlock()->getId() === Block::HARDENED_CLAY) {
            foreach ($settings->bronze as $i => $v3) {
                if ($v3["x"] === $vector3["x"] && $v3["y"] === $vector3["y"] && $v3["z"] === $vector3["z"]) {
                    $e->getPlayer()->sendMessage(Loader::$prefix . "§cDer §cBronzeSpawner§6 wurde bereits ausgewählt. Zerstöre ihn um ihn zurückzusetzen.");
                    return;
                }
            }
            $settings->bronze[] = (array)$e->getBlock()->asVector3();
            $e->getPlayer()->sendMessage(Loader::$prefix . "Der §cBronzeSpawner§6 wurde gesetzt §f[§e" . (join(", ", (array)$e->getBlock()->asVector3())) . "§f]");
        }
        $arena->getSettings()->save();
    }
}
