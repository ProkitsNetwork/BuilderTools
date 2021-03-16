<?php

/**
 * Copyright (C) 2018-2021  CzechPMDevs
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);

namespace czechpmdevs\buildertools\commands;

use czechpmdevs\buildertools\BuilderTools;
use czechpmdevs\buildertools\editors\Printer;
use pocketmine\command\CommandSender;
use pocketmine\Player;

class HollowCylinderCommand extends BuilderToolsCommand {

    public function __construct() {
        parent::__construct("/hcylinder", "Create hollow cylinder", null, ["/hcyl"]);
    }

    /** @noinspection PhpUnused */
    public function execute(CommandSender $sender, string $commandLabel, array $args) {
        if(!$this->testPermission($sender)) return;
        if(!$sender instanceof Player) {
            $sender->sendMessage("§cThis command can be used only in game!");
            return;
        }
        if(!isset($args[0])) {
            $sender->sendMessage("§cUsage: §7//hcylinder <id1:dmg1,id2:dmg2:...> [radius] [height]");
            return;
        }

        $radius = isset($args[1]) ? (int)($args[1]) : 5;
        $height = isset($args[2]) ? (int)($args[2]) : 8;

        $result = Printer::getInstance()->makeHollowCylinder($sender, $sender, $radius, $height, $args[0]);
        $sender->sendMessage(BuilderTools::getPrefix()."§aHollow cylinder created, $result->countBlocks blocks changed (Took $result->time seconds)");
    }
}