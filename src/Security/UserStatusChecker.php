<?php

namespace App\Security;

use App\Entity\Adminuser;
use App\Entity\Customer;
use App\Entity\Staff;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class UserStatusChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        $this->assertActive($user);
        $this->assertVerified($user);
    }

    public function checkPostAuth(UserInterface $user): void
    {
        // No-op; all checks happen pre-auth.
    }

    private function assertActive(UserInterface $user): void
    {
        if ($user instanceof Staff && !$user->isActive()) {
            throw new CustomUserMessageAccountStatusException('This staff account is disabled.');
        }

        if ($user instanceof Adminuser && !$user->isActive()) {
            throw new CustomUserMessageAccountStatusException('This admin account is disabled.');
        }

        if ($user instanceof Customer && !$user->isActive()) {
            throw new CustomUserMessageAccountStatusException('This customer account is disabled.');
        }
    }

    private function assertVerified(UserInterface $user): void
    {
        if ($user instanceof Customer && !$user->isVerified()) {
            throw new CustomUserMessageAccountStatusException('Please verify your email address before logging in.');
        }

        if ($user instanceof Staff && !$user->isVerified()) {
            throw new CustomUserMessageAccountStatusException('Please verify your email address before logging in.');
        }
    }
}

