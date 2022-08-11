<?php

declare(strict_types=1);

namespace PeskyORMColumns\TableStructureTraits;

use PeskyORMColumns\Column\RecordPositionColumn;

/**
 * @psalm-require-implements \PeskyORM\ORM\TableStructureInterface
 */
trait PositionColumn
{
    
    private function position(): RecordPositionColumn
    {
        return RecordPositionColumn::create()
            ->disallowsNullValues()
            ->uniqueValues();
    }
}