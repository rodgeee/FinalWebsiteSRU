<?php

namespace App\Controller\Api\Customer;

use App\Controller\Api\AbstractApiController;
use App\Repository\CustomerRepository;
use App\Service\Api\ApiResponseFactory;
use App\Service\Customer\CustomerRegistrationService;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/customer/signup', name: 'api_customer_signup_')]
final class SignupController extends AbstractApiController
{
    public function __construct(
        ApiResponseFactory $api,
        private readonly CustomerRegistrationService $registrationService,
        private readonly CustomerRepository $customerRepository,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly LoggerInterface $logger,
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

        try {
            $result = $this->registrationService->register(
                firstName: (string) ($payload['firstName'] ?? ''),
                lastName: (string) ($payload['lastName'] ?? ''),
                email: (string) ($payload['email'] ?? ''),
                password: (string) ($payload['password'] ?? ''),
                middleName: isset($payload['middleName']) ? (string) $payload['middleName'] : null,
                phoneNumber: isset($payload['phoneNumber']) ? (string) $payload['phoneNumber'] : null,
                shoeSize: isset($payload['shoeSize']) ? (string) $payload['shoeSize'] : null,
            );
        } catch (\Throwable $e) {
            $this->logger->error('API customer signup failed.', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return $this->api->error('Could not create account. Please try again.', 500);
        }

        if ($result['ok'] === false) {
            return $this->api->error($result['error'], 422, $result['field']);
        }

        $email = trim((string) ($payload['email'] ?? ''));
        $data = [
            'message' => $result['message'],
            'verificationUrl' => $result['verificationUrl'],
            'token' => null,
        ];

        $customer = $this->customerRepository->findOneByEmailCanonical($email);
        if ($customer !== null) {
            try {
                $data['token'] = $this->jwtManager->create($customer);
            } catch (\Throwable $e) {
                $this->logger->error('Signup JWT issue failed.', [
                    'email' => $email,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $this->api->success($data, 201);
    }
}
