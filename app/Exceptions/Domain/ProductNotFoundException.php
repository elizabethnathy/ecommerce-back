<?php

namespace App\Exceptions\Domain;

class ProductNotFoundException extends DomainException
{
    public function __construct(int|string $id)
    {
        parent::__construct("Producto '{$id}' no encontrado en la API externa.");
    }
    public function httpStatus(): int { return 404; }
}
