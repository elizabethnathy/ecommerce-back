<?php

namespace App\Services;

use App\DTOs\ProductDTO;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductService
{
    public function __construct(
        private readonly DummyJsonService $dummyJson,
    ) {}

    /**
     * Retorna productos paginados, filtrados y ordenados.
     * El stock se sobreescribe con el valor local (tabla products) para
     * reflejar los descuentos realizados en checkouts anteriores.
     *
     * @return array{data: array<array>, meta: array}
     */
    public function listProducts(int $perPage, int $page, string $sortPrice, string $search = ''): array
    {
        $skip = ($page - 1) * $perPage;

        $result = $this->dummyJson->listProductsSorted($perPage, $skip, $sortPrice, $search);

        // Cargar todos los stocks locales de una sola query (evita N+1)
        $externalIds  = $result['products']->pluck('id')->all();
        $localStocks  = Product::whereIn('external_id', $externalIds)
            ->get(['external_id', 'stock'])
            ->keyBy('external_id');

        $total    = $result['total'];
        $lastPage = (int) ceil($total / $perPage);

        $data = $result['products']->map(function (ProductDTO $p) use ($localStocks): array {
            $arr = $p->toArray();

            // Si existe en tabla local, usar ese stock (es el real tras checkouts)
            if (isset($localStocks[$p->id])) {
                $arr['stock'] = (int) $localStocks[$p->id]->stock;
            }

            return $arr;
        })->values()->all();

        return [
            'data' => $data,
            'meta' => [
                'current_page' => $page,
                'per_page'     => $perPage,
                'total'        => $total,
                'last_page'    => max($lastPage, 1),
            ],
        ];
    }

    /**
     * Obtiene el detalle completo de un producto.
     * Sobreescribe el stock con el valor local.
     */
    public function getProduct(int $id): array
    {
        $dto   = $this->dummyJson->getProductById($id);
        $arr   = $dto->toDetailArray();
        $local = Product::where('external_id', $id)->first();

        if ($local) {
            $arr['stock'] = (int) $local->stock;
        }

        return $arr;
    }
}
