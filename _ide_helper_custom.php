<?php
/** @noinspection PhpFullyQualifiedNameUsageInspection */

/** @noinspection AutoloadingIssuesInspection */

/** @noinspection PhpUnused */

class PeskyORMColumnsIdeHelperRecord extends \PeskyORM\ORM\Record\Record
{
    use \PeskyORMColumns\RecordTraits\HandlesPositioningCollisions;

    public function getTable(): \PeskyORM\ORM\Table\TableInterface
    {
        return \PeskyORM\ORM\Table\Table::getInstance();
    }
}