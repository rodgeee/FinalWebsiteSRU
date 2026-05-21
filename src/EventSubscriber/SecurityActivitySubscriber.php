<?php

namespace App\EventSubscriber;

use App\Service\ActivityLogger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

final class SecurityActivitySubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly ActivityLogger $activityLogger)
    {
    }

    /**
     * @return array<class-string, string|array<int, string>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
            LogoutEvent::class => 'onLogout',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user) {
            return;
        }

        $this->activityLogger->log(
            'login',
            'auth',
            null,
            'User logged in.',
            $this->describeUser($user)
        );
    }

    public function onLogout(LogoutEvent $event): void
    {
        $token = $event->getToken();
        $user = $token?->getUser();
        if (!$user) {
            return;
        }

        $this->activityLogger->log(
            'logout',
            'auth',
            null,
            'User logged out.',
            $this->describeUser($user)
        );
    }

    private function describeUser(UserInterface|string $user): string
    {
        if ($user instanceof UserInterface) {
            $identifier = method_exists($user, 'getUserIdentifier') ? $user->getUserIdentifier() : '';
            $name = method_exists($user, 'getUsername') ? $user->getUsername() : '';

            return trim(sprintf('%s (%s)', $name, $identifier)) ?: 'user';
        }

        return $user;
    }
}

