<?php

namespace App\Exceptions\Domain;

class CartNotFoundException extends DomainException
{
    public function __construct()
    {
        parent::__construct('No existe un carrito activo para este usuario.');
    }
    public function httpStatus(): int { return 404; }
}
