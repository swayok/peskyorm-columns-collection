<?php

declare(strict_types=1);

namespace PeskyORMColumns\TableColumn\Files\Utils;

use PeskyORM\ORM\Record\RecordInterface;
use PeskyORMColumns\TableColumn\Files\VirtualImagesColumnInterface;
use Swayok\Utils\File;
use Swayok\Utils\ImageUtils;

class DbImageFileInfo extends DbFileInfoAbstract
{
    protected array $filesNames = [];

    public function __construct(
        RecordInterface $record,
        VirtualImagesColumnInterface $column,
        string $metadataGroupName,
        int $fileIndexInMetadataGroup
    ) {
        $this->jsonMap['files_names'] = 'filesNames';
        parent::__construct(
            $record,
            $column,
            $metadataGroupName,
            $fileIndexInMetadataGroup
        );
    }

    /** @noinspection PhpRedundantMethodOverrideInspection */
    public function getColumn(): VirtualImagesColumnInterface
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return parent::getColumn();
    }

    public function getFilesNames(): array
    {
        return $this->filesNames;
    }

    public function setFilesNames(array $filesNames): static
    {
        $this->filesNames = $filesNames;
        return $this;
    }

    public function getFilePath(?string $versionName = null): array|string
    {
        return $this->getColumn()
            ->getImageVersionPath($this->getRecord(), $versionName);
    }

    public function getAbsoluteFileUrl(?string $versionName = null): array|string
    {
        return $this->getColumn()
            ->getAbsoluteFileUrl($this->getRecord(), $versionName);
    }

    /**
     * @param string|null $versionName = null: returns file size of 1st version (usually it is original file)
     * @return int
     */
    public function getFileSize(?string $versionName = null): int
    {
        $path = $this->getFilePath($versionName);
        if (empty($path)) {
            return 0;
        }
        if (is_array($path)) {
            // get 1st version (usually it is original file)
            $path = array_values($path)[0];
        }
        if (File::exist($path)) {
            return filesize($path);
        }
        return 0;
    }

    public function restoreImageVersion(?string $versionName, ?string $ext = null): ?string
    {
        $configs = $this->getColumn()->getImageVersionsConfigs();
        if (empty($configs[$versionName])) {
            return null;
        }
        $filePath = ImageUtils::restoreVersionForConfig(
            $versionName,
            $configs[$versionName],
            $this->getFileNameWithoutExtension(),
            $this->getColumn()->getFileDirPath($this->getRecord()),
            $ext
        );
        return $filePath ?: null;
    }
}