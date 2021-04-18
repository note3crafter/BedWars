<?php

namespace xenialdan\customui\elements;

use pocketmine\Player;

class Label extends UIElement
{

    public function __construct($text)
    {
        $this->text = $text;
    }

    final public function jsonSerialize()
    {
        return [
            "type" => "label",
            "text" => $this->text
        ];
    }

    final public function handle($value, Player $player)
    {
        return $this->text;
    }

}
