<?php

declare(strict_types=1);

namespace App\Command;

use App\DataFixtures\DemoCustomerSeeder;
use App\Repository\CustomerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:sync-demo-customer',
    description: 'Create or reset the demo customer login (customer@shoesrus.local / Password1!)',
)]
final class SyncDemoCustomerCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly CustomerRepository $customerRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        DemoCustomerSeeder::ensureLoaded($this->entityManager, $this->passwordHasher, $this->customerRepository);
        $this->entityManager->flush();

        $io->success('Demo customer is ready for mobile and web login.');
        $io->table(
            ['Field', 'Value'],
            [
                ['Email', DemoCustomerSeeder::EMAIL],
                ['Password', DemoCustomerSeeder::PASSWORD],
                ['API login', 'POST /api/login'],
                ['Web login', '/login'],
            ],
        );

        return Command::SUCCESS;
    }
}
