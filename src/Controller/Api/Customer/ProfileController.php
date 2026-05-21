<?php

namespace App\Controller\Api\Customer;

use App\Controller\Api\AbstractApiController;
use App\Entity\Customer;
use App\Repository\CustomerRepository;
use App\Service\Api\ApiResponseFactory;
use App\Service\Api\CustomerResourceSerializer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[IsGranted('ROLE_CUSTOMER')]
#[Route('/api/customer/profile', name: 'api_customer_profile_')]
final class ProfileController extends AbstractApiController
{
    public function __construct(
        ApiResponseFactory $api,
        private readonly CustomerResourceSerializer $serializer,
    ) {
        parent::__construct($api);
    }

    #[Route('', name: 'show', methods: ['GET'])]
    public function show(): JsonResponse
    {
        $customer = $this->requireCustomer();
        if ($customer instanceof JsonResponse) {
            return $customer;
        }

        return $this->api->success($this->serializer->profile($customer));
    }

    #[Route('', name: 'update', methods: ['PUT', 'PATCH'])]
    public function update(
        Request $request,
        CustomerRepository $customerRepository,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
    ): JsonResponse {
        $customer = $this->requireCustomer();
        if ($customer instanceof JsonResponse) {
            return $customer;
        }

        $payload = $this->decodeJson($request);
        if ($payload === [] && (string) $request->getContent() !== '') {
            return $this->api->error('Invalid JSON body.', 400);
        }

        if (array_key_exists('fullName', $payload)) {
            $fullName = trim((string) $payload['fullName']);
            if ($fullName === '') {
                return $this->api->error('Full name is required.', 422, 'fullName');
            }
            $customer->setFullName($fullName);
        }

        if (array_key_exists('email', $payload)) {
            $email = trim((string) $payload['email']);
            if ($email === '') {
                return $this->api->error('Email is required.', 422, 'email');
            }
            $existing = $customerRepository->findOneByEmailCanonical($email);
            if ($existing instanceof Customer && $existing->getId() !== $customer->getId()) {
                return $this->api->error('An account with this email already exists.', 409, 'email');
            }
            $customer->setEmail($email);
        }

        if (array_key_exists('phoneNumber', $payload)) {
            $phone = trim((string) $payload['phoneNumber']);
            $customer->setPhoneNumber($phone !== '' ? $phone : null);
        }

        if (array_key_exists('shoeSize', $payload)) {
            $shoeSize = trim((string) $payload['shoeSize']);
            $customer->setShoeSize($shoeSize !== '' ? $shoeSize : null);
        }

        $violations = $validator->validate($customer);
        if (\count($violations) > 0) {
            return $this->validationErrors($violations);
        }

        $entityManager->flush();

        return $this->api->success($this->serializer->profile($customer));
    }
}
