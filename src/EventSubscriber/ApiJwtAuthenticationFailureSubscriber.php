<?php

namespace App\EventSubscriber;

use App\Service\Api\ApiResponseFactory;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Return API envelope JSON for JWT failures on /api/* (mobile expects success/errors shape).
 */
final class ApiJwtAuthenticationFailureSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ApiResponseFactory $api,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            Events::AUTHENTICATION_FAILURE => ['onJwtFailure', 0],
            Events::JWT_INVALID => ['onJwtFailure', 0],
            Events::JWT_EXPIRED => ['onJwtFailure', 0],
            Events::JWT_NOT_FOUND => ['onJwtFailure', 0],
        ];
    }

    public function onJwtFailure(AuthenticationFailureEvent $event): void
    {
        $request = $event->getRequest();
        if ($request === null || !str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        // POST /api/login uses ApiCustomerLoginFailureSubscriber for tailored messages.
        if ($request->getPathInfo() === '/api/login') {
            return;
        }

        $message = $event->getException()->getMessage();
        if ($message === '') {
            $message = 'Authentication required.';
        }

        $event->setResponse($this->api->error($message, 401));
    }
}
