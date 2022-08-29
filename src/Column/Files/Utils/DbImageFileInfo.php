<?php

declare(strict_types=1);

namespace PeskyORMColumns\Column\Files\Utils;

use PeskyORM\ORM\RecordValue;
use Swayok\Utils\File;
use Swayok\Utils\ImageUtils;

class DbImageFileInfo extends DbFileInfo
{
    
    protected array $filesNames = [];
    
    public function __construct(RecordValue $valueContainer)
    {
        $this->jsonMap['files_names'] = 'filesNames';
        parent::__construct($valueContainer);
    }
    
    public function getFilesNames(): array
    {
        return $this->filesNames;
    }
    
    /**
     * @return static
     */
    public function setFilesNames(array $filesNames)
    {
        $this->filesNames = $filesNames;
        return $this;
    }
    
    /*public function getOriginalFileNameWithExtension(): ?string {
        // file extension might be modified so we need to add actual file extension to be consistent
        return $this->getOriginalFileNameWithoutExtension() . '.' . $this->getFileExtension();
    }*/
    
    /**
     * @return string|array
     */
    public function getFilePath(?string $versionName = null)
    {
        return $this->column->getImageVersionPath($this->record, $versionName);
    }
    
    /**
     * @returns string|array
     */
    public function getAbsoluteFileUrl(?string $versionName = null)
    {
        return $this->column->getAbsoluteFileUrl($this->valueContainer, $versionName);
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
        $configs = $this->column->getImageVersionsConfigs();
        if (empty($configs[$versionName])) {
            return null;
        }
        $filePath = ImageUtils::restoreVersionForConfig(
            $versionName,
            $configs[$versionName],
            $this->getFileNameWithoutExtension(),
            $this->column->getFileDirPath($this->record),
            $ext
        );
        return $filePath ?: null;
    }
}