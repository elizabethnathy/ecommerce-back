<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    protected $fillable = [
        'user_id',
        'direccion',
        'ciudad',
        'pais',
        'codigo_postal',
        'card_holder',
        'card_last4',
        'card_brand',
        'card_expiry',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
