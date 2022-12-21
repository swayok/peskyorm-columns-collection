<?php

declare(strict_types=1);

namespace PeskyORMColumns\TableColumn\Files\Utils;

class DbFileInfo extends DbFileInfoAbstract
{
    public function getFilePath(): string
    {
        return $this->column->getFilePath($this->getRecord());
    }

    public function getAbsoluteFileUrl(): string
    {
        return $this->column->getAbsoluteFileUrl($this->getRecord());
    }
}