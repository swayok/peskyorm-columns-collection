<?php

declare(strict_types=1);

namespace PeskyORMColumns\TableColumn;

use PeskyORM\ORM\TableStructure\TableColumn\TemplateColumn\BooleanColumnTemplate;

class IsDeletedColumn extends BooleanColumnTemplate
{
    public function __construct(string $name = 'is_deleted')
    {
        parent::__construct($name);
        $this->setDefaultValue(false);
    }
}