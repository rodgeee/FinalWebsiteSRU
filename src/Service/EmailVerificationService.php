<?php

namespace App\Service;

use App\Entity\Customer;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class EmailVerificationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer,
        private string $fromAddress,
        private string $fromName,
        private LoggerInterface $logger
    ) {}

    /**
     * Generate a unique verification token
     */
    public function generateVerificationToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Send verification email to user
     */
    public function sendVerificationEmail(Customer $customer, string $verificationUrl): void
    {
        $to = (string) $customer->getEmail();

        $this->logger->info('Sending verification email.', [
            'to' => $to,
            'from' => $this->fromAddress,
            'subject' => 'Please verify your email address',
        ]);

        $email = (new TemplatedEmail())
            ->from(new Address($this->fromAddress, $this->fromName))
            ->to(new Address($to))
            ->subject('Please verify your email address')
            ->htmlTemplate('emails/verification.html.twig')
            ->textTemplate('emails/verification.txt.twig')
            ->context([
                'customer' => $customer,
                'verificationUrl' => $verificationUrl,
            ]);

        try {
            $this->mailer->send($email);
            $this->logger->info('Verification email sent (handed to transport).', ['to' => $to]);
        } catch (\Throwable $e) {
            $this->logger->error('Verification email send failed.', [
                'to' => $to,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Verify a token and mark user as verified
     */
    public function verifyToken(string $token): ?Customer
    {
        $user = $this->entityManager
            ->getRepository(Customer::class)
            ->findOneBy(['verificationToken' => $token]);

        if (!$user) {
            return null;
        }

        // Mark user as verified
        $user->setIsVerified(true);
        $user->setVerificationToken(null); // Clear the token

        $this->entityManager->flush();

        return $user;
    }

    /**
     * Check if a user needs verification
     */
    public function needsVerification(Customer $customer): bool
    {
        return !$customer->isVerified();
    }
}