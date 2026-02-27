<?php

namespace App\Exceptions\Domain;

class ExternalApiException extends DomainException
{
    public function __construct(string $detail = '')
    {
        $message = 'Error al comunicarse con la API externa de productos.';
        if ($detail) {
            $message .= " Detalle: {$detail}";
        }
        parent::__construct($message);
    }
    public function httpStatus(): int { return 502; }
}
