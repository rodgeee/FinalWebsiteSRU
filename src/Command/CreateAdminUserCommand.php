<?php

namespace App\Command;

use App\Entity\Adminuser;
use App\Repository\AdminuserRepository;
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
    name: 'app:create-admin',
    description: 'Create or reset an admin dashboard account (Adminuser entity).',
)]
final class CreateAdminUserCommand extends Command
{
    public function __construct(
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
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Admin login email')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Plain password (min 8 chars, upper, lower, number, symbol)')
            ->addOption('full-name', null, InputOption::VALUE_REQUIRED, 'Display name', 'System Admin')
            ->addOption('if-missing', null, InputOption::VALUE_NONE, 'Only create when this email does not exist yet (do not reset password)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = trim((string) $input->getOption('email'));
        $password = (string) $input->getOption('password');
        $fullName = trim((string) $input->getOption('full-name'));

        if ($email === '') {
            $email = $io->ask('Admin email', 'admin@shoesrus.local');
        }

        if ($password === '') {
            $password = $io->askHidden('Password (min 8 chars, upper, lower, number, symbol)');
        }

        if ($fullName === '') {
            $fullName = 'System Admin';
        }

        $existing = $this->adminuserRepository->findOneBy(['Email' => $email]);
        if ($existing !== null && $input->getOption('if-missing')) {
            $io->note(sprintf('Admin "%s" already exists — password unchanged.', $email));

            return Command::SUCCESS;
        }

        $admin = $existing ?? new Adminuser();
        $isNew = $existing === null;

        $admin->setEmail($email);
        $admin->setFullname($fullName);
        $admin->setIsActive(true);
        $admin->setPlainPassword($password);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, $password));

        $violations = $this->validator->validate($admin, null, $isNew ? ['Registration'] : null);
        if ($violations->count() > 0) {
            $io->error($violations->get(0)->getMessage());

            return Command::FAILURE;
        }

        if ($isNew) {
            $this->entityManager->persist($admin);
        }

        $this->entityManager->flush();

        $io->success($isNew ? 'Admin account created.' : 'Admin password updated.');
        $io->table(
            ['Field', 'Value'],
            [
                ['Login URL', '/adminls/login (or /admin/login)'],
                ['Email', $email],
                ['Password', '(the value you entered)'],
                ['Role', 'ROLE_ADMIN'],
            ],
        );

        return Command::SUCCESS;
    }
}
