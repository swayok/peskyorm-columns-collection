<?php

declare(strict_types=1);

namespace PeskyORMColumns\TableColumn;

use PeskyORM\ORM\TableStructure\TableColumn\TemplateColumn\BooleanColumnTemplate;

class IsActiveColumn extends BooleanColumnTemplate
{
    public function __construct(string $name = 'is_active')
    {
        parent::__construct($name);
        $this->setDefaultValue(true);
    }
}