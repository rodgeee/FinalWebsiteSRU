<?php

namespace App\Controller;

use App\Repository\ProductsRepository;
use App\Service\Cart\CartService;
use App\Service\Checkout\CustomerCheckoutService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_CUSTOMER')]
#[Route('/cart', name: 'customer_cart_')]
final class CartController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        CartService $cart,
        CustomerCheckoutService $checkout,
        ProductsRepository $productsRepository,
    ): Response {
        $lines = $cart->getLines();
        $stockByProduct = [];
        foreach ($lines as $line) {
            $product = $productsRepository->find($line['productId']);
            if ($product !== null) {
                $stockByProduct[$line['key']] = $checkout->getAvailableStock($product);
            }
        }

        return $this->render('public/cart.html.twig', [
            'lines' => $lines,
            'subtotal' => $cart->getSubtotal(),
            'itemCount' => $cart->getItemCount(),
            'stockByLine' => $stockByProduct,
        ]);
    }

    #[Route('/add', name: 'add', methods: ['POST'])]
    public function add(Request $request, CartService $cart, ProductsRepository $productsRepository): Response
    {
        if (!$this->isCsrfTokenValid('cart_add', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid security token.');
        }

        $productId = (int) $request->request->get('product_id', 0);
        $quantity = (int) $request->request->get('quantity', 1);
        $size = trim((string) $request->request->get('size', ''));

        $product = $productsRepository->find($productId);
        if ($product === null) {
            $this->addFlash('error', 'Product not found.');

            return $this->redirectToRoute('public_releases');
        }

        $cart->addProduct($product, $size, $quantity);
        $this->addFlash('success', 'Added to your bag.');

        $referer = $request->headers->get('referer');
        if (is_string($referer) && $referer !== '') {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('customer_cart_index');
    }

    #[Route('/update', name: 'update', methods: ['POST'])]
    public function update(Request $request, CartService $cart): Response
    {
        if (!$this->isCsrfTokenValid('cart_update', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid security token.');
        }

        $lineKey = (string) $request->request->get('line_key', '');
        $quantity = (int) $request->request->get('quantity', 1);

        if ($lineKey === '' || !$cart->updateQuantity($lineKey, $quantity)) {
            $this->addFlash('error', 'Could not update that item.');
        }

        return $this->redirectToRoute('customer_cart_index');
    }

    #[Route('/remove', name: 'remove', methods: ['POST'])]
    public function remove(Request $request, CartService $cart): Response
    {
        if (!$this->isCsrfTokenValid('cart_remove', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid security token.');
        }

        $lineKey = (string) $request->request->get('line_key', '');
        if ($lineKey === '' || !$cart->removeLine($lineKey)) {
            $this->addFlash('error', 'Could not remove that item.');
        } else {
            $this->addFlash('success', 'Item removed from your bag.');
        }

        return $this->redirectToRoute('customer_cart_index');
    }
}
