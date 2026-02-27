<?php

namespace App\Exceptions;

use App\Exceptions\Domain\DomainException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontFlash = ['current_password', 'password', 'password_confirmation'];

    public function register(): void
    {
        // Excepciones de dominio tipadas
        $this->renderable(function (DomainException $e) {
            return response()->json([
                'success' => false,
                'error'   => $e->toArray(),
            ], $e->httpStatus());
        });

        // Sin token / token inválido
        $this->renderable(function (AuthenticationException $e) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'error'   => 'UNAUTHENTICATED',
                    'message' => 'No autenticado. Token inválido o ausente.',
                ],
            ], 401);
        });

        // Validación de Form Requests
        $this->renderable(function (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'error'   => 'VALIDATION_ERROR',
                    'message' => 'Los datos proporcionados no son válidos.',
                    'details' => $e->errors(),
                ],
            ], 422);
        });

        // Fallback — oculta stack trace en producción
        $this->renderable(function (Throwable $e) {
            if (config('app.debug')) {
                return null; // Laravel muestra el debug completo
            }

            return response()->json([
                'success' => false,
                'error'   => [
                    'error'   => 'INTERNAL_SERVER_ERROR',
                    'message' => 'Error interno del servidor.',
                ],
            ], 500);
        });
    }
}
