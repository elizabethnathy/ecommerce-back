<?php

namespace App\Services;

use App\DTOs\ProductDTO;
use App\Exceptions\Domain\CartClosedException;
use App\Exceptions\Domain\CartNotFoundException;
use App\Exceptions\Domain\InsufficientStockException;
use App\Exceptions\Domain\ProductNotFoundException;
use App\Exceptions\Domain\UserNotFoundException;
use App\Models\Cart;
use App\Models\Product;
use App\Models\User;
use App\Repositories\Contracts\CartRepositoryInterface;
use Illuminate\Support\Facades\DB;

class CartService
{
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly DummyJsonService        $dummyJson,
    ) {}

    // -------------------------------------------------------------------------
    // Obtener o crear carrito activo
    // -------------------------------------------------------------------------

    public function getOrCreate(int $userId): Cart
    {
        $this->ensureUserExists($userId);

        $cart = $this->cartRepository->findActiveByUser($userId);

        return $cart ?? $this->cartRepository->create($userId);
    }

    // -------------------------------------------------------------------------
    // Agregar producto
    // -------------------------------------------------------------------------

    public function addItem(int $userId, int $externalProductId, int $quantity): Cart
    {
        $cart = $this->getOrCreate($userId);

        if ($cart->isClosed()) {
            throw new CartClosedException();
        }

        // Fuente de verdad de stock: tabla local products
        // Si no existe en local, cae a dummyjson (primer uso antes del seed)
        [$stockAvailable, $minQty, $title, $sku, $thumbnail, $price, $minimumOrderQty] =
            $this->resolveProductData($externalProductId);

        $existingItem = $this->cartRepository->findItem($cart->id, $externalProductId);
        $currentQty   = $existingItem?->cantidad ?? 0;

        // Auto-ajustar al mínimo si es el primer item
        if ($currentQty === 0 && $quantity < $minimumOrderQty) {
            $quantity = $minimumOrderQty;
        }

        $totalQty = $currentQty + $quantity;

        if ($stockAvailable < $totalQty) {
            throw new InsufficientStockException($totalQty, $stockAvailable);
        }

        if ($existingItem) {
            $this->cartRepository->updateItemQuantity($existingItem, $totalQty);
        } else {
            $this->cartRepository->addItem(
                cartId:               $cart->id,
                externalProductId:    $externalProductId,
                sku:                  $sku,
                productTitle:         $title,
                productThumbnail:     $thumbnail,
                price:                $price,
                quantity:             $quantity,
                minimumOrderQuantity: $minimumOrderQty,
            );
        }

        $this->cartRepository->updateTotal($cart);

        return $cart->fresh(['items']);
    }

    // -------------------------------------------------------------------------
    // Actualizar cantidad de un item
    // -------------------------------------------------------------------------

    public function updateItemQuantity(int $userId, int $externalProductId, int $quantity): Cart
    {
        $cart = $this->cartRepository->findActiveByUser($userId);

        if (!$cart) {
            throw new CartNotFoundException();
        }

        if ($cart->isClosed()) {
            throw new CartClosedException();
        }

        [$stockAvailable, $minQty] = $this->resolveProductData($externalProductId);

        // Respetar cantidad mínima al actualizar
        if ($quantity < $minQty) {
            $quantity = $minQty;
        }

        if ($stockAvailable < $quantity) {
            throw new InsufficientStockException($quantity, $stockAvailable);
        }

        $item = $this->cartRepository->findItem($cart->id, $externalProductId);

        if (!$item) {
            throw new CartNotFoundException();
        }

        $this->cartRepository->updateItemQuantity($item, $quantity);
        $this->cartRepository->updateTotal($cart);

        return $cart->fresh(['items']);
    }

    // -------------------------------------------------------------------------
    // Eliminar producto
    // -------------------------------------------------------------------------

    public function removeItem(int $userId, int $externalProductId): Cart
    {
        $cart = $this->cartRepository->findActiveByUser($userId);

        if (!$cart) {
            throw new CartNotFoundException();
        }

        if ($cart->isClosed()) {
            throw new CartClosedException();
        }

        $item = $this->cartRepository->findItem($cart->id, $externalProductId);

        if ($item) {
            $this->cartRepository->removeItem($item);
            $this->cartRepository->updateTotal($cart);
        }

        return $cart->fresh(['items']);
    }

    // -------------------------------------------------------------------------
    // Checkout
    // -------------------------------------------------------------------------

    public function checkout(int $userId): Cart
    {
        return DB::transaction(function () use ($userId) {
            $cart = Cart::where('user_id', $userId)
                ->where('estado', Cart::ESTADO_ACTIVO)
                ->lockForUpdate()
                ->first();

            if (!$cart) {
                throw new CartNotFoundException();
            }

            if ($cart->isClosed()) {
                throw new CartClosedException();
            }

            // Validar y descontar stock local con lock (evita race conditions)
            foreach ($cart->items as $item) {
                $localProduct = Product::where('external_id', $item->external_product_id)
                    ->lockForUpdate()
                    ->first();

                if ($localProduct) {
                    // Fuente de verdad: tabla local
                    if ($localProduct->stock < $item->cantidad) {
                        throw new InsufficientStockException($item->cantidad, $localProduct->stock);
                    }
                    $localProduct->decrementStock($item->cantidad);
                } else {
                    // Fallback a dummyjson si no hay registro local
                    $externalProduct = $this->dummyJson->getProductById($item->external_product_id);
                    if ($externalProduct->stock < $item->cantidad) {
                        throw new InsufficientStockException($item->cantidad, $externalProduct->stock);
                    }
                }
            }

            return $this->cartRepository->close($cart);
        });
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Resuelve los datos de un producto priorizando la tabla local.
     * Si el producto no existe en local, cae a dummyjson como fallback.
     *
     * @return array{int, int, string, string, string, float, int}
     *         [stockAvailable, minimumOrderQty, title, sku, thumbnail, price, minimumOrderQty]
     */
    private function resolveProductData(int $externalProductId): array
    {
        $local = Product::where('external_id', $externalProductId)->first();

        if ($local) {
            return [
                $local->stock,
                $local->minimum_order_quantity,
                $local->title,
                $local->sku ?? '',
                $local->thumbnail ?? '',
                (float) $local->price,
                $local->minimum_order_quantity,
            ];
        }

        // Fallback a dummyjson (cuando el producto no está en la tabla local)
        $dto = $this->dummyJson->getProductById($externalProductId);

        return [
            $dto->stock,
            $dto->minimumOrderQuantity,
            $dto->title,
            $dto->sku,
            $dto->thumbnail,
            $dto->price,
            $dto->minimumOrderQuantity,
        ];
    }

    private function ensureUserExists(int $userId): void
    {
        if (!User::find($userId)) {
            throw new UserNotFoundException($userId);
        }
    }
}
