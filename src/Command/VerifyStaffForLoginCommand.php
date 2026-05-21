<?php

namespace App\Command;

use App\Repository\StaffRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:verify-staff-for-login',
    description: 'Mark staff accounts as email-verified so they can log in on production',
)]
final class VerifyStaffForLoginCommand extends Command
{
    public function __construct(
        private readonly StaffRepository $staffRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('email', null, InputOption::VALUE_REQUIRED, 'Only verify this staff email');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getOption('email');

        $staffList = $email
            ? array_filter([$this->staffRepository->findOneBy(['email' => $email])])
            : $this->staffRepository->findAll();

        $updated = 0;
        foreach ($staffList as $staff) {
            if ($staff === null) {
                continue;
            }
            if (!$staff->isVerified() || !$staff->isActive() || $staff->getStatus() !== 'active') {
                $staff->setIsVerified(true);
                $staff->setIsActive(true);
                $staff->setStatus('active');
                $staff->setVerificationToken(null);
                ++$updated;
            }
        }

        if ($updated > 0) {
            $this->entityManager->flush();
        }

        $io->success($updated > 0
            ? sprintf('Verified %d staff account(s). They can log in now.', $updated)
            : 'No unverified staff accounts needed updating.');

        return Command::SUCCESS;
    }
}
