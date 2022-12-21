<?php

declare(strict_types=1);

namespace PeskyORMColumns\TableColumn\Files\Utils;

use PeskyORM\ORM\Record\RecordInterface;
use PeskyORMColumns\TableColumn\Files\VirtualFilesColumnInterface;
use Ramsey\Uuid\Uuid;

abstract class DbFileInfoAbstract implements DbFileInfoInterface
{
    protected ?string $fileExtension = null;
    protected ?string $fileNameWithoutExtension = null;
    protected ?string $fileNameWithExtension = null;
    protected ?string $originalFileNameWithExtension = null;
    protected ?string $originalFileNameWithoutExtension = null;
    protected ?string $uuid = null;
    protected ?int $position = null;

    protected array $jsonMap = [
        'file_name' => 'fileNameWithoutExtension',
        'full_file_name' => 'fileNameWithExtension',
        'original_file_name' => 'originalFileNameWithoutExtension',
        'original_full_file_name' => 'originalFileNameWithExtension',
        'ext' => 'fileExtension',
        'uuid' => 'uuid',
        'position' => 'position',
    ];

    public function __construct(
        protected RecordInterface $record,
        protected VirtualFilesColumnInterface $column,
        protected string $metadataGroupName,
        protected int $fileIndexInMetadataGroup
    ) {
        $this->readFileInfo();
    }

    public function getRecord(): RecordInterface
    {
        return $this->record;
    }

    public function getColumn(): VirtualFilesColumnInterface
    {
        return $this->column;
    }

    public function readFileInfo(): static
    {
        if ($this->record->existsInDb()) {
            $info = $this->getFileMetadataFromRecord();
            if (!empty($info)) {
                $this->update($info);
            }
        }
        return $this;
    }

    public function saveToFile(): void
    {
        if (!$this->record->existsInDb()) {
            throw new \UnexpectedValueException(
                'Unable to save file info json file of non-existing object'
            );
        }
        $metadata = $this->getFilesMetadataFromRecord();
        if (!isset($metadata[$this->metadataGroupName])) {
            $metadata[$this->metadataGroupName] = [];
        }

        $metadata[$this->metadataGroupName][$this->fileIndexInMetadataGroup]
            = $this->getInfoForMetadataItem();

        $isRecordSavingRequired = !$this->record->isCollectingUpdates();
        if ($isRecordSavingRequired) {
            $this->record->begin();
        }
        $this->record->updateValue($this->getColumn()->getMetadataColumnName(), $metadata, false);
        if ($isRecordSavingRequired) {
            $this->record->commit();
        }
    }

    public function getInfoForMetadataItem(): array
    {
        $data = [];
        foreach ($this->jsonMap as $jsonKey => $paramName) {
            $method = 'get' . ucfirst($paramName);
            $value = $this->$method();
            if ($value !== null) {
                $data[$jsonKey] = $value;
            }
        }
        return $data;
    }

    protected function getFilesMetadataFromRecord(): array
    {
        return $this->record->existsInDb()
            ? (array)$this->record->getValue($this->column->getMetadataColumnName(), 'array')
            : [];
    }

    protected function getFileMetadataFromRecord(): ?array
    {
        if (!$this->record->existsInDb()) {
            return null;
        }
        $metadata = $this->getFilesMetadataFromRecord();
        $info = $metadata[$this->metadataGroupName][$this->fileIndexInMetadataGroup] ?? null;
        return is_array($info) ? $info : null;
    }

    public function update(array $data): static
    {
        foreach ($this->jsonMap as $jsonKey => $paramName) {
            if (array_key_exists($jsonKey, $data) && $data[$jsonKey] !== null) {
                $method = 'set' . ucfirst($paramName);
                $this->$method($data[$jsonKey]);
            }
        }
        $this->normalizeUuid();
        $this->normalizePosition();
        return $this;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): static
    {
        $this->uuid = $uuid;
        return $this;
    }

    protected function normalizeUuid(): static
    {
        if (
            !$this->uuid
            && (
                $this->fileNameWithExtension
                || $this->originalFileNameWithExtension
            )
        ) {
            $this->uuid = Uuid::uuid4()->toString();
        }
        return $this;
    }

    public function getPosition(): int
    {
        return $this->position ?? $this->fileIndexInMetadataGroup;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;
        return $this;
    }

    protected function normalizePosition(): static
    {
        if (!$this->position) {
            $this->position = $this->fileIndexInMetadataGroup;
        }
        return $this;
    }

    public function getFileExtension(): ?string
    {
        return $this->fileExtension;
    }

    public function getMimeType(): string
    {
        $ext = $this->getFileExtension();
        return $ext
            ? MimeTypesHelper::getMimeTypeForExtension($ext)
            : MimeTypesHelper::UNKNOWN;
    }

    public function setFileExtension(string $extension): static
    {
        $this->fileExtension = $extension;
        return $this;
    }

    public function getFileNameWithoutExtension(): ?string
    {
        return $this->fileNameWithoutExtension;
    }

    public function setFileNameWithoutExtension(string $fileNameWithoutExtension): static
    {
        $this->fileNameWithoutExtension = rtrim($fileNameWithoutExtension, '.');
        return $this;
    }

    public function getFileNameWithExtension(): ?string
    {
        return $this->fileNameWithExtension;
    }

    public function setFileNameWithExtension(string $fileNameWithExtension): static
    {
        $this->fileNameWithExtension = rtrim($fileNameWithExtension, '.');
        $this->normalizeUuid();
        return $this;
    }

    public function getOriginalFileNameWithExtension(): ?string
    {
        return empty($this->originalFileNameWithExtension)
            ? $this->getFileNameWithExtension()
            : $this->originalFileNameWithExtension;
    }

    public function hasOriginalFileNameWithExtension(): bool
    {
        return !empty($this->originalFileNameWithExtension);
    }

    public function setOriginalFileNameWithExtension(string $fileNameWithExtension): static
    {
        $this->originalFileNameWithExtension = rtrim($fileNameWithExtension, '.');
        $this->normalizeUuid();
        return $this;
    }

    public function getOriginalFileNameWithoutExtension(): ?string
    {
        return empty($this->originalFileNameWithoutExtension)
            ? $this->getFileNameWithoutExtension()
            : $this->originalFileNameWithoutExtension;
    }

    public function setOriginalFileNameWithoutExtension(
        string $fileNameWithoutExtension
    ): static {
        $this->originalFileNameWithoutExtension = rtrim($fileNameWithoutExtension, '.');
        return $this;
    }

    public function isFileExists(): bool
    {
        if (
            empty($this->originalFileNameWithExtension)
            && empty($this->fileNameWithExtension)
        ) {
            return false;
        }
        return $this->getColumn()->isFileExists($this->record);
    }

    public function getFileSize(): int
    {
        if ($this->isFileExists()) {
            return filesize($this->getFilePath());
        }
        return 0;
    }

    public function toArray(): array
    {
        return [
            'path' => $this->getFilePath(),
            'url' => $this->getAbsoluteFileUrl(),
            'file_name' => $this->getFileNameWithoutExtension(),
            'full_file_name' => $this->getFileNameWithExtension(),
            'ext' => $this->getFileExtension(),
            'position' => $this->getPosition(),
            'uuid' => $this->getUuid(),
        ];
    }

}