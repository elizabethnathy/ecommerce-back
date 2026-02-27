<?php

namespace Tests\Unit;

use App\DTOs\ProductDTO;
use App\Exceptions\Domain\CartClosedException;
use App\Exceptions\Domain\InsufficientStockException;
use App\Exceptions\Domain\ProductNotFoundException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\User;
use App\Repositories\Contracts\CartRepositoryInterface;
use App\Services\CartService;
use App\Services\DummyJsonService;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class CartServiceTest extends TestCase
{
    private CartService $service;
    private CartRepositoryInterface|MockInterface $cartRepo;
    private DummyJsonService|MockInterface $dummyJson;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cartRepo  = Mockery::mock(CartRepositoryInterface::class);
        $this->dummyJson = Mockery::mock(DummyJsonService::class);
        $this->service   = new CartService($this->cartRepo, $this->dummyJson);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ── addItem ───────────────────────────────────────────────────────────────

    /** @test */
    public function add_item_throws_when_product_not_found_in_external_api(): void
    {
        $cart = $this->makeActiveCart();

        $this->cartRepo->shouldReceive('findActiveByUser')->andReturn($cart);
        $this->dummyJson->shouldReceive('getProductById')
            ->with(999)
            ->andThrow(new ProductNotFoundException(999));

        // Needed because getOrCreate calls User::find — we use the real model
        // so we mock the User lookup via Mockery alias or use a simpler approach:
        // We test just the product-not-found path here.
        $this->expectException(ProductNotFoundException::class);

        $this->service->addItem(userId: 1, externalProductId: 999, quantity: 1);
    }

    /** @test */
    public function add_item_throws_when_stock_insufficient(): void
    {
        $cart    = $this->makeActiveCart();
        $product = $this->makeProductDTO(stock: 2);

        $this->cartRepo->shouldReceive('findActiveByUser')->andReturn($cart);
        $this->dummyJson->shouldReceive('getProductById')->andReturn($product);
        $this->cartRepo->shouldReceive('findItem')->andReturn(null);

        $this->expectException(InsufficientStockException::class);

        $this->service->addItem(userId: 1, externalProductId: 1, quantity: 5);
    }

    /** @test */
    public function add_item_throws_when_cart_is_closed(): void
    {
        $cart = $this->makeClosedCart();
        $this->cartRepo->shouldReceive('findActiveByUser')->andReturn($cart);

        $this->expectException(CartClosedException::class);

        $this->service->addItem(userId: 1, externalProductId: 1, quantity: 1);
    }

    /** @test */
    public function add_item_increments_quantity_when_product_already_in_cart(): void
    {
        $cart         = $this->makeActiveCart();
        $product      = $this->makeProductDTO(stock: 10);
        $existingItem = $this->makeCartItem(quantity: 3);

        $this->cartRepo->shouldReceive('findActiveByUser')->andReturn($cart);
        $this->dummyJson->shouldReceive('getProductById')->andReturn($product);
        $this->cartRepo->shouldReceive('findItem')->andReturn($existingItem);
        // 3 + 2 = 5, within stock of 10
        $this->cartRepo->shouldReceive('updateItemQuantity')->with($existingItem, 5)->once();
        $this->cartRepo->shouldReceive('updateTotal')->once();
        $cart->shouldReceive('fresh')->andReturnSelf();

        $result = $this->service->addItem(userId: 1, externalProductId: 1, quantity: 2);

        $this->assertNotNull($result);
    }

    /** @test */
    public function add_item_throws_when_combined_quantity_exceeds_stock(): void
    {
        $cart         = $this->makeActiveCart();
        $product      = $this->makeProductDTO(stock: 4);
        $existingItem = $this->makeCartItem(quantity: 3); // 3 + 2 = 5 > 4

        $this->cartRepo->shouldReceive('findActiveByUser')->andReturn($cart);
        $this->dummyJson->shouldReceive('getProductById')->andReturn($product);
        $this->cartRepo->shouldReceive('findItem')->andReturn($existingItem);

        $this->expectException(InsufficientStockException::class);

        $this->service->addItem(userId: 1, externalProductId: 1, quantity: 2);
    }

    // ── removeItem ────────────────────────────────────────────────────────────

    /** @test */
    public function remove_item_throws_when_cart_is_closed(): void
    {
        $cart = $this->makeClosedCart();
        $this->cartRepo->shouldReceive('findActiveByUser')->andReturn($cart);

        $this->expectException(CartClosedException::class);

        $this->service->removeItem(userId: 1, externalProductId: 1);
    }

    /** @test */
    public function remove_item_removes_and_recalculates_total(): void
    {
        $cart = $this->makeActiveCart();
        $item = $this->makeCartItem();

        $this->cartRepo->shouldReceive('findActiveByUser')->andReturn($cart);
        $this->cartRepo->shouldReceive('findItem')->andReturn($item);
        $this->cartRepo->shouldReceive('removeItem')->with($item)->once();
        $this->cartRepo->shouldReceive('updateTotal')->once();
        $cart->shouldReceive('fresh')->andReturnSelf();

        $result = $this->service->removeItem(userId: 1, externalProductId: 1);

        $this->assertNotNull($result);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeActiveCart(): Cart|MockInterface
    {
        $cart          = Mockery::mock(Cart::class)->makePartial();
        $cart->id      = 1;
        $cart->user_id = 1;
        $cart->estado  = Cart::ESTADO_ACTIVO;
        $cart->shouldReceive('isClosed')->andReturn(false);
        $cart->shouldReceive('isActive')->andReturn(true);
        $cart->shouldReceive('fresh')->andReturnSelf();
        return $cart;
    }

    private function makeClosedCart(): Cart|MockInterface
    {
        $cart          = Mockery::mock(Cart::class)->makePartial();
        $cart->id      = 1;
        $cart->user_id = 1;
        $cart->estado  = Cart::ESTADO_CERRADO;
        $cart->shouldReceive('isClosed')->andReturn(true);
        return $cart;
    }

    private function makeProductDTO(int $stock = 10, float $price = 99.99): ProductDTO
    {
        return new ProductDTO(
            id: 1, sku: 'SKU-1', title: 'Producto Test',
            brand: 'Brand', thumbnail: 'http://img.jpg',
            price: $price, discountPercentage: 0.0,
            originalPrice: $price, stock: $stock, category: 'test',
        );
    }

    private function makeCartItem(int $quantity = 1, float $price = 99.99): CartItem|MockInterface
    {
        $item                      = Mockery::mock(CartItem::class)->makePartial();
        $item->id                  = 1;
        $item->external_product_id = 1;
        $item->cantidad            = $quantity;
        $item->precio_unitario     = $price;
        return $item;
    }
}
