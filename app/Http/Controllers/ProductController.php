<?php

namespace App\Http\Controllers;

use App\Http\Requests\Product\ListProductsRequest;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;

class ProductController extends ApiController
{
    public function __construct(private readonly ProductService $productService) {}

    /**
     * GET /api/products
     * Query: per_page, page, sort_price (asc|desc), search
     */
    public function index(ListProductsRequest $request): JsonResponse
    {
        $result = $this->productService->listProducts(
            perPage:   $request->integer('per_page', 12),
            page:      $request->integer('page', 1),
            sortPrice: $request->string('sort_price', 'asc')->toString(),
            search:    $request->string('search', '')->toString(),
        );

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data'    => $result['data'],
            'meta'    => $result['meta'],
        ]);
    }

    /**
     * GET /api/products/{id}
     * Devuelve el detalle completo con stock real (tabla local).
     */
    public function show(int $id): JsonResponse
    {
        $product = $this->productService->getProduct($id);

        return $this->success($product);
    }
}
