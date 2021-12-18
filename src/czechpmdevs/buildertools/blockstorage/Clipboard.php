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

namespace czechpmdevs\buildertools\blockstorage;

use InvalidArgumentException;
use pocketmine\math\Vector3;
use pocketmine\world\World;
use function pack;
use function unpack;

class Clipboard extends BlockArray {

	protected Vector3 $relativePosition;

	public string $compressedPlayerPosition;

	/**
	 * @param bool $modifyBuffer If it's false, only relative position will be changed.
	 */
	public function addVector3(Vector3 $vector3, bool $modifyBuffer = false): BlockArray {
		if(!$vector3->floor()->equals($vector3)) {
			throw new InvalidArgumentException("Vector3 coordinates must be integer.");
		}

		if(isset($this->relativePosition)) {
			$clipboard = clone $this;
			$clipboard->relativePosition->addVector($vector3);

			return $clipboard;
		}

		return parent::addVector3($vector3);
	}

	public function getRelativePosition(): Vector3 {
		return $this->relativePosition;
	}

	/**
	 * @return $this
	 */
	public function setRelativePosition(Vector3 $relativePosition): Clipboard {
		$this->relativePosition = $relativePosition->floor();

		return $this;
	}

	public function compress(bool $cleanDecompressed = true): void {
		parent::compress($cleanDecompressed);

		if(!isset($this->relativePosition)) {
			return;
		}

		$vector3 = $this->getRelativePosition();
		$this->compressedPlayerPosition = pack("q", World::blockHash($vector3->getFloorX(), $vector3->getFloorY(), $vector3->getFloorZ()));

		unset($this->relativePosition);
	}

	public function decompress(bool $cleanCompressed = true): void {
		parent::decompress($cleanCompressed);

		if(!isset($this->compressedPlayerPosition)) {
			return;
		}

		/** @phpstan-ignore-next-line */
		World::getBlockXYZ((int)(unpack("q", $this->compressedPlayerPosition)[1]), $x, $y, $z);
		$this->relativePosition = new Vector3($x, $y, $z);
	}

	public static function fromBlockArray(BlockArray $blockArray, Vector3 $playerPosition): Clipboard {
		$selectionData = new Clipboard();
		$selectionData->setRelativePosition($playerPosition);
		$selectionData->setWorld($blockArray->getWorld());
		$selectionData->blocks = $blockArray->getBlockArray();
		$selectionData->coords = $blockArray->getCoordsArray();

		return $selectionData;
	}
}