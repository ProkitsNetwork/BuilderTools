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

namespace czechpmdevs\buildertools\editors;

use czechpmdevs\buildertools\blockstorage\BlockArray;
use czechpmdevs\buildertools\blockstorage\SelectionData;
use czechpmdevs\buildertools\BuilderTools;
use czechpmdevs\buildertools\ClipboardManager;
use czechpmdevs\buildertools\editors\object\EditorResult;
use czechpmdevs\buildertools\editors\object\FillSession;
use czechpmdevs\buildertools\math\BlockGenerator;
use czechpmdevs\buildertools\utils\RotationUtil;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\utils\SingletonTrait;
use pocketmine\world\World;
use function abs;
use function max;
use function microtime;
use function min;
use function round;

class Copier {
    use SingletonTrait;

    public const DIRECTION_PLAYER = 0;
    public const DIRECTION_UP = 1;
    public const DIRECTION_DOWN = 2;

    public function copy(Vector3 $pos1, Vector3 $pos2, Player $player): EditorResult {
        $startTime = microtime(true);

        $clipboard = (new SelectionData())->setPlayerPosition($player->getPosition()->ceil());
        $level = $player->getWorld();

        $i = 0;
        foreach (BlockGenerator::fillCuboid($pos1, $pos2) as [$x, $y, $z]) {
            $block = $level->getBlockAt($x, $y, $z, true, false);
            $clipboard->addBlockAt($x, $y, $z, $block->getId(), $block->getMeta());

            $i++;
        }

        ClipboardManager::saveClipboard($player, $clipboard);

        return EditorResult::success($i, microtime(true) - $startTime);
    }

    public function cut(Vector3 $pos1, Vector3 $pos2, Player $player): EditorResult {
        $startTime = microtime(true);

        $clipboard = (new SelectionData())->setPlayerPosition($player->getPosition()->ceil());

        $minX = (int)min($pos1->getX(), $pos2->getX());
        $maxX = (int)max($pos1->getX(), $pos2->getX());
        $minZ = (int)min($pos1->getZ(), $pos2->getZ());
        $maxZ = (int)max($pos1->getZ(), $pos2->getZ());

        $minY = (int)max(min($pos1->getY(), $pos2->getY(), World::Y_MAX), 0);
        $maxY = (int)min(max($pos1->getY(), $pos2->getY(), 0), World::Y_MAX);

        $fillSession = new FillSession($player->getWorld(), false);
        $fillSession->setDimensions($minX, $maxX, $minZ, $maxZ);

        for($x = $minX; $x <= $maxX; ++$x) {
            for ($z = $minZ; $z <= $maxZ; ++$z) {
                for ($y = $minY; $y <= $maxY; ++$y) {
                    $fillSession->getBlockAt($x, $y, $z, $id, $meta);
                    $clipboard->addBlockAt($x, $y, $z, $id, $meta);

                    $fillSession->setBlockAt($x, $y, $z, 0, 0);
                }
            }
        }

        $fillSession->reloadChunks($player->getWorld());
        ClipboardManager::saveClipboard($player, $clipboard);

        return EditorResult::success($fillSession->getBlocksChanged(), microtime(true) - $startTime);
    }

    public function merge(Player $player): EditorResult {
        if(!ClipboardManager::hasClipboardCopied($player)) {
            return EditorResult::error("Clipboard is empty");
        }

        /** @var SelectionData $clipboard */
        $clipboard = ClipboardManager::getClipboard($player);
        $clipboard->setLevel($player->getWorld());

        /** @var Vector3 $playerPosition */
        $playerPosition = $clipboard->getPlayerPosition();

        return Filler::getInstance()->merge($player, $clipboard, $player->getPosition()->ceil()->subtractVector($playerPosition));
    }

    public function paste(Player $player): EditorResult {
        if(!ClipboardManager::hasClipboardCopied($player)) {
            return EditorResult::error("Clipboard is empty");
        }

        /** @var SelectionData $clipboard */
        $clipboard = ClipboardManager::getClipboard($player);
        $clipboard->setLevel($player->getWorld());

        /** @var Vector3 $playerPosition */
        $playerPosition = $clipboard->getPlayerPosition();

        return Filler::getInstance()->fill($player, $clipboard, $player->getPosition()->ceil()->subtractVector($playerPosition));
    }

    public function rotate(Player $player, int $axis, int $rotation): void {
        if(!ClipboardManager::hasClipboardCopied($player)) {
            $player->sendMessage(BuilderTools::getPrefix() . "§cUse //copy first!");
            return;
        }

        /** @var SelectionData $clipboard */
        $clipboard = ClipboardManager::getClipboard($player);

        ClipboardManager::saveClipboard($player, RotationUtil::rotate($clipboard, $axis, $rotation));
    }

    public function stack(Player $player, int $pasteCount, int $mode = Copier::DIRECTION_PLAYER): void {
        if (!ClipboardManager::hasClipboardCopied($player)) {
            $player->sendMessage(BuilderTools::getPrefix() . "§cUse //copy first!");
            return;
        }

        /** @var SelectionData $clipboard */
        $clipboard = ClipboardManager::getClipboard($player);

        $updateData = new BlockArray();
        $updateData->setLevel($player->getWorld());

        /** @var Vector3 $playerPosition */
        $playerPosition = $clipboard->getPlayerPosition();

        $center = $playerPosition->ceil(); // Why there was +vec(1, 0, 1)?
        switch ($mode) {
            case self::DIRECTION_PLAYER:
                $d = $player->getDirectionPlane()->getFloorX(); // TODO - Is that substitute for Player->getDirection()?
                switch ($d) {
                    case 0:
                    case 2:
                        $metadata = $clipboard->getSizeData();
                        $minX = $metadata->minX;
                        $maxX = $metadata->maxX;

                        $length = (int)(round(abs($maxX - $minX)) + 1);
                        if ($d == 2) $length = -$length;

                        for ($pasted = 0; $pasted < $pasteCount; ++$pasted) {
                            $addX = $length * $pasted;
                            while ($clipboard->hasNext()) {
                                $clipboard->readNext($x, $y, $z, $id, $meta);
                                $updateData->addBlock($center->add($x + $addX, $y, $z), $id, $meta);
                            }
                        }
                        break;
                    case 1:
                    case 3:
                        $metadata = $clipboard->getSizeData();
                        $minZ = $metadata->minZ;
                        $maxZ = $metadata->maxZ;

                        $length = (int)(round(abs($maxZ - $minZ)) + 1);
                        if ($d == 3) $length = -$length;

                        for ($pasted = 0; $pasted < $pasteCount; ++$pasted) {
                            $addZ = $length * $pasted;
                            while ($clipboard->hasNext()) {
                                $clipboard->readNext($x, $y, $z, $id, $meta);
                                $updateData->addBlock($center->add($x, $y, $z + $addZ), $id, $meta);
                            }
                        }
                        break;
                }
                break;
            case self::DIRECTION_UP:
            case self::DIRECTION_DOWN:
                $metadata = $clipboard->getSizeData();
                $minY = $metadata->minY;
                $maxY = $metadata->maxY;

                $length = (int)(round(abs($maxY - $minY))+1);
                if ($mode == self::DIRECTION_DOWN) $length = -$length;

                for ($pasted = 0; $pasted <= $pasteCount; ++$pasted) {
                    $addY = $length * $pasted;
                    while ($clipboard->hasNext()) {
                        $clipboard->readNext($x, $y, $z, $id, $meta);
                        $updateData->addBlock($center->add($x, $y + $addY, $z), $id, $meta);
                    }
                }
                break;
        }

        Filler::getInstance()->fill($player, $updateData);
        $player->sendMessage(BuilderTools::getPrefix() . "§aCopied area stacked!");
    }

    public function move(Vector3 $pos1, Vector3 $pos2, Vector3 $add, Player $player): EditorResult {
        $start = microtime(true);

        $blocks = new BlockArray();
        $blocks->setLevel($player->getWorld());

        // Old blocks (to remove)
        $blockPositions = [];

        // Add new blocks
        /** @var Vector3 $vector3 */
        foreach (BlockGenerator::fillCuboid($pos1, $pos2) as $vector3) {
            if(($block = $player->getWorld()->getBlock($vector3))->getId() != 0) {
                /** @phpstan-ignore-next-line */
                $blockPositions[] = World::blockHash($vector3->getX(), $vector3->getY(), $vector3->getZ()); // BlockGenerator::fillCuboid returns only vectors with int coords
                $blocks->addBlock($add->addVector($vector3), $block->getId(), $block->getMeta());
            }
        }

        // Remove old blocks
        foreach ($blockPositions as $hash) {
            World::getBlockXYZ($hash, $x, $y, $z);

            $blocks->addBlock(new Vector3($x, $y, $z), 0, 0);
        }

        $result = Filler::getInstance()->fill($player, $blocks, null, true, false, true);
        return EditorResult::success($result->getBlocksChanged(), microtime(true) - $start);
    }
}