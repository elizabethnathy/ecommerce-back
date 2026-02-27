<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained('carts')->onDelete('cascade');

            // El producto vive en la API externa (dummyjson), no en DB local.
            // Guardamos sus datos esenciales en el momento de agregar al carrito.
            $table->unsignedBigInteger('external_product_id')->comment('ID del producto en dummyjson');
            $table->string('sku')->nullable();
            $table->string('product_title');
            $table->string('product_thumbnail')->nullable();
            $table->decimal('precio_unitario', 10, 2);
            $table->unsignedInteger('cantidad');
            $table->decimal('subtotal', 10, 2);
            $table->timestamps();

            // Un producto externo solo puede aparecer una vez por carrito
            $table->unique(['cart_id', 'external_product_id']);
            $table->index('cart_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
