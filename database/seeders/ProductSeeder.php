<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Services\DummyJsonService;
use App\DTOs\ProductDTO;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

/**
 * Sincroniza todos los productos de dummyjson con la tabla local `products`.
 *
 * Uso:
 *   php artisan db:seed --class=ProductSeeder
 *
 * Puede ejecutarse múltiples veces (upsert seguro por external_id).
 */
class ProductSeeder extends Seeder
{
    public function __construct(private readonly DummyJsonService $dummyJson) {}

    public function run(): void
    {
        $this->command->info('Sincronizando productos desde dummyjson.com...');

        try {
            // Usamos el método interno que trae todos los productos
            $result = $this->dummyJson->listProductsSorted(PHP_INT_MAX, 0, 'asc');

            /** @var \Illuminate\Support\Collection<ProductDTO> $products */
            $products = $result['products'];

            $total = $products->count();
            $bar   = $this->command->getOutput()->createProgressBar($total);

            foreach ($products as $dto) {
                Product::updateOrCreate(
                    ['external_id' => $dto->id],
                    [
                        'sku'                    => $dto->sku,
                        'title'                  => $dto->title,
                        'brand'                  => $dto->brand,
                        'category'               => $dto->category,
                        'thumbnail'              => $dto->thumbnail,
                        'price'                  => $dto->price,
                        'discount_percentage'    => $dto->discountPercentage,
                        'original_price'         => $dto->originalPrice,
                        'stock'                  => $dto->stock,
                        'rating'                 => $dto->rating,
                        'minimum_order_quantity' => $dto->minimumOrderQuantity,
                    ]
                );
                $bar->advance();
            }

            $bar->finish();
            $this->command->newLine();
            $this->command->info("✅ {$total} productos sincronizados correctamente.");

        } catch (\Throwable $e) {
            Log::error('[ProductSeeder] Error al sincronizar', ['error' => $e->getMessage()]);
            $this->command->error('Error al sincronizar productos: ' . $e->getMessage());
        }
    }
}
