<?php

declare(strict_types=1);

namespace PeskyORMColumns\TableColumn\Files\Utils;

use PeskyORM\ORM\Record\RecordInterface;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnInterface;

interface DbFileInfoInterface
{
    public function getRecord(): RecordInterface;

    public function getColumn(): TableColumnInterface;

    public function readFileInfo(): static;

    public function saveToFile(): void;

    public function getInfoForMetadataItem(): array;

    public function update(array $data): static;

    public function getUuid(): string;

    public function setUuid(string $uuid): static;

    public function getPosition(): int;

    public function setPosition(int $position): static;

    public function getFileExtension(): ?string;

    public function getMimeType(): string;

    public function setFileExtension(string $extension): static;

    public function getFileNameWithoutExtension(): ?string;

    public function setFileNameWithoutExtension(string $fileNameWithoutExtension): static;

    public function getFileNameWithExtension(): ?string;

    public function setFileNameWithExtension(string $fileNameWithExtension): static;

    public function getOriginalFileNameWithExtension(): ?string;

    public function hasOriginalFileNameWithExtension(): bool;

    public function setOriginalFileNameWithExtension(string $fileNameWithExtension): static;

    public function getOriginalFileNameWithoutExtension(): ?string;

    public function setOriginalFileNameWithoutExtension(
        string $fileNameWithoutExtension
    ): static;

    public function getFilePath(): mixed;

    public function getAbsoluteFileUrl(): mixed;

    public function isFileExists(): bool;

    public function getFileSize(): int;

    public function toArray(): array;
}