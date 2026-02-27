<?php

namespace Tests\Unit;

use App\DTOs\ProductDTO;
use PHPUnit\Framework\TestCase;

class ProductDTOTest extends TestCase
{
    /** @test */
    public function it_calculates_original_price_from_discounted_price(): void
    {
        // price = 90, discount = 10% → original = 90 / 0.9 = 100
        $dto = ProductDTO::fromRaw([
            'id'                 => 1,
            'sku'                => 'SKU-A',
            'title'              => 'Test',
            'brand'              => 'Brand',
            'thumbnail'          => 'http://img.jpg',
            'price'              => 90.0,
            'discountPercentage' => 10.0,
            'stock'              => 50,
            'category'           => 'electronics',
        ]);

        $this->assertEquals(100.0, $dto->originalPrice);
        $this->assertEquals(90.0,  $dto->price);
        $this->assertEquals(10.0,  $dto->discountPercentage);
    }

    /** @test */
    public function it_returns_same_price_when_no_discount(): void
    {
        $dto = ProductDTO::fromRaw([
            'id' => 2, 'title' => 'No Discount',
            'price' => 50.0, 'discountPercentage' => 0,
            'stock' => 5,
        ]);

        $this->assertEquals(50.0, $dto->price);
        $this->assertEquals(50.0, $dto->originalPrice);
    }

    /** @test */
    public function it_does_not_divide_by_zero_on_100_percent_discount(): void
    {
        $dto = ProductDTO::fromRaw([
            'id' => 3, 'title' => 'Free',
            'price' => 0.0, 'discountPercentage' => 100,
            'stock' => 1,
        ]);

        // divisor = 0 → original_price = price = 0.0 (sin error)
        $this->assertEquals(0.0, $dto->originalPrice);
    }

    /** @test */
    public function it_handles_missing_optional_fields_with_defaults(): void
    {
        $dto = ProductDTO::fromRaw([
            'id'    => 4,
            'title' => 'Minimal',
            'price' => 10.0,
        ]);

        $this->assertEquals('', $dto->brand);
        $this->assertEquals('', $dto->thumbnail);
        $this->assertEquals(0,  $dto->stock);
        $this->assertEquals('', $dto->sku);
    }

    /** @test */
    public function it_rounds_original_price_to_two_decimal_places(): void
    {
        // 33.33 / 0.95 = 35.0842...  → should round to 35.09
        $dto = ProductDTO::fromRaw([
            'id' => 5, 'title' => 'Rounding',
            'price' => 33.33, 'discountPercentage' => 5.0,
            'stock' => 1,
        ]);

        $decimals = strlen(substr(strrchr((string) $dto->originalPrice, '.'), 1));
        $this->assertLessThanOrEqual(2, $decimals);
    }

    /** @test */
    public function to_array_returns_all_expected_keys(): void
    {
        $dto   = ProductDTO::fromRaw(['id' => 6, 'title' => 'T', 'price' => 10.0]);
        $array = $dto->toArray();

        $this->assertArrayHasKey('id',                  $array);
        $this->assertArrayHasKey('price',               $array);
        $this->assertArrayHasKey('discount_percentage', $array);
        $this->assertArrayHasKey('original_price',      $array);
        $this->assertArrayHasKey('stock',               $array);
        $this->assertArrayHasKey('thumbnail',           $array);
        $this->assertArrayHasKey('brand',               $array);
    }
}
