<?php

declare(strict_types=1);

namespace PeskyORMColumns\TableStructureTraits;

use PeskyORM\ORM\Column;

/**
 * @psalm-require-implements \PeskyORM\ORM\TableStructureInterface
 */
trait UserAuthColumns
{
    
    use PasswordColumn;
    
    private function remember_token(): Column
    {
        return Column::create(Column::TYPE_STRING)
            ->allowsNullValues()
            ->convertsEmptyStringToNull()
            ->privateValue();
    }
    
}