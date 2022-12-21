<?php

declare(strict_types=1);

namespace PeskyORMColumns\TableColumn;

use PeskyORM\ORM\TableStructure\TableColumn\Column\TimestampColumn;

class DeletedAtColumn extends TimestampColumn
{
    public function __construct(string $name = 'deleted_at')
    {
        parent::__construct($name);
        $this->allowsNullValues();
    }
}