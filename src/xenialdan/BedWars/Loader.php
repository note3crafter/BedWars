<?php

namespace xenialdan\BedWars;

use muqsit\invmenu\inventories\BaseFakeInventory;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\InvMenuHandler;
use pocketmine\block\BlockIds;
use pocketmine\entity\Entity;
use pocketmine\entity\object\ItemEntity;
use pocketmine\entity\object\PrimedTNT;
use pocketmine\entity\projectile\Arrow;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\item\Potion;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\LevelEventPacket;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use xenialdan\BedWars\commands\BedwarsCommand;
use xenialdan\BedWars\task\SpawnItemsTask;
use xenialdan\customui\elements\Button;
use xenialdan\customui\elements\Input;
use xenialdan\customui\elements\Label;
use xenialdan\customui\elements\StepSlider;
use xenialdan\customui\windows\CustomForm;
use xenialdan\customui\windows\ModalForm;
use xenialdan\customui\windows\SimpleForm;
use xenialdan\gameapi\API;
use xenialdan\gameapi\Arena;
use xenialdan\gameapi\Game;
use xenialdan\gameapi\Team;

class Loader extends Game
{
    const BRONZE = "Bronze";
    const SILVER = "Silver";
    const GOLD = "Gold";
    private static $instance = null;
    public static $prefix = "§f[§4Bed§fWars] §6";

    public static function getInstance()
    {
        return self::$instance;
    }

    public function onLoad()
    {
        self::$instance = $this;
    }

    public function onEnable()
    {
        if (!InvMenuHandler::isRegistered()) InvMenuHandler::register($this);
        $this->getServer()->getPluginManager()->registerEvents(new EventListener(), $this);
        $this->getServer()->getPluginManager()->registerEvents(new JoinGameListener(), $this);
        $this->getServer()->getPluginManager()->registerEvents(new LeaveGameListener(), $this);
        $this->getServer()->getPluginManager()->registerEvents(new SetupEventListener(), $this);
        $this->getServer()->getCommandMap()->register("bw", new BedwarsCommand($this));
        API::registerGame($this);
        foreach (glob($this->getDataFolder() . "*.json") as $v) {
            $this->addArena($this->getNewArena($v));
        }
    }

    public function getNewArena(string $settingsPath): Arena
    {
        $settings = new BedwarsSettings($settingsPath);
        var_dump($settings->gold, $settings->get("gold"));
        $levelname = basename($settingsPath, ".json");
        $arena = new Arena($levelname, $this, $settings);
        var_dump($settings->teams, $settings->get("teams"));
        foreach ($settings->get("teams", []) as $teamname => $teaminfo) {
            $team = new BedwarsTeam($teaminfo["color"] ?? TextFormat::RESET, $teamname);
            $team->setMinPlayers(1);
            $team->setMaxPlayers($teaminfo["maxplayers"] ?? 1);
            #if (!is_null($teaminfo["spawn"]))
            $team->setSpawn(new Vector3(
                    $teaminfo["spawn"]["x"] ?? $arena->getLevel()->getSpawnLocation()->getFloorX(),
                    $teaminfo["spawn"]["y"] ?? $arena->getLevel()->getSpawnLocation()->getFloorY(),
                    $teaminfo["spawn"]["z"] ?? $arena->getLevel()->getSpawnLocation()->getFloorZ()
                )
            );
            var_dump($team);
            $arena->addTeam($team);
        }
        return $arena;
    }

    private static function getTeamNamesByAmount(int $amount): array
    {
        $teams = [
            TextFormat::RED => "Rot",
            TextFormat::DARK_BLUE => "Blau",
            TextFormat::GREEN => "Grün",
            TextFormat::YELLOW => "Gelb",
            TextFormat::DARK_PURPLE => "Lila",
            TextFormat::GOLD => "Orange",
            TextFormat::LIGHT_PURPLE => "Pink",
            TextFormat::DARK_AQUA => "Cyan",
        ];
        return array_slice($teams, 0, $amount, true);
    }

    public function setupArena(Player $player): void
    {
        $form = new SimpleForm(Loader::$prefix . "Einstellung");
        $na = "§6Neue Arena";
        $form->addButton(new Button($na));
        $ea = "§6Arena Bearbeiten";
        $form->addButton(new Button($ea));
        $form->setCallable(function (Player $player, $data) use ($na, $ea) {
            if ($data === $na) {
                $form = new SimpleForm(Loader::$prefix . "Einstellung");
                $nw = "§6Neue Welt";
                $form->addButton(new Button($nw));
                $ew = "§6Vorhandene Welt";
                $form->addButton(new Button($ew));
                $form->setCallable(function (Player $player, $data) use ($ew, $nw) {
                    $new = true;
                    if ($data === $ew) {
                        $new = false;
                        $form = new SimpleForm(Loader::$prefix ."Einstellung", "Erstelle eine neue Welt");
                        foreach (API::getAllWorlds() as $worldName) {
                            $form->addButton(new Button($worldName));
                        }
                    } else {
                        $form = new CustomForm(Loader::$prefix . "Einstellung");
                        $form->addElement(new Label("§6Erstelle eine Welt"));
                        $form->addElement(new Input("§6Welten Namen", "Beispiel: BW2x1"));
                    }
                    $form->setCallable(function (Player $player, $data) use ($new) {
                        $setup["name"] = $new ? $data[1] : $data;
                        if ($new) {
                            API::$generator->generateLevel($setup["name"]);
                        }
                        Server::getInstance()->loadLevel($setup["name"]);
                        $form = new CustomForm(Loader::$prefix . "Team Einstellung");
                        $form->addElement(new StepSlider("§6Teams", array_keys(array_fill(2, 7, ""))));
                        $form->addElement(new StepSlider("§6Maximum Spieler pro Team", array_keys(array_fill(1, 5, ""))));
                        $form->setCallable(function (Player $player, $data) use ($new, $setup) {
                            $setup["teamcount"] = intval($data[0]);
                            $setup["maxplayers"] = intval($data[1]);
                            $teams = self::getTeamNamesByAmount($setup["teamcount"]);
                            //New arena
                            $settings = new BedwarsSettings($this->getDataFolder() . $setup["name"] . ".json");
                            foreach ($teams as $color => $name) {
                                $settings->teams[$name] = ["color" => $color, "maxplayers" => $setup["maxplayers"]];
                            }
                            $settings->save();
                            $this->addArena($this->getNewArena($this->getDataFolder() . $setup["name"] . ".json"));
                            //Messages
                            $player->sendMessage(TextFormat::GOLD . TextFormat::BOLD . Loader::$prefix . "Die Arena wurde erfolgreich erstellt mit den Einstellungen:");
                            $player->sendMessage(TextFormat::AQUA . "§6Welten Name: " . TextFormat::DARK_AQUA . $setup["name"]);
                            $message = TextFormat::AQUA . "§6Teams: " . TextFormat::LIGHT_PURPLE . $setup["teamcount"];
                            $message .= TextFormat::RESET . "(";
                            $tc = [];
                            foreach ($teams as $color => $name) $tc[] = $color . ucfirst($name);
                            $message .= implode(TextFormat::RESET . ", ", $tc);
                            $message .= TextFormat::RESET . ")";
                            $player->sendMessage($message);
                            $player->sendMessage(TextFormat::AQUA . Loader::$prefix . "Maximale Spieler pro Team: " . TextFormat::DARK_AQUA . $setup["maxplayers"]);
                            $player->sendMessage(TextFormat::GOLD . Loader::$prefix . "Benutzte /bw setup um die Welt zu Bearbeiten");
                        });
                        $player->sendForm($form);
                    });
                    $player->sendForm($form);
                });
                $player->sendForm($form);
            } elseif ($data === $ea) {
                $form = new SimpleForm("§6Bearbeite die Welten");
                $build = "§6Bearbeiten der Map/ItemSpawner";
                $button = new Button($build);
                $button->addImage(Button::IMAGE_TYPE_PATH, "textures/ui/icon_recipe_construction");
                $form->addButton($button);
                $editspawnpoints = "§6Bearbeiten der §eT§ce§aa§bm §6Spawns";
                $button = new Button($editspawnpoints);
                $button->addImage(Button::IMAGE_TYPE_PATH, "textures/items/bed_red");
                $form->addButton($button);
                $addvillager = "§f[§4Bed§fwars]§6 Shop";
                $button = new Button($addvillager);
                $button->addImage(Button::IMAGE_TYPE_PATH, "textures/items/emerald");
                $form->addButton($button);
                $delete = "§6Arena Löschen";
                $button = new Button($delete);
                $button->addImage(Button::IMAGE_TYPE_PATH, "textures/ui/trash");
                $form->addButton($button);
                $form->setCallable(function (Player $player, $data) use ($addvillager, $editspawnpoints, $delete, $build) {
                    switch ($data) {
                        case $build:
                            {
                                $form = new SimpleForm($build, Loader::$prefix . "Wähle eine Welt aus zum Bearbeiten");
                                foreach ($this->getArenas() as $arena) $form->addButton(new Button($arena->getLevelName()));
                                $form->setCallable(function (Player $player, $data) {
                                    $worldname = $data;
                                    $arena = API::getArenaByLevelName($this, $worldname);
                                    $this->getServer()->broadcastMessage(Loader::$prefix . "Arena Gestoppt, wegen: ein Admin hat diese Arena geschlossen!", $arena->getPlayers());
                                    $arena->stopArena();
                                    $arena->setState(Arena::SETUP);
                                    if (!$this->getServer()->isLevelLoaded($worldname)) $this->getServer()->loadLevel($worldname);
                                    $player->teleport($arena->getLevel()->getSpawnLocation());
                                    $player->setGamemode(Player::CREATIVE);
                                    $player->setAllowFlight(true);
                                    $player->setFlying(true);
                                    $player->getInventory()->clearAll();
                                    $arena->getLevel()->stopTime();
                                    $arena->getLevel()->setTime(Level::TIME_DAY);
                                    $player->sendMessage(TextFormat::GOLD . Loader::$prefix . "Du kannst die Arena jetzt Bearbeiten.");
                                    $player->sendMessage(TextFormat::GOLD . Loader::$prefix . "Setze Gold, Silber, Bronze Blöcke und makiere Sie durch anklicken, durch das Zerstören der Blöcke wird die makierung zurückgesetzt");
                                });
                                $player->sendForm($form);
                                break;
                            }
                        case $editspawnpoints:
                            {
                                $form = new SimpleForm($editspawnpoints, Loader::$prefix . "Bearbeite die Welt und setze die Spawn Punkte der Teams");
                                foreach ($this->getArenas() as $arena) $form->addButton(new Button($arena->getLevelName()));
                                $form->setCallable(function (Player $player, $data) {
                                    $worldname = $data;
                                    $arena = API::getArenaByLevelName($this, $worldname);
                                    $this->getServer()->broadcastMessage(Loader::$prefix . "Die Arena wurde von einem Admin gestoppt.", $arena->getPlayers());
                                    $arena->stopArena();
                                    $arena->setState(Arena::SETUP);
                                    if (!$this->getServer()->isLevelLoaded($worldname)) $this->getServer()->loadLevel($worldname);
                                    $player->teleport($arena->getLevel()->getSpawnLocation());
                                    $player->setGamemode(Player::SURVIVAL);
                                    $player->setAllowFlight(true);
                                    $player->setFlying(true);
                                    $player->getInventory()->clearAll();
                                    $arena->getLevel()->stopTime();
                                    $arena->getLevel()->setTime(Level::TIME_DAY);
                                    foreach ($arena->getTeams() as $team) {
                                        $item = ItemFactory::get(Item::CONCRETE, API::getMetaByColor($team->getColor()));
                                        $item->setLore(["Spawn Punkt für das " . $team->getColor() . $team->getName() . TextFormat::RESET . " Team", Loader::$prefix . "Spawn Punkt festgelegt"]);
                                        $item->setCustomName($team->getColor() . $team->getName());
                                        $player->getInventory()->addItem($item);
                                    }
                                    $player->sendMessage(Loader::$prefix . "Bitte setze die Betonblöcke um die TeamSpawns Festzulegen");
                                });
                                $player->sendForm($form);
                                break;
                            }
                        case $addvillager:
                            {
                                $form = new SimpleForm($editspawnpoints, Loader::$prefix . "Setzte einen Villager um den Shop festzulegen");
                                foreach ($this->getArenas() as $arena) $form->addButton(new Button($arena->getLevelName()));
                                $form->setCallable(function (Player $player, $data) {
                                    $worldname = $data;
                                    $arena = API::getArenaByLevelName($this, $worldname);
                                    $this->getServer()->broadcastMessage(Loader::$prefix . "Die Arena wurde von rinrm Admin gestoppt", $arena->getPlayers());
                                    $arena->stopArena();
                                    $arena->setState(Arena::SETUP);
                                    if (!$this->getServer()->isLevelLoaded($worldname)) $this->getServer()->loadLevel($worldname);
                                    $player->teleport($arena->getLevel()->getSpawnLocation());
                                    $player->setGamemode(Player::SURVIVAL);
                                    $player->setAllowFlight(true);
                                    $player->setFlying(true);
                                    $player->getInventory()->clearAll();
                                    $arena->getLevel()->stopTime();
                                    $arena->getLevel()->setTime(Level::TIME_DAY);
                                    $item = ItemFactory::get(Item::SPAWN_EGG, Entity::VILLAGER, 64);
                                    $item->setLore([Loader::$prefix . "Tippe um den Villager zu drehen, zum entfernen Sneaken und auf den Villager drücken"]);
                                    $item->setCustomName(Loader::$prefix . "Shop");
                                    $player->getInventory()->addItem($item);
                                    $player->sendMessage(Loader::$prefix . "Benutze das SpawnEgg um den Villager zu setzten. Sneake und klicke auf den Villager um in zu entfernen, Durch jeweiliges drücken dreht er sich um 45 Grad");
                                });
                                $player->sendForm($form);
                                break;
                            }
                        case $delete:
                            {
                                $form = new SimpleForm("Welt Löschen", "Wähle eine Welt aus. Die Welt wird nicht gelöscht");
                                foreach ($this->getArenas() as $arena) $form->addButton(new Button($arena->getLevelName()));
                                $form->setCallable(function (Player $player, $data) {
                                    $worldname = $data;
                                    $form = new ModalForm("§6Löschen bestätigen", "§6Bitte bestätige das löschen der Welt \"$worldname\"", "§6Lösche§e $worldname", "§6Abbrechen");
                                    $form->setCallable(function (Player $player, $data) use ($worldname) {
                                        if ($data) {
                                            $arena = API::getArenaByLevelName($this, $worldname);
                                            $this->deleteArena($arena) ? $player->sendMessage(Loader::$prefix . "§aWelt wurde erfolgreich gelöscht") : $player->sendMessage(Loader::$prefix . "§c Welt wurde gelöscht, die Konfigurations Datei konnte nicht gelöscht werden!");
                                        }
                                    });
                                    $player->sendForm($form);
                                });
                                $player->sendForm($form);
                                break;
                            }
                    }
                });
                $player->sendForm($form);
            }
        });
        $player->sendForm($form);
    }

    public function removePlayer(Arena $arena, Player $player)
    {
        $arena->bossbar->setTitle(count(array_filter($arena->getTeams(), function (Team $team): bool {
                return count($team->getPlayers()) > 0;
            })) . ' teams alive');
    }

    public function startArena(Arena $arena): void
    {
        foreach ($arena->getTeams() as $team) {
            $team->setBedDestroyed(false);
            foreach ($team->getPlayers() as $player) {
                $player->setSpawn(Position::fromObject($team->getSpawn(), $arena->getLevel()));
                $player->teleport($player->getSpawn());
            }
        }

        $arena->bossbar->setSubTitle()->setTitle(count(array_filter($arena->getTeams(), function (BedwarsTeam $team): bool {
                return count($team->getPlayers()) > 0;
            })) . ' teams alive')->setPercentage(1);

        $this->getScheduler()->scheduleDelayedRepeatingTask(new SpawnItemsTask($arena), 100, 1);
    }

    public function stopArena(Arena $arena): void
    {
    }

    public function spawnBronze(Arena $arena)
    {
        $settings = $arena->getSettings();
        foreach ($settings->bronze ?? [] as $i => $spawn) {
            if ($arena->getLevel()->getBlockIdAt($spawn["x"], $spawn["y"], $spawn["z"]) !== BlockIds::HARDENED_CLAY) {
                $s = $settings->bronze;
                unset($s[$i]);
                $settings->bronze = $s;
                $settings->save();
                $this->getLogger()->debug(Loader::$prefix . "Der §cBronzeSpawner§6 wurde entfernt §f[§e" . (join(", ", $spawn) . "§f]§6 da es an dieser Stelle kein Spawner gibt!"));
                continue;
            }
            $v = new Vector3($spawn["x"] + 0.5, $spawn["y"] + 1, $spawn["z"] + 0.5);
            if (!$arena->getLevel()->isChunkLoaded($v->x >> 4, $v->z >> 4)) $arena->getLevel()->loadChunk($v->x >> 4, $v->z >> 4);
            if (count($arena->getLevel()->getChunkEntities($v->x >> 4, $v->z >> 4)) >= 50) {
                $last = null;
                foreach ($arena->getLevel()->getChunkEntities($v->x >> 4, $v->z >> 4) as $chunkEntity) {
                    if (!$chunkEntity instanceof ItemEntity) continue;
                    if ($chunkEntity->getItem()->getId() === ItemIds::BRICK) {
                        if ($last === null || $last->getItem()->getCount() >= 64) {
                            $last = $chunkEntity;
                            continue;
                        }
                        $last->getItem()->setCount($last->getItem()->getCount() + $chunkEntity->getItem()->getCount());
                        $chunkEntity->close();
                        $last->respawnToAll();
                    }
                }
                if ($last instanceof ItemEntity) {
                    $last->getLevel()->broadcastLevelEvent($last, LevelEventPacket::EVENT_PARTICLE_EYE_DESPAWN);
                    $last->getLevel()->broadcastLevelEvent($last, LevelEventPacket::EVENT_PARTICLE_EYE_DESPAWN);
                    $last->getLevel()->broadcastLevelEvent($last, LevelEventPacket::EVENT_PARTICLE_EYE_DESPAWN);
                }
            }

            $arena->getLevel()->dropItem($v, (new Item(ItemIds::BRICK))->setCount(2)->setCustomName(TextFormat::GOLD . "§f[§cBronze§f]"));
            $arena->getLevel()->broadcastLevelSoundEvent($v, LevelSoundEventPacket::SOUND_DROP_SLOT);
        }
    }

    public function spawnSilver(Arena $arena)
    {
        $settings = $arena->getSettings();
        foreach ($settings->silver ?? [] as $i => $spawn) {
            if ($arena->getLevel()->getBlockIdAt($spawn["x"], $spawn["y"], $spawn["z"]) !== BlockIds::IRON_BLOCK) {
                $s = $settings->silver;
                unset($s[$i]);
                $settings->set("silver", $s);
                $settings->save();
                $settings->reload();
                $this->getLogger()->debug(Loader::$prefix . "Der §7SilberSpawner§6 wurde entfernt §f[§e" . (join(", ", $spawn) . "§f]§6 da es an dieser Stelle kein Spawner gibt!"));
                continue;
            }
            $v = new Vector3($spawn["x"] + 0.5, $spawn["y"] + 1, $spawn["z"] + 0.5);
            if (!$arena->getLevel()->isChunkLoaded($v->x >> 4, $v->z >> 4)) $arena->getLevel()->loadChunk($v->x >> 4, $v->z >> 4);
            $arena->getLevel()->dropItem($v, (new Item(ItemIds::IRON_INGOT))->setCustomName(TextFormat::GRAY . "§f[§7Silber§f]"));
            $arena->getLevel()->broadcastLevelSoundEvent($v, LevelSoundEventPacket::SOUND_DROP_SLOT);
        }
    }

    public function spawnGold(Arena $arena)
    {
        $settings = $arena->getSettings();
        foreach ($settings->gold ?? [] as $i => $spawn) {
            if ($arena->getLevel()->getBlockIdAt($spawn["x"], $spawn["y"], $spawn["z"]) !== BlockIds::GOLD_BLOCK) {
                $s = $settings->gold;
                unset($s[$i]);
                $settings->gold = $s;
                $settings->save();
                $this->getLogger()->debug(Loader::$prefix . "Der §eGoldSpawner§6 wurde entfernt §f[§e" . (join(", ", $spawn) . "§f]§6 da es an dieser Stelle kein Spawner gibt!"));
                continue;
            }
            $v = new Vector3($spawn["x"] + 0.5, $spawn["y"] + 1, $spawn["z"] + 0.5);
            if (!$arena->getLevel()->isChunkLoaded($v->x >> 4, $v->z >> 4)) $arena->getLevel()->loadChunk($v->x >> 4, $v->z >> 4);
            $arena->getLevel()->dropItem($v, (new Item(ItemIds::GOLD_INGOT))->setCustomName(TextFormat::YELLOW . "§f[§eGold§f]"));
            $arena->getLevel()->broadcastLevelSoundEvent($v, LevelSoundEventPacket::SOUND_DROP_SLOT);
        }
    }

    public function onPlayerJoinTeam(Player $player): void
    {
        $player->setSpawn(Position::fromObject(API::getTeamOfPlayer($player)->getSpawn(), API::getArenaOfPlayer($player)->getLevel()));
        //Team color switching
        $player->getInventory()->addItem(Item::get(ItemIds::BED, API::getMetaByColor(API::getTeamOfPlayer($player)->getColor()))->setCustomName("§f[§6Wechsle das Team§f]"));
    }

    public function removeEntityOnArenaReset(Entity $entity): bool
    {
        return $entity instanceof ItemEntity || $entity instanceof PrimedTNT || $entity instanceof Arrow;
    }

    public function openShop(Player $player)
    {
        $menu = InvMenu::create(InvMenu::TYPE_CHEST)->setName(Loader::$prefix . "Shop")->readonly();
        $menu->getInventory()->setContents([
            Item::get(ItemIds::CHAIN_CHESTPLATE)->setCustomName("Rüstung"),
            Item::get(ItemIds::SANDSTONE)->setCustomName("Blöcke"),
            Item::get(ItemIds::STONE_PICKAXE)->setCustomName("Spitzhacken"),
            Item::get(ItemIds::STONE_SWORD)->setCustomName("Waffen"),
            Item::get(ItemIds::BOW)->setCustomName("Bogen"),
            Item::get(ItemIds::POTION)->setCustomName("Sonstiges"),
			Item::get(ItemIds::COOKED_BEEF)->setCustomName("Essen")
        ]);
        $menu->setListener(function (Player $player, Item $clicked, Item $clickedWith, SlotChangeAction $action): bool {
            switch ($clicked->getId()) {
                case ItemIds::CHAIN_CHESTPLATE:
                    $this->openShopArmor($player);
                    break;
                case ItemIds::SANDSTONE:
                    $this->openShopBlock($player);
                    break;
                case ItemIds::STONE_PICKAXE:
                    $this->openShopPickaxe($player);
                    break;
                case ItemIds::STONE_SWORD:
                    $this->openShopWeapons($player);
                    break;
                case ItemIds::BOW:
                    $this->openShopBow($player);
                    break;
                case ItemIds::POTION:
                    $this->openShopSpecial($player);
                    break;
            }
            return true;
        });
        $menu->send($player);
    }

    private function openShopArmor(Player $player)
    {
       $menu = InvMenu::create(InvMenu::TYPE_CHEST)->setName(Loader::$prefix . "Rüstungs Shop")->readonly();
        $menu->setInventoryCloseListener($this->subToMainShop());
        //enchanted and colored items
        $lc = $this->generateShopItem(Item::get(ItemIds::LEATHER_CAP), 1, 2 * 1, self::BRONZE);
        $lc = API::setCustomColor($lc, API::colorFromTextFormat(API::getTeamOfPlayer($player)->getColor()));
        $lp = $this->generateShopItem(Item::get(ItemIds::LEATHER_PANTS), 1, 2 * 1, self::BRONZE);
        $lp = API::setCustomColor($lp, API::colorFromTextFormat(API::getTeamOfPlayer($player)->getColor()));
        $lb = $this->generateShopItem(Item::get(ItemIds::LEATHER_BOOTS), 1, 2 * 1, self::BRONZE);
        $lb = API::setCustomColor($lb, API::colorFromTextFormat(API::getTeamOfPlayer($player)->getColor()));
        $c1 = $this->generateShopItem(Item::get(ItemIds::CHAIN_CHESTPLATE), 1, 2 * 1, self::SILVER);
        $c1->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Enchantment::PROTECTION)));
        $c1->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Enchantment::UNBREAKING)));
        $c2 = $this->generateShopItem(Item::get(ItemIds::CHAIN_CHESTPLATE), 1, 4 * 1, self::SILVER);
        $c2->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Enchantment::PROTECTION), 2));
        $c2->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Enchantment::UNBREAKING)));

        $menu->getInventory()->setContents([
            $lc,
            $this->generateShopItem(Item::get(ItemIds::CHAIN_CHESTPLATE), 1, 1 * 1, self::SILVER),
            $lp,
            $lb,
            $c1,
            $c2
        ]);
        $menu->setListener(function (Player $player, Item $clicked, Item $clickedWith, SlotChangeAction $action): bool {
            $this->buyItem($clicked, $player);
            return true;
        });
        $menu->send($player);
    }

    private function openShopBlock(Player $player)
    {
        $menu = InvMenu::create(InvMenu::TYPE_CHEST)->setName(Loader::$prefix . "Blöcke Shop")->readonly();
        $menu->setInventoryCloseListener($this->subToMainShop());
        $menu->getInventory()->setContents([
            $this->generateShopItem(Item::get(ItemIds::SANDSTONE), 4, 0.5 * 4, self::BRONZE),
            $this->generateShopItem(Item::get(ItemIds::SANDSTONE), 16, 0.5 * 16, self::BRONZE),
            $this->generateShopItem(Item::get(ItemIds::SANDSTONE), 32, 0.5 * 32, self::BRONZE),
            $this->generateShopItem(Item::get(ItemIds::SANDSTONE), 64, 0.5 * 64, self::BRONZE),
            $this->generateShopItem(Item::get(ItemIds::END_STONE), 1, 8 * 1, self::BRONZE),
            $this->generateShopItem(Item::get(ItemIds::END_STONE), 4, 8 * 4, self::BRONZE),
            $this->generateShopItem(Item::get(ItemIds::END_STONE), 16, 8 * 16, self::BRONZE),
            $this->generateShopItem(Item::get(ItemIds::END_STONE), 32, 8 * 32, self::BRONZE)
        ]);
        $menu->setListener(function (Player $player, Item $clicked, Item $clickedWith, SlotChangeAction $action): bool {
            $this->buyItem($clicked, $player);
            return true;
        });
        $menu->send($player);
    }

    private function openShopPickaxe(Player $player)
    {
        $menu = InvMenu::create(InvMenu::TYPE_CHEST)->setName(Loader::$prefix . "Spitzhacken Shop")->readonly();
        $menu->setInventoryCloseListener($this->subToMainShop());
        //enchanted items
        $ipe1 = $this->generateShopItem(Item::get(ItemIds::IRON_PICKAXE), 1, 8, self::SILVER);
        $ipe1->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Enchantment::EFFICIENCY)));
        $gpe2 = $this->generateShopItem(Item::get(ItemIds::GOLD_PICKAXE), 1, 4, self::GOLD);
        $gpe2->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Enchantment::EFFICIENCY), 2));
		
        $menu->getInventory()->setContents([
            $this->generateShopItem(Item::get(ItemIds::STONE_PICKAXE), 1, 16, self::BRONZE),
            $this->generateShopItem(Item::get(ItemIds::IRON_PICKAXE), 1, 4, self::SILVER),
            $ipe1,
            $gpe2
        ]);
        $menu->setListener(function (Player $player, Item $clicked, Item $clickedWith, SlotChangeAction $action): bool {
            $this->buyItem($clicked, $player);
            return true;
        });
        $menu->send($player);
    }

    private function openShopWeapons(Player $player)
    {
        $menu = InvMenu::create(InvMenu::TYPE_CHEST)->setName(Loader::$prefix . "Waffen Shop")->readonly();
        $menu->setInventoryCloseListener($this->subToMainShop());
        //enchanted items
        $kbs = $this->generateShopItem(Item::get(ItemIds::STICK), 1, 8, self::BRONZE);
        $kbs->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Enchantment::KNOCKBACK)));
		
        $kbs1 = $this->generateShopItem(Item::get(ItemIds::STICK), 1, 8, self::SILVER);
        $kbs1->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Enchantment::KNOCKBACK), 2));
		
        $kbs2 = $this->generateShopItem(Item::get(ItemIds::STICK), 1, 8, self::GOLD);
        $kbs2->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Enchantment::KNOCKBACK), 3));
		
        $gs1 = $this->generateShopItem(Item::get(ItemIds::GOLD_SWORD), 1, 2, self::SILVER);
        $gs1->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Enchantment::UNBREAKING)));
		
        $gs2 = $this->generateShopItem(Item::get(ItemIds::GOLD_SWORD), 1, 4, self::SILVER);
        $gs2->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Enchantment::UNBREAKING)));
        $gs2->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Enchantment::SHARPNESS)));;
        $gs3 = $this->generateShopItem(Item::get(ItemIds::GOLD_SWORD), 1, 8, self::SILVER);
        $gs3->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Enchantment::UNBREAKING)));
        $gs3->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Enchantment::SHARPNESS), 2));;
		
        $is1 = $this->generateShopItem(Item::get(ItemIds::IRON_SWORD), 1, 4, self::GOLD);
        $is1->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Enchantment::UNBREAKING)));
        $is1->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Enchantment::SHARPNESS)));
		
        $menu->getInventory()->setContents([
            $kbs,
	        $kbs1,
            $kbs2, 
            $gs1,
            $gs2,
            $gs3,
            $is1
        ]);
        $menu->setListener(function (Player $player, Item $clicked, Item $clickedWith, SlotChangeAction $action): bool {
            $this->buyItem($clicked, $player);
            return true;
        });
        $menu->send($player);
    }

    private function openShopBow(Player $player)
    {
        $menu = InvMenu::create(InvMenu::TYPE_CHEST)->setName(Loader::$prefix . "Bogen Shop")->readonly();
        $menu->setInventoryCloseListener($this->subToMainShop());
        //enchanted items
        $b1 = $this->generateShopItem(Item::get(ItemIds::BOW), 1, 4, self::GOLD);
        $b1->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Enchantment::UNBREAKING)));
		
        $b2 = $this->generateShopItem(Item::get(ItemIds::BOW), 1, 8, self::GOLD);
        $b2->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Enchantment::UNBREAKING)));
        $b2->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Enchantment::POWER)));
		
        $b3 = $this->generateShopItem(Item::get(ItemIds::BOW), 1, 16, self::GOLD);
        $b3->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Enchantment::UNBREAKING)));
        $b3->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Enchantment::INFINITY)));
		
        $menu->getInventory()->setContents([
            $b1,
            $b2,
            $b3,
            $this->generateShopItem(Item::get(ItemIds::ARROW), 4, 0.25 * 4, self::SILVER),
            $this->generateShopItem(Item::get(ItemIds::ARROW), 8, 0.25 * 8, self::SILVER),
            $this->generateShopItem(Item::get(ItemIds::ARROW), 16, 0.25 * 16, self::SILVER)
        ]);
        $menu->setListener(function (Player $player, Item $clicked, Item $clickedWith, SlotChangeAction $action): bool {
            $this->buyItem($clicked, $player);
            return true;
        });
        $menu->send($player);
    }

    private function openShopSpecial(Player $player)
    {
        $menu = InvMenu::create(InvMenu::TYPE_CHEST)->setName(Loader::$prefix . "Special Shop")->readonly();
        $menu->setInventoryCloseListener($this->subToMainShop());
        $menu->getInventory()->setContents([
            $this->generateShopItem(Item::get(ItemIds::ENDER_PEARL), 1, 4 * 1, self::GOLD),
            $this->generateShopItem(Item::get(ItemIds::TNT), 1, 16 * 1, self::SILVER),
            $this->generateShopItem(Item::get(ItemIds::POTION, Potion::STRONG_SWIFTNESS), 1, 4 * 1, self::SILVER),
            $this->generateShopItem(Item::get(ItemIds::POTION, Potion::STRONG_STRENGTH), 1, 2 * 1, self::GOLD),
            $this->generateShopItem(Item::get(ItemIds::SPLASH_POTION, Potion::SLOWNESS), 1, 4 * 1, self::SILVER),
            $this->generateShopItem(Item::get(ItemIds::SPLASH_POTION, Potion::WEAKNESS), 1, 2 * 1, self::GOLD),
            $this->generateShopItem(Item::get(ItemIds::SPLASH_POTION, Potion::POISON), 1, 2 * 1, self::GOLD),
            $this->generateShopItem(Item::get(ItemIds::BUCKET, 1), 1, 2 * 1, self::SILVER),
		    $this->generateShopItem(Item::get(ItemIds::COBWEB), 1, 32 * 1, self::BRONZE),
        ]);
        $menu->setListener(function (Player $player, Item $clicked, Item $clickedWith, SlotChangeAction $action): bool {
            $this->buyItem($clicked, $player);
            return true;
        });
        $menu->send($player);
    }

    private function subToMainShop(): \Closure
    {
        return function (Player $player, BaseFakeInventory $inventory) {
            $player->removeWindow($inventory);
            $this->openShop($player);
        };
    }

    private function generateShopItem(Item $item, int $size, int $value, string $valueType = self::GOLD): Item
    {
        $item->setCount($size);
        $item->setLore([$value . "x " . $valueType]);
        return $item;
    }

    private function buyItem(Item $item, Player $player): bool
    {
        [$value, $valueType] = explode("x ", $item->getLore()[0] ?? "0x " . self::GOLD);
        $value = intval($value);
        if ($value < 1) return false;
        $item = $item->setLore([]);
        switch ($valueType) {
            case self::BRONZE:
                $id = ItemIds::BRICK;
                break;
            case self::SILVER:
                $id = ItemIds::IRON_INGOT;
                break;
            case self::GOLD:
                $id = ItemIds::GOLD_INGOT;
                break;
            default:
                throw new \InvalidArgumentException("§cValueType ist Falsch");
        }
        $payment = Item::get($id, 0, $value);
        if ($player->getInventory()->contains($payment)) {
            $player->getInventory()->removeItem($payment);
            $player->getInventory()->addItem($item);
            return true;
        }
        return false;
    }
}
