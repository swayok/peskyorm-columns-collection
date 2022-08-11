<?php

declare(strict_types=1);

namespace PeskyORMColumns\TableStructureTraits;

use PeskyORM\ORM\Column;

/**
 * @psalm-require-implements \PeskyORM\ORM\TableStructureInterface
 */
trait FilesMetadataColumn
{
    
    private function files_metadata(): Column
    {
        return Column::create(Column::TYPE_JSONB)
            ->disallowsNullValues()
            ->setDefaultValue('{}');
    }
}