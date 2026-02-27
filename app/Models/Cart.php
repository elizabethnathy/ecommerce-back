<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    use HasFactory;

    const ESTADO_ACTIVO  = 'activo';
    const ESTADO_CERRADO = 'cerrado';

    // Usamos timestamps custom en lugar de created_at/updated_at
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'estado',
        'total_compra',
        'fecha_creacion',
        'fecha_actualizacion',
    ];

    protected $casts = [
        'total_compra'        => 'decimal:2',
        'fecha_creacion'      => 'datetime',
        'fecha_actualizacion' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function isActive(): bool
    {
        return $this->estado === self::ESTADO_ACTIVO;
    }

    public function isClosed(): bool
    {
        return $this->estado === self::ESTADO_CERRADO;
    }
}
