<?php

declare(strict_types=1);

namespace PeskyORMColumns\TableColumn\Files;

use PeskyORM\Exception\InvalidDataException;
use PeskyORM\ORM\Record\RecordInterface;
use PeskyORM\ORM\Record\RecordValueContainerInterface;
use PeskyORM\ORM\TableStructure\TableColumn\VirtualTableColumnAbstract;
use PeskyORMColumns\TableColumn\Files\Utils\DbFileInfoInterface;
use PeskyORMColumns\TableColumn\Files\Utils\MimeTypesHelper;
use PeskyORMColumns\TableColumn\Files\Utils\UploadedTempFileInfo;
use Swayok\Utils\File;
use Swayok\Utils\Folder;
use Swayok\Utils\Utils;
use Symfony\Component\HttpFoundation\File\UploadedFile;

abstract class VirtualFilesColumnAbstract extends VirtualTableColumnAbstract implements
    VirtualFilesColumnInterface
{
    protected const PAYLOAD_KEY_FILE_INFO = 'FileInfo';

    protected ?int $indexInMetadataColumn = null;

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

    public function __construct(
        ?string $name,
        string|\Closure|null $basePathToFiles,
        string|\Closure|null $baseUrlToFiles = null,
        protected string $metadataColumnName = 'files_metadata',
        protected string $metadataGroupName = 'files',
    ) {
        parent::__construct($name);
        if (!empty($basePathToFiles)) {
            $this->setBasePathToFiles($basePathToFiles);
        }
        if (!empty($baseUrlToFiles)) {
            $this->setBaseUrlToFiles($baseUrlToFiles);
        }
    }

    public function isFile(): bool
    {
        return true;
    }

    public function hasValue(
        RecordValueContainerInterface $valueContainer,
        bool $allowDefaultValue
    ): bool {
        return (
            $valueContainer->hasValue()
            || (
                $valueContainer->getRecord()->existsInDb()
                && $this->isFileExists($valueContainer->getRecord())
            )
        );
    }

    public function getValue(
        RecordValueContainerInterface $valueContainer,
        ?string $format
    ): mixed {
        $record = $valueContainer->getRecord();
        if ($record->existsInDb()) {
            return $valueContainer->rememberPayload(
                static::PAYLOAD_KEY_FILE_INFO,
                function () use ($record) {
                    return $this->getFileInfo($record);
                }
            );
        }
        return $valueContainer->getValue();
    }

    public function setValue(
        RecordValueContainerInterface $currentValueContainer,
        mixed $newValue,
        bool $isFromDb,
        bool $trustDataReceivedFromDb
    ): RecordValueContainerInterface {
        if ($isFromDb) {
            throw new \BadMethodCallException(
                'It is impossible to set a value from DB for a virtual column.'
            );
        }
        if (empty($newValue) && $this->isNullableValues()) {
            // empty non-db value
            return $currentValueContainer;
        }
        $normalizedValue = $this->normalizeFileUpload($newValue);
        $errors = $this->validateValue($normalizedValue, false, false);
        if (count($errors) > 0) {
            throw new InvalidDataException(
                $errors,
                $currentValueContainer->getRecord(),
                $this,
                $normalizedValue
            );
        }
        // it is file upload - store it in container
        $valueContainer = $currentValueContainer;
        if ($currentValueContainer->hasValue()) {
            $valueContainer = $this->getNewRecordValueContainer(
                $currentValueContainer->getRecord()
            );
        }
        $valueContainer->setValue(null, $normalizedValue['tmp_name'], false, false);
        $valueContainer->addPayload(static::AFTER_SAVE_PAYLOAD_KEY, $normalizedValue);
        return $valueContainer;
    }

    public function validateValue(
        mixed $value,
        bool $isFromDb = false,
        bool $isForCondition = false
    ): array {
        if ($isFromDb || empty($value)) {
            return [];
        }
        return $this->validateUploadedFile($value);
    }

    public function normalizeValidatedValue(mixed $validatedValue, bool $isFromDb): mixed
    {
        return $validatedValue;
    }

    public function afterSave(
        RecordValueContainerInterface $valueContainer,
        bool $isUpdate
    ): RecordValueContainerInterface {
        $fileUpload = $valueContainer->pullPayload(static::AFTER_SAVE_PAYLOAD_KEY);
        if (empty($fileUpload)) {
            return $valueContainer;
        }
        $valueContainer->setIsFromDb(true);
        if ($isUpdate) {
            $this->deleteFiles($valueContainer->getRecord());
        }
        $dbFileInfo = $this->analyzeUploadedFileAndSaveToFS($valueContainer, $fileUpload);
        $newValueContainer = $this->getNewRecordValueContainer($valueContainer->getRecord());
        if ($dbFileInfo) {
            $newValueContainer->addPayload(static::PAYLOAD_KEY_FILE_INFO, $dbFileInfo);
        }
        return $newValueContainer;
    }

    public function afterDelete(
        RecordValueContainerInterface $valueContainer,
        bool $shouldDeleteFiles
    ): RecordValueContainerInterface {
        if ($shouldDeleteFiles) {
            $this->deleteFiles($valueContainer->getRecord());
        }
        return $this->getNewRecordValueContainer($valueContainer->getRecord());
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
        }

        if ($value instanceof \SplFileInfo) {
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
        }

        throw new \InvalidArgumentException(
            '$value must be file upload info (array) or instance of UploadedFile or SplFileInfo'
        );
    }

    public function validateUploadedFile(mixed $value): array
    {
        if (!Utils::isFileUpload($value)) {
            return ['File upload expected'];
        }

        if (!Utils::isSuccessfullFileUpload($value)) {
            return ['File upload failed'];
        }

        if (!File::exist($value['tmp_name'])) {
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
        $this->assertRecordExistsInDb($record);
        $generator = $this->getFileDirPathGenerator();
        if (!empty($generator)) {
            $dirPath = $generator($this, $record);
            if (empty($dirPath) || !is_string($dirPath)) {
                throw new \UnexpectedValueException(
                    'File dir path genetartor Closure should return not-empty string'
                );
            }
            $dirPath = rtrim($dirPath, '/\\') . DIRECTORY_SEPARATOR;
        } else {
            $objectSubdir = DIRECTORY_SEPARATOR . trim($this->getFilesSubdir($record), '/\\');
            $dirPath = rtrim($this->getBasePathToFiles(), '/\\')
                . $objectSubdir . DIRECTORY_SEPARATOR;
        }
        return $dirPath;
    }

    protected function getFilesSubdir(
        RecordInterface $record,
        string $directorySeparator = DIRECTORY_SEPARATOR
    ): string {
        $this->assertRecordExistsInDb($record);
        $generator = $this->getFileSubdirGenerator();
        if (!empty($generator)) {
            $subdir = $generator($this, $record, $directorySeparator);
            if (empty($subdir) || !is_string($subdir)) {
                throw new \UnexpectedValueException(
                    'File subdir genetartor Closure should return not-empty string'
                );
            }
            return $subdir;
        }

        return $record->getPrimaryKeyValue();
    }

    public function getFilePath(RecordInterface $record): string
    {
        return $this->getFileDirPath($record)
            . $this->getFullFileName($record);
    }

    protected function getFullFileName(RecordInterface $record): string
    {
        $fileName = $this->getFileNameWithoutExtension();
        $ext = $this->getFileExtension($record);
        if (!empty($ext)) {
            $fileName .= '.' . $ext;
        }
        return $fileName;
    }

    public function getFileInfo(RecordInterface $record): DbFileInfoInterface
    {
        return $this->createFileInfoObject($record);
    }

    abstract protected function createFileInfoObject(RecordInterface $record): DbFileInfoInterface;

    /**
     * Get file name without extension
     * @param string|\Closure|null $fallbackValue - null: $this->getName() is used
     * @return string - file name without extension
     */
    public function getFileNameWithoutExtension(
        string|\Closure|null $fallbackValue = null
    ): string {
        $generator = $this->getFileNameGenerator();
        if (!empty($generator)) {
            $fileName = $generator($this);
            if (empty($fileName) || !is_string($fileName)) {
                throw new \UnexpectedValueException('File name genetartor Closure should return not-empty string');
            }
            return $fileName;
        }
        return empty($fallbackValue) ? $this->getName() : $fallbackValue;
    }

    protected function getFileExtension(RecordInterface $record): string
    {
        if ($record->existsInDb()) {
            $allowedExtensions = $this->getAllowedFileExtensions();
            if (!empty($allowedExtensions)) {
                foreach ($allowedExtensions as $ext) {
                    $fileDir = $this->getFileDirPath($record);
                    $fileNameNoExt = $this->getFileNameWithoutExtension();
                    if (File::exist($fileDir . $fileNameNoExt . '.' . $ext)) {
                        return $ext;
                    }
                }
            }
        }
        return $this->getDefaultFileExtension();
    }

    protected function getFileDirAbsoluteUrl(RecordInterface $record): string
    {
        $this->assertRecordExistsInDb($record);
        $relativeUrl = $this->getFileDirRelativeUrl($record);
        if ($this->isAbsoluteUrl($relativeUrl)) {
            return $relativeUrl;
        }
        return $this->getFileServerUrl() . '/' . trim($relativeUrl, '/\\') . '/';
    }

    protected function getFileDirRelativeUrl(RecordInterface $record): string
    {
        $this->assertRecordExistsInDb($record);

        $generator = $this->getFileDirRelativeUrlGenerator();
        if (!empty($generator)) {
            $relUrl = $generator($this, $record);
            if (empty($relUrl) || !is_string($relUrl)) {
                throw new \UnexpectedValueException('File dir relative url genetartor Closure should return not-empty string');
            }
            return rtrim($relUrl, '/\\') . '/';
        }

        $relUrl = trim($this->getBaseUrlToFiles(), '/\\');
        $objectSubdir = '/' . trim($this->getFilesSubdir($record, '/'), '/\\') . '/';
        return ($this->isAbsoluteUrl($relUrl) ? '' : '/') . $relUrl . $objectSubdir;
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

    public function isFileExists(RecordInterface $record): bool
    {
        return File::exist($this->getFilePath($record));
    }

    protected function assertRecordExistsInDb(RecordInterface $record): void
    {
        if (!$record->existsInDb()) {
            throw new \BadMethodCallException(
                'Unable to get file dir path of non-existing db record'
            );
        }
    }

    public function getBasePathToFiles(): string
    {
        return is_callable($this->basePathToFiles)
            ? call_user_func($this->basePathToFiles)
            : $this->basePathToFiles;
    }

    public function setBasePathToFiles(\Closure|string $basePathToFiles): static
    {
        if (empty($basePathToFiles)) {
            throw new \InvalidArgumentException(
                '$basePathToFiles argument must be a not empty string or \Closure'
            );
        }
        $this->basePathToFiles = $basePathToFiles;
        return $this;
    }

    public function getBaseUrlToFiles(): string
    {
        if ($this->baseUrlToFiles === null) {
            throw new \UnexpectedValueException(
                '$this->baseUrlToFiles is not provided'
            );
        }
        return is_callable($this->baseUrlToFiles) ? call_user_func($this->baseUrlToFiles) : $this->baseUrlToFiles;
    }

    public function setBaseUrlToFiles(\Closure|string $baseUrlToFiles): static
    {
        if (empty($baseUrlToFiles)) {
            throw new \InvalidArgumentException(
                '$baseUrlToFiles argument must be not empty string or \Closure'
            );
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
            throw new \InvalidArgumentException(
                '$defaultFileExtension argument must be a not-empty string'
            );
        }
        $allowedExtensions = $this->getAllowedFileExtensions();
        if (
            !empty($allowedExtensions)
            && !in_array($defaultFileExtension, $allowedExtensions, true)
        ) {
            throw new \InvalidArgumentException(
                "\$defaultFileExtension argument value '{$defaultFileExtension}' is not allowed"
            );
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
            throw new \InvalidArgumentException(
                '$allowedFileExtensions argument must be a not-empty array'
            );
        }
        $defaultExtension = $this->getDefaultFileExtension();
        if (
            !empty($defaultExtension)
            && !in_array($defaultExtension, $allowedFileExtensions, true)
        ) {
            throw new \InvalidArgumentException(
                "Default file extension '$defaultExtension' provided"
                . ' via setDefaultFileExtension() is not allowed'
            );
        }
        $this->allowedFileExtensions = array_values($allowedFileExtensions);
        return $this;
    }

    public function getFileDirRelativeUrlGenerator(): ?\Closure
    {
        return $this->fileDirRelativeUrlGenerator;
    }

    /**
     * Closure signature: function (VirtualFilesColumn $column, Record $record): string
     */
    public function setFileDirRelativeUrlGenerator(
        \Closure $fileDirRelativeUrlGenerator
    ): static {
        $this->fileDirRelativeUrlGenerator = $fileDirRelativeUrlGenerator;
        return $this;
    }

    public function getFileDirPathGenerator(): ?\Closure
    {
        return $this->fileDirPathGenerator;
    }

    /**
     * Closure signature: function (VirtualFilesColumn $column, Record $record): string
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
     * Closure signature:
     * function (VirtualFilesColumn $column, Record $record, $directorySeparator): string
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
     * Closure signature: function (VirtualFilesColumn $column): string
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
            throw new \InvalidArgumentException(
                'Unable to delete files of non-existing object'
            );
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
     * @param RecordValueContainerInterface $valueContainer - uploaded file info
     * @param array $uploadedFileInfo - uploaded file info
     * @return DbFileInfoInterface|null - array: information about file same as when you get by callings $this->getFileInfoFromInfoFile()
     */
    protected function analyzeUploadedFileAndSaveToFS(
        RecordValueContainerInterface $valueContainer,
        array $uploadedFileInfo
    ): DbFileInfoInterface|null {
        $pathToFiles = $this->getFileDirPath($valueContainer->getRecord());
        if (!is_dir($pathToFiles)) {
            Folder::add($pathToFiles, 0777);
        }
        $fileInfo = $this->createFileInfoObject($valueContainer->getRecord());

        $fileInfo->setOriginalFileNameWithoutExtension(
            preg_replace('%\.[a-zA-Z0-9]{1,6}$%', '', $uploadedFileInfo['name'])
        );
        $fileInfo->setOriginalFileNameWithExtension($uploadedFileInfo['name']);

        $fileName = $this->getFileNameWithoutExtension();
        $fileInfo->setFileNameWithoutExtension($fileName);
        $fileInfo->setFileNameWithExtension($fileName);
        $ext = $this->detectUploadedFileExtension($uploadedFileInfo);
        $fileName .= '.' . $ext;
        $fileInfo->setFileExtension($ext);
        $fileInfo->setFileNameWithExtension($fileName);
        $fileInfo->setOriginalFileNameWithExtension(
            $fileInfo->getOriginalFileNameWithoutExtension() . '.' . $ext
        );
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

    protected function storeFileToFS(
        array $uploadedFileInfo,
        string $filePath,
        DbFileInfoInterface $fileInfo
    ): void {
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
        if (
            empty($uploadedFileInfo['type'])
            && empty($uploadedFileInfo['name'])
            && empty($uploadedFileInfo['tmp_name'])
        ) {
            throw new \InvalidArgumentException(
                'Uploaded file extension cannot be detected'
            );
        }
        // test content type
        $receivedExt = null;
        if (!empty($uploadedFileInfo['type'])) {
            $receivedExt = MimeTypesHelper::getExtensionForMimeType(
                $uploadedFileInfo['type']
            );
        }
        if (!$receivedExt) {
            $fileName = null;
            if (!empty($uploadedFileInfo['name'])) {
                $fileName = $uploadedFileInfo['name'];
            } elseif (!empty($uploadedFileInfo['tmp_name'])) {
                $fileName = $uploadedFileInfo['tmp_name'];
            }
            if ($fileName) {
                $receivedExt = null;
                if (preg_match('%\.([a-zA-Z0-9]+)\s*$%i', $uploadedFileInfo['name'], $matches)) {
                    $receivedExt = $matches[1];
                }
            } else {
                $receivedExt = $this->getDefaultFileExtension();
            }
        }
        if (!$receivedExt) {
            throw new \InvalidArgumentException(
                'Uploaded file extension cannot be detected'
            );
        }
        $receivedExt = strtolower($receivedExt);
        if (!$this->isFileExtensionAllowed($receivedExt)) {
            throw new \InvalidArgumentException(
                'Uploaded file extension is not allowed'
            );
        }
        return $receivedExt;
    }

    protected function isFileExtensionAllowed(string $ext): bool
    {
        return in_array($ext, $this->getAllowedFileExtensions(), true);
    }

    protected function getIndexInMetadataColumn(): int
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

    protected function getMetadataGroupName(): string
    {
        return $this->metadataGroupName;
    }

    public function setMetadataGroupName(string $metadataGroupName): static
    {
        $this->metadataGroupName = $metadataGroupName;
        return $this;
    }
}