<?php

declare(strict_types=1);

namespace PeskyORMColumns\TableColumn;

use PeskyORM\DbExpr;
use PeskyORM\ORM\TableStructure\TableColumn\TemplateColumn\IntegerColumnTemplate;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBeNullable;
use PeskyORM\Utils\ArgumentValidators;

class RecordPositionColumn extends IntegerColumnTemplate
{
    use CanBeNullable;

    protected int $increment;

    public function __construct(
        string $name = 'position',
        int $increment = 100
    ) {
        parent::__construct($name);
        $this
            ->setIncrement($increment)
            ->setDefaultValue(function () {
                /** @noinspection NullPointerExceptionInspection - intended */
                $tableName = $this->getTableStructure()->getTableName();
                return DbExpr::create(
                    'COALESCE((SELECT `' . $this->getName() . '` FROM `' . $tableName
                    . '` ORDER BY `' . $this->getName() . '` DESC LIMIT 1), 0) + ``' . $this->getIncrement() . '``',
                    false
                );
            });
    }

    protected function getIncrement(): int
    {
        return $this->increment;
    }

    /**
     * @param int $increment - numeric distance between positions
     * @throws \InvalidArgumentException
     */
    public function setIncrement(int $increment): static
    {
        ArgumentValidators::assertPositiveInteger('$increment', $increment, false);
        $this->increment = $increment;
        return $this;
    }
}
