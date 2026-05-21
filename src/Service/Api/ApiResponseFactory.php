<?php

namespace App\Service\Api;

use Symfony\Component\HttpFoundation\JsonResponse;

final class ApiResponseFactory
{
    /**
     * @param array<string, mixed>|list<mixed>|null $data
     * @param array<string, mixed>                  $meta
     */
    public function success(mixed $data = null, int $status = 200, array $meta = []): JsonResponse
    {
        return new JsonResponse($this->envelope(true, $data, null, $meta), $status);
    }

    /**
     * @param list<array{field?: string, message: string}> $errors
     * @param array<string, mixed>                        $meta
     */
    public function error(
        string $message,
        int $status = 400,
        ?string $field = null,
        array $meta = [],
    ): JsonResponse {
        $errors = [['field' => $field, 'message' => $message]];

        return new JsonResponse($this->envelope(false, null, $errors, $meta), $status);
    }

    /**
     * @param list<array{field?: string|null, message: string}> $errors
     * @param array<string, mixed>                              $meta
     */
    public function errors(array $errors, int $status = 422, array $meta = []): JsonResponse
    {
        return new JsonResponse($this->envelope(false, null, $errors, $meta), $status);
    }

    /**
     * @param array<string, mixed>|list<mixed>|null              $data
     * @param list<array{field?: string|null, message: string}>|null $errors
     * @param array<string, mixed>                              $meta
     *
     * @return array<string, mixed>
     */
    private function envelope(bool $success, mixed $data, ?array $errors, array $meta): array
    {
        return [
            'success' => $success,
            'data' => $data,
            'errors' => $errors,
            'meta' => array_merge(
                ['timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM)],
                $meta,
            ),
        ];
    }
}
