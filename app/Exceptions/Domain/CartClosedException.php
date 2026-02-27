<?php

namespace App\Exceptions\Domain;

class CartClosedException extends DomainException
{
    public function __construct()
    {
        parent::__construct('El carrito ya está cerrado y no admite modificaciones.');
    }
    public function httpStatus(): int { return 409; }
}
