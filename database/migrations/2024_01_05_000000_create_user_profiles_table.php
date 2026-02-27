<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            // Dirección de envío
            $table->string('direccion')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('pais')->nullable()->default('PE');
            $table->string('codigo_postal')->nullable();

            // Tarjeta preferida (solo últimos 4 dígitos + tipo, jamás datos reales)
            $table->string('card_holder')->nullable();
            $table->string('card_last4', 4)->nullable();
            $table->string('card_brand')->nullable(); // visa, mastercard, etc.
            $table->string('card_expiry', 7)->nullable(); // MM/YYYY

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
