<?php


namespace xenialdan\gameapi\gamerule;


class FloatGameRule extends GameRule//Not used yet
{
    public $value;

    public function __construct(string $name, float $value)
    {
        $this->name = $name;
        $this->value = $value;
    }

    public function getValue(): float
    {
        return $this->value;
    }

    public function setValue(float $value): void
    {
        $this->value = $value;
    }

    public function getType(): int
    {
        return GameRule::TYPE_FLOAT;
    }
}