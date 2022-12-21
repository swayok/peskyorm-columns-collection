<?php

declare(strict_types=1);

namespace PeskyORMColumns\TableColumn;

use PeskyORM\ORM\TableStructure\TableColumn\TemplateColumn\BooleanColumnTemplate;

class IsPublishedColumn extends BooleanColumnTemplate
{
    public function __construct(string $name = 'is_published')
    {
        parent::__construct($name);
        $this->setDefaultValue(false);
    }
}