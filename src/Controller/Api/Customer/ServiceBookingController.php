<?php

namespace App\Controller\Api\Customer;

use App\Controller\Api\AbstractApiController;
use App\Entity\Services;
use App\Service\Api\ApiResponseFactory;
use App\Service\Api\CustomerResourceSerializer;
use App\Service\Customer\CustomerServiceBookingService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_CUSTOMER')]
#[Route('/api/customer/service-bookings', name: 'api_customer_service_bookings_')]
final class ServiceBookingController extends AbstractApiController
{
    public function __construct(
        ApiResponseFactory $api,
        private readonly CustomerResourceSerializer $serializer,
        private readonly CustomerServiceBookingService $bookingService,
    ) {
        parent::__construct($api);
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $customer = $this->requireCustomer();
        if ($customer instanceof JsonResponse) {
            return $customer;
        }

        $bookings = $this->bookingService->listForCustomer($customer);
        $data = array_map(
            fn (Services $service): array => $this->serializer->serviceBookingSummary($service),
            $bookings,
        );

        return $this->api->success($data, meta: ['count' => \count($data)]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): JsonResponse
    {
        $customer = $this->requireCustomer();
        if ($customer instanceof JsonResponse) {
            return $customer;
        }

        $service = $this->bookingService->findForCustomer($customer, $id);
        if (!$service instanceof Services) {
            return $this->api->error('Service booking not found.', 404);
        }

        return $this->api->success($this->serializer->serviceBookingDetail($service));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer();
        if ($customer instanceof JsonResponse) {
            return $customer;
        }

        $payload = $this->decodeJson($request);
        if ($payload === [] && (string) $request->getContent() !== '') {
            return $this->api->error('Invalid JSON body.', 400);
        }

        $result = $this->bookingService->createBooking(
            $customer,
            (string) ($payload['packageName'] ?? ''),
            (string) ($payload['shoeName'] ?? ''),
            isset($payload['notes']) ? (string) $payload['notes'] : null,
            isset($payload['material']) ? (string) $payload['material'] : null,
        );

        if ($result['ok'] === false) {
            return $this->api->error($result['error'], 422, $result['field']);
        }

        return $this->api->success(
            $this->serializer->serviceBookingDetail($result['service']),
            201,
        );
    }
}
