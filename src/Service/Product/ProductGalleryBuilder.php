<?php

namespace App\Service\Product;

use App\Entity\Products;

/**
 * Builds the image path list shown on the storefront product page and mobile API.
 */
final class ProductGalleryBuilder
{
    /**
     * @return list<string> Relative or absolute image paths (up to 4 from entity).
     */
    public function buildGalleryPaths(Products $product): array
    {
        $gallery = $product->getImages();
        if ($gallery === []) {
            $primary = $product->getPrimaryImage();
            if ($primary !== null && $primary !== '') {
                $gallery = [$primary];
            }
        }

        return array_values(array_unique($gallery));
    }
}
