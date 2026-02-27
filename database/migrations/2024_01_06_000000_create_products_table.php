<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de productos persistidos localmente desde dummyjson.
 *
 * Se pobla con el seeder ProductSeeder y permite descontar stock
 * al hacer checkout, ya que dummyjson es una API de solo lectura.
 *
 * El stock local es la fuente de verdad para las operaciones de carrito.
 * El seed inicial se sincroniza con dummyjson, y cada checkout descuenta
 * del stock local.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('external_id')->unique()->comment('ID en dummyjson');
            $table->string('sku')->nullable();
            $table->string('title');
            $table->string('brand')->nullable();
            $table->string('category')->nullable();
            $table->string('thumbnail')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('discount_percentage', 5, 2)->default(0);
            $table->decimal('original_price', 10, 2);
            $table->unsignedInteger('stock')->default(0);
            $table->float('rating')->default(0);
            $table->unsignedInteger('minimum_order_quantity')->default(1);
            $table->timestamps();

            $table->index('external_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
