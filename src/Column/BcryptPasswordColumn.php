<?php

namespace PeskyORMColumns\Column;

use PeskyORM\ORM\Column;
use PeskyORM\ORM\DefaultColumnClosures;
use PeskyORM\ORM\RecordValue;

class BcryptPasswordColumn extends Column
{
    
    /** @noinspection PhpParameterNameChangedDuringInheritanceInspection */
    public static function create(?string $name = null, ?string $notUsed = null)
    {
        return new static($name);
    }
    
    public function __construct(?string $name = null, string $notUsed = null)
    {
        parent::__construct($name, self::TYPE_PASSWORD);
        $this
            ->convertsEmptyStringToNull()
            ->setValuePreprocessor(function ($value, $isDbValue, $isForValidation, Column $column) {
                $value = DefaultColumnClosures::valuePreprocessor($value, $isDbValue, $isForValidation, $column);
                if ($isDbValue) {
                    return $value;
                } elseif (!empty($value)) {
                    return static::hashPassword($value);
                } else {
                    return $value;
                }
            })
            ->setValueSetter(function ($newValue, $isFromDb, RecordValue $valueContainer, $trustDataReceivedFromDb) {
                if (!$isFromDb && ($newValue === null || (is_string($newValue) && trim($newValue) === ''))) {
                    return;
                }
                DefaultColumnClosures::valueSetter($newValue, $isFromDb, $valueContainer, $trustDataReceivedFromDb);
            })
            ->privateValue();
    }
    
    public static function hashPassword(string $plainPassword): string
    {
        return password_hash($plainPassword, PASSWORD_BCRYPT, [
            'cost' => 10,
        ]);
    }
    
    public static function checkPassword($plainPassword, $hashedPassword): bool
    {
        return password_verify($plainPassword, $hashedPassword);
    }
}