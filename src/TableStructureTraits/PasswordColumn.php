<?php
/** @noinspection PhpUnusedPrivateMethodInspection */

declare(strict_types=1);

namespace PeskyORMColumns\TableStructureTraits;

use PeskyORM\ORM\Column;
use PeskyORMColumns\Column\BcryptPasswordColumn;

/**
 * @psalm-require-implements \PeskyORM\ORM\TableStructureInterface
 */
trait PasswordColumn
{
    
    private function password(): Column
    {
        $column = static::createPasswordColumn();
        static::modifyPasswordColumn($column);
        return $column;
    }
    
    protected static function createPasswordColumn(): Column
    {
        return BcryptPasswordColumn::create();
    }
    
    protected static function modifyPasswordColumn(Column $column): void
    {
        $column->disallowsNullValues();
    }
    
}