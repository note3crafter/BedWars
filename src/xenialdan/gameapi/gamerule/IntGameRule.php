<?php


namespace xenialdan\gameapi\gamerule;


class IntGameRule extends GameRule
{
    public $value;

    public function __construct(string $name, int $value)
    {
        $this->name = $name;
        $this->value = $value;
    }

    public function getValue(): int
    {
        return $this->value;
    }

    public function setValue(int $value): void
    {
        $this->value = $value;
    }

    public function getType(): int
    {
        return GameRule::TYPE_INT;
    }
}