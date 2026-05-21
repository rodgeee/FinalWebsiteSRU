<?php

namespace App\Controller\Api\Customer;

use App\Controller\Api\AbstractApiController;
use App\Service\Api\ApiResponseFactory;
use App\Service\Customer\CustomerRegistrationService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/customer/signup', name: 'api_customer_signup_')]
final class SignupController extends AbstractApiController
{
    public function __construct(
        ApiResponseFactory $api,
        private readonly CustomerRegistrationService $registrationService,
    ) {
        parent::__construct($api);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = $this->decodeJson($request);
        if ($payload === [] && (string) $request->getContent() !== '') {
            return $this->api->error('Invalid JSON body.', 400);
        }

        $result = $this->registrationService->register(
            firstName: (string) ($payload['firstName'] ?? ''),
            lastName: (string) ($payload['lastName'] ?? ''),
            email: (string) ($payload['email'] ?? ''),
            password: (string) ($payload['password'] ?? ''),
            middleName: isset($payload['middleName']) ? (string) $payload['middleName'] : null,
            phoneNumber: isset($payload['phoneNumber']) ? (string) $payload['phoneNumber'] : null,
            shoeSize: isset($payload['shoeSize']) ? (string) $payload['shoeSize'] : null,
        );

        if ($result['ok'] === false) {
            return $this->api->error($result['error'], 422, $result['field']);
        }

        return $this->api->success(
            [
                'message' => $result['message'],
                'verificationUrl' => $result['verificationUrl'],
            ],
            201,
        );
    }
}
