<?php

declare(strict_types=1);

namespace PeskyORMColumns\Column;

use PeskyORM\Core\DbExpr;
use PeskyORM\ORM\Column;

class RecordPositionColumn extends Column
{
    
    protected $increment = 100;
    
    public static function create(string $type = self::TYPE_INT, ?string $name = null)
    {
        return parent::create($type, $name);
    }
    
    public function __construct(?string $name = null, string $type = self::TYPE_INT)
    {
        if (!in_array($type, [static::TYPE_INT, static::TYPE_FLOAT, static::TYPE_UNIX_TIMESTAMP], true)) {
            throw new \InvalidArgumentException(
                'Column $type must be of a numeric type (integer, float, unix timestamp).'
                . "'$type' type is not allowed"
            );
        }
        parent::__construct($name, $type);
        $this->disallowsNullValues();
        $this->setDefaultValue(function () {
            $tableName = $this->getTableStructure()
                ->getTableName();
            return DbExpr::create(
                'COALESCE((SELECT `' . $this->getName() . '` FROM `' . $tableName
                . '` ORDER BY `' . $this->getName() . '` DESC LIMIT 1), 0) + ``' . $this->getIncrement() . '``',
                false
            );
        });
    }
    
    /**
     * @throws \BadMethodCallException
     */
    public function doesNotExistInDb()
    {
        throw new \BadMethodCallException(
            'This column must exist in database. Column: '
            . get_class($this->getTableStructure()) . '->' . ($this->hasName() ? $this->getName() : '__undefined__')
        );
    }
    
    /**
     * @return int
     */
    public function getIncrement(): int
    {
        return $this->increment;
    }
    
    /**
     * @param int $increment - distance between  step
     * @return static
     * @throws \InvalidArgumentException
     */
    public function setIncrement(int $increment)
    {
        if ($increment === 0) {
            throw new \InvalidArgumentException('$increment argument cannot be 0');
        }
        $this->increment = $increment;
        return $this;
    }
    
}