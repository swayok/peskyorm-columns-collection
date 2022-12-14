<?php

declare(strict_types=1);

namespace PeskyORMColumns\Column\Files;

use PeskyORM\ORM\Column;
use PeskyORM\ORM\RecordInterface;
use PeskyORM\ORM\RecordValue;
use PeskyORMColumns\Column\Files\Utils\DbFileInfo;
use PeskyORMColumns\Column\Files\Utils\DbImageFileInfo;
use PeskyORMColumns\Column\Files\Utils\MimeTypesHelper;
use PeskyORMColumns\Column\Files\Utils\UploadedTempFileInfo;
use Swayok\Utils\File;
use Swayok\Utils\Folder;
use Swayok\Utils\Utils;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class MetadataFilesColumn extends Column
{
    
    protected string $fileInfoClassName = DbFileInfo::class;
    
    protected ?int $indexInMetadataColumn = null;
    
    protected string $metadataColumnName = 'files_metadata';
    
    protected string $metadataGroupName = 'files';
    
    protected string|\Closure|null $basePathToFiles = null;
    protected string|\Closure|null $baseUrlToFiles = null;
    /** null: any extension */
    protected ?array $allowedFileExtensions = null;
    protected string $defaultFileExtension = '';
    
    protected ?\Closure $fileNameGenerator = null;
    protected ?\Closure $fileSubdirGenerator = null;
    protected ?\Closure $fileDirPathGenerator = null;
    protected ?\Closure $fileDirRelativeUrlGenerator = null;
    protected ?\Closure $fileServerUrlGenerator = null;
    
    /**
     * @noinspection PhpParameterNameChangedDuringInheritanceInspection
     */
    public static function create(string|\Closure|null $basePathToFiles = null, string|\Closure $baseUrlToFiles = null, ?string $name = null): static
    {
        return new static($name, $basePathToFiles, $baseUrlToFiles);
    }
    
    public function __construct(?string $name, string|\Closure|null $basePathToFiles, string|\Closure|null $baseUrlToFiles = null)
    {
        parent::__construct($name, $this->isItAnImage() ? static::TYPE_IMAGE : static::TYPE_FILE);
        $this->doesNotExistInDb();
        if (!empty($basePathToFiles)) {
            $this->setBasePathToFiles($basePathToFiles);
        }
        if (!empty($baseUrlToFiles)) {
            $this->setBaseUrlToFiles($baseUrlToFiles);
        }
    }
    
    protected function setDefaultColumnClosures(): static
    {
        $this
            ->setValueExistenceChecker(function (RecordValue $value) {
                return $value->hasValue() || ($value->getRecord()->existsInDb() && $this->isFileExists($value));
            })
            ->setValueGetter(function (RecordValue $value) {
                $record = $value->getRecord();
                if ($record->existsInDb()) {
                    return $this->getFileInfo($value);
                } else {
                    return $value->getValue();
                }
            })
            ->setValueValidator(function ($value, $isFromDb) {
                if ($isFromDb || empty($value)) {
                    return [];
                }
                return $this->validateUploadedFile($value);
            })
            ->setValueSetter(function ($newValue, $isFromDb, RecordValue $valueContainer) {
                if ($isFromDb) {
                    // this column cannot have DB value because it does not exist in DB
                    $valueContainer
                        ->setRawValue(null, null, true)
                        ->setValidValue(null, null);
                    return $valueContainer;
                } elseif (empty($newValue) && $this->isValueCanBeNull()) {
                    // empty non-db value
                    $valueContainer
                        ->setRawValue(null, null, false)
                        ->setValidValue(null, null);
                    return $valueContainer;
                }
                $newValue = $this->normalizeFileUpload($newValue);
                $errors = $this->validateValue($newValue, $isFromDb, false);
                if (count($errors) > 0) {
                    return $valueContainer->setValidationErrors($errors);
                }
                // it is file upload - store it in container
                return $valueContainer
                    ->setIsFromDb(false)
                    ->setRawValue($newValue['tmp_name'], $newValue['tmp_name'], false)
                    ->setValidValue($newValue['tmp_name'], $newValue['tmp_name'])
                    ->setDataForSavingExtender($newValue);
            })
            ->setValueSavingExtender(function (RecordValue $valueContainer, $isUpdate) {
                $fileUpload = $valueContainer->pullDataForSavingExtender();
                if (empty($fileUpload)) {
                    // do not remove! infinite recursion will happen!
                    return;
                }
                $valueContainer
                    ->setIsFromDb(true)
                    ->setRawValue(null, null, true)
                    ->setValidValue(null, null);
                if ($isUpdate) {
                    $this->deleteFiles($valueContainer->getRecord());
                }
                $this->analyzeUploadedFileAndSaveToFS($valueContainer, $fileUpload);
            })
            ->setValueDeleteExtender(function (RecordValue $valueContainer, $deleteFiles) {
                if ($deleteFiles) {
                    $this->deleteFiles($valueContainer->getRecord());
                }
            });
        
        return parent::setDefaultColumnClosures();
    }
    
    protected function normalizeFileUpload($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if ($value instanceof UploadedFile) {
            // also applies to Base64UploadedFile
            return [
                'name' => $value->getClientOriginalName(),
                'type' => $value->getClientMimeType(),
                'size' => $value->getSize(),
                'error' => $value->getError(),
                'tmp_name' => $value->getRealPath(),
            ];
        } elseif ($value instanceof \SplFileInfo) {
            $ret = [
                'name' => $value->getFilename(),
                'type' => $value->getType(),
                'size' => $value->getSize(),
                'error' => UPLOAD_ERR_OK,
                'tmp_name' => $value->getRealPath(),
            ];
            if ($value instanceof UploadedTempFileInfo) {
                $ret['position'] = $value->getPosition();
            }
            return $ret;
        } else {
            throw new \InvalidArgumentException('$value must be file upload info (array) or instance of UploadedFile or SplFileInfo');
        }
    }
    
    public function validateUploadedFile($value): array
    {
        if (!Utils::isFileUpload($value)) {
            return ['File upload expected'];
        } elseif (!Utils::isSuccessfullFileUpload($value)) {
            return ['File upload failed'];
        } elseif (!File::exist($value['tmp_name'])) {
            return ['File upload was successful but file is missing'];
        }
        try {
            $this->detectUploadedFileExtension($value);
        } catch (\InvalidArgumentException $exception) {
            return [$exception->getMessage()];
        }
        return [];
    }
    
    /**
     * Get absolute FS path to file dir
     */
    public function getFileDirPath(RecordInterface $record): string
    {
        $this->requireRecordExistence($record);
        $generator = $this->getFileDirPathGenerator();
        if (!empty($generator)) {
            $dirPath = $generator($this, $record);
            if (empty($dirPath) || !is_string($dirPath)) {
                throw new \UnexpectedValueException('File dir path genetartor Closure should return not-empty string');
            }
            $dirPath = rtrim($dirPath, '/\\') . DIRECTORY_SEPARATOR;
        } else {
            $objectSubdir = DIRECTORY_SEPARATOR . trim($this->getFilesSubdir($record), '/\\');
            $dirPath = rtrim($this->getBasePathToFiles(), '/\\') . $objectSubdir . DIRECTORY_SEPARATOR;
        }
        return $dirPath;
    }
    
    protected function getFilesSubdir(RecordInterface $record, $directorySeparator = DIRECTORY_SEPARATOR): string
    {
        $this->requireRecordExistence($record);
        $generator = $this->getFileSubdirGenerator();
        if (!empty($generator)) {
            $subdir = $generator($this, $record, $directorySeparator);
            if (empty($subdir) || !is_string($subdir)) {
                throw new \UnexpectedValueException('File subdir genetartor Closure should return not-empty string');
            }
            return $subdir;
        } else {
            return $record->getPrimaryKeyValue();
        }
    }
    
    public function getFilePath(RecordValue $valueContainer): string
    {
        return $this->getFileDirPath($valueContainer->getRecord()) . $this->getFullFileName($valueContainer);
    }
    
    protected function getFullFileName(RecordValue $valueContainer): string
    {
        $fileInfo = $this->getFileInfo($valueContainer);
        if (!empty($fileInfo->getFileNameWithExtension())) {
            return $fileInfo->getFileNameWithExtension();
        } else {
            $fileName = $this->getFileNameWithoutExtension();
            $fileInfo->setFileNameWithoutExtension($fileName);
            $ext = $this->getFileExtension($valueContainer);
            if (!empty($ext)) {
                $fileName .= '.' . $ext;
            }
            $fileInfo->setFileNameWithExtension($fileName);
            return $fileName;
        }
    }
    
    /**
     * @param RecordValue $valueContainer
     * @return DbFileInfo|DbImageFileInfo
     */
    public function getFileInfo(RecordValue $valueContainer): DbImageFileInfo|DbFileInfo
    {
        return $valueContainer->getCustomInfo(
            'FileInfo',
            function () use ($valueContainer) {
                return $this->createFileInfoObject($valueContainer);
            },
            true
        );
    }
    
    /**
     * @param RecordValue $valueContainer
     * @return DbFileInfo|DbImageFileInfo
     */
    protected function createFileInfoObject(RecordValue $valueContainer): DbImageFileInfo|DbFileInfo
    {
        $class = $this->fileInfoClassName;
        return new $class($valueContainer);
    }
    
    /**
     * Get file name without extension
     * @param string|\Closure|null $fallbackValue - null: $this->getName() is used
     * @return string - file name without extension
     */
    public function getFileNameWithoutExtension(string|\Closure|null $fallbackValue = null): string
    {
        $generator = $this->getFileNameGenerator();
        if (!empty($generator)) {
            $fileName = $generator($this);
            if (empty($fileName) || !is_string($fileName)) {
                throw new \UnexpectedValueException('File name genetartor Closure should return not-empty string');
            }
            return $fileName;
        } else {
            return empty($fallbackValue) ? $this->getName() : $fallbackValue;
        }
    }
    
    protected function getFileExtension(RecordValue $valueContainer): string
    {
        $fileInfo = $this->getFileInfo($valueContainer);
        if (empty($fileInfo->getFileExtension())) {
            $fileInfo->setFileExtension($this->getDefaultFileExtension());
            if ($valueContainer->getRecord()->existsInDb()) {
                $allowedExtensions = $this->getAllowedFileExtensions();
                if (!empty($allowedExtensions)) {
                    foreach ($allowedExtensions as $ext) {
                        $fileDir = $this->getFileDirPath($valueContainer->getRecord());
                        $fileNameNoExt = $this->getFileNameWithoutExtension();
                        if (File::exist($fileDir . $fileNameNoExt . '.' . $ext)) {
                            $fileInfo->setFileExtension($ext);
                            return $ext;
                        }
                    }
                }
            }
        }
        return $fileInfo->getFileExtension();
    }
    
    /**
     * @return string
     * @noinspection PhpDocSignatureInspection
     */
    public function getAbsoluteFileUrl(RecordValue $valueContainer): array|string|null
    {
        return $this->getFileDirAbsoluteUrl($valueContainer->getRecord()) . $this->getFullFileName($valueContainer);
    }
    
    protected function getFileDirAbsoluteUrl(RecordInterface $record): string
    {
        $this->requireRecordExistence($record);
        $relativeUrl = $this->getFileDirRelativeUrl($record);
        if ($this->isAbsoluteUrl($relativeUrl)) {
            return $relativeUrl;
        } else {
            return $this->getFileServerUrl() . '/' . trim($relativeUrl, '/\\') . '/';
        }
    }
    
    protected function getFileDirRelativeUrl(RecordInterface $record): string
    {
        $this->requireRecordExistence($record);
        $generator = $this->getFileDirRelativeUrlGenerator();
        if (!empty($generator)) {
            $relUrl = $generator($this, $record);
            if (empty($relUrl) || !is_string($relUrl)) {
                throw new \UnexpectedValueException('File dir relative url genetartor Closure should return not-empty string');
            }
            return rtrim($relUrl, '/\\') . '/';
        } else {
            $relUrl = trim($this->getBaseUrlToFiles(), '/\\');
            $objectSubdir = '/' . trim($this->getFilesSubdir($record, '/'), '/\\') . '/';
            return ($this->isAbsoluteUrl($relUrl) ? '' : '/') . $relUrl . $objectSubdir;
        }
    }
    
    protected function getFileServerUrl(): string
    {
        $generator = $this->getFileServerUrlGenerator();
        if (!empty($generator)) {
            $url = $generator($this);
            if (empty($url) || !is_string($url)) {
                throw new \UnexpectedValueException('File server url genetartor function should return not-empty string');
            }
        } else {
            $url = 'http://' . $_SERVER['HTTP_HOST'];
        }
        return rtrim($url, '/\\');
    }
    
    protected function isAbsoluteUrl(string $url): bool
    {
        return preg_match('%^(https?|ftp)://%i', $url);
    }
    
    public function isFileExists(RecordValue $valueContainer): bool
    {
        return File::exist($this->getFilePath($valueContainer));
    }
    
    protected function requireRecordExistence(RecordInterface $record): void
    {
        if (!$record->existsInDb()) {
            throw new \BadMethodCallException('Unable to get file dir path of non-existing db record');
        }
    }
    
    public function getBasePathToFiles(): string
    {
        return is_callable($this->basePathToFiles) ? call_user_func($this->basePathToFiles) : $this->basePathToFiles;
    }
    
    public function setBasePathToFiles(\Closure|string $basePathToFiles): static
    {
        if (empty($basePathToFiles)) {
            throw new \InvalidArgumentException('$basePathToFiles argument must be a not empty string or \Closure');
        }
        $this->basePathToFiles = $basePathToFiles;
        return $this;
    }
    
    public function getBaseUrlToFiles(): string
    {
        if ($this->baseUrlToFiles === null) {
            throw new \UnexpectedValueException('$this->baseUrlToFiles is not provided');
        }
        return is_callable($this->baseUrlToFiles) ? call_user_func($this->baseUrlToFiles) : $this->baseUrlToFiles;
    }
    
    public function setBaseUrlToFiles(\Closure|string $baseUrlToFiles): static
    {
        if (empty($baseUrlToFiles)) {
            throw new \InvalidArgumentException('$baseUrlToFiles argument must be not empty string or \Closure');
        }
        if (is_string($baseUrlToFiles) && preg_match('%(https?://[^/]+)(/.*$|$)%i', $baseUrlToFiles, $urlParts)) {
            $this->baseUrlToFiles = $urlParts[2];
            if (!$this->hasFileServerUrlGenerator()) {
                $baseUrl = $urlParts[1];
                $this->setFileServerUrlGenerator(function () use ($baseUrl) {
                    return $baseUrl;
                });
            }
        } else {
            $this->baseUrlToFiles = $baseUrlToFiles;
        }
        return $this;
    }
    
    public function getDefaultFileExtension(): ?string
    {
        return $this->defaultFileExtension;
    }
    
    public function setDefaultFileExtension(string $defaultFileExtension): static
    {
        if (empty($defaultFileExtension)) {
            throw new \InvalidArgumentException('$defaultFileExtension argument must be a not-empty string');
        }
        $allowedExtensions = $this->getAllowedFileExtensions();
        if (!empty($allowedExtensions) && !in_array($defaultFileExtension, $allowedExtensions, true)) {
            throw new \InvalidArgumentException("\$defaultFileExtension argument value '{$defaultFileExtension}' is not allowed");
        }
        $this->defaultFileExtension = $defaultFileExtension;
        return $this;
    }
    
    public function getAllowedFileExtensions(): ?array
    {
        return $this->allowedFileExtensions;
    }
    
    public function setAllowedFileExtensions(array $allowedFileExtensions): static
    {
        if (count($allowedFileExtensions) === 0) {
            throw new \InvalidArgumentException('$allowedFileExtensions argument must be a not-empty array');
        }
        $defaultExtension = $this->getDefaultFileExtension();
        if (!empty($defaultExtension) && !in_array($defaultExtension, $allowedFileExtensions, true)) {
            throw new \InvalidArgumentException("Default file extension '$defaultExtension' provided via setDefaultFileExtension() is not allowed");
        }
        $this->allowedFileExtensions = array_values($allowedFileExtensions);
        return $this;
    }
    
    public function getFileDirRelativeUrlGenerator(): ?\Closure
    {
        return $this->fileDirRelativeUrlGenerator;
    }
    
    /**
     * @param \Closure $fileDirRelativeUrlGenerator - function (FileColumnConfig $column, Record $record) {}
     */
    public function setFileDirRelativeUrlGenerator(\Closure $fileDirRelativeUrlGenerator): static
    {
        $this->fileDirRelativeUrlGenerator = $fileDirRelativeUrlGenerator;
        return $this;
    }
    
    public function getFileDirPathGenerator(): ?\Closure
    {
        return $this->fileDirPathGenerator;
    }
    
    /**
     * @param \Closure $fileDirPathGenerator - function (FileColumnConfig $column, Record $record) {}
     */
    public function setFileDirPathGenerator(\Closure $fileDirPathGenerator): static
    {
        $this->fileDirPathGenerator = $fileDirPathGenerator;
        return $this;
    }
    
    public function getFileSubdirGenerator(): ?\Closure
    {
        return $this->fileSubdirGenerator;
    }
    
    /**
     * @param \Closure $fileSubdirGenerator - function (FileColumnConfig $column, Record $record, $directorySeparator = DIRECTORY_SEPARATOR) {}
     */
    public function setFileSubdirGenerator(\Closure $fileSubdirGenerator): static
    {
        $this->fileSubdirGenerator = $fileSubdirGenerator;
        return $this;
    }
    
    public function getFileNameGenerator(): ?\Closure
    {
        return $this->fileNameGenerator;
    }
    
    /**
     * @param \Closure $fileNameGenerator - function (FileColumnConfig $column) {}
     */
    public function setFileNameGenerator(\Closure $fileNameGenerator): static
    {
        $this->fileNameGenerator = $fileNameGenerator;
        return $this;
    }
    
    public function getFileServerUrlGenerator(): ?\Closure
    {
        return $this->fileServerUrlGenerator;
    }
    
    public function hasFileServerUrlGenerator(): bool
    {
        return (bool)$this->fileServerUrlGenerator;
    }
    
    public function setFileServerUrlGenerator(\Closure $fileServerUrlGenerator): static
    {
        $this->fileServerUrlGenerator = $fileServerUrlGenerator;
        return $this;
    }
    
    /**
     * @throws \InvalidArgumentException
     */
    public function deleteFiles(RecordInterface $record): void
    {
        if (!$record->existsInDb()) {
            throw new \InvalidArgumentException('Unable to delete files of non-existing object');
        }
        $pathToFiles = $this->getFileDirPath($record);
        if (Folder::exist($pathToFiles)) {
            $baseFileName = $this->getFileNameWithoutExtension();
            $files = Folder::load($pathToFiles)->find("{$baseFileName}.*");
            foreach ($files as $fileName) {
                File::remove($pathToFiles . $fileName);
            }
        }
    }
    
    /**
     * Save file to FS + collect information
     * @param RecordValue $valueContainer - uploaded file info
     * @param array $uploadedFileInfo - uploaded file info
     * @return null|DbFileInfo|DbImageFileInfo - array: information about file same as when you get by callings $this->getFileInfoFromInfoFile()
     */
    protected function analyzeUploadedFileAndSaveToFS(RecordValue $valueContainer, array $uploadedFileInfo): DbImageFileInfo|DbFileInfo|null
    {
        $pathToFiles = $this->getFileDirPath($valueContainer->getRecord());
        if (!is_dir($pathToFiles)) {
            Folder::add($pathToFiles, 0777);
        }
        $fileInfo = $this->createFileInfoObject($valueContainer);
        
        $fileInfo->setOriginalFileNameWithoutExtension(preg_replace('%\.[a-zA-Z0-9]{1,6}$%', '', $uploadedFileInfo['name']));
        $fileInfo->setOriginalFileNameWithExtension($uploadedFileInfo['name']);
        
        $fileName = $this->getFileNameWithoutExtension();
        $fileInfo->setFileNameWithoutExtension($fileName);
        $fileInfo->setFileNameWithExtension($fileName);
        $ext = $this->detectUploadedFileExtension($uploadedFileInfo);
        $fileName .= '.' . $ext;
        $fileInfo->setFileExtension($ext);
        $fileInfo->setFileNameWithExtension($fileName);
        $fileInfo->setOriginalFileNameWithExtension($fileInfo->getOriginalFileNameWithoutExtension() . '.' . $ext);
        if (isset($uploadedFileInfo['position'])) {
            $fileInfo->setPosition($uploadedFileInfo['position']);
        } elseif (method_exists($this, 'getIndexInMetadataColumn')) {
            $fileInfo->setPosition($this->getIndexInMetadataColumn());
        }
        
        // move tmp file to target file path
        $filePath = $pathToFiles . $fileInfo->getFileNameWithExtension();
        $this->storeFileToFS($uploadedFileInfo, $filePath, $fileInfo);
        $fileInfo->saveToFile();
        
        return $fileInfo;
    }
    
    /**
     * @param array $uploadedFileInfo
     * @param string $filePath
     * @param DbFileInfo|DbImageFileInfo $fileInfo
     * @return void
     */
    protected function storeFileToFS(array $uploadedFileInfo, string $filePath, DbImageFileInfo|DbFileInfo $fileInfo): void
    {
        $file = File::load($uploadedFileInfo['tmp_name'])->move($filePath, 0666);
        if (!$file) {
            throw new \UnexpectedValueException('Failed to store file to file system');
        }
    }
    
    /**
     * Detect Uploaded file extension by file name or content type
     * @param array $uploadedFileInfo - uploaded file info
     * @return string - file extension without leading point (ex: 'mp4', 'mov', '')
     * @throws \InvalidArgumentException
     */
    protected function detectUploadedFileExtension(array $uploadedFileInfo): string
    {
        if (empty($uploadedFileInfo['type']) && empty($uploadedFileInfo['name']) && empty($uploadedFileInfo['tmp_name'])) {
            throw new \InvalidArgumentException('Uploaded file extension cannot be detected');
        }
        // test content type
        $receivedExt = null;
        if (!empty($uploadedFileInfo['type'])) {
            $receivedExt = MimeTypesHelper::getExtensionForMimeType($uploadedFileInfo['type']);
        }
        if (!$receivedExt) {
            if (!empty($uploadedFileInfo['name'])) {
                $receivedExt = preg_match('%\.([a-zA-Z0-9]+)\s*$%i', $uploadedFileInfo['name'], $matches) ? $matches[1] : '';
            } elseif (!empty($uploadedFileInfo['tmp_name'])) {
                $receivedExt = preg_match('%\.([a-zA-Z0-9]+)\s*$%i', $uploadedFileInfo['tmp_name'], $matches) ? $matches[1] : '';
            } else {
                $receivedExt = $this->getDefaultFileExtension();
            }
        }
        if (!$receivedExt) {
            throw new \InvalidArgumentException('Uploaded file extension cannot be detected');
        }
        $receivedExt = strtolower($receivedExt);
        if (!$this->isFileExtensionAllowed($receivedExt)) {
            throw new \InvalidArgumentException('Uploaded file extension is not allowed');
        }
        return $receivedExt;
    }
    
    protected function isFileExtensionAllowed(string $ext): bool
    {
        return in_array($ext, $this->getAllowedFileExtensions(), true);
    }
    
    public function getIndexInMetadataColumn(): int
    {
        if (!isset($this->indexInMetadataColumn)) {
            throw new \UnexpectedValueException('Index in metadata column is not set');
        }
        return $this->indexInMetadataColumn;
    }
    
    public function setIndexInMetadataColumn(int $indexInMetadataColumn): static
    {
        $this->indexInMetadataColumn = $indexInMetadataColumn;
        return $this;
    }
    
    public function getMetadataColumnName(): string
    {
        return $this->metadataColumnName;
    }
    
    public function setMetadataColumnName(string $metadataColumnName): static
    {
        $this->metadataColumnName = $metadataColumnName;
        return $this;
    }
    
    public function getMetadataGroupName(): string
    {
        return $this->metadataGroupName;
    }
    
    public function setMetadataGroupName(string $metadataGroupName): static
    {
        $this->metadataGroupName = $metadataGroupName;
        return $this;
    }
    
}