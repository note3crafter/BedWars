<?php

namespace xenialdan\customui;

use pocketmine\OfflinePlayer;
use pocketmine\Player;
use pocketmine\plugin\Plugin;
use xenialdan\customui\windows\CustomUI;

class API
{
    private static $UIs = [];

    public static function addUI(Plugin $plugin, CustomUI &$ui)
    {
        $ui->setID(count(self::$UIs[$plugin->getName()] ?? []));
        $id = $ui->getID();
        self::$UIs[$plugin->getName()][$id] = $ui;
        $ui = null;
        return $id;
    }

    public static function resetUIs(Plugin $plugin)
    {
        self::$UIs[$plugin->getName()] = [];
    }

    public static function getAllUIs(): array
    {
        return self::$UIs;
    }

    public static function getPluginUIs(Plugin $plugin): array
    {
        return self::$UIs[$plugin->getName()];
    }

    public static function getPluginUI(Plugin $plugin, int $id): CustomUI
    {
        return self::$UIs[$plugin->getName()][$id];
    }

    public static function handle(Plugin $plugin, int $id, $response, Player $player)
    {
        $ui = self::getPluginUIs($plugin)[$id];
        var_dump($ui);
        return $ui->handle($response, $player) ?? "";
    }

    public static function showUI(CustomUI $ui, Player $player)
    {
        $player->sendForm($ui);
    }

    public static function showUIbyID(Plugin $plugin, int $id, Player $player)
    {
        $ui = self::getPluginUIs($plugin)[$id];
        $player->sendForm($ui);
    }

    public static function playerArrayToNameArray(array $players): array
    {
        $return = array_map(function ($player) {
            return $player->getName();
        }, $players);
        sort($return, SORT_NATURAL);
        return $return;
    }
}
