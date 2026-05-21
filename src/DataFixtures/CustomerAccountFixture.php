<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Customer;
use App\Entity\CustomerAddress;
use App\Repository\CustomerRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Dev-only demo customer (verified, active, with a default address).
 *
 * Load without wiping the full snapshot:
 *   php bin/console doctrine:fixtures:load --group=customer --append
 *
 * Credentials: {@see self::EMAIL} / {@see self::PASSWORD}
 * Login: /login (route customer_login)
 */
final class CustomerAccountFixture extends Fixture implements FixtureGroupInterface
{
    public const string EMAIL = 'customer@shoesrus.local';
    public const string PASSWORD = 'Password1!';
    public const string FULL_NAME = 'Demo Customer';

    public function __construct(
        private readonly CustomerRepository $customerRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public static function getGroups(): array
    {
        return ['customer'];
    }

    public function load(ObjectManager $manager): void
    {
        if ($this->customerRepository->findOneBy(['email' => self::EMAIL]) !== null) {
            return;
        }

        $customer = new Customer();
        $customer
            ->setFullName(self::FULL_NAME)
            ->setEmail(self::EMAIL)
            ->setRoles(['ROLE_CUSTOMER'])
            ->setIsActive(true)
            ->setIsVerified(true)
            ->setVerificationToken(null)
            ->setShoeSize('9')
            ->setPhoneNumber('+63 9000000000')
            ->setPassword($this->passwordHasher->hashPassword($customer, self::PASSWORD));

        $address = (new CustomerAddress())
            ->setLabel('Home')
            ->setAddressLine1('123 Demo Street')
            ->setCity('Dumaguete City')
            ->setProvince('Negros Oriental')
            ->setPostalCode('6200')
            ->setCountry('Philippines')
            ->setContactEmail(self::EMAIL)
            ->setContactPhone('+63 9000000000')
            ->setIsDefault(true);

        $customer->addAddress($address);

        $manager->persist($customer);
        $manager->flush();
    }
}
