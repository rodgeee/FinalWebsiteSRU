<?php

declare(strict_types=1);

namespace App\Service\Product;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Turns stored product paths (uploads/products/..., img/...) into absolute URLs for the mobile API.
 */
final class ProductImageUrlResolver
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly string $defaultUri,
    ) {
    }

    public function resolve(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        $path = trim($path);
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return rtrim($this->getBaseUrl(), '/') . '/' . ltrim($path, '/');
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
            $url = $this->resolve($path);
            if ($url !== null) {
                $urls[] = $url;
            }
        }

        return array_values(array_unique($urls));
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
