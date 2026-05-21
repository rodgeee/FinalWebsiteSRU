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
                'This Google account is not set up yet. Use Continue with Google on the Sign Up screen to create your account and receive a verification email.'
            );
        }

        return $this->createUnverifiedCustomer($email, $fullName);
    }

    private function handleExistingCustomer(
        Customer $customer,
        string $email,
        string $fullName,
        string $oauthAction,
    ): GoogleCustomerAuthResult {
        if (!$customer->isVerified()) {
            if ($oauthAction === 'signup') {
                return $this->resendVerificationEmail($customer, $email);
            }

            return GoogleCustomerAuthResult::unverifiedLogin(
                'Please verify your email address before using Google sign-in. Check your inbox for the verification link.'
            );
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

    private function resendVerificationEmail(Customer $customer, string $email): GoogleCustomerAuthResult
    {
        $customer->setVerificationToken($this->emailVerificationService->generateVerificationToken());

        $verificationUrl = $this->router->generate(
            'app_verify_email',
            ['token' => (string) $customer->getVerificationToken()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $this->entityManager->flush();

        try {
            $this->emailVerificationService->sendVerificationEmail($customer, $verificationUrl);

            return GoogleCustomerAuthResult::unverifiedSignupResent(
                'We sent you a new verification email. Verify it, then you can sign in.'
            );
        } catch (\Throwable $mailException) {
            $this->logger->error('Google signup resend: verification email failed.', [
                'email' => $email,
                'exception_class' => $mailException::class,
                'exception_message' => $mailException->getMessage(),
            ]);

            return GoogleCustomerAuthResult::unverifiedSignupResent(
                'We could not send the verification email right now. Open this link to verify: ' . $verificationUrl
            );
        }
    }

    private function createUnverifiedCustomer(string $email, string $fullName): GoogleCustomerAuthResult
    {
        $customer = new Customer();
        $customer->setFullName($fullName);
        $customer->setEmail($email);
        $customer->setRoles(['ROLE_CUSTOMER']);
        $customer->setIsVerified(false);
        $customer->setVerificationToken($this->emailVerificationService->generateVerificationToken());
        $customer->setPassword($this->passwordHasher->hashPassword($customer, bin2hex(random_bytes(16))));
        $this->entityManager->persist($customer);
        $this->entityManager->flush();

        $verificationUrl = $this->router->generate(
            'app_verify_email',
            ['token' => (string) $customer->getVerificationToken()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        try {
            $this->emailVerificationService->sendVerificationEmail($customer, $verificationUrl);

            return GoogleCustomerAuthResult::signupPending(
                'We created your account with Google. Check your email to verify your address, then you can sign in with Google.'
            );
        } catch (\Throwable $mailException) {
            $this->logger->error('Google signup: verification email failed.', [
                'email' => $email,
                'exception_class' => $mailException::class,
                'exception_message' => $mailException->getMessage(),
            ]);

            return GoogleCustomerAuthResult::signupPending(
                'Account created, but we could not send the verification email. Open this link to verify: ' . $verificationUrl
            );
        }
    }
}
