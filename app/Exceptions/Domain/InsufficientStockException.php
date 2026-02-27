<?php

namespace App\Exceptions\Domain;

class InsufficientStockException extends DomainException
{
    public function __construct(int $requested, int $available)
    {
        parent::__construct(
            "Stock insuficiente. Solicitado: {$requested}, disponible: {$available}."
        );
    }
    public function httpStatus(): int { return 409; }
}
