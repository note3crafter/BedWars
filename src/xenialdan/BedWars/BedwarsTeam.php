<?php

namespace xenialdan\BedWars;

use xenialdan\gameapi\Team;

class BedwarsTeam extends Team
{
    private $bedDestroyed = false;

    public function isBedDestroyed(): bool
    {
        return $this->bedDestroyed;
    }

    public function setBedDestroyed(bool $bedDestroyed = true): void
    {
        $this->bedDestroyed = $bedDestroyed;
    }
}
