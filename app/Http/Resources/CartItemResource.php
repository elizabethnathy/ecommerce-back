<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'external_product_id'    => $this->external_product_id,
            'sku'                    => $this->sku,
            'product_title'          => $this->product_title,
            'product_thumbnail'      => $this->product_thumbnail,
            'precio_unitario'        => (float) $this->precio_unitario,
            'cantidad'               => $this->cantidad,
            'minimum_order_quantity' => (int) $this->minimum_order_quantity,
            'subtotal'               => (float) $this->subtotal,
        ];
    }
}
