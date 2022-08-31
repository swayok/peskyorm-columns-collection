<?php

declare(strict_types=1);

namespace PeskyORMColumns\Column\Files\Utils;

use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class Base64UploadedFile extends UploadedFile {

    protected string $tempFilePath;

    /**
     * @param string $fileData - file data encoded as base64 string
     * @param string $fileName - file name with extension
     */
    public function __construct(string $fileData, string $fileName) {
        $this->tempFilePath = tempnam(sys_get_temp_dir(), 'tmp');
        $handle = fopen($this->tempFilePath, 'wb');
        fwrite($handle, base64_decode(preg_replace('%^.{0,200}base64,%i', '', $fileData)));
        fclose($handle);
        if (preg_match('%^data:(.{1,100}/.{1,100});%i', $fileData, $mimeMatches)) {
            $mime = $mimeMatches[1];
        } else {
            $rawExt = strtolower(preg_replace('%^.*\.([a-zA-Z0-9]+?)$%', '$1', $fileName));
            /** @noinspection PhpComposerExtensionStubsInspection */
            $mime = MimeTypesHelper::getMimeTypeForExtension($rawExt) ?? mime_content_type($this->tempFilePath);
        }
        // update file extension according to detected mime type
        $ext = MimeTypesHelper::getExtensionForMimeType($mime);
        if ($ext) {
            $fileName = preg_replace('%^.*\.([a-zA-Z0-9]+?)$%', '$1', $fileName) . '.' . $ext;
        }
        parent::__construct($this->tempFilePath, $fileName, $mime, filesize($this->tempFilePath));
    }
    
    public function isValid(): bool {
        return true;
    }

    public function move($directory, $name = null): File {
        return File::move($directory, $name);
    }

    public function __destruct() {
        if ($this->tempFilePath && file_exists($this->tempFilePath) && !is_dir($this->tempFilePath)) {
            unlink($this->tempFilePath);
        }
    }

}