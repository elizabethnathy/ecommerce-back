<?php

namespace App\Services;

use App\DTOs\ProductDTO;
use App\Exceptions\Domain\ExternalApiException;
use App\Exceptions\Domain\ProductNotFoundException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DummyJsonService
{
    private const BASE_URL    = 'https://dummyjson.com/products';
    private const TIMEOUT     = 10;
    /**
     * TTL del caché de productos completo.
     * 5 minutos: balance entre frescura de stock y ahorro de requests.
     */
    private const CACHE_TTL   = 300;
    private const CACHE_KEY   = 'dummyjson_all_products';

    // -------------------------------------------------------------------------
    // API pública
    // -------------------------------------------------------------------------

    /**
     * Devuelve TODOS los productos, con ordenamiento y paginación aplicados en PHP.
     *
     * ¿Por qué traer todos?
     *   dummyjson no soporta sortBy en /products (solo en /products/search),
     *   por lo que ordenar página a página produce resultados incorrectos.
     *   Traemos todos una sola vez, los cacheamos 5 min y paginamos localmente.
     *
     * @return array{products: Collection<ProductDTO>, total: int}
     */
    public function listProductsSorted(int $limit, int $skip, string $sortDirection, string $search = ''): array
    {
        $all = $this->getAllProductsCached();

        // Filtro por búsqueda (nombre o brand)
        if ($search !== '') {
            $term = strtolower($search);
            $all  = $all->filter(
                fn (ProductDTO $p) =>
                    str_contains(strtolower($p->title), $term) ||
                    str_contains(strtolower($p->brand), $term)
            )->values();
        }

        // Ordenamiento global
        $direction = strtolower($sortDirection) === 'desc' ? 'desc' : 'asc';
        $sorted    = $all->sortBy(
            fn (ProductDTO $p) => $p->price,
            SORT_REGULAR,
            $direction === 'desc'
        )->values();

        $total    = $sorted->count();
        $products = $sorted->slice($skip, $limit)->values();

        return [
            'products' => $products,
            'total'    => $total,
        ];
    }

    /**
     * Obtiene un producto por su ID externo.
     * Primero busca en el caché; si no está, llama a la API individualmente.
     */
    public function getProductById(int $id): ProductDTO
    {
        // Intentar desde caché primero (evita request extra)
        $cached = $this->getAllProductsCached()->firstWhere('id', $id);

        if ($cached instanceof ProductDTO) {
            // Para el detalle, hacemos la request individual para obtener
            // campos completos (images, reviews, etc.) que la lista no incluye.
            try {
                $raw = $this->get(self::BASE_URL . "/{$id}", [], $id);
                return ProductDTO::fromRaw($raw);
            } catch (ProductNotFoundException $e) {
                throw $e;
            } catch (\Throwable) {
                // Si falla la request de detalle, usamos el caché
                return $cached;
            }
        }

        $raw = $this->get(self::BASE_URL . "/{$id}", [], $id);
        return ProductDTO::fromRaw($raw);
    }

    /**
     * Invalida el caché de productos (útil tras descuento de stock local).
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    /**
     * Trae todos los productos de dummyjson en una sola request y los cachea.
     * dummyjson tiene ~194 productos; con limit=0 devuelve todos.
     *
     * @return Collection<ProductDTO>
     */
    private function getAllProductsCached(): Collection
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            $raw = $this->get(self::BASE_URL, ['limit' => 0, 'skip' => 0]);

            return collect($raw['products'] ?? [])
                ->map(fn (array $item) => ProductDTO::fromRaw($item));
        });
    }

    /**
     * Ejecuta un GET contra dummyjson con manejo de errores centralizado.
     */
    private function get(string $url, array $params = [], ?int $productId = null): array
    {
        try {
            $response = Http::timeout(self::TIMEOUT)->get($url, $params);

            if ($response->status() === 404 && $productId !== null) {
                throw new ProductNotFoundException($productId);
            }

            if ($response->failed()) {
                throw new ExternalApiException(
                    "HTTP {$response->status()} en {$url}"
                );
            }

            return $response->json();

        } catch (ConnectionException $e) {
            Log::error('[DummyJson] Connection failed', [
                'url'   => $url,
                'error' => $e->getMessage(),
            ]);
            throw new ExternalApiException('Tiempo de espera agotado al conectar con dummyjson.com');
        } catch (ProductNotFoundException $e) {
            throw $e;
        } catch (ExternalApiException $e) {
            Log::error('[DummyJson] API error', ['url' => $url, 'error' => $e->getMessage()]);
            throw $e;
        }
    }
}
