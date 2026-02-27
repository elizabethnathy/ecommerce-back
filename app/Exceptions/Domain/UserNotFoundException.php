<?php

namespace App\Exceptions\Domain;

class UserNotFoundException extends DomainException
{
    public function __construct(int|string $id)
    {
        parent::__construct("Usuario '{$id}' no encontrado.");
    }
    public function httpStatus(): int { return 404; }
}
