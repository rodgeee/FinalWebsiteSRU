<?php

declare(strict_types=1);

namespace App\Command;

use App\DataFixtures\CustomerAccountFixture;
use App\Entity\Customer;
use App\Repository\CustomerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Ensures the mobile-app demo customer exists with a known password (Railway / local dev).
 */
#[AsCommand(
    name: 'app:ensure-demo-customer',
    description: 'Create or reset demo customer customer@shoesrus.local / Password1! for API login',
)]
final class EnsureDemoCustomerCommand extends Command
{
    public function __construct(
        private readonly CustomerRepository $customerRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = CustomerAccountFixture::EMAIL;

        $customer = $this->customerRepository->findOneByEmailCanonical($email);
        $isNew = $customer === null;

        if ($isNew) {
            $customer = new Customer();
            $customer->setFullName(CustomerAccountFixture::FULL_NAME);
            $customer->setEmail($email);
            $customer->setRoles(['ROLE_CUSTOMER']);
            $customer->setShoeSize('9');
            $customer->setPhoneNumber('+63 9000000000');
        }

        $customer
            ->setIsActive(true)
            ->setIsVerified(true)
            ->setVerificationToken(null)
            ->setPassword($this->passwordHasher->hashPassword($customer, CustomerAccountFixture::PASSWORD));

        if ($isNew) {
            $this->entityManager->persist($customer);
        }

        $this->entityManager->flush();

        $io->success(sprintf(
            '%s demo customer %s — sign in with %s / %s',
            $isNew ? 'Created' : 'Reset',
            $email,
            $email,
            CustomerAccountFixture::PASSWORD,
        ));

        return Command::SUCCESS;
    }
}
