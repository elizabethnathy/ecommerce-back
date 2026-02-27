<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\User;
use App\Services\DummyJsonService;
use App\DTOs\ProductDTO;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Tests de integración del flujo completo del carrito.
 *
 * DummyJsonService se mockea para que los tests sean:
 *  - Rápidos (sin llamadas HTTP reales)
 *  - Deterministas (stock controlado)
 *  - Sin dependencias externas
 */
class CartFlowTest extends TestCase
{
    use RefreshDatabase;

    private User   $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user  = User::create([
            'nombre'   => 'Test User',
            'email'    => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->token = $this->user->createToken('test')->plainTextToken;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function auth(): array
    {
        return [
            'Authorization' => "Bearer {$this->token}",
            'Accept'        => 'application/json',
        ];
    }

    private function mockProduct(
        int   $externalId = 1,
        int   $stock      = 10,
        float $price      = 50.0
    ): ProductDTO {
        return new ProductDTO(
            id: $externalId, sku: "SKU-{$externalId}",
            title: "Producto {$externalId}", brand: 'Brand',
            thumbnail: 'https://example.com/img.jpg',
            price: $price, discountPercentage: 10.0,
            originalPrice: round($price / 0.9, 2),
            stock: $stock, category: 'test',
        );
    }

    private function bindMockDummyJson(ProductDTO $product): void
    {
        $mock = $this->createMock(DummyJsonService::class);
        $mock->method('getProductById')->willReturn($product);
        $this->app->instance(DummyJsonService::class, $mock);
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    /** @test */
    public function get_cart_creates_active_cart_if_none_exists(): void
    {
        $this->getJson('/api/cart', $this->auth())
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.estado', 'activo')
            ->assertJsonPath('data.user_id', $this->user->id)
            ->assertJsonPath('data.total_compra', 0.0);
    }

    /** @test */
    public function get_cart_returns_same_cart_on_subsequent_calls(): void
    {
        $this->getJson('/api/cart', $this->auth());
        $this->getJson('/api/cart', $this->auth());

        $this->assertDatabaseCount('carts', 1);
    }

    /** @test */
    public function add_item_creates_cart_item(): void
    {
        $product = $this->mockProduct(externalId: 1, stock: 10, price: 50.0);
        $this->bindMockDummyJson($product);

        $this->postJson('/api/cart/items', ['product_id' => 1, 'quantity' => 2], $this->auth())
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('cart_items', [
            'external_product_id' => 1,
            'cantidad'            => 2,
            'subtotal'            => 100.0,
        ]);
    }

    /** @test */
    public function add_item_increments_quantity_when_product_already_in_cart(): void
    {
        $product = $this->mockProduct(externalId: 1, stock: 10);
        $this->bindMockDummyJson($product);

        $this->postJson('/api/cart/items', ['product_id' => 1, 'quantity' => 2], $this->auth());
        $this->postJson('/api/cart/items', ['product_id' => 1, 'quantity' => 3], $this->auth());

        $this->assertDatabaseHas('cart_items', [
            'external_product_id' => 1,
            'cantidad'            => 5,
        ]);
        $this->assertDatabaseCount('cart_items', 1);
    }

    /** @test */
    public function add_item_rejects_when_stock_insufficient(): void
    {
        $product = $this->mockProduct(externalId: 1, stock: 1);
        $this->bindMockDummyJson($product);

        $this->postJson('/api/cart/items', ['product_id' => 1, 'quantity' => 5], $this->auth())
            ->assertStatus(409)
            ->assertJsonPath('error.error', 'INSUFFICIENT_STOCK');
    }

    /** @test */
    public function remove_item_removes_product_and_recalculates_total(): void
    {
        $product = $this->mockProduct(externalId: 1, stock: 10, price: 20.0);
        $this->bindMockDummyJson($product);

        $this->postJson('/api/cart/items', ['product_id' => 1, 'quantity' => 3], $this->auth());

        $this->deleteJson('/api/cart/items/1', [], $this->auth())
            ->assertStatus(200)
            ->assertJsonPath('data.total_compra', 0.0);

        $this->assertDatabaseMissing('cart_items', ['external_product_id' => 1]);
    }

    /** @test */
    public function cart_total_recalculates_correctly(): void
    {
        $product = $this->mockProduct(externalId: 1, stock: 10, price: 25.0);
        $this->bindMockDummyJson($product);

        $this->postJson('/api/cart/items', ['product_id' => 1, 'quantity' => 4], $this->auth());

        $this->getJson('/api/cart', $this->auth())
            ->assertJsonPath('data.total_compra', 100.0); // 25 * 4
    }

    /** @test */
    public function checkout_closes_cart(): void
    {
        $product = $this->mockProduct(externalId: 1, stock: 10);
        $this->bindMockDummyJson($product);

        $this->postJson('/api/cart/items', ['product_id' => 1, 'quantity' => 2], $this->auth());

        $this->postJson('/api/cart/checkout', [], $this->auth())
            ->assertStatus(200)
            ->assertJsonPath('data.estado', 'cerrado');

        $this->assertDatabaseHas('carts', [
            'user_id' => $this->user->id,
            'estado'  => Cart::ESTADO_CERRADO,
        ]);
    }

    /** @test */
    public function checkout_fails_if_stock_became_insufficient(): void
    {
        // Primer add: stock 10
        $productOk = $this->mockProduct(externalId: 1, stock: 10);
        $this->bindMockDummyJson($productOk);
        $this->postJson('/api/cart/items', ['product_id' => 1, 'quantity' => 5], $this->auth());

        // En checkout, el stock bajó a 2 (alguien más compró)
        $productLow = $this->mockProduct(externalId: 1, stock: 2);
        $this->bindMockDummyJson($productLow);

        $this->postJson('/api/cart/checkout', [], $this->auth())
            ->assertStatus(409)
            ->assertJsonPath('error.error', 'INSUFFICIENT_STOCK');
    }

    /** @test */
    public function after_checkout_new_cart_is_created_on_next_request(): void
    {
        $product = $this->mockProduct(externalId: 1, stock: 10);
        $this->bindMockDummyJson($product);

        $this->postJson('/api/cart/items', ['product_id' => 1, 'quantity' => 1], $this->auth());
        $this->postJson('/api/cart/checkout', [], $this->auth());

        // Siguiente GET crea un carrito nuevo
        $this->getJson('/api/cart', $this->auth())
            ->assertStatus(200)
            ->assertJsonPath('data.estado', 'activo');

        $this->assertDatabaseCount('carts', 2);
    }

    /** @test */
    public function cart_endpoints_require_authentication(): void
    {
        $this->getJson('/api/cart')->assertStatus(401);
        $this->postJson('/api/cart/items')->assertStatus(401);
        $this->deleteJson('/api/cart/items/1')->assertStatus(401);
        $this->postJson('/api/cart/checkout')->assertStatus(401);
    }

    /** @test */
    public function add_item_validates_request_body(): void
    {
        // Sin product_id
        $this->postJson('/api/cart/items', [], $this->auth())
            ->assertStatus(422)
            ->assertJsonPath('error.error', 'VALIDATION_ERROR');
    }
}
