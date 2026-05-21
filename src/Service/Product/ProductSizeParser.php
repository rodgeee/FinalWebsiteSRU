<?php

namespace App\Service\Product;

use App\Entity\Products;

/**
 * Expands product Size field (e.g. "8-12", "US 9, 10, 11") into selectable US sizes.
 * Same rules as the public product page.
 */
final class ProductSizeParser
{
    /**
     * @return list<string> Numeric US sizes for the size picker (e.g. 8, 9, 10, 11).
     */
    public function parseSelectableSizes(Products $product): array
    {
        $raw = trim((string) $product->getSize());
        if ($raw === '') {
            return ['8', '9', '10', '11'];
        }

        $normalized = str_replace(['–', '—'], '-', $raw);
        $normalized = preg_replace('/\bUS\s*/i', '', $normalized) ?? $normalized;
        $normalized = trim($normalized);

        if (str_contains($normalized, ',')) {
            $parts = array_map('trim', explode(',', $normalized));
            $sizes = [];
            foreach ($parts as $part) {
                $sizes = array_merge($sizes, $this->expandSizeToken($part));
            }

            return $this->uniqueSizes($sizes);
        }

        return $this->uniqueSizes($this->expandSizeToken($normalized));
    }

    /**
     * @return list<string>
     */
    private function expandSizeToken(string $token): array
    {
        $token = trim($token);
        if ($token === '') {
            return [];
        }

        if (preg_match('/^(\d+(?:\.\d+)?)\s*-\s*(\d+(?:\.\d+)?)$/', $token, $matches)) {
            return $this->expandNumericSizeRange((float) $matches[1], (float) $matches[2]);
        }

        if (preg_match_all('/\d+(?:\.\d+)?/', $token, $found) > 0 && \count($found[0]) >= 2) {
            $nums = array_map(static fn (string $n): float => (float) $n, $found[0]);

            return $this->expandNumericSizeRange(min($nums), max($nums));
        }

        if (preg_match('/^(\d+(?:\.\d+)?)$/', $token, $single)) {
            return [(string) (int) (float) $single[1]];
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private function expandNumericSizeRange(float $start, float $end): array
    {
        if ($end < $start) {
            [$start, $end] = [$end, $start];
        }

        $from = (int) floor($start);
        $to = (int) ceil($end);
        if ($to - $from > 16) {
            return [(string) $from];
        }

        $sizes = [];
        for ($i = $from; $i <= $to; ++$i) {
            $sizes[] = (string) $i;
        }

        return $sizes;
    }

    /**
     * @param list<string> $sizes
     *
     * @return list<string>
     */
    private function uniqueSizes(array $sizes): array
    {
        $sizes = array_values(array_filter($sizes, static fn (string $s): bool => $s !== ''));
        $sizes = array_values(array_unique($sizes));
        usort($sizes, static fn (string $a, string $b): int => (float) $a <=> (float) $b);

        return $sizes !== [] ? $sizes : ['8', '9', '10', '11'];
    }
}
