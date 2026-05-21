<?php

namespace App\Security;

use App\Entity\Customer;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Mobile /api login and JWT auth — customers only (not staff or admin).
 */
final class CustomerAppUserChecker implements UserCheckerInterface
{
    public function __construct(
        private readonly UserStatusChecker $userStatusChecker,
    ) {
    }

    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof Customer) {
            throw new CustomUserMessageAccountStatusException(
                'This app is for customer accounts only. Staff and admin accounts must sign in on the website.',
            );
        }

        $this->userStatusChecker->checkPreAuth($user);
    }

    public function checkPostAuth(UserInterface $user): void
    {
        $this->userStatusChecker->checkPostAuth($user);
    }
}
