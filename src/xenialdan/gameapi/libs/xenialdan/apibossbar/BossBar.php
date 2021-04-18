<?php


namespace xenialdan\gameapi\libs\xenialdan\apibossbar;

use pocketmine\entity\Attribute;
use pocketmine\entity\AttributeMap;
use pocketmine\entity\DataPropertyManager;
use pocketmine\entity\Entity;
use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\network\mcpe\protocol\BossEventPacket;
use pocketmine\network\mcpe\protocol\RemoveActorPacket;
use pocketmine\network\mcpe\protocol\SetActorDataPacket;
use pocketmine\network\mcpe\protocol\UpdateAttributesPacket;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\MainLogger;

class BossBar
{
    protected const NETWORK_ID = Entity::SLIME;

    private $players = [];
    private $title = "";
    private $subTitle = "";
    public $entityId = null;
    private $attributeMap;
    protected $propertyManager;

    public function __construct()
    {
        $this->entityId = Entity::$entityCount++;
        $this->attributeMap = new AttributeMap();
        $this->getAttributeMap()->addAttribute(Attribute::getAttribute(Attribute::HEALTH)->setMaxValue(100.0)->setMinValue(0.0)->setDefaultValue(100.0));
        $this->propertyManager = new DataPropertyManager();
        $this->propertyManager->setLong(Entity::DATA_FLAGS, 0
            ^ 1 << Entity::DATA_FLAG_SILENT
            ^ 1 << Entity::DATA_FLAG_INVISIBLE
            ^ 1 << Entity::DATA_FLAG_NO_AI
            ^ 1 << Entity::DATA_FLAG_FIRE_IMMUNE);
        $this->propertyManager->setShort(Entity::DATA_MAX_AIR, 400);
        $this->propertyManager->setString(Entity::DATA_NAMETAG, $this->getFullTitle());
        $this->propertyManager->setLong(Entity::DATA_LEAD_HOLDER_EID, -1);
        $this->propertyManager->setFloat(Entity::DATA_SCALE, 0);
        $this->propertyManager->setFloat(Entity::DATA_BOUNDING_BOX_WIDTH, 0.0);
        $this->propertyManager->setFloat(Entity::DATA_BOUNDING_BOX_HEIGHT, 0.0);
    }

    public function getPlayers(): array
    {
        return $this->players;
    }

    public function addPlayers(array $players): BossBar
    {
        foreach ($players as $player) {
            $this->addPlayer($player);
        };
        return $this;
    }

    public function addPlayer(Player $player): BossBar
    {
        if (isset($this->players[$player->getId()])) return $this;
        if(!$this->getEntity() instanceof Player) $this->sendSpawnPacket([$player]);
        $this->sendBossPacket([$player]);
        $this->players[$player->getId()] = $player;
        return $this;
    }

    public function removePlayer(Player $player): BossBar
    {
        if (!isset($this->players[$player->getId()])) {
            MainLogger::getLogger()->debug("Removed player that was not added to the boss bar (" . $this . ")");
            return $this;
        }
        $this->sendRemoveBossPacket([$player]);
        unset($this->players[$player->getId()]);
        return $this;
    }

    public function removePlayers(array $players): BossBar
    {
        foreach ($players as $player) {
            $this->removePlayer($player);
        };
        return $this;
    }

    public function removeAllPlayers(): BossBar
    {
        foreach ($this->getPlayers() as $player) $this->removePlayer($player);
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title = ""): BossBar
    {
        $this->title = $title;
        $this->sendEntityDataPacket($this->getPlayers());
        $this->sendBossTextPacket($this->getPlayers());
        return $this;
    }

    public function getSubTitle(): string
    {
        return $this->subTitle;
    }

    public function setSubTitle(string $subTitle = ""): BossBar
    {
        $this->subTitle = $subTitle;
        $this->sendEntityDataPacket($this->getPlayers());
        $this->sendBossTextPacket($this->getPlayers());
        return $this;
    }

    public function getFullTitle(): string
    {
        $text = $this->title;
        if (!empty($this->subTitle)) {
            $text .= "\n\n" . $this->subTitle;
        }
        return mb_convert_encoding($text, 'UTF-8');
    }

    public function setPercentage(float $percentage): BossBar
    {
        $percentage = (float)max(0.01, $percentage);
        $this->getAttributeMap()->getAttribute(Attribute::HEALTH)->setValue($percentage* $this->getAttributeMap()->getAttribute(Attribute::HEALTH)->getMaxValue(), true, true);
        $this->sendAttributesPacket($this->getPlayers());
        $this->sendBossHealthPacket($this->getPlayers());

        return $this;
    }

    public function getPercentage(): float
    {
        return $this->getAttributeMap()->getAttribute(Attribute::HEALTH)->getValue()/100;
    }

    public function hideFrom(array $players): void
    {
        $pk = new BossEventPacket();
        $pk->bossEid = $this->entityId;
        $pk->eventType = BossEventPacket::TYPE_HIDE;
        Server::getInstance()->broadcastPacket($players, $pk);
    }

    public function hideFromAll(): void
    {
        $this->hideFrom($this->getPlayers());
    }

    public function showTo(array $players): void
    {
        $pk = new BossEventPacket();
        $pk->bossEid = $this->entityId;
        $pk->eventType = BossEventPacket::TYPE_SHOW;
        Server::getInstance()->broadcastPacket($players, $this->addDefaults($pk));
    }

    public function showToAll(): void
    {
        $this->showTo($this->getPlayers());
    }

    public function getEntity(): ?Entity
    {
        return Server::getInstance()->findEntity($this->entityId);
    }

    public function setEntity(?Entity $entity = null): BossBar
    {
        if ($entity instanceof Entity && ($entity->isClosed() || $entity->isFlaggedForDespawn())) throw new \InvalidArgumentException("Entity $entity can not be used since its not valid anymore (closed or flagged for despawn)");
        if ($this->getEntity() instanceof Entity && !$entity instanceof Player) $this->getEntity()->flagForDespawn();
        else {
            $pk = new RemoveActorPacket();
            $pk->entityUniqueId = $this->entityId;
            Server::getInstance()->broadcastPacket($this->getPlayers(), $pk);
        }
        if ($entity instanceof Entity) {
            $this->entityId = $entity->getId();
            $this->attributeMap = $entity->getAttributeMap();//TODO try some kind of auto-updating reference
            $this->getAttributeMap()->addAttribute($entity->getAttributeMap()->getAttribute(Attribute::HEALTH));//TODO Auto-update bar for entity? Would be cool, so the api can be used for actual bosses
            $this->propertyManager = $entity->getDataPropertyManager();
            if(!$entity instanceof Player) $entity->despawnFromAll();
        } else {
            $this->entityId = Entity::$entityCount++;
        }
        if(!$entity instanceof Player) $this->sendSpawnPacket($this->getPlayers());
        $this->sendBossPacket($this->getPlayers());
        return $this;
    }

    public function resetEntity(bool $removeEntity = false): BossBar
    {
        if ($removeEntity && $this->getEntity() instanceof Entity && !$this->getEntity() instanceof Player) $this->getEntity()->close();
        return $this->setEntity();
    }

    protected function sendSpawnPacket(array $players): void
    {
        $pk = new AddActorPacket();
        $pk->entityRuntimeId = $this->entityId;
        $pk->type = $this->getEntity() instanceof Entity ? $this->getEntity()::NETWORK_ID : static::NETWORK_ID;
        $pk->attributes = $this->getAttributeMap()->getAll();
        var_dump($this->getPropertyManager()->getAll());
        $pk->metadata = $this->getPropertyManager()->getAll();
        foreach ($players as $player) {
            $pkc = clone $pk;
            $pkc->position = $player->asVector3()->subtract(0, 28);
            $player->dataPacket($pkc);
        }
    }

    protected function sendBossPacket(array $players): void
    {
        $pk = new BossEventPacket();
        $pk->bossEid = $this->entityId;
        $pk->eventType = BossEventPacket::TYPE_SHOW;
        $pk->title = $this->getFullTitle();
        $pk->healthPercent = $this->getPercentage();
        Server::getInstance()->broadcastPacket($players, $this->addDefaults($pk));
    }

    protected function sendRemoveBossPacket(array $players): void
    {
        $pk = new BossEventPacket();
        $pk->bossEid = $this->entityId;
        $pk->eventType = BossEventPacket::TYPE_HIDE;
        Server::getInstance()->broadcastPacket($players, $pk);
    }

    protected function sendBossTextPacket(array $players): void
    {
        $pk = new BossEventPacket();
        $pk->bossEid = $this->entityId;
        $pk->eventType = BossEventPacket::TYPE_TITLE;
        $pk->title = $this->getFullTitle();
        Server::getInstance()->broadcastPacket($players, $pk);
    }

    protected function sendAttributesPacket(array $players): void
    {
        $pk = new UpdateAttributesPacket();
        $pk->entityRuntimeId = $this->entityId;
        $pk->entries = $this->getAttributeMap()->needSend();
        Server::getInstance()->broadcastPacket($players, $pk);
    }

    protected function sendEntityDataPacket(array $players): void
    {
        $this->getPropertyManager()->setString(Entity::DATA_NAMETAG, $this->getFullTitle());
        $pk = new SetActorDataPacket();
        $pk->metadata = $this->getPropertyManager()->getDirty();
        $pk->entityRuntimeId = $this->entityId;
        Server::getInstance()->broadcastPacket($players, $pk);

        $this->getPropertyManager()->clearDirtyProperties();
    }

    protected function sendBossHealthPacket(array $players): void
    {
        $pk = new BossEventPacket();
        $pk->bossEid = $this->entityId;
        $pk->eventType = BossEventPacket::TYPE_HEALTH_PERCENT;
        $pk->healthPercent = $this->getPercentage();
        Server::getInstance()->broadcastPacket($players, $pk);
    }

    private function addDefaults(BossEventPacket $pk):BossEventPacket{
        $pk->title = $this->getFullTitle();
        $pk->healthPercent = $this->getPercentage();
        $pk->unknownShort = 1;
        $pk->color = 0;//Does not function anyways
        $pk->overlay = 0;//Neither. Typical for Mojang: Copy-pasted from Java edition
        return $pk;
    }

    public function __toString(): string
    {
        return __CLASS__ . " ID: $this->entityId, Players: " . count($this->players) . ", Title: \"$this->title\", Subtitle: \"$this->subTitle\", Percentage: \"".$this->getPercentage()."\"";
    }

    public function getAttributeMap(Player $player = null): AttributeMap
    {
        return $this->attributeMap;
    }

    protected function getPropertyManager(): DataPropertyManager
    {
        return $this->propertyManager;
    }

    //TODO callable on client2server register/unregister request
}