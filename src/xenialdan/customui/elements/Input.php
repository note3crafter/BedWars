<?php

namespace xenialdan\customui\elements;

use pocketmine\Player;

class Input extends UIElement
{
    protected $placeholder = '';
    protected $defaultText = '';

    public function __construct($text, $placeholder, $defaultText = '')
    {
        $this->text = $text;
        $this->placeholder = $placeholder;
        $this->defaultText = $defaultText;
    }

    final public function jsonSerialize()
    {
        return [
            "type" => "input",
            "text" => $this->text,
            "placeholder" => $this->placeholder,
            "default" => $this->defaultText
        ];
    }

    public function handle($value, Player $player)
    {
        return $value;
    }

}
