<?php

declare(strict_types=1);

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Dev-only bridge for doctrine:fixtures:load.
 *
 * Production and app:load-fixtures use {@see FixtureLoader} (no FixturesBundle).
 */
final class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        (new FixtureLoader())->load($manager);
    }
}
