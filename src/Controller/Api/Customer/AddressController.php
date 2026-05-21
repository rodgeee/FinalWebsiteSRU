<?php

namespace App\Controller\Api\Customer;

use App\Controller\Api\AbstractApiController;
use App\Entity\Customer;
use App\Entity\CustomerAddress;
use App\Repository\CustomerAddressRepository;
use App\Service\Api\ApiResponseFactory;
use App\Service\Api\CustomerResourceSerializer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[IsGranted('ROLE_CUSTOMER')]
#[Route('/api/customer/addresses', name: 'api_customer_addresses_')]
final class AddressController extends AbstractApiController
{
    public function __construct(
        ApiResponseFactory $api,
        private readonly CustomerResourceSerializer $serializer,
    ) {
        parent::__construct($api);
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(CustomerAddressRepository $addressRepository): JsonResponse
    {
        $customer = $this->requireCustomer();
        if ($customer instanceof JsonResponse) {
            return $customer;
        }

        $addresses = $addressRepository->findForCustomer($customer);
        $data = array_map(
            fn (CustomerAddress $address): array => $this->serializer->address($address),
            $addresses,
        );

        return $this->api->success($data, meta: ['count' => \count($data)]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id, CustomerAddressRepository $addressRepository): JsonResponse
    {
        $customer = $this->requireCustomer();
        if ($customer instanceof JsonResponse) {
            return $customer;
        }

        $address = $addressRepository->findOneForCustomer($customer, $id);
        if (!$address instanceof CustomerAddress) {
            return $this->api->error('Address not found.', 404);
        }

        return $this->api->success($this->serializer->address($address));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        CustomerAddressRepository $addressRepository,
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

        $address = new CustomerAddress();
        $customer->addAddress($address);
        $this->applyAddressPayload($address, $payload);

        $existing = $addressRepository->findForCustomer($customer);
        $makeDefault = (bool) ($payload['isDefault'] ?? false);
        if ($existing === []) {
            $makeDefault = true;
        }
        $address->setIsDefault($makeDefault);

        $violations = $validator->validate($address);
        if (\count($violations) > 0) {
            return $this->validationErrors($violations);
        }

        if ($address->getAddressLine1() === '' || $address->getCity() === '') {
            return $this->api->error('addressLine1 and city are required.', 422);
        }

        if ($makeDefault) {
            $this->clearDefaultFlags($addressRepository, $customer, null);
        }

        $entityManager->persist($address);
        $entityManager->flush();

        return $this->api->success($this->serializer->address($address), 201);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT', 'PATCH'], requirements: ['id' => '\d+'])]
    public function update(
        int $id,
        Request $request,
        CustomerAddressRepository $addressRepository,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
    ): JsonResponse {
        $customer = $this->requireCustomer();
        if ($customer instanceof JsonResponse) {
            return $customer;
        }

        $address = $addressRepository->findOneForCustomer($customer, $id);
        if (!$address instanceof CustomerAddress) {
            return $this->api->error('Address not found.', 404);
        }

        $payload = $this->decodeJson($request);
        if ($payload === [] && (string) $request->getContent() !== '') {
            return $this->api->error('Invalid JSON body.', 400);
        }

        $this->applyAddressPayload($address, $payload);

        if (array_key_exists('isDefault', $payload) && (bool) $payload['isDefault']) {
            $this->clearDefaultFlags($addressRepository, $customer, $address->getId());
            $address->setIsDefault(true);
        }

        $violations = $validator->validate($address);
        if (\count($violations) > 0) {
            return $this->validationErrors($violations);
        }

        $entityManager->flush();

        return $this->api->success($this->serializer->address($address));
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(
        int $id,
        CustomerAddressRepository $addressRepository,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $customer = $this->requireCustomer();
        if ($customer instanceof JsonResponse) {
            return $customer;
        }

        $address = $addressRepository->findOneForCustomer($customer, $id);
        if (!$address instanceof CustomerAddress) {
            return $this->api->error('Address not found.', 404);
        }

        $wasDefault = $address->isDefault();
        $entityManager->remove($address);
        $entityManager->flush();

        if ($wasDefault) {
            $remaining = $addressRepository->findForCustomer($customer);
            if ($remaining !== []) {
                $remaining[0]->setIsDefault(true);
                $entityManager->flush();
            }
        }

        return $this->api->success(null, 200);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function applyAddressPayload(CustomerAddress $address, array $payload): void
    {
        if (array_key_exists('label', $payload)) {
            $address->setLabel(trim((string) $payload['label']) ?: 'Home');
        }
        if (array_key_exists('addressLine1', $payload)) {
            $address->setAddressLine1(trim((string) $payload['addressLine1']));
        }
        if (array_key_exists('addressLine2', $payload)) {
            $line2 = trim((string) $payload['addressLine2']);
            $address->setAddressLine2($line2 !== '' ? $line2 : null);
        }
        if (array_key_exists('city', $payload)) {
            $address->setCity(trim((string) $payload['city']));
        }
        if (array_key_exists('province', $payload)) {
            $province = trim((string) $payload['province']);
            $address->setProvince($province !== '' ? $province : null);
        }
        if (array_key_exists('postalCode', $payload)) {
            $postal = trim((string) $payload['postalCode']);
            $address->setPostalCode($postal !== '' ? $postal : null);
        }
        if (array_key_exists('country', $payload)) {
            $country = trim((string) $payload['country']);
            $address->setCountry($country !== '' ? $country : 'Philippines');
        }
        if (array_key_exists('contactEmail', $payload)) {
            $email = trim((string) $payload['contactEmail']);
            $address->setContactEmail($email !== '' ? $email : null);
        }
        if (array_key_exists('contactPhone', $payload)) {
            $phone = trim((string) $payload['contactPhone']);
            $address->setContactPhone($phone !== '' ? $phone : null);
        }
    }

    private function clearDefaultFlags(
        CustomerAddressRepository $addressRepository,
        Customer $customer,
        ?int $exceptId,
    ): void {
        foreach ($addressRepository->findForCustomer($customer) as $other) {
            if ($exceptId !== null && $other->getId() === $exceptId) {
                continue;
            }
            $other->setIsDefault(false);
        }
    }
}
