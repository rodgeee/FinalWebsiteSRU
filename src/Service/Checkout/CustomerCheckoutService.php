<?php

namespace App\Service\Checkout;

use App\Entity\Customer;
use App\Entity\CustomerAddress;
use App\Entity\Orders;
use App\Entity\Products;
use App\Entity\Stocks;
use App\Repository\ProductsRepository;
use App\Repository\StocksRepository;
use App\Service\Cart\CartService;
use App\Service\OrderNumberGenerator;
use Doctrine\ORM\EntityManagerInterface;

final class CustomerCheckoutService
{
    public const REMARKS_PREFIX = '[WEB_CHECKOUT]';
    public const REMARKS_PREFIX_API = '[API_CHECKOUT]';

    public function __construct(
        private readonly CartService $cartService,
        private readonly ProductsRepository $productsRepository,
        private readonly StocksRepository $stocksRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly OrderNumberGenerator $orderNumberGenerator,
    ) {
    }

    /**
     * @return array{ok: true, order: Orders}|array{ok: false, error: string}
     */
    public function placeOrder(
        Customer $customer,
        string $paymentMethod,
        ?CustomerAddress $shippingAddress,
        ?string $orderNotes,
    ): array {
        $lines = $this->cartService->getLines();
        if ($lines === []) {
            return ['ok' => false, 'error' => 'Your bag is empty.'];
        }

        $result = $this->executePlaceOrder(
            $customer,
            $lines,
            $paymentMethod,
            $shippingAddress,
            $orderNotes,
            self::REMARKS_PREFIX,
            'web',
        );

        if ($result['ok'] === true) {
            $this->cartService->clear();
        }

        return $result;
    }

    /**
     * @param list<array{productId: int, quantity: int, size?: string}> $items
     *
     * @return array{ok: true, order: Orders}|array{ok: false, error: string}
     */
    public function placeOrderFromItems(
        Customer $customer,
        array $items,
        string $paymentMethod,
        ?CustomerAddress $shippingAddress,
        ?string $orderNotes,
    ): array {
        if ($items === []) {
            return ['ok' => false, 'error' => 'At least one order item is required.'];
        }

        $lines = [];
        foreach ($items as $item) {
            $productId = (int) ($item['productId'] ?? 0);
            $quantity = (int) ($item['quantity'] ?? 0);
            if ($productId <= 0 || $quantity <= 0) {
                return ['ok' => false, 'error' => 'Each item must have a valid productId and positive quantity.'];
            }

            $product = $this->productsRepository->find($productId);
            if (!$product instanceof Products) {
                return ['ok' => false, 'error' => 'One or more products are no longer available.'];
            }

            $selectedSize = trim((string) ($item['size'] ?? ''));
            $lines[] = [
                'productId' => $productId,
                'productName' => (string) ($product->getName() ?? 'Product'),
                'color' => (string) ($product->getColor() ?? ''),
                'size' => $selectedSize !== '' ? $selectedSize : (string) ($product->getSize() ?? ''),
                'quantity' => $quantity,
                'unitPrice' => (string) ($product->getPrice() ?? '0.00'),
            ];
        }

        return $this->executePlaceOrder(
            $customer,
            $lines,
            $paymentMethod,
            $shippingAddress,
            $orderNotes,
            self::REMARKS_PREFIX_API,
            'api',
        );
    }

    public function getAvailableStock(Products $product): int
    {
        $stockRows = $this->stocksRepository->findBy(['Product' => $product], ['id' => 'ASC']);
        $available = 0;
        foreach ($stockRows as $stockRow) {
            $available += (int) ($stockRow->getQuantity() ?? 0);
        }

        return $available;
    }

    /**
     * @param list<array{
     *     productId: int,
     *     productName: string,
     *     color?: string,
     *     size: string,
     *     quantity: int,
     *     unitPrice: string
     * }> $lines
     *
     * @return array{ok: true, order: Orders}|array{ok: false, error: string}
     */
    private function executePlaceOrder(
        Customer $customer,
        array $lines,
        string $paymentMethod,
        ?CustomerAddress $shippingAddress,
        ?string $orderNotes,
        string $remarksPrefix,
        string $source,
    ): array {
        $productsById = [];
        foreach ($lines as $line) {
            $product = $this->productsRepository->find($line['productId']);
            if (!$product instanceof Products) {
                return ['ok' => false, 'error' => 'One or more items are no longer available.'];
            }
            $productsById[$line['productId']] = $product;

            $available = $this->getAvailableStock($product);
            if ($available < $line['quantity']) {
                return [
                    'ok' => false,
                    'error' => sprintf(
                        'Insufficient stock for %s. Only %d available.',
                        $product->getName() ?? 'an item',
                        $available,
                    ),
                ];
            }
        }

        $subtotal = 0.0;
        foreach ($lines as $line) {
            $subtotal += (float) $line['unitPrice'] * $line['quantity'];
        }
        $totalQty = array_sum(array_map(static fn (array $line): int => $line['quantity'], $lines));

        $order = new Orders();
        $order->setCustomerName((string) ($customer->getFullName() ?? 'Customer'));
        $order->setEmail((string) $customer->getEmail());
        $order->setQuantity($totalQty);
        $order->setTotalPrice(number_format($subtotal, 2, '.', ''));
        $order->setPaymentMethod($paymentMethod);
        $order->setOrderStatus('Pending');
        $createdAt = new \DateTimeImmutable();
        $order->setDateCreated($createdAt);
        $order->setOrderNumber($this->orderNumberGenerator->generate($createdAt));

        foreach ($productsById as $product) {
            $order->addProduct($product);
        }

        $remarksPayload = [
            'source' => $source,
            'lines' => array_map(static fn (array $line): array => [
                'productId' => $line['productId'],
                'name' => $line['productName'],
                'size' => $line['size'],
                'qty' => $line['quantity'],
                'price' => $line['unitPrice'],
            ], $lines),
            'shipping' => $this->formatShipping($shippingAddress, $orderNotes),
        ];
        $order->setRemarks($remarksPrefix . json_encode($remarksPayload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        foreach ($lines as $line) {
            $product = $productsById[$line['productId']];
            $this->deductStock($product, $line['quantity']);
        }

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        return ['ok' => true, 'order' => $order];
    }

    /**
     * Customer-initiated cancel while order is still Pending or Processing.
     *
     * @return array{ok: true, order: Orders}|array{ok: false, error: string}
     */
    public function cancelOrder(Orders $order): array
    {
        $status = (string) ($order->getOrderStatus() ?? '');
        if (!in_array($status, ['Pending', 'Processing'], true)) {
            return [
                'ok' => false,
                'error' => 'This order can only be cancelled while it is Pending or Processing.',
            ];
        }

        $lines = $this->parseCheckoutLines($order);
        if ($lines !== null) {
            foreach ($lines as $line) {
                if (!is_array($line)) {
                    continue;
                }
                $productId = (int) ($line['productId'] ?? 0);
                $qty = (int) ($line['qty'] ?? $line['quantity'] ?? 0);
                if ($productId <= 0 || $qty <= 0) {
                    continue;
                }
                $product = $this->productsRepository->find($productId);
                if (!$product instanceof Products) {
                    continue;
                }
                $this->restoreStock($product, $qty);
            }
        } else {
            $products = $order->getProducts()->toArray();
            if (count($products) === 1 && $products[0] instanceof Products) {
                $this->restoreStock($products[0], max(1, (int) ($order->getQuantity() ?? 1)));
            }
        }

        $order->setOrderStatus('Cancelled');
        $this->entityManager->flush();

        return ['ok' => true, 'order' => $order];
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    private function parseCheckoutLines(Orders $order): ?array
    {
        $remarks = $order->getRemarks();
        if ($remarks === null || $remarks === '') {
            return null;
        }

        foreach ([self::REMARKS_PREFIX_API, self::REMARKS_PREFIX] as $prefix) {
            if (!str_starts_with($remarks, $prefix)) {
                continue;
            }
            $json = substr($remarks, strlen($prefix));
            try {
                $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                return null;
            }
            if (is_array($data) && isset($data['lines']) && is_array($data['lines'])) {
                /** @var list<array<string, mixed>> $lines */
                $lines = $data['lines'];

                return $lines;
            }
        }

        return null;
    }

    private function restoreStock(Products $product, int $quantity): void
    {
        if ($quantity <= 0) {
            return;
        }

        $stockRows = $this->stocksRepository->findBy(['Product' => $product], ['id' => 'ASC']);
        if ($stockRows === []) {
            $stockRow = new Stocks();
            $stockRow->setProduct($product);
            $stockRow->setQuantity($quantity);
            $now = new \DateTimeImmutable();
            $stockRow->setCreatedAt($now);
            $stockRow->setUpdatedAt($now);
            $this->entityManager->persist($stockRow);

            return;
        }

        $stockRow = $stockRows[0];
        $stockRow->setQuantity((int) ($stockRow->getQuantity() ?? 0) + $quantity);
        $stockRow->setUpdatedAt(new \DateTimeImmutable());
    }

    private function deductStock(Products $product, int $quantity): void
    {
        $stockRows = $this->stocksRepository->findBy(['Product' => $product], ['id' => 'ASC']);
        $toDeduct = $quantity;

        foreach ($stockRows as $stockRow) {
            $currentQty = (int) ($stockRow->getQuantity() ?? 0);
            if ($currentQty <= 0) {
                continue;
            }

            $deductNow = min($currentQty, $toDeduct);
            $stockRow->setQuantity($currentQty - $deductNow);
            $stockRow->setUpdatedAt(new \DateTimeImmutable());
            $toDeduct -= $deductNow;

            if ($toDeduct <= 0) {
                break;
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formatShipping(?CustomerAddress $address, ?string $orderNotes): array
    {
        if ($address instanceof CustomerAddress) {
            return [
                'addressId' => $address->getId(),
                'label' => $address->getLabel(),
                'line1' => $address->getAddressLine1(),
                'line2' => $address->getAddressLine2(),
                'city' => $address->getCity(),
                'province' => $address->getProvince(),
                'postalCode' => $address->getPostalCode(),
                'country' => $address->getCountry(),
                'contactEmail' => $address->getContactEmail(),
                'contactPhone' => $address->getContactPhone(),
                'display' => $address->getDisplayLine1(),
                'notes' => $orderNotes !== null && trim($orderNotes) !== '' ? trim($orderNotes) : null,
            ];
        }

        return [
            'notes' => $orderNotes !== null && trim($orderNotes) !== '' ? trim($orderNotes) : null,
        ];
    }
}
