<?php

namespace App\Service\Customer;

use App\Entity\Customer;
use App\Repository\CustomerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class CustomerRegistrationService
{
    private const PASSWORD_PATTERN = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/';

    public function __construct(
        private readonly CustomerRepository $customerRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{ok: true, message: string, verificationUrl: string|null}|array{ok: false, error: string, field: string|null}
     */
    public function register(
        string $firstName,
        string $lastName,
        string $email,
        string $password,
        ?string $middleName = null,
        ?string $phoneNumber = null,
        ?string $shoeSize = null,
    ): array {
        $firstName = trim($firstName);
        $lastName = trim($lastName);
        $email = trim($email);
        $middleName = $middleName !== null ? trim($middleName) : '';

        if ($firstName === '' || $lastName === '') {
            return ['ok' => false, 'error' => 'First name and last name are required.', 'field' => 'firstName'];
        }

        if ($email === '') {
            return ['ok' => false, 'error' => 'Email is required.', 'field' => 'email'];
        }

        if ($password === '') {
            return ['ok' => false, 'error' => 'Password is required.', 'field' => 'password'];
        }

        if (!preg_match(self::PASSWORD_PATTERN, $password)) {
            return [
                'ok' => false,
                'error' => 'Password must be at least 8 characters and include upper, lower, number, and symbol.',
                'field' => 'password',
            ];
        }

        if ($this->customerRepository->findOneByEmailCanonical($email) instanceof Customer) {
            return ['ok' => false, 'error' => 'An account with this email already exists.', 'field' => 'email'];
        }

        $fullNameParts = array_filter([$firstName, $middleName, $lastName]);
        $fullName = implode(' ', $fullNameParts);

        $customer = new Customer();
        $customer->setFullName($fullName);
        $customer->setEmail($email);
        $customer->setPlainPassword($password);
        $customer->setRoles(['ROLE_CUSTOMER']);
        $customer->setIsVerified(true);
        $customer->setVerificationToken(null);

        if ($shoeSize !== null && trim($shoeSize) !== '') {
            $customer->setShoeSize(trim($shoeSize));
        }
        if ($phoneNumber !== null && trim($phoneNumber) !== '') {
            $customer->setPhoneNumber(trim($phoneNumber));
        }

        $violations = $this->validator->validate($customer, null, ['create']);
        if (\count($violations) > 0) {
            $first = $violations[0];

            return [
                'ok' => false,
                'error' => (string) $first->getMessage(),
                'field' => $first->getPropertyPath() !== '' ? $first->getPropertyPath() : null,
            ];
        }

        $customer->setPassword($this->passwordHasher->hashPassword($customer, $password));

        try {
            $this->entityManager->persist($customer);
            $this->entityManager->flush();
        } catch (\Throwable $e) {
            $this->logger->error('Customer registration failed.', [
                'email' => $email,
                'exception' => $e->getMessage(),
            ]);

            return ['ok' => false, 'error' => 'Could not create account. Please try again.', 'field' => null];
        }

        $message = 'Account created! You can sign in with your email and password.';

        return ['ok' => true, 'message' => $message, 'verificationUrl' => null];
    }
}
