<?php

namespace xenialdan\gameapi\libs\xenialdan\apibossbar;

use pocketmine\plugin\Plugin;

class API{

    public static function load(Plugin $plugin){
        //Handle packets related to boss bars
        PacketListener::register($plugin);
    }
}