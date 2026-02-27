<?php

namespace App\DTOs;

/**
 * Representa un producto proveniente de dummyjson.com, ya transformado.
 * Inmutable por diseño (readonly).
 */
final readonly class ProductDTO
{
    public function __construct(
        public int    $id,
        public string $sku,
        public string $title,
        public string $brand,
        public string $thumbnail,
        public float  $price,
        public float  $discountPercentage,
        public float  $originalPrice,
        public int    $stock,
        public string $category,
        public float  $rating,
        public int    $minimumOrderQuantity,
        // Detalle completo (solo se rellena en getProductById)
        public string $description,
        public array  $images,
        public string $warrantyInformation,
        public string $shippingInformation,
        public string $returnPolicy,
        public string $availabilityStatus,
        public string $barcode,
        public array  $reviews,
    ) {}

    /**
     * Construye el DTO desde el array raw de dummyjson.
     * El precio original se calcula aquí una sola vez:
     *   originalPrice = price / (1 - discountPercentage / 100)
     */
    public static function fromRaw(array $raw): self
    {
        $price    = (float) ($raw['price'] ?? 0);
        $discount = (float) ($raw['discountPercentage'] ?? 0);
        $divisor  = 1 - ($discount / 100);
        $original = ($divisor > 0) ? round($price / $divisor, 2) : $price;

        // Normalizar reviews
        $reviews = array_map(function (array $r): array {
            return [
                'rating'        => (int)    ($r['rating']       ?? 0),
                'comment'       => (string) ($r['comment']      ?? ''),
                'date'          => (string) ($r['date']         ?? ''),
                'reviewer_name' => (string) ($r['reviewerName'] ?? ''),
            ];
        }, $raw['reviews'] ?? []);

        return new self(
            id:                   (int)    ($raw['id']                   ?? 0),
            sku:                  (string) ($raw['sku']                  ?? ''),
            title:                (string) ($raw['title']                ?? ''),
            brand:                (string) ($raw['brand']                ?? ''),
            thumbnail:            (string) ($raw['thumbnail']            ?? ''),
            price:                $price,
            discountPercentage:   $discount,
            originalPrice:        $original,
            stock:                (int)    ($raw['stock']                ?? 0),
            category:             (string) ($raw['category']             ?? ''),
            rating:               (float)  ($raw['rating']               ?? 0.0),
            minimumOrderQuantity: (int)    ($raw['minimumOrderQuantity'] ?? 1),
            description:          (string) ($raw['description']          ?? ''),
            images:               (array)  ($raw['images']               ?? []),
            warrantyInformation:  (string) ($raw['warrantyInformation']  ?? ''),
            shippingInformation:  (string) ($raw['shippingInformation']  ?? ''),
            returnPolicy:         (string) ($raw['returnPolicy']         ?? ''),
            availabilityStatus:   (string) ($raw['availabilityStatus']   ?? ''),
            barcode:              (string) ($raw['meta']['barcode']       ?? ''),
            reviews:              $reviews,
        );
    }

    /** Forma compacta para el listado de productos */
    public function toArray(): array
    {
        return [
            'id'                     => $this->id,
            'sku'                    => $this->sku,
            'title'                  => $this->title,
            'brand'                  => $this->brand,
            'thumbnail'              => $this->thumbnail,
            'price'                  => $this->price,
            'discount_percentage'    => $this->discountPercentage,
            'original_price'         => $this->originalPrice,
            'stock'                  => $this->stock,
            'category'               => $this->category,
            'rating'                 => $this->rating,
            'minimum_order_quantity' => $this->minimumOrderQuantity,
        ];
    }

    /** Forma completa para el detalle de un producto */
    public function toDetailArray(): array
    {
        return array_merge($this->toArray(), [
            'description'          => $this->description,
            'images'               => $this->images,
            'warranty_information' => $this->warrantyInformation,
            'shipping_information' => $this->shippingInformation,
            'return_policy'        => $this->returnPolicy,
            'availability_status'  => $this->availabilityStatus,
            'barcode'              => $this->barcode,
            'reviews'              => $this->reviews,
        ]);
    }
}
