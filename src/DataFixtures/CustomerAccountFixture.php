<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Repository\CustomerRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Dev-only: load demo customer without wiping the full snapshot.
 *
 *   php bin/console doctrine:fixtures:load --group=customer --append
 *
 * Full loads ({@see FixtureLoader} / app:load-fixtures) already include this account.
 *
 * Credentials: {@see DemoCustomerSeeder::EMAIL} / {@see DemoCustomerSeeder::PASSWORD}
 */
final class CustomerAccountFixture extends Fixture implements FixtureGroupInterface
{
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
        DemoCustomerSeeder::ensureLoaded($manager, $this->passwordHasher, $this->customerRepository);
        $manager->flush();
    }
}
