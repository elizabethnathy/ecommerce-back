<?php

namespace App\Exceptions\Domain;

use RuntimeException;

abstract class DomainException extends RuntimeException
{
    abstract public function httpStatus(): int;

    public function toArray(): array
    {
        return [
            'error'   => $this->errorCode(),
            'message' => $this->getMessage(),
        ];
    }

    protected function errorCode(): string
    {
        $class = class_basename(static::class);
        $class = str_replace('Exception', '', $class);
        // PascalCase → SCREAMING_SNAKE_CASE
        return strtoupper(preg_replace('/(?<!^)[A-Z]/', '_$0', $class));
    }
}
