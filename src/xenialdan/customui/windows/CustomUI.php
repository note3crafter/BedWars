<?php

namespace xenialdan\customui\windows;

use pocketmine\form\Form;
use pocketmine\Player;
use xenialdan\customui\elements\Button;
use xenialdan\customui\elements\UIElement;

interface CustomUI extends Form
{

    public function close(Player $player);

    public function getTitle();

    public function getContent(): array;

    public function getElement(int $index);

    public function setElement(UIElement $element, int $index);

    public function setID(int $id);

    public function getID(): int;

    public function getCallable(): ?callable;

    public function setCallable($callable): void;
}
