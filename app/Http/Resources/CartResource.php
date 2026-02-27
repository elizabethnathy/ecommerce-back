<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'user_id'             => $this->user_id,
            'estado'              => $this->estado,
            'total_compra'        => (float) $this->total_compra,
            'fecha_creacion'      => $this->fecha_creacion?->toIso8601String(),
            'fecha_actualizacion' => $this->fecha_actualizacion?->toIso8601String(),
            'items'               => CartItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
