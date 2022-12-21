<?php

declare(strict_types=1);

namespace PeskyORMColumns\TableColumn\Files;

use PeskyORM\ORM\Record\RecordInterface;
use PeskyORM\ORM\Record\RecordValueContainerInterface;
use PeskyORM\ORM\TableStructure\TableColumn\VirtualTableColumnAbstract;
use PeskyORMColumns\TableColumn\Files\Utils\DbFileInfo;
use PeskyORMColumns\TableColumn\Files\Utils\DbFileInfoInterface;
use PeskyORMColumns\TableColumn\Files\Utils\DbImageFileInfo;
use PeskyORMColumns\TableColumn\Files\Utils\MimeTypesHelper;
use PeskyORMColumns\TableColumn\Files\Utils\UploadedTempFileInfo;
use Swayok\Utils\File;
use Swayok\Utils\Folder;
use Swayok\Utils\ImageVersionConfig;
use Swayok\Utils\Utils;
use Symfony\Component\HttpFoundation\File\UploadedFile;

interface VirtualImagesColumnInterface extends VirtualFilesColumnInterface
{
    /**
     * @return ImageVersionConfig[]
     */
    public function getImageVersionsConfigs(): array;

    public function hasImageVersionConfig(string $versionName): bool;

    public function getImageVersionConfig($versionName): ImageVersionConfig;

    public function addImageVersionConfig(
        string $versionName,
        ImageVersionConfig $config
    ): static;

    public function getImageVersionPath(
        RecordInterface $record,
        ?string $versionName
    ): array|string|null;

    public function getImagesPaths(RecordInterface $record): array;
}