<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\CustomerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:verify-customers-for-login',
    description: 'Mark customer accounts as verified so mobile and API login work on production',
)]
final class VerifyCustomersForLoginCommand extends Command
{
    public function __construct(
        private readonly CustomerRepository $customerRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('email', null, InputOption::VALUE_REQUIRED, 'Only verify this customer email');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getOption('email');

        $customers = $email
            ? array_filter([$this->customerRepository->findOneByEmailCanonical((string) $email)])
            : $this->customerRepository->findAll();

        $updated = 0;
        foreach ($customers as $customer) {
            if ($customer === null) {
                continue;
            }
            if (!$customer->isVerified() || $customer->getVerificationToken() !== null) {
                $customer->setIsVerified(true);
                $customer->setVerificationToken(null);
                ++$updated;
            }
        }

        if ($updated > 0) {
            $this->entityManager->flush();
        }

        $io->success($updated > 0
            ? sprintf('Verified %d customer account(s). They can log in now.', $updated)
            : 'No unverified customer accounts needed updating.');

        return Command::SUCCESS;
    }
}
