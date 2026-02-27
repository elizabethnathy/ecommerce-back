<?php

namespace App\Repositories\Contracts;

use App\Models\Cart;
use App\Models\CartItem;

interface CartRepositoryInterface
{
    public function findActiveByUser(int $userId): ?Cart;

    public function findById(int $cartId): ?Cart;

    public function create(int $userId): Cart;

    public function findItem(int $cartId, int $externalProductId): ?CartItem;

    public function addItem(
        int    $cartId,
        int    $externalProductId,
        string $sku,
        string $productTitle,
        string $productThumbnail,
        float  $price,
        int    $quantity,
        int    $minimumOrderQuantity,
    ): CartItem;

    public function updateItemQuantity(CartItem $item, int $newQuantity): CartItem;

    public function removeItem(CartItem $item): void;

    public function updateTotal(Cart $cart): void;

    public function close(Cart $cart): Cart;
}
