<?php

declare(strict_types=1);

namespace czechpmdevs\buildertools\async\session;

use pocketmine\math\Vector3;
use pocketmine\nbt\BigEndianNBTStream;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\scheduler\AsyncTask;
use function file_put_contents;

class OfflineSessionSaveTask extends AsyncTask {

    /** @var string */
    public string $dataFolder;
    /** @var string */
    public string $playerId;

    /** @var string */
    public string $clipboard;
    /** @var Vector3|null */
    public ?Vector3 $clipboardRelativePosition;
    /** @var bool */
    public bool $clipboardDuplicateDetectionEnabled;

    /** @var string */
    public string $undoData;
    /** @var string */
    public string $redoData;

    public function __construct(string $dataFolder, string $playerId, string $clipboard, ?Vector3 $clipboardRelativePosition, bool $clipboardDuplicateDetectionEnabled, string $undoData, string $redoData) {
        $this->dataFolder = $dataFolder;
        $this->playerId = $playerId;
        $this->clipboard = $clipboard;
        $this->clipboardRelativePosition = $clipboardRelativePosition;
        $this->clipboardDuplicateDetectionEnabled = $clipboardDuplicateDetectionEnabled;
        $this->undoData = $undoData;
        $this->redoData = $redoData;
    }

    /** @noinspection PhpUnused */
    public function onRun() {
        $data = new CompoundTag();
        $save = false;

        if($this->clipboard != "") {
            $data->setString("Clipboard", $this->clipboard);
            $data->setIntArray("ClipboardRelativePosition", [
                $this->clipboardRelativePosition->getX(),
                $this->clipboardRelativePosition->getY(),
                $this->clipboardRelativePosition->getZ()
            ]);
            $data->setByte("ClipboardDuplicateDetection", (int)$this->clipboardDuplicateDetectionEnabled);

            $save = true;
        }

        if($this->undoData != "") {
            $data->setString("UndoData", $this->undoData);

            $save = true;
        }

        if($this->redoData != "") {
            $data->setString("RedoData", $this->redoData);

            $save = true;
        }

        if(!$save) {
            return;
        }

        $stream = new BigEndianNBTStream();
        $data->write($stream);

        file_put_contents($this->dataFolder . "offline_data" . DIRECTORY_SEPARATOR . $this->playerId . ".btsession", $stream->buffer);
    }
}