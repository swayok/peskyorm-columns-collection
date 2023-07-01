<?php

declare(strict_types=1);

namespace PeskyORMColumns\TableColumn;

use PeskyORM\ORM\TableStructure\TableColumn\TemplateColumn\StringColumnTemplate;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBePrivate;

class RememberTokenColumn extends StringColumnTemplate
{
    use CanBePrivate;

    public function __construct(string $name = 'remember_token')
    {
        parent::__construct($name);
    }

    public function isNullableValues(): bool
    {
        return true;
    }

    protected function normalizeValueForValidation(mixed $value, bool $isFromDb): mixed
    {
        $value = parent::normalizeValueForValidation($value, $isFromDb);
        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }
        }
        return $value;
    }
}
