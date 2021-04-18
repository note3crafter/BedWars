<?php

namespace xenialdan\customui\elements;

use pocketmine\Player;

abstract class UIElement implements \JsonSerializable
{

    protected $text = '';

    public function jsonSerialize()
    {
        return [];
    }

    abstract public function handle($value, Player $player);

    public function getText(): string
    {
        return $this->text;
    }

}
