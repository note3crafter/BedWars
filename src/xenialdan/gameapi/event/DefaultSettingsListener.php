<?php


namespace xenialdan\gameapi\event;


use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\inventory\InventoryPickupArrowEvent;
use pocketmine\event\inventory\InventoryPickupItemEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerBedEnterEvent;
use pocketmine\event\player\PlayerBedLeaveEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\inventory\PlayerInventory;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\Player;
use pocketmine\plugin\Plugin;
use xenialdan\gameapi\API;
use xenialdan\gameapi\Arena;
use xenialdan\gameapi\DefaultSettings;
use xenialdan\gameapi\Game;

class DefaultSettingsListener implements Listener
{

    private static $registrant;

    public static function isRegistered(): bool
    {
        return self::$registrant instanceof Game;
    }

    public static function getRegistrant(): Game
    {
        return self::$registrant;
    }

    public static function unregister(): void
    {
        self::$registrant = null;
    }

    public static function register(Game $plugin): void
    {
        if (self::isRegistered()) {
            throw new \Error($plugin->getName() . "attempted to register " . self::class . " twice.");
        }

        self::$registrant = $plugin;
        $plugin->getServer()->getPluginManager()->registerEvents(new DefaultSettingsListener(), $plugin);
    }

    public function noBuild(BlockPlaceEvent $e)
    {
        if (!$e->getBlock()->isValid() || is_null($level = $e->getBlock()->getLevel())) return;
        if (!API::isArena($level)) return;
        $settings = ($arena = API::getArenaByLevel(null, $level))->getSettings();
        if ($arena->getState() === Arena::SETUP) return;
        if ($arena->getState() !== Arena::INGAME) {
            $e->setCancelled();
        }
        if (!$settings instanceof DefaultSettings) return;
        if ($settings->noBuild && !in_array($e->getBlock()->getId(), $settings->placeBlockIds)) $e->setCancelled();
        if ($settings->immutableWorld) $e->setCancelled();
    }

    public function noBreakAndNoBlockDrops(BlockBreakEvent $e)
    {
        if (!$e->getBlock()->isValid() || is_null($level = $e->getBlock()->getLevel())) return;
        if (!API::isArena($level)) return;
        $settings = ($arena = API::getArenaByLevel(null, $level))->getSettings();
        if ($arena->getState() === Arena::SETUP) return;
        if ($arena->getState() !== Arena::INGAME) {
            $e->setCancelled();
        }
        if (!$settings instanceof DefaultSettings) return;
        if ($settings->noBreak && !in_array($e->getBlock()->getId(), $settings->breakBlockIds)) $e->setCancelled();
        if ($settings->immutableWorld) $e->setCancelled();
        if ($settings->noBlockDrops) $e->setDrops([]);
    }

    public function noBedEnter(PlayerBedEnterEvent $e)
    {
        if (!$e->getBed()->isValid() || is_null($level = $e->getBed()->getLevel())) return;
        if (!API::isArena($level)) return;
        $settings = ($arena = API::getArenaByLevel(null, $level))->getSettings();
        if ($arena->getState() === Arena::SETUP) return;
        if ($arena->getState() !== Arena::INGAME) {
            $e->setCancelled();
        }
        if (!$settings instanceof DefaultSettings) return;
        if ($settings->noBed) $e->setCancelled();
    }

    public function noBedLeave(PlayerBedLeaveEvent $e)
    {
        if (!$e->getBed()->isValid() || is_null($level = $e->getBed()->getLevel())) return;
        if (!API::isArena($level)) return;
        $settings = ($arena = API::getArenaByLevel(null, $level))->getSettings();
        if ($arena->getState() === Arena::SETUP) return;
        if ($arena->getState() !== Arena::INGAME) {
            $e->setCancelled();
        }
        if (!$settings instanceof DefaultSettings) return;
        if ($settings->noBed) $e->setCancelled();
    }

    public function noWalkStart(PlayerMoveEvent $e)
    {
        if (!$e->getFrom()->isValid() || is_null($level = $e->getFrom()->getLevel())) return;
        if (!API::isArena($level)) return;
        $settings = ($arena = API::getArenaByLevel(null, $level))->getSettings();
        if ($arena->getState() === Arena::SETUP) return;
        if ($arena->getState() !== Arena::WAITING && $arena->getState() !== Arena::STARTING) {
            return;
        }
        if (!$settings instanceof DefaultSettings) return;
        if ($settings->startNoWalk && !$e->getFrom()->floor()->equals($e->getTo()->floor())) $e->setCancelled();
    }

    public function noPickup(InventoryPickupItemEvent $e)
    {
        if (!$e->getItem()->isValid() || is_null($level = $e->getItem()->getLevel())) return;
        if (!API::isArena($level)) return;
        $settings = ($arena = API::getArenaByLevel(null, $level))->getSettings();
        if ($arena->getState() === Arena::SETUP) return;
        if ($arena->getState() !== Arena::INGAME) {
            $e->setCancelled();
        }
        if (!$settings instanceof DefaultSettings) return;
        if ($settings->noPickup) $e->setCancelled();
    }

    public function noDropItems(PlayerDropItemEvent $e)
    {
        if (!$e->getPlayer()->isValid() || is_null($level = $e->getPlayer()->getLevel())) return;
        if (!API::isArena($level)) return;
        $settings = ($arena = API::getArenaByLevel(null, $level))->getSettings();
        if ($arena->getState() === Arena::SETUP) return;
        if ($arena->getState() !== Arena::INGAME) {
            $e->setCancelled();
        }
        if (!$settings instanceof DefaultSettings) return;
        if ($settings->noDropItem) $e->setCancelled();
    }

    public function noInvEdit(InventoryTransactionEvent $e)
    {
        if (!$e->getTransaction()->getSource()->isValid() || is_null($level = $e->getTransaction()->getSource()->getLevel())) return;
        if (!$e->getTransaction()->getSource() instanceof Player) return;
        if (!API::isArena($level)) return;
        $settings = ($arena = API::getArenaByLevel(null, $level))->getSettings();
        if ($arena->getState() === Arena::SETUP) return;
        if ($arena->getState() !== Arena::INGAME) {
            $e->setCancelled();
        }
        if (!$settings instanceof DefaultSettings) return;
        if ($settings->noInventoryEditing) {
            foreach ($e->getTransaction()->getActions() as $action) {
                if ($action instanceof SlotChangeAction) {
                    if ($action->getInventory() instanceof PlayerInventory)
                        $e->setCancelled();
                }
            }
        }
    }

    public function noEntityDrops(EntityDeathEvent $e)
    {
        if (!$e->getEntity()->isValid() || is_null($level = $e->getEntity()->getLevel())) return;
        if (!API::isArena($level)) return;
        $settings = ($arena = API::getArenaByLevel(null, $level))->getSettings();
        if ($arena->getState() === Arena::SETUP) return;
        if ($arena->getState() !== Arena::INGAME) {
            $e->setCancelled();
        }
        if (!$settings instanceof DefaultSettings) return;
        if ($settings->noEntityDrops) $e->setDrops([]);
    }

    public function keepClearInventory(PlayerDeathEvent $e)
    {
        if (!$e->getPlayer()->isValid() || is_null($level = $e->getPlayer()->getLevel())) return;
        if (!API::isArena($level)) return;
        $settings = ($arena = API::getArenaByLevel(null, $level))->getSettings();
        if ($arena->getState() === Arena::SETUP) return;
        if ($arena->getState() !== Arena::INGAME) {
            $e->setCancelled();
        }
        if (!$settings instanceof DefaultSettings) return;
        if ($settings->keepInventory) $e->setKeepInventory(true);
        if ($settings->clearInventory) {
            $e->setKeepInventory(false);
            $e->setDrops([]);
        }
    }

    public function noArrowPickup(InventoryPickupArrowEvent $e)
    {
        if (!$e->getArrow()->isValid() || is_null($level = $e->getArrow()->getLevel())) return;
        if (!API::isArena($level)) return;
        $settings = ($arena = API::getArenaByLevel(null, $level))->getSettings();
        if ($arena->getState() === Arena::SETUP) return;
        if ($arena->getState() !== Arena::INGAME) {
            $e->setCancelled();
        }
        if (!$settings instanceof DefaultSettings) return;
        if ($settings->noArrowPickup) {
            $e->setCancelled();
            $e->getArrow()->close();
        }
    }

    public function noEntityDamage(EntityDamageEvent $e)
    {
        if (!$e->getEntity()->isValid()) return;
        $level = $e->getEntity()->getLevel();
        if (!API::isArena($level)) return;
        $settings = ($arena = API::getArenaByLevel(null, $level))->getSettings();
        if ($arena->getState() === Arena::SETUP) return;
        if ($arena->getState() !== Arena::INGAME) {
            $e->setCancelled();
            return;
        }
        if (!$settings instanceof DefaultSettings) return;
        $cause = $e->getCause();
        if ($cause === EntityDamageEvent::CAUSE_ENTITY_ATTACK) {
            $self = $e->getEntity();
            $damager = $e->getDamager();
            if ($self instanceof Player && $damager instanceof Player) {
                $inTeam = API::getTeamOfPlayer($self)->inTeam($damager);
                if ($inTeam && $settings->noDamageTeam || !$inTeam && $settings->noDamageEnemies) {
                    $e->setCancelled();
                }
            } elseif ($self instanceof Player && !$damager instanceof Player && $settings->noDamageEntities) $e->setCancelled();
        } elseif (($cause === EntityDamageEvent::CAUSE_FIRE ||
                $cause === EntityDamageEvent::CAUSE_LAVA ||
                $cause === EntityDamageEvent::CAUSE_CONTACT ||
                $cause === EntityDamageEvent::CAUSE_FIRE_TICK ||
                $cause === EntityDamageEvent::CAUSE_SUFFOCATION) &&
            $settings->noEnvironmentDamage) {
            $e->setCancelled();
        } elseif (($cause === EntityDamageEvent::CAUSE_FALL) && $settings->noFallDamage) {
            $e->setCancelled();
        } elseif (($cause === EntityDamageEvent::CAUSE_BLOCK_EXPLOSION || $cause === EntityDamageEvent::CAUSE_ENTITY_EXPLOSION) && $settings->noExplosionDamage) {
            $e->setCancelled();
        } elseif (($cause === EntityDamageEvent::CAUSE_DROWNING) && $settings->noDrowningDamage) {
            $e->setCancelled();
        }
    }
}