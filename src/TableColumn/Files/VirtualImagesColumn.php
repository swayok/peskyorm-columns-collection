<?php

declare(strict_types=1);

namespace PeskyORMColumns\TableColumn\Files;

use PeskyORM\ORM\Record\RecordInterface;
use PeskyORM\ORM\Record\RecordValueContainerInterface;
use PeskyORMColumns\TableColumn\Files\Utils\DbFileInfoInterface;
use PeskyORMColumns\TableColumn\Files\Utils\DbImageFileInfo;
use Swayok\Utils\ImageUtils;
use Swayok\Utils\ImageVersionConfig;

class VirtualImagesColumn extends VirtualFilesColumnAbstract implements VirtualImagesColumnInterface
{
    protected string $metadataGroupName = 'images';

    /**
     * @var ImageVersionConfig[]
     */
    private array $versionsConfigs = [];

    public function __construct(
        ?string $name,
        string|\Closure|null $basePathToFiles,
        string|\Closure|null $baseUrlToFiles = null,
        string $metadataColumnName = 'files_metadata',
        string $metadataGroupName = 'images',
    ) {
        parent::__construct(
            $name,
            $basePathToFiles,
            $baseUrlToFiles,
            $metadataColumnName,
            $metadataGroupName
        );
    }

    /**
     * @return ImageVersionConfig[]
     */
    public function getImageVersionsConfigs(): array
    {
        return $this->versionsConfigs;
    }

    public function hasImageVersionConfig(string $versionName): bool
    {
        return !empty($this->versionsConfigs[$versionName]);
    }

    public function getImageVersionConfig($versionName): ImageVersionConfig
    {
        if (!$this->hasImageVersionConfig($versionName)) {
            throw new \InvalidArgumentException(
                "Image version config '$versionName' is not defined for column "
                . $this->getNameForException()
            );
        }
        return $this->versionsConfigs[$versionName];
    }

    public function addImageVersionConfig(
        string $versionName,
        ImageVersionConfig $config
    ): static {
        if ($this->hasImageVersionConfig($versionName)) {
            throw new \InvalidArgumentException(
                "Image version config '$versionName' already defined"
            );
        }
        $this->versionsConfigs[$versionName] = $config;
        return $this;
    }

    public function getImageVersionPath(
        RecordInterface $record,
        ?string $versionName
    ): array|string|null {
        $paths = $this->getImagesPaths($record);
        if (empty($versionName)) {
            return $paths;
        }
        if (!empty($paths[$versionName])) {
            return $paths[$versionName];
        }
        return null;
    }

    public function getImagesPaths(RecordInterface $record): array
    {
        $this->assertRecordExistsInDb($record);
        return ImageUtils::getVersionsPaths(
            $this->getFileDirPath($record),
            $this->getFileNameWithoutExtension(),
            $this->getImageVersionsConfigs()
        );
    }

    /**
     * @return string[]|string|null
     */
    public function getAbsoluteFileUrl(
        RecordInterface $record,
        ?string $versionName = null
    ): array|string|null {
        $relativeUrl = $this->getRelativeImageUrl($record, $versionName);
        $serverUrl = $this->getFileServerUrl();
        if (is_array($relativeUrl)) {
            $ret = [];
            foreach ($relativeUrl as $version => $url) {
                if (!$this->isAbsoluteUrl($url)) {
                    $ret[$version] = $serverUrl . $url;
                } else {
                    $ret[$version] = $url;
                }
            }
            return $ret;
        }
        if (empty($relativeUrl)) {
            return null;
        }
        if (!$this->isAbsoluteUrl($relativeUrl)) {
            return $serverUrl . $relativeUrl;
        }
        return $relativeUrl;
    }

    protected function createFileInfoObject(
        RecordInterface $record
    ): DbImageFileInfo {
        return new DbImageFileInfo(
            $record,
            $this,
            $this->getMetadataGroupName(),
            $this->getIndexInMetadataColumn()
        );
    }

    /**
     * @return string[]|string|null
     */
    protected function getRelativeImageUrl(
        RecordInterface $record,
        ?string $versionName
    ): array|string|null {
        $urls = $this->getRelativeImagesUrls($record);
        if (empty($versionName)) {
            return $urls;
        }
        if (!empty($urls[$versionName])) {
            return $urls[$versionName];
        }
        return null;
    }

    protected function getRelativeImagesUrls(RecordInterface $record): array
    {
        $this->assertRecordExistsInDb($record);
        return ImageUtils::getVersionsUrls(
            $this->getFileDirPath($record),
            $this->getFileDirRelativeUrl($record),
            $this->getFileNameWithoutExtension(),
            $this->getImageVersionsConfigs()
        );
    }

    public function validateUploadedFile(mixed $value): array
    {
        $errors = parent::validateUploadedFile($value);
        if (empty($errors) && !ImageUtils::isImage($value)) {
            return ['Uploaded file is not image or image type is not supported'];
        }
        return $errors;
    }

    public function getFileDirPath(RecordInterface $record): string
    {
        return parent::getFileDirPath($record) . $this->getName() . DIRECTORY_SEPARATOR;
    }

    protected function getFileDirRelativeUrl(RecordInterface $record): string
    {
        return parent::getFileDirRelativeUrl($record) . $this->getName() . '/';
    }

    protected function storeFileToFS(
        array $uploadedFileInfo,
        string $filePath,
        DbFileInfoInterface|DbImageFileInfo $fileInfo
    ): void {
        $filesNames = ImageUtils::resize(
            $uploadedFileInfo,
            dirname($filePath) . DIRECTORY_SEPARATOR,
            $fileInfo->getFileNameWithoutExtension(),
            $this->getImageVersionsConfigs()
        );
        $fileInfo->setFilesNames($filesNames);

        // Update file info if there was a ImageVersionConfig::SOURCE_VERSION_NAME
        // version of image, and it is different.
        // Differences might be related to image format change or image resize
        if (empty($filesNames[ImageVersionConfig::SOURCE_VERSION_NAME])) {
            return;
        }
        $sourceFileName = $filesNames[ImageVersionConfig::SOURCE_VERSION_NAME];
        if ($sourceFileName === $fileInfo->getFileNameWithExtension()) {
            return;
        }

        $fileInfo->setFileNameWithExtension($sourceFileName);
        if (preg_match('%(^.*?)\.([a-zA-Z0-9]+)$%', $sourceFileName, $parts)) {
            $fileInfo
                ->setFileNameWithoutExtension($parts[1])
                ->setFileExtension($parts[2])
                // Also update extension for original file - we usually need to preserve only name
                ->setOriginalFileNameWithExtension(
                    $fileInfo->getOriginalFileNameWithoutExtension() . '.' . $parts[2]
                );
        }
    }
}