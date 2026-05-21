<?php

namespace App\EventSubscriber;

use App\Repository\CustomerRepository;
use App\Service\Api\ApiResponseFactory;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

/**
 * Customer app POST /api/login — distinct errors for unknown email vs wrong password.
 */
final class ApiCustomerLoginFailureSubscriber implements EventSubscriberInterface
{
    public const MESSAGE_NOT_REGISTERED = 'Please use an existing account or sign up for an account.';
    public const MESSAGE_BAD_CREDENTIALS = 'Incorrect email or password.';

    public function __construct(
        private readonly ApiResponseFactory $api,
        private readonly CustomerRepository $customerRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            Events::AUTHENTICATION_FAILURE => ['onLoginFailure', 10],
        ];
    }

    public function onLoginFailure(AuthenticationFailureEvent $event): void
    {
        $request = $event->getRequest();
        if ($request === null || $request->getPathInfo() !== '/api/login') {
            return;
        }

        $exception = $event->getException();
        if ($exception instanceof CustomUserMessageAccountStatusException) {
            $message = $exception->getMessage();
        } else {
            $email = $this->extractLoginEmail($request);
            if ($email === null || $this->customerRepository->findOneByEmailCanonical($email) === null) {
                $message = self::MESSAGE_NOT_REGISTERED;
            } else {
                $message = self::MESSAGE_BAD_CREDENTIALS;
            }
        }

        $event->setResponse($this->api->error($message, 401));
    }

    private function extractLoginEmail(Request $request): ?string
    {
        $content = $request->getContent();
        if ($content === '') {
            return null;
        }

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!is_array($data)) {
            return null;
        }

        $email = trim((string) ($data['email'] ?? ''));
        if ($email === '') {
            return null;
        }

        return $email;
    }
}
