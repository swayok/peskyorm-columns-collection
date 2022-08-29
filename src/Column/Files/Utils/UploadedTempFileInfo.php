<?php

declare(strict_types=1);

namespace PeskyORMColumns\Column\Files\Utils;

use Ramsey\Uuid\Uuid;
use Swayok\Utils\File;
use Swayok\Utils\Folder;
use Symfony\Component\HttpFoundation\File\UploadedFile;

abstract class UploadedTempFileInfo extends \SplFileInfo
{
    
    protected ?string $name = null;
    protected ?string $type = null;
    protected ?string $relativePath = null;
    protected ?string $realPath = null;
    protected bool $isSaved = false;
    protected bool $isValid = true;
    protected ?int $size = null;
    protected ?int $position = null;
    
    abstract public static function getUploadsTempFolder(): string;
    
    abstract protected static function encodeData(array $data): string;
    
    abstract protected static function decodeData(string $encodedData): ?array;
    
    protected static function createFolder(string $path): void
    {
        Folder::load($path, true, 0777);
    }
    
    protected static function copyFile(string $fromPath, string $toPath): void
    {
        $file = File::load($fromPath);
        if (!$file->exists()) {
            throw new \InvalidArgumentException('$fromPath argument points to not existing file: ' . $fromPath);
        }
        $file->copy($toPath, true, 0666);
    }
    
    protected static function deleteFile(string $path): void
    {
        File::remove($path);
    }
    
    protected static function moveFile(string $fromPath, string $toPath, ?int $fileAccess = null): void
    {
        $file = File::load($fromPath);
        if (!$file->exists()) {
            throw new \InvalidArgumentException('$fromPath argument points to not existing file: ' . $fromPath);
        }
        $file->move($toPath, $fileAccess);
    }
    
    public static function getSubfolderName(): string
    {
        return date('Y-m-d');
    }
    
    /**
     * @param \SplFileInfo|UploadedFile|string|array|DbFileInfo|DbImageFileInfo $file
     * @param bool $save - true: if $file is UploadedFile or array - save it to disk
     * @param bool $makeCopy - true: create a copy of uploaded file and return it instead of original
     *      (use to create multiple records with same files attached)
     */
    public function __construct($file, bool $save = false, bool $makeCopy = false)
    {
        if (is_string($file)) {
            $this->decode($file);
        } elseif (is_array($file)) {
            $this->name = $file['name'];
            $this->type = $file['type'];
            $this->realPath = $file['tmp_name'];
        } elseif ($file instanceof DbFileInfo) {
            $this->name = $file->getOriginalFileNameWithExtension();
            $this->type = $file->getMimeType();
            if ($file instanceof DbImageFileInfo) {
                /** @noinspection PhpPossiblePolymorphicInvocationInspection */
                $this->realPath = $file->getFilePath(array_keys($file->getColumn()->getImageVersionsConfigs())[0]);
            } else {
                $this->realPath = $file->getFilePath();
            }
            $this->isSaved = true;
        } else {
            // \SplFileInfo or UploadedFile
            $this->name = $file->getClientOriginalName();
            $this->type = $file->getClientMimeType();
            $this->realPath = $file->getRealPath();
        }
        if (!$this->relativePath) {
            $this->relativePath = $this->makeRelativeFilePath();
        }
        if ($makeCopy) {
            $this->useCopiedFile();
        }
        if ($save) {
            $this->save();
        }
        parent::__construct($this->realPath);
    }
    
    /**
     * @return static
     */
    public function setPosition(int $position)
    {
        $this->position = $position;
        return $this;
    }
    
    public function getPosition(): ?int
    {
        return $this->position;
    }
    
    /**
     * Replace real path by copied file real path returning modified instance
     * @return static
     */
    public function useCopiedFile()
    {
        $copiedFilePath = $this->realPath . '.' . microtime(true);
        static::copyFile($this->getRealPath(), $copiedFilePath);
        $this->realPath = $copiedFilePath;
        return $this;
    }
    
    /**
     * Create a copy of this instance that uses a copy of original file
     * @return static
     */
    public function makeCopy()
    {
        return (clone $this)->useCopiedFile();
    }
    
    /**
     * @return static
     */
    public function save()
    {
        if (!$this->isSaved) {
            $this->createSubfolder();
            $newRealPath = $this->makeAbsolutePath($this->getRelativePath());
            static::moveFile($this->getRealPath(), $newRealPath);
            $this->realPath = $newRealPath;
        }
        return $this;
    }
    
    /**
     * @return static
     */
    public function delete()
    {
        static::deleteFile($this->getRealPath());
        return $this;
    }
    
    public function toArray(): array
    {
        return [
            'name' => $this->getName(),
            'type' => $this->getType(),
            'relative_path' => $this->getRelativePath(),
            'absolute_path' => $this->getRealPath(),
            'position' => $this->getPosition(),
        ];
    }
    
    public function getName(): string
    {
        return $this->name;
    }
    
    public function getFilename(): string
    {
        return $this->name;
    }
    
    public function getType(): string
    {
        return $this->type;
    }
    
    public function getRelativePath(): string
    {
        return $this->relativePath;
    }
    
    public function getRealPath(): string
    {
        return $this->realPath;
    }
    
    public function getSize(): int
    {
        if (!isset($this->size)) {
            $this->size = filesize($this->getRealPath());
        }
        return $this->size;
    }
    
    public function isValid(): bool
    {
        return $this->isValid;
    }
    
    public function isImage(): bool
    {
        return preg_match('%^image/%', $this->getType());
    }
    
    public function encode(): string
    {
        return static::encodeData([
            'name' => $this->getName(),
            'type' => $this->getType(),
            'path' => $this->getRelativePath(),
        ]);
    }
    
    protected function decode(string $encodedData): void
    {
        $data = static::decodeData($encodedData);
        if ($data && isset($data['name'], $data['type'], $data['path'])) {
            $this->name = $data['name'];
            $this->type = $data['type'];
            $this->relativePath = $data['path'];
            $this->realPath = $this->makeAbsolutePath($data['path']);
            $this->isSaved = true;
        } else {
            $this->isValid = false;
        }
    }
    
    protected function makeRelativeFilePath(): string
    {
        return '/' . static::getSubfolderName() . '/' . static::getUniqueTempFileNameWithoutExtension() . '.tmp';
    }
    
    protected static function getUniqueTempFileNameWithoutExtension(): string
    {
        return Uuid::uuid4()->toString();
    }
    
    protected function createSubfolder(): void
    {
        static::createFolder($this->makeAbsolutePath(static::getSubfolderName()));
    }
    
    protected function makeAbsolutePath(string $relativePath): string
    {
        return static::getUploadsTempFolder() . DIRECTORY_SEPARATOR . ltrim($relativePath, '/\\');
    }
}