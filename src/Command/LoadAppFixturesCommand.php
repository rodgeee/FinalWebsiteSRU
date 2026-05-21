<?php

declare(strict_types=1);

namespace App\Command;

use App\DataFixtures\AppFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:load-fixtures',
    description: 'Load AppFixtures (embedded PHP data, same as doctrine:fixtures:load)',
)]
final class LoadAppFixturesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('append', null, InputOption::VALUE_NONE, 'Do not purge the database before loading');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$input->getOption('append')) {
            $io->warning('Purging all application tables before loading fixtures.');
            $this->purgeApplicationData();
        }

        (new AppFixtures())->load($this->entityManager);

        $io->success('Application fixtures loaded from App\\DataFixtures\\AppFixtures');

        return Command::SUCCESS;
    }

    private function purgeApplicationData(): void
    {
        $conn = $this->entityManager->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        foreach ([
            'activity_log',
            'orders_products',
            'orders',
            'services',
            'stocks',
            'products',
            'customer_address',
            'customer',
            'staff',
            'adminuser',
        ] as $table) {
            $conn->executeStatement(sprintf('DELETE FROM `%s`', $table));
        }
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        $this->entityManager->clear();
    }
}
