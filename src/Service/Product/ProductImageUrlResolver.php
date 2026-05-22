<?php

declare(strict_types=1);

namespace App\Service\Product;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Turns stored product paths (uploads/products/..., img/...) into absolute URLs for the mobile API.
 */
final class ProductImageUrlResolver
{
    /** Storefront fallback when a product has no upload yet (avoids null URLs in the mobile app). */
    public const PLACEHOLDER_PATH = 'img/shoes-r-us-logo.png';

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly string $defaultUri,
    ) {
    }

    public function resolve(?string $path): string
    {
        if ($path === null || trim($path) === '') {
            return $this->placeholderUrl();
        }

        $path = trim($path);
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return rtrim($this->getBaseUrl(), '/') . '/' . ltrim($path, '/');
    }

    public function placeholderUrl(): string
    {
        return rtrim($this->getBaseUrl(), '/') . '/' . self::PLACEHOLDER_PATH;
    }

    /**
     * @param list<string> $paths
     *
     * @return list<string>
     */
    public function resolveMany(array $paths): array
    {
        $urls = [];
        foreach ($paths as $path) {
            $urls[] = $this->resolve($path);
        }

        $urls = array_values(array_unique($urls));

        return $urls !== [] ? $urls : [$this->placeholderUrl()];
    }

    private function getBaseUrl(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request !== null) {
            return $request->getSchemeAndHttpHost();
        }

        return rtrim($this->defaultUri, '/');
    }
}
