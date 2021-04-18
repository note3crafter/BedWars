<?php

declare(strict_types=1);

namespace xenialdan\customui\windows;

use pocketmine\Player;

trait CallableTrait
{
    private $callable;
    private $callableClose;

    public function getCallable(): ?callable
    {
        return $this->callable;
    }

    public function setCallable($callable = null): void
    {
        $this->callable = $callable;
    }

    public function getCallableClose(): ?callable
    {
        return $this->callableClose;
    }

    public function setCallableClose($callableClose = null): void
    {
        $this->callableClose = $callableClose;
    }

    final public function close(Player $player)
    {
        $callable = $this->getCallableClose();
        if ($callable !== null) {
            $callable($player);
        }
    }

}