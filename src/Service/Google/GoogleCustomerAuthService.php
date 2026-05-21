<?php

namespace App\Service\Google;

use App\Entity\Customer;
use App\Repository\CustomerRepository;
use App\Service\EmailVerificationService;
use App\Security\UserStatusChecker;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

/**
 * Shared Google sign-in / sign-up rules for web OAuth and mobile API.
 */
final class GoogleCustomerAuthService
{
    public function __construct(
        private readonly CustomerRepository $customerRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EmailVerificationService $emailVerificationService,
        private readonly RouterInterface $router,
        private readonly LoggerInterface $logger,
        private readonly UserStatusChecker $userStatusChecker,
    ) {
    }

    public function authenticate(GoogleProfile $profile, string $oauthAction): GoogleCustomerAuthResult
    {
        if ($oauthAction !== 'login' && $oauthAction !== 'signup') {
            $oauthAction = 'login';
        }

        $email = $profile->email;
        $fullName = $profile->fullName;

        $customer = $this->customerRepository->findOneByEmailCanonical($email);
        if ($customer) {
            return $this->handleExistingCustomer($customer, $email, $fullName, $oauthAction);
        }

        if ($oauthAction === 'login') {
            return GoogleCustomerAuthResult::notRegistered(
                'This Google account is not set up yet. Use Continue with Google on the Sign Up screen to create your account.'
            );
        }

        return $this->createVerifiedCustomer($email, $fullName);
    }

    private function handleExistingCustomer(
        Customer $customer,
        string $email,
        string $fullName,
        string $oauthAction,
    ): GoogleCustomerAuthResult {
        if (!$customer->isVerified()) {
            $customer->setIsVerified(true);
            $customer->setVerificationToken(null);
            $this->entityManager->flush();
        }

        if ($customer->getFullName() === null || $customer->getFullName() === '') {
            $customer->setFullName($fullName);
            $this->entityManager->flush();
        }

        $userId = $customer->getId();
        if ($userId === null) {
            return GoogleCustomerAuthResult::unverifiedLogin('Account state is invalid. Please contact support.');
        }

        $userForLogin = $this->customerRepository->find($userId);
        if (!$userForLogin instanceof Customer) {
            return GoogleCustomerAuthResult::unverifiedLogin('Could not load your account. Please try again.');
        }

        if (!$userForLogin->isVerified()) {
            return GoogleCustomerAuthResult::unverifiedLogin(
                'Please verify your email address before logging in.'
            );
        }

        try {
            $this->userStatusChecker->checkPreAuth($userForLogin);
        } catch (CustomUserMessageAccountStatusException $e) {
            return GoogleCustomerAuthResult::unverifiedLogin($e->getMessage());
        }

        return GoogleCustomerAuthResult::jwtReady($userForLogin);
    }

    private function createVerifiedCustomer(string $email, string $fullName): GoogleCustomerAuthResult
    {
        $customer = new Customer();
        $customer->setFullName($fullName);
        $customer->setEmail($email);
        $customer->setRoles(['ROLE_CUSTOMER']);
        $customer->setIsVerified(true);
        $customer->setVerificationToken(null);
        $customer->setPassword($this->passwordHasher->hashPassword($customer, bin2hex(random_bytes(16))));
        $this->entityManager->persist($customer);
        $this->entityManager->flush();

        try {
            $this->userStatusChecker->checkPreAuth($customer);
        } catch (CustomUserMessageAccountStatusException $e) {
            return GoogleCustomerAuthResult::unverifiedLogin($e->getMessage());
        }

        return GoogleCustomerAuthResult::jwtReady($customer);
    }
}
