<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Staff;
use App\Repository\AdminuserRepository;
use App\Repository\StaffRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(
    name: 'app:create-staff',
    description: 'Create or reset a staff dashboard account (verified and active, no email step).',
)]
final class CreateStaffUserCommand extends Command
{
    public function __construct(
        private readonly StaffRepository $staffRepository,
        private readonly AdminuserRepository $adminuserRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Staff login email')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Plain password (min 8 chars, upper, lower, number, symbol)')
            ->addOption('full-name', null, InputOption::VALUE_REQUIRED, 'Display name', 'Demo Staff')
            ->addOption('if-missing', null, InputOption::VALUE_NONE, 'Only create when this email does not exist yet (do not reset password)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = trim((string) $input->getOption('email'));
        $password = (string) $input->getOption('password');
        $fullName = trim((string) $input->getOption('full-name'));

        if ($email === '') {
            $email = $io->ask('Staff email', 'staff@shoesrus.local');
        }

        if ($password === '') {
            $password = $io->askHidden('Password (min 8 chars, upper, lower, number, symbol)');
        }

        if ($fullName === '') {
            $fullName = 'Demo Staff';
        }

        if ($this->adminuserRepository->findOneBy(['Email' => $email]) !== null) {
            $io->error(sprintf('Email "%s" is already used by an admin account.', $email));

            return Command::FAILURE;
        }

        $existing = $this->staffRepository->findOneBy(['email' => $email]);
        if ($existing !== null && $input->getOption('if-missing')) {
            $io->note(sprintf('Staff "%s" already exists — password unchanged.', $email));

            return Command::SUCCESS;
        }

        $staff = $existing ?? new Staff();
        $isNew = $existing === null;

        $staff->setEmail($email);
        $staff->setFullName($fullName);
        $staff->setRoles(['ROLE_STAFF']);
        $staff->setIsVerified(true);
        $staff->setIsActive(true);
        $staff->setStatus('active');
        $staff->setVerificationToken(null);
        $staff->setPlainPassword($password);
        $staff->setPassword($this->passwordHasher->hashPassword($staff, $password));

        $violations = $this->validator->validate($staff, null, $isNew ? ['create'] : null);
        if ($violations->count() > 0) {
            $io->error($violations->get(0)->getMessage());

            return Command::FAILURE;
        }

        if ($isNew) {
            $this->entityManager->persist($staff);
        }

        $this->entityManager->flush();

        $io->success($isNew ? 'Staff account created (verified, ready to log in).' : 'Staff account updated.');
        $io->table(
            ['Field', 'Value'],
            [
                ['Login URL', '/adminls/login'],
                ['Email', $email],
                ['Password', '(the value you entered)'],
                ['After login', 'Staff orders dashboard (/admin/orders)'],
                ['Role', 'ROLE_STAFF'],
                ['Verified', 'yes'],
            ],
        );

        return Command::SUCCESS;
    }
}
