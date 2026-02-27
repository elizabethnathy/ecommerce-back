<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'external_id',
        'sku',
        'title',
        'brand',
        'category',
        'thumbnail',
        'price',
        'discount_percentage',
        'original_price',
        'stock',
        'rating',
        'minimum_order_quantity',
    ];

    protected $casts = [
        'price'               => 'float',
        'discount_percentage' => 'float',
        'original_price'      => 'float',
        'rating'              => 'float',
        'stock'               => 'integer',
        'minimum_order_quantity' => 'integer',
    ];

    /**
     * Descuenta stock de manera segura (sin bajar de 0).
     */
    public function decrementStock(int $quantity): void
    {
        $this->stock = max(0, $this->stock - $quantity);
        $this->save();
    }

    public function hasStock(int $quantity = 1): bool
    {
        return $this->stock >= $quantity;
    }
}
