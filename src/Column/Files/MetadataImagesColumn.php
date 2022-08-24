<?php

declare(strict_types=1);

namespace PeskyORMColumns\Column\Files;

use PeskyORM\ORM\RecordInterface;
use PeskyORM\ORM\RecordValue;
use PeskyORMColumns\Column\Files\Utils\DbImageFileInfo;
use Swayok\Utils\ImageUtils;
use Swayok\Utils\ImageVersionConfig;

class MetadataImagesColumn extends MetadataFilesColumn
{
    
    protected string $fileInfoClassName = DbImageFileInfo::class;
    
    protected string $metadataGroupName = 'images';
    
    /**
     * @var ImageVersionConfig[]
     */
    private array $versionsConfigs = [];
    
    /**
     * @return static
     */
    public function addImageVersionConfig(string $versionName, ImageVersionConfig $config): MetadataImagesColumn
    {
        if ($this->hasImageVersionConfig($versionName)) {
            throw new \InvalidArgumentException("Image version config '$versionName' already defined");
        }
        $this->versionsConfigs[$versionName] = $config;
        return $this;
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
                . get_class($this->getTableStructure()) . '->' . $this->getName()
            );
        }
        return $this->versionsConfigs[$versionName];
    }
    
    /**
     * @param RecordInterface $record
     * @param string|null $versionName
     * @return array|string|null
     */
    public function getImageVersionPath(RecordInterface $record, ?string $versionName)
    {
        $paths = $this->getImagesPaths($record);
        if (empty($versionName)) {
            return $paths;
        } elseif (!empty($paths[$versionName])) {
            return $paths[$versionName];
        } else {
            return null;
        }
    }
    
    public function getImagesPaths(RecordInterface $record): array
    {
        $this->requireRecordExistence($record);
        return ImageUtils::getVersionsPaths(
            $this->getFileDirPath($record),
            $this->getFileNameWithoutExtension(),
            $this->getImageVersionsConfigs()
        );
    }
    
    /**
     * @return string|string[]|null
     */
    public function getAbsoluteFileUrl(RecordValue $valueContainer, ?string $versionName = null)
    {
        $relativeUrl = $this->getRelativeImageUrl($valueContainer->getRecord(), $versionName);
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
        } elseif (empty($relativeUrl)) {
            return null;
        } elseif (!$this->isAbsoluteUrl($relativeUrl)) {
            return $serverUrl . $relativeUrl;
        } else {
            return $relativeUrl;
        }
    }
    
    /**
     * @param RecordInterface $record
     * @param string|null $versionName
     * @return string[]|string|null
     */
    protected function getRelativeImageUrl(RecordInterface $record, ?string $versionName)
    {
        $urls = $this->getRelativeImagesUrls($record);
        if (empty($versionName)) {
            return $urls;
        } elseif (!empty($urls[$versionName])) {
            return $urls[$versionName];
        } else {
            return null;
        }
    }
    
    protected function getRelativeImagesUrls(RecordInterface $record): array
    {
        $this->requireRecordExistence($record);
        return ImageUtils::getVersionsUrls(
            $this->getFileDirPath($record),
            $this->getFileDirRelativeUrl($record),
            $this->getFileNameWithoutExtension(),
            $this->getImageVersionsConfigs()
        );
    }
    
    public function validateUploadedFile($value): array
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
    
    protected function storeFileToFS(array $uploadedFileInfo, string $filePath, $fileInfo): void
    {
        $filesNames = ImageUtils::resize(
            $uploadedFileInfo,
            dirname($filePath) . DIRECTORY_SEPARATOR,
            $fileInfo->getFileNameWithoutExtension(),
            $this->getImageVersionsConfigs()
        );
        $fileInfo->setFilesNames($filesNames);
        // update file info if there was a ImageVersionConfig::SOURCE_VERSION_NAME version of image and it is different
        // differences might be related to image format change or image resize
        if (
            !empty($filesNames[ImageVersionConfig::SOURCE_VERSION_NAME])
            && $filesNames[ImageVersionConfig::SOURCE_VERSION_NAME] !== $fileInfo->getFileNameWithExtension()
        ) {
            $fileInfo->setFileNameWithExtension($filesNames[ImageVersionConfig::SOURCE_VERSION_NAME]);
            if (preg_match('%(^.*?)\.([a-zA-Z0-9]+)$%', $filesNames[ImageVersionConfig::SOURCE_VERSION_NAME], $parts)) {
                $fileInfo
                    ->setFileNameWithoutExtension($parts[1])
                    ->setFileExtension($parts[2])
                    // also update extension for original file - we usually need to preserve only name
                    ->setOriginalFileNameWithExtension($fileInfo->getOriginalFileNameWithoutExtension() . '.' . $parts[2]);
            }
        }
    }
}