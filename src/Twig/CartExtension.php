<?php

namespace App\Twig;

use App\Service\Cart\CartService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class CartExtension extends AbstractExtension
{
    public function __construct(
        private readonly CartService $cartService,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('cart_item_count', [$this, 'getItemCount']),
        ];
    }

    public function getItemCount(): int
    {
        return $this->cartService->getItemCount();
    }
}
