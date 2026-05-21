<?php

namespace App\EventSubscriber;

use App\Service\Api\ApiResponseFactory;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

final class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ApiResponseFactory $api,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 0],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        $path = $request->getPathInfo();
        if (!str_starts_with($path, '/api/customer')) {
            return;
        }

        $throwable = $event->getThrowable();

        if ($throwable instanceof CustomUserMessageAccountStatusException) {
            $event->setResponse($this->api->error($throwable->getMessage(), 403));
            return;
        }

        if ($throwable instanceof AuthenticationException) {
            $event->setResponse($this->api->error('Authentication required.', 401));
            return;
        }

        if ($throwable instanceof AccessDeniedException) {
            $event->setResponse($this->api->error('Access denied.', 403));
            return;
        }

        if ($throwable instanceof HttpExceptionInterface) {
            $status = $throwable->getStatusCode();
            $message = $throwable->getMessage() !== '' ? $throwable->getMessage() : 'Request failed.';
            $event->setResponse($this->api->error($message, $status));

            return;
        }

        $event->setResponse(new JsonResponse([
            'success' => false,
            'data' => null,
            'errors' => [['field' => null, 'message' => 'An unexpected error occurred.']],
            'meta' => ['timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM)],
        ], 500));
    }
}
