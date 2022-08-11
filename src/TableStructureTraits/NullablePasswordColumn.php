<?php

declare(strict_types=1);

namespace PeskyORMColumns\TableStructureTraits;

use PeskyORM\ORM\Column;

/**
 * @psalm-require-implements \PeskyORM\ORM\TableStructureInterface
 */
trait NullablePasswordColumn
{
    
    use PasswordColumn;
    
    protected static function modifyPasswordColumn(Column $column): void
    {
        $column->allowsNullValues();
    }
    
}