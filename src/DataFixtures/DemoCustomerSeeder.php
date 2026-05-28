<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Customer;
use App\Entity\CustomerAddress;
use App\Repository\CustomerRepository;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Verified demo customer for local dev, mobile app, and full fixture loads.
 *
 * Email: customer@shoesrus.local
 * Password: Password1!
 */
final class DemoCustomerSeeder
{
    public const EMAIL = 'customer@shoesrus.local';
    public const PASSWORD = 'Password1!';
    public const FULL_NAME = 'Demo Customer';

    /** Symfony {@see UserPasswordHasherInterface} hash for {@see PASSWORD} (used without DI). */
    private const PASSWORD_HASH = '$2y$13$40PIj/Ey/DkTtPA4EsSWXu8eNmVp5M2sxI7jkhkmIIb4yzgeRFlHe';

    public static function ensureLoaded(
        ObjectManager $manager,
        ?UserPasswordHasherInterface $passwordHasher = null,
        ?CustomerRepository $customerRepository = null,
    ): void {
        $repo = $customerRepository ?? $manager->getRepository(Customer::class);
        $existing = $repo instanceof CustomerRepository
            ? $repo->findOneByEmailCanonical(self::EMAIL)
            : $repo->findOneBy(['email' => self::EMAIL]);

        if ($existing instanceof Customer) {
            self::applyDemoState($existing, $passwordHasher);

            return;
        }

        $customer = new Customer();
        self::applyDemoState($customer, $passwordHasher);

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
    }

    private static function applyDemoState(Customer $customer, ?UserPasswordHasherInterface $passwordHasher): void
    {
        $customer
            ->setFullName(self::FULL_NAME)
            ->setEmail(self::EMAIL)
            ->setRoles(['ROLE_CUSTOMER'])
            ->setIsActive(true)
            ->setIsVerified(true)
            ->setVerificationToken(null)
            ->setShoeSize('9')
            ->setPhoneNumber('+63 9000000000');

        if ($passwordHasher !== null) {
            $customer->setPassword($passwordHasher->hashPassword($customer, self::PASSWORD));
        } else {
            $customer->setPassword(self::PASSWORD_HASH);
        }
    }
}
