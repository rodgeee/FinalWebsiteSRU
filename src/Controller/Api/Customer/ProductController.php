<?php

namespace App\Controller\Api\Customer;

use App\Controller\Api\AbstractApiController;
use App\Entity\Products;
use App\Repository\ProductsRepository;
use App\Service\Api\ApiResponseFactory;
use App\Service\Api\CustomerResourceSerializer;
use App\Service\Checkout\CustomerCheckoutService;
use App\Service\Product\ProductSizeParser;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_CUSTOMER')]
#[Route('/api/customer/products', name: 'api_customer_products_')]
final class ProductController extends AbstractApiController
{
    public function __construct(
        ApiResponseFactory $api,
        private readonly CustomerResourceSerializer $serializer,
        private readonly CustomerCheckoutService $checkoutService,
        private readonly ProductSizeParser $productSizeParser,
    ) {
        parent::__construct($api);
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request, ProductsRepository $productsRepository): JsonResponse
    {
        $search = trim((string) $request->query->get('search', ''));
        $limit = min(100, max(1, (int) $request->query->get('limit', 50)));

        $products = $search !== ''
            ? $productsRepository->findForIndex($search, 'id', 'DESC')
            : $productsRepository->findRecentlyAdded($limit);

        if ($search !== '') {
            $products = array_slice($products, 0, $limit);
        }

        $data = [];
        foreach ($products as $product) {
            $data[] = $this->serializer->product(
                $product,
                $this->checkoutService->getAvailableStock($product),
            );
        }

        return $this->api->success($data, meta: ['count' => \count($data)]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id, ProductsRepository $productsRepository): JsonResponse
    {
        $product = $productsRepository->find($id);
        if (!$product instanceof Products) {
            return $this->api->error('Product not found.', 404);
        }

        $payload = $this->serializer->product(
            $product,
            $this->checkoutService->getAvailableStock($product),
        );
        $payload['availableSizes'] = $this->productSizeParser->parseSelectableSizes($product);

        return $this->api->success($payload);
    }
}
