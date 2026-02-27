<?php

namespace App\Http\Controllers;

use App\Http\Requests\Cart\AddItemRequest;
use App\Http\Requests\Cart\UpdateItemQuantityRequest;
use App\Http\Resources\CartResource;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends ApiController
{
    public function __construct(private readonly CartService $cartService) {}

    /**
     * GET /api/cart
     * Obtiene el carrito activo del usuario autenticado o lo crea si no existe.
     */
    public function index(Request $request): JsonResponse
    {
        $cart = $this->cartService->getOrCreate($request->user()->id);

        return $this->success(new CartResource($cart));
    }

    /**
     * POST /api/cart/items
     * Agrega un producto. Si ya existe → incrementa cantidad.
     */
    public function addItem(AddItemRequest $request): JsonResponse
    {
        $cart = $this->cartService->addItem(
            userId:             $request->user()->id,
            externalProductId:  $request->integer('product_id'),
            quantity:           $request->integer('quantity', 1),
        );

        return $this->success(new CartResource($cart), 'Producto agregado al carrito.');
    }

    /**
     * PUT /api/cart/items/{productId}
     * Actualiza la cantidad de un producto. Valida stock antes de actualizar.
     */
    public function updateItem(UpdateItemQuantityRequest $request, int $productId): JsonResponse
    {
        $cart = $this->cartService->updateItemQuantity(
            userId:            $request->user()->id,
            externalProductId: $productId,
            quantity:          $request->integer('quantity'),
        );

        return $this->success(new CartResource($cart), 'Cantidad actualizada.');
    }

    /**
     * DELETE /api/cart/items/{productId}
     * productId = external_product_id de dummyjson
     */
    public function removeItem(Request $request, int $productId): JsonResponse
    {
        $cart = $this->cartService->removeItem(
            userId:             $request->user()->id,
            externalProductId:  $productId,
        );

        return $this->success(new CartResource($cart), 'Producto eliminado del carrito.');
    }

    /**
     * POST /api/cart/checkout
     * Cierra el carrito. No admite modificaciones posteriores.
     */
    public function checkout(Request $request): JsonResponse
    {
        $cart = $this->cartService->checkout($request->user()->id);

        return $this->success(new CartResource($cart), 'Compra confirmada exitosamente.');
    }
}
