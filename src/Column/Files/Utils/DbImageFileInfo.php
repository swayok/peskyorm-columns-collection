<?php

declare(strict_types=1);

namespace PeskyORMColumns\Column\Files\Utils;

use PeskyORM\ORM\RecordValue;
use Swayok\Utils\ImageUtils;

class DbImageFileInfo extends DbFileInfo
{
    
    protected $filesNames = [];
    
    public function __construct(RecordValue $valueContainer)
    {
        $this->jsonMap['files_names'] = 'filesNames';
        parent::__construct($valueContainer);
    }
    
    public function getFilesNames(): array
    {
        return $this->filesNames;
    }
    
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
    
    public function getAbsoluteFileUrl(?string $versionName = null): string
    {
        return $this->column->getAbsoluteFileUrl($this->valueContainer, $versionName);
    }
    
    /**
     * @return bool|string
     */
    public function restoreImageVersion(?string $versionName, ?string $ext = null)
    {
        $configs = $this->column->getImageVersionsConfigs();
        if (empty($configs[$versionName])) {
            return false;
        }
        return ImageUtils::restoreVersionForConfig(
            $versionName,
            $configs[$versionName],
            $this->getFileNameWithoutExtension(),
            $this->column->getFileDirPath($this->record),
            $ext
        );
    }
}