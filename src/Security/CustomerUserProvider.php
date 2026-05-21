<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Customer;
use App\Repository\CustomerRepository;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Case-insensitive customer lookup (matches signup / Google / failure messages).
 */
final class CustomerUserProvider implements UserProviderInterface
{
    public function __construct(
        private readonly CustomerRepository $customerRepository,
    ) {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $customer = $this->customerRepository->findOneByEmailCanonical($identifier);
        if (!$customer instanceof Customer) {
            throw new UserNotFoundException(sprintf('Customer "%s" not found.', $identifier));
        }

        return $customer;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof Customer) {
            throw new UnsupportedUserException(sprintf('Invalid user class "%s".', $user::class));
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return $class === Customer::class || is_subclass_of($class, Customer::class);
    }
}
