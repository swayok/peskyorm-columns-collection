<?php

declare(strict_types=1);

namespace PeskyORMColumns\TableStructureTraits;

use PeskyORM\ORM\Column;

/**
 * @psalm-require-implements \PeskyORM\ORM\TableStructureInterface
 */
trait IdColumn
{
    
    private function id(): Column
    {
        return Column::create(Column::TYPE_INT)
            ->primaryKey()
            ->disallowsNullValues()
            ->convertsEmptyStringToNull();
    }
}