<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Product;

readonly class LicenseManagerProductRepository
{
    public function findById(int $productId): ?Product
    {
        return Product::query()
            ->where('id', $productId)
            ->first();
    }

    public function findByIdentifier(?int $productId, ?int $envatoItemId): ?Product
    {
        if ($productId !== null) {
            $product = $this->findById($productId);

            if ($product instanceof Product) {
                if ($envatoItemId === null || $product->envato_item_id === $envatoItemId) {
                    return $product;
                }

                $productByEnvatoItemId = Product::query()
                    ->where('envato_item_id', $envatoItemId)
                    ->first();

                if ($productByEnvatoItemId instanceof Product) {
                    return $productByEnvatoItemId;
                }

                return $product;
            }
        }

        if ($envatoItemId !== null) {
            return Product::query()
                ->where('envato_item_id', $envatoItemId)
                ->first();
        }

        return null;
    }
}
