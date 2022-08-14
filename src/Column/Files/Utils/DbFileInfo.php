<?php

declare(strict_types=1);

namespace PeskyORMColumns\Column\Files\Utils;

use PeskyORM\ORM\Column;
use PeskyORM\ORM\RecordInterface;
use PeskyORM\ORM\RecordValue;
use PeskyORMColumns\Column\Files\MetadataFilesColumn;
use PeskyORMColumns\Column\Files\MetadataImagesColumn;
use Ramsey\Uuid\Uuid;

class DbFileInfo
{
    
    /**
     * @var RecordValue
     */
    protected $valueContainer;
    /**
     * @var Column|MetadataFilesColumn|MetadataImagesColumn
     */
    protected $column;
    /**
     * @var RecordInterface
     */
    protected $record;
    
    /**
     * @var null|string
     */
    protected $fileExtension = null;
    /**
     * @var null|string
     */
    protected $fileNameWithoutExtension = null;
    /**
     * @var null|string
     */
    protected $fileNameWithExtension = null;
    /**
     * @var null|string
     */
    protected $originalFileNameWithExtension = null;
    /**
     * @var null|string
     */
    protected $originalFileNameWithoutExtension = null;
    /**
     * @var null|string
     */
    protected $uuid = null;
    /**
     * @var null|int
     */
    protected $position = null;
    
    protected $jsonMap = [
        'file_name' => 'fileNameWithoutExtension',
        'full_file_name' => 'fileNameWithExtension',
        'original_file_name' => 'originalFileNameWithoutExtension',
        'original_full_file_name' => 'originalFileNameWithExtension',
        'ext' => 'fileExtension',
        'uuid' => 'uuid',
        'position' => 'position',
    ];
    
    public function __construct(RecordValue $valueContainer)
    {
        $this->valueContainer = $valueContainer;
        $this->column = $valueContainer->getColumn();
        $this->record = $valueContainer->getRecord();
        $this->readFileInfo();
    }
    
    public function getRecord(): RecordInterface
    {
        return $this->record;
    }
    
    /**
     * @return MetadataFilesColumn
     */
    public function getColumn(): Column
    {
        return $this->column;
    }
    
    public function readFileInfo()
    {
        if ($this->record->existsInDb()) {
            $info = $this->getFileMetadataFromRecord();
            if (!empty($info)) {
                $this->update($info);
            }
        }
        return $this;
    }
    
    public function saveToFile()
    {
        if (!$this->record->existsInDb()) {
            throw new \UnexpectedValueException('Unable to save file info json file of non-existing object');
        }
        $metadata = $this->getFilesMetadataFromRecord();
        if (!isset($metadata[$this->column->getMetadataGroupName()])) {
            $metadata[$this->column->getMetadataGroupName()] = [];
        }
        
        $metadata[$this->column->getMetadataGroupName()][$this->column->getIndexInMetadataColumn()] = $this->getInfoForMetadataItem();
        
        $isRecordSavingRequired = !$this->record->isCollectingUpdates();
        if ($isRecordSavingRequired) {
            $this->record->begin();
        }
        $this->record->updateValue($this->column->getMetadataColumnName(), $metadata, false);
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
        $groupName = $this->column->getMetadataGroupName();
        $index = $this->column->getIndexInMetadataColumn();
        $info = $metadata[$groupName][$index] ?? null;
        return is_array($info) ? $info : null;
    }
    
    public function update(array $data)
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
    
    public function setUuid(string $uuid)
    {
        $this->uuid = $uuid;
        return $this;
    }
    
    protected function normalizeUuid()
    {
        if (!$this->uuid && ($this->fileNameWithExtension || $this->originalFileNameWithExtension)) {
            $this->uuid = Uuid::uuid4()->toString();
        }
        return $this;
    }
    
    public function getPosition(): int
    {
        return $this->position ?? $this->getColumn()->getIndexInMetadataColumn();
    }
    
    public function setPosition(int $position)
    {
        $this->position = $position;
        return $this;
    }
    
    protected function normalizePosition()
    {
        if (!$this->position) {
            $this->position = $this->getColumn()->getIndexInMetadataColumn();
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
        return $ext ? MimeTypesHelper::getMimeTypeForExtension($ext) : MimeTypesHelper::UNKNOWN;
    }
    
    public function setFileExtension(string $extension)
    {
        $this->fileExtension = $extension;
        return $this;
    }
    
    public function getFileNameWithoutExtension(): ?string
    {
        return $this->fileNameWithoutExtension;
    }
    
    public function setFileNameWithoutExtension(string $fileNameWithoutExtension)
    {
        $this->fileNameWithoutExtension = rtrim($fileNameWithoutExtension, '.');
        return $this;
    }
    
    public function getFileNameWithExtension(): ?string
    {
        return $this->fileNameWithExtension;
    }
    
    public function setFileNameWithExtension(string $fileNameWithExtension)
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
    
    public function setOriginalFileNameWithExtension(string $fileNameWithExtension)
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
    
    public function setOriginalFileNameWithoutExtension(string $fileNameWithoutExtension)
    {
        $this->originalFileNameWithoutExtension = rtrim($fileNameWithoutExtension, '.');
        return $this;
    }
    
    /**
     * @returns string
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function getFilePath()
    { //< do not add return type!!
        return $this->column->getFilePath($this->valueContainer);
    }
    
    public function getAbsoluteFileUrl(): string
    {
        return $this->column->getAbsoluteFileUrl($this->valueContainer);
    }
    
    public function isFileExists(): bool
    {
        if (empty($this->originalFileNameWithExtension) && empty($this->fileNameWithExtension)) {
            return false;
        }
        return $this->column->isFileExists($this->valueContainer);
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