<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — v1
|--------------------------------------------------------------------------
*/

// ── Auth (públicas) ──────────────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);
});

// ── Productos (públicas) ─────────────────────────────────────────────────────
Route::get('/products',          [ProductController::class, 'index']);
Route::get('/products/{id}',     [ProductController::class, 'show'])
    ->where('id', '[0-9]+');

// ── Rutas protegidas (Sanctum) ───────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Perfil de usuario (dirección + tarjeta preferida)
    Route::get('/profile',  [ProfileController::class, 'show']);
    Route::put('/profile',  [ProfileController::class, 'update']);

    // Carrito
    Route::get('/cart',                          [CartController::class, 'index']);
    Route::post('/cart/items',                   [CartController::class, 'addItem']);
    Route::put('/cart/items/{productId}',        [CartController::class, 'updateItem'])
        ->where('productId', '[0-9]+');
    Route::delete('/cart/items/{productId}',     [CartController::class, 'removeItem'])
        ->where('productId', '[0-9]+');
    Route::post('/cart/checkout',                [CartController::class, 'checkout']);
});
