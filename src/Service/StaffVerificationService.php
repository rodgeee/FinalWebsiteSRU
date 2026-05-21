<?php

namespace App\Service;

use App\Entity\Staff;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

final class StaffVerificationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer,
        private string $fromAddress,
        private string $fromName,
        private LoggerInterface $logger
    ) {
    }

    public function generateVerificationToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function ensureVerificationToken(Staff $staff): string
    {
        if ($staff->getVerificationToken()) {
            return $staff->getVerificationToken();
        }

        $token = $this->generateVerificationToken();
        $staff->setVerificationToken($token);

        $this->entityManager->flush();

        return $token;
    }

    public function sendVerificationEmail(Staff $staff, string $verificationUrl): void
    {
        $to = (string) $staff->getEmail();

        $this->logger->info('Sending staff verification email.', [
            'to' => $to,
        ]);

        $email = (new TemplatedEmail())
            ->from(new Address($this->fromAddress, $this->fromName))
            ->to(new Address($to))
            ->subject('Please verify your email address')
            ->htmlTemplate('emails/verification.html.twig')
            ->textTemplate('emails/verification.txt.twig')
            ->context([
                // Reuse existing templates: they only require customer.fullName + verificationUrl.
                'customer' => $staff,
                'verificationUrl' => $verificationUrl,
            ]);

        $this->mailer->send($email);
    }

    public function verifyToken(string $token): ?Staff
    {
        $staff = $this->entityManager
            ->getRepository(Staff::class)
            ->findOneBy(['verificationToken' => $token]);

        if (!$staff) {
            return null;
        }

        $staff->setIsVerified(true);
        $staff->setIsActive(true);
        $staff->setStatus('active');
        $staff->setVerificationToken(null);

        $this->entityManager->flush();

        return $staff;
    }
}

