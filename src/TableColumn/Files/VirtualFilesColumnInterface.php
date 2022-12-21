<?php

declare(strict_types=1);

namespace PeskyORMColumns\TableColumn\Files;

use PeskyORM\ORM\Record\RecordInterface;
use PeskyORM\ORM\Record\RecordValueContainerInterface;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnInterface;
use PeskyORM\ORM\TableStructure\TableColumn\VirtualTableColumnAbstract;
use PeskyORMColumns\TableColumn\Files\Utils\DbFileInfo;
use PeskyORMColumns\TableColumn\Files\Utils\DbFileInfoInterface;
use PeskyORMColumns\TableColumn\Files\Utils\DbImageFileInfo;
use PeskyORMColumns\TableColumn\Files\Utils\MimeTypesHelper;
use PeskyORMColumns\TableColumn\Files\Utils\UploadedTempFileInfo;
use Swayok\Utils\File;
use Swayok\Utils\Folder;
use Swayok\Utils\Utils;
use Symfony\Component\HttpFoundation\File\UploadedFile;

interface VirtualFilesColumnInterface extends TableColumnInterface
{
    public function validateUploadedFile(mixed $value): array;

    /**
     * Get absolute FS path to file dir
     */
    public function getFileDirPath(RecordInterface $record): string;

    public function getFilePath(RecordInterface $record): string;

    public function getFileInfo(RecordInterface $record): DbFileInfoInterface;

    /**
     * Get file name without extension
     * @param string|\Closure|null $fallbackValue - null: $this->getName() is used
     * @return string - file name without extension
     */
    public function getFileNameWithoutExtension(
        string|\Closure|null $fallbackValue = null
    ): string;

    public function getAbsoluteFileUrl(RecordInterface $record): mixed;

    public function isFileExists(RecordInterface $record): bool;

    public function getBasePathToFiles(): string;

    public function setBasePathToFiles(\Closure|string $basePathToFiles): static;

    public function getBaseUrlToFiles(): string;

    public function setBaseUrlToFiles(\Closure|string $baseUrlToFiles): static;

    public function getDefaultFileExtension(): ?string;

    public function setDefaultFileExtension(string $defaultFileExtension): static;

    public function getAllowedFileExtensions(): ?array;

    public function setAllowedFileExtensions(array $allowedFileExtensions): static;

    public function getFileDirRelativeUrlGenerator(): ?\Closure;

    /**
     * Closure signature: function (VirtualFilesColumn $column, Record $record): string
     */
    public function setFileDirRelativeUrlGenerator(
        \Closure $fileDirRelativeUrlGenerator
    ): static;

    public function getFileDirPathGenerator(): ?\Closure;

    /**
     * Closure signature: function (VirtualFilesColumn $column, Record $record): string
     */
    public function setFileDirPathGenerator(\Closure $fileDirPathGenerator): static;

    public function getFileSubdirGenerator(): ?\Closure;

    /**
     * Closure signature:
     * function (VirtualFilesColumn $column, Record $record, $directorySeparator): string
     */
    public function setFileSubdirGenerator(\Closure $fileSubdirGenerator): static;

    public function getFileNameGenerator(): ?\Closure;

    /**
     * Closure signature: function (VirtualFilesColumn $column): string
     */
    public function setFileNameGenerator(\Closure $fileNameGenerator): static;

    public function getFileServerUrlGenerator(): ?\Closure;

    public function hasFileServerUrlGenerator(): bool;

    public function setFileServerUrlGenerator(\Closure $fileServerUrlGenerator): static;

    public function deleteFiles(RecordInterface $record): void;

    public function setIndexInMetadataColumn(int $indexInMetadataColumn): static;

    public function getMetadataColumnName(): string;

    public function setMetadataColumnName(string $metadataColumnName): static;

    public function setMetadataGroupName(string $metadataGroupName): static;
}