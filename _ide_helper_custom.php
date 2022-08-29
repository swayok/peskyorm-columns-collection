<?php
/** @noinspection PhpFullyQualifiedNameUsageInspection */

/** @noinspection AutoloadingIssuesInspection */

/** @noinspection PhpUnused */

class PeskyORMColumnsIdeHelperRecord extends \PeskyORM\ORM\Record
{
    use \PeskyORMColumns\RecordTraits\HandlesPositioningCollisions;
    
    public static function getTable(): \PeskyORM\ORM\TableInterface
    {
        return \PeskyORM\ORM\Table::getInstance();
    }
}