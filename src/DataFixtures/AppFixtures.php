<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Repository\CustomerRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Dev-only bridge for doctrine:fixtures:load.
 *
 * Production and app:load-fixtures use {@see FixtureLoader} (no FixturesBundle).
 */
final class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly CustomerRepository $customerRepository,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        (new FixtureLoader())->load($manager);
        DemoCustomerSeeder::ensureLoaded($manager, $this->passwordHasher, $this->customerRepository);
        $manager->flush();
    }
}
