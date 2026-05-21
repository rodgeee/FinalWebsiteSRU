<?php

namespace App\Controller\Api\Customer;

use App\Controller\Api\AbstractApiController;
use App\Entity\CustomerAddress;
use App\Entity\Orders;
use App\Repository\CustomerAddressRepository;
use App\Repository\OrdersRepository;
use App\Service\Api\ApiResponseFactory;
use App\Service\Api\CustomerResourceSerializer;
use App\Service\Checkout\CustomerCheckoutService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_CUSTOMER')]
#[Route('/api/customer/orders', name: 'api_customer_orders_')]
final class OrderController extends AbstractApiController
{
    private const PAYMENT_METHODS = ['COD', 'GCash', 'Bank Transfer', 'Card'];

    public function __construct(
        ApiResponseFactory $api,
        private readonly CustomerResourceSerializer $serializer,
        private readonly CustomerCheckoutService $checkoutService,
    ) {
        parent::__construct($api);
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(OrdersRepository $ordersRepository): JsonResponse
    {
        $customer = $this->requireCustomer();
        if ($customer instanceof JsonResponse) {
            return $customer;
        }

        $orders = $ordersRepository->findForCustomerEmail((string) $customer->getEmail());
        $data = array_map(
            fn (Orders $order): array => $this->serializer->orderSummary($order),
            $orders,
        );

        return $this->api->success($data, meta: ['count' => \count($data)]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id, OrdersRepository $ordersRepository): JsonResponse
    {
        $customer = $this->requireCustomer();
        if ($customer instanceof JsonResponse) {
            return $customer;
        }

        $order = $ordersRepository->find($id);
        if (!$order instanceof Orders || !$this->orderBelongsToCustomer($order, $customer->getEmail())) {
            return $this->api->error('Order not found.', 404);
        }

        return $this->api->success($this->serializer->orderDetail($order));
    }

    #[Route('/{id}/cancel', name: 'cancel', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function cancel(int $id, OrdersRepository $ordersRepository): JsonResponse
    {
        $customer = $this->requireCustomer();
        if ($customer instanceof JsonResponse) {
            return $customer;
        }

        $order = $ordersRepository->find($id);
        if (!$order instanceof Orders || !$this->orderBelongsToCustomer($order, $customer->getEmail())) {
            return $this->api->error('Order not found.', 404);
        }

        $result = $this->checkoutService->cancelOrder($order);
        if ($result['ok'] === false) {
            return $this->api->error($result['error'], 422);
        }

        return $this->api->success($this->serializer->orderDetail($result['order']));
    }

    #[Route('/{id}/payment', name: 'payment', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function payment(int $id, OrdersRepository $ordersRepository): JsonResponse
    {
        $customer = $this->requireCustomer();
        if ($customer instanceof JsonResponse) {
            return $customer;
        }

        $order = $ordersRepository->find($id);
        if (!$order instanceof Orders || !$this->orderBelongsToCustomer($order, $customer->getEmail())) {
            return $this->api->error('Order not found.', 404);
        }

        return $this->api->success([
            'orderId' => $order->getId(),
            'orderNumber' => $order->getDisplayOrderNumber(),
            'paymentMethod' => $order->getPaymentMethod(),
            'totalPrice' => $order->getTotalPrice(),
            'orderStatus' => $order->getOrderStatus(),
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        CustomerAddressRepository $addressRepository,
    ): JsonResponse {
        $customer = $this->requireCustomer();
        if ($customer instanceof JsonResponse) {
            return $customer;
        }

        $payload = $this->decodeJson($request);
        if ($payload === [] && (string) $request->getContent() !== '') {
            return $this->api->error('Invalid JSON body.', 400);
        }

        $paymentMethod = trim((string) ($payload['paymentMethod'] ?? ''));
        if (!in_array($paymentMethod, self::PAYMENT_METHODS, true)) {
            return $this->api->error(
                'Invalid payment method. Allowed: ' . implode(', ', self::PAYMENT_METHODS),
                422,
                'paymentMethod',
            );
        }

        $items = $payload['items'] ?? null;
        if (!is_array($items) || $items === []) {
            return $this->api->error('At least one item is required.', 422, 'items');
        }

        $addressId = (int) ($payload['addressId'] ?? 0);
        if ($addressId <= 0) {
            return $this->api->error('A valid addressId is required.', 422, 'addressId');
        }

        $shippingAddress = $addressRepository->findOneForCustomer($customer, $addressId);
        if (!$shippingAddress instanceof CustomerAddress) {
            return $this->api->error('Shipping address not found.', 404, 'addressId');
        }

        $orderNotes = isset($payload['orderNotes']) ? (string) $payload['orderNotes'] : null;

        $result = $this->checkoutService->placeOrderFromItems(
            $customer,
            $items,
            $paymentMethod,
            $shippingAddress,
            $orderNotes,
        );

        if ($result['ok'] === false) {
            return $this->api->error($result['error'], 400);
        }

        return $this->api->success(
            $this->serializer->orderDetail($result['order']),
            201,
        );
    }

    private function orderBelongsToCustomer(Orders $order, ?string $email): bool
    {
        if ($email === null || $email === '') {
            return false;
        }

        return mb_strtolower((string) $order->getEmail()) === mb_strtolower($email);
    }
}
