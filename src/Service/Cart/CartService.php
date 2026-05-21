<?php

namespace App\Service\Cart;

use App\Entity\Products;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class CartService
{
    private const SESSION_KEY = 'customer_cart';

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @return list<array{
     *     key: string,
     *     productId: int,
     *     productName: string,
     *     color: string,
     *     size: string,
     *     quantity: int,
     *     unitPrice: string,
     *     image: ?string
     * }>
     */
    public function getLines(): array
    {
        $session = $this->getSession();
        if ($session === null) {
            return [];
        }

        $lines = $session->get(self::SESSION_KEY, []);
        if (!is_array($lines)) {
            return [];
        }

        $normalized = [];
        foreach ($lines as $line) {
            if (!is_array($line) || !isset($line['key'], $line['productId'])) {
                continue;
            }
            $normalized[] = [
                'key' => (string) $line['key'],
                'productId' => (int) $line['productId'],
                'productName' => (string) ($line['productName'] ?? 'Product'),
                'color' => (string) ($line['color'] ?? ''),
                'size' => (string) ($line['size'] ?? ''),
                'quantity' => max(1, (int) ($line['quantity'] ?? 1)),
                'unitPrice' => (string) ($line['unitPrice'] ?? '0'),
                'image' => isset($line['image']) ? (string) $line['image'] : null,
            ];
        }

        return $normalized;
    }

    public function getItemCount(): int
    {
        return array_sum(array_map(static fn (array $line): int => $line['quantity'], $this->getLines()));
    }

    public function getSubtotal(): float
    {
        $total = 0.0;
        foreach ($this->getLines() as $line) {
            $total += (float) $line['unitPrice'] * $line['quantity'];
        }

        return $total;
    }

    public function isEmpty(): bool
    {
        return $this->getLines() === [];
    }

    public function addProduct(Products $product, string $size, int $quantity): void
    {
        $quantity = max(1, min(99, $quantity));
        $productId = (int) $product->getId();
        if ($productId <= 0) {
            return;
        }

        $key = $this->lineKey($productId);
        $lines = $this->getLines();
        $found = false;

        foreach ($lines as &$line) {
            if ($line['key'] === $key) {
                $line['quantity'] = min(99, $line['quantity'] + $quantity);
                $line['size'] = $size !== '' ? $size : $line['size'];
                $found = true;
                break;
            }
        }
        unset($line);

        if (!$found) {
            $lines[] = [
                'key' => $key,
                'productId' => $productId,
                'productName' => (string) ($product->getName() ?? 'Product'),
                'color' => (string) ($product->getColor() ?? ''),
                'size' => $size,
                'quantity' => $quantity,
                'unitPrice' => (string) ($product->getPrice() ?? '0'),
                'image' => $product->getImage(),
            ];
        }

        $this->saveLines($lines);
    }

    public function updateQuantity(string $lineKey, int $quantity): bool
    {
        if ($quantity <= 0) {
            return $this->removeLine($lineKey);
        }

        $quantity = min(99, $quantity);
        $lines = $this->getLines();
        $updated = false;

        foreach ($lines as &$line) {
            if ($line['key'] === $lineKey) {
                $line['quantity'] = $quantity;
                $updated = true;
                break;
            }
        }
        unset($line);

        if ($updated) {
            $this->saveLines($lines);
        }

        return $updated;
    }

    public function removeLine(string $lineKey): bool
    {
        $lines = array_values(array_filter(
            $this->getLines(),
            static fn (array $line): bool => $line['key'] !== $lineKey
        ));
        $removed = count($lines) !== count($this->getLines());
        if ($removed) {
            $this->saveLines($lines);
        }

        return $removed;
    }

    public function clear(): void
    {
        $session = $this->getSession();
        $session?->remove(self::SESSION_KEY);
    }

    private function lineKey(int $productId): string
    {
        return 'p' . $productId;
    }

    /**
     * @param list<array<string, mixed>> $lines
     */
    private function saveLines(array $lines): void
    {
        $session = $this->getSession();
        $session?->set(self::SESSION_KEY, $lines);
    }

    private function getSession(): ?SessionInterface
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null || !$request->hasSession()) {
            return null;
        }

        return $request->getSession();
    }
}
