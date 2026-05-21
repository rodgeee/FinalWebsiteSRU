<?php

namespace App\Service;

use App\Repository\OrdersRepository;

final class OrderNumberGenerator
{
    public function __construct(
        private readonly OrdersRepository $ordersRepository,
    ) {
    }

    public function generate(?\DateTimeImmutable $at = null): string
    {
        $at ??= new \DateTimeImmutable();
        $datePart = $at->format('Ymd');
        $prefix = sprintf('SRU-%s-', $datePart);

        $sequence = $this->ordersRepository->countWithOrderNumberPrefix($prefix) + 1;

        return $prefix . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
