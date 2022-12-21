<?php

declare(strict_types=1);

namespace PeskyORMColumns\TableColumn\Files;

use PeskyORM\ORM\Record\RecordInterface;
use PeskyORM\ORM\Record\RecordValueContainerInterface;
use PeskyORMColumns\TableColumn\Files\Utils\DbFileInfo;
use PeskyORMColumns\TableColumn\Files\Utils\DbFileInfoInterface;

class VirtualFilesColumn extends VirtualFilesColumnAbstract
{
    protected function createFileInfoObject(RecordInterface $record): DbFileInfoInterface
    {
        return new DbFileInfo(
            $record,
            $this,
            $this->getMetadataGroupName(),
            $this->getIndexInMetadataColumn()
        );
    }

    public function getAbsoluteFileUrl(RecordInterface $record): string|null {
        return $this->getFileDirAbsoluteUrl($record) . $this->getFullFileName($record);
    }
}