<?php

namespace App\Controller\Api;

use App\Entity\Customer;
use App\Service\Api\ApiResponseFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\ConstraintViolationListInterface;

abstract class AbstractApiController extends AbstractController
{
    public function __construct(
        protected readonly ApiResponseFactory $api,
    ) {
    }

    protected function requireCustomer(): Customer|JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof Customer) {
            return $this->api->error('Authentication required.', 401);
        }

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeJson(Request $request): array
    {
        $content = (string) $request->getContent();
        if ($content === '') {
            return [];
        }

        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    protected function validationErrors(ConstraintViolationListInterface $violations): JsonResponse
    {
        $errors = [];
        foreach ($violations as $violation) {
            $property = $violation->getPropertyPath();
            $errors[] = [
                'field' => $property !== '' ? $property : null,
                'message' => (string) $violation->getMessage(),
            ];
        }

        return $this->api->errors($errors, 422);
    }
}
