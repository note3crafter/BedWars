<?php

namespace xenialdan\customui\elements;

use pocketmine\Player;

class Toggle extends UIElement
{

    protected $defaultValue = false;

    public function __construct($text, bool $value = false)
    {
        $this->text = $text;
        $this->defaultValue = $value;
    }

    public function setDefaultValue(bool $value)
    {
        $this->defaultValue = $value;
    }

    public function jsonSerialize()
    {
        return [
            "type" => "toggle",
            "text" => $this->text,
            "default" => $this->defaultValue
        ];
    }

    public function handle($value, Player $player)
    {
        return $value;
    }

}
