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

    /**
     * @return bool true when the message was handed to the mail transport
     */
    public function sendVerificationEmail(Staff $staff, string $verificationUrl): bool
    {
        $to = (string) $staff->getEmail();

        $email = (new TemplatedEmail())
            ->from(new Address($this->fromAddress, $this->fromName))
            ->to(new Address($to))
            ->subject('Please verify your email address')
            ->htmlTemplate('emails/verification.html.twig')
            ->textTemplate('emails/verification.txt.twig')
            ->context([
                'customer' => $staff,
                'verificationUrl' => $verificationUrl,
            ]);

        try {
            $this->mailer->send($email);
            $this->logger->info('Staff verification email sent.', ['to' => $to]);

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Staff verification email failed.', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
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

