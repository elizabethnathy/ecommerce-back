<?php

namespace App\Repositories;

use App\Models\Cart;
use App\Models\CartItem;
use App\Repositories\Contracts\CartRepositoryInterface;

class CartRepository implements CartRepositoryInterface
{
    public function findActiveByUser(int $userId): ?Cart
    {
        return Cart::where('user_id', $userId)
            ->where('estado', Cart::ESTADO_ACTIVO)
            ->with('items')
            ->first();
    }

    public function findById(int $cartId): ?Cart
    {
        return Cart::with('items')->find($cartId);
    }

    public function create(int $userId): Cart
    {
        $cart = Cart::create([
            'user_id'             => $userId,
            'estado'              => Cart::ESTADO_ACTIVO,
            'total_compra'        => 0.00,
            'fecha_creacion'      => now(),
            'fecha_actualizacion' => now(),
        ]);

        $cart->setRelation('items', collect());

        return $cart;
    }

    public function findItem(int $cartId, int $externalProductId): ?CartItem
    {
        return CartItem::where('cart_id', $cartId)
            ->where('external_product_id', $externalProductId)
            ->first();
    }

    public function addItem(
        int    $cartId,
        int    $externalProductId,
        string $sku,
        string $productTitle,
        string $productThumbnail,
        float  $price,
        int    $quantity,
        int    $minimumOrderQuantity = 1,
    ): CartItem {
        return CartItem::create([
            'cart_id'                => $cartId,
            'external_product_id'    => $externalProductId,
            'sku'                    => $sku,
            'product_title'          => $productTitle,
            'product_thumbnail'      => $productThumbnail,
            'precio_unitario'        => $price,
            'cantidad'               => $quantity,
            'minimum_order_quantity' => $minimumOrderQuantity,
            'subtotal'               => round($price * $quantity, 2),
        ]);
    }

    public function updateItemQuantity(CartItem $item, int $newQuantity): CartItem
    {
        $item->cantidad = $newQuantity;
        $item->subtotal = round((float) $item->precio_unitario * $newQuantity, 2);
        $item->save();

        return $item;
    }

    public function removeItem(CartItem $item): void
    {
        $item->delete();
    }

    public function updateTotal(Cart $cart): void
    {
        $cart->total_compra        = (float) $cart->items()->sum('subtotal');
        $cart->fecha_actualizacion = now();
        $cart->save();
    }

    public function close(Cart $cart): Cart
    {
        $cart->estado              = Cart::ESTADO_CERRADO;
        $cart->fecha_actualizacion = now();
        $cart->save();

        return $cart;
    }
}
