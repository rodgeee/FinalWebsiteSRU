<?php

namespace App\Service\Customer;

use App\Entity\Customer;
use App\Entity\Services;
use App\Repository\ServicesRepository;
use App\Service\ActivityLogger;
use App\Service\ServiceWorkflow;
use Doctrine\ORM\EntityManagerInterface;

final class CustomerServiceBookingService
{
    public const ALLOWED_PACKAGES = [
        'Essential Clean',
        'Deep Clean',
        'Premium Restore',
        // Legacy admin labels still accepted
        'Basic Cleaning',
        'Deep Cleaning',
        'Premium Repaint',
        'Sole Swap & Repair',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ServicesRepository $servicesRepository,
        private readonly ActivityLogger $activityLogger,
    ) {
    }

    /**
     * @return array{ok: true, service: Services}|array{ok: false, error: string, field: string|null}
     */
    public function createBooking(
        Customer $customer,
        string $packageName,
        string $shoeName,
        ?string $notes = null,
        ?string $material = null,
    ): array {
        $packageName = trim($packageName);
        $shoeName = trim($shoeName);

        if ($packageName === '') {
            return ['ok' => false, 'error' => 'Please choose a service package.', 'field' => 'packageName'];
        }

        if (!in_array($packageName, self::ALLOWED_PACKAGES, true)) {
            return ['ok' => false, 'error' => 'Invalid service package.', 'field' => 'packageName'];
        }

        if ($shoeName === '') {
            return ['ok' => false, 'error' => 'Please describe the shoe or pair you are sending in.', 'field' => 'shoeName'];
        }

        if (mb_strlen($shoeName) > 255) {
            return ['ok' => false, 'error' => 'Shoe description is too long.', 'field' => 'shoeName'];
        }

        $noteParts = array_filter([
            $material !== null && trim($material) !== '' ? 'Material: ' . trim($material) : null,
            $notes !== null && trim($notes) !== '' ? trim($notes) : null,
            'Booked via mobile app.',
        ]);
        $note = implode(' | ', $noteParts);
        if (mb_strlen($note) > 255) {
            $note = mb_substr($note, 0, 252) . '...';
        }

        $now = new \DateTimeImmutable();
        $service = new Services();
        $service->setShoeName($shoeName);
        $service->setServiceType($packageName);
        $service->setStatus(ServiceWorkflow::DEFAULT_STATUS);
        $service->setNote($note !== '' ? $note : 'Booked via mobile app.');
        $service->setCreatedAt($now);
        $service->setUpdatedAt($now);
        $service->setCustomer($customer);
        $service->setCustomerEmail((string) $customer->getEmail());
        $service->setSource('mobile');

        $this->entityManager->persist($service);
        $this->entityManager->flush();

        $this->activityLogger->log(
            'create',
            'service',
            (string) $service->getId(),
            sprintf(
                'Mobile booking: “%s” — %s (%s).',
                $service->getShoeName(),
                $service->getServiceType(),
                $service->getCustomerEmail() ?? 'customer',
            ),
            sprintf('Service: %s (ID: %s)', $service->getShoeName(), $service->getId()),
            [
                'status' => $service->getStatus(),
                'type' => $service->getServiceType(),
                'source' => 'mobile',
                'customerEmail' => $service->getCustomerEmail(),
            ],
        );

        return ['ok' => true, 'service' => $service];
    }

    /**
     * @return Services[]
     */
    public function listForCustomer(Customer $customer): array
    {
        return $this->servicesRepository->findForCustomer($customer);
    }

    public function findForCustomer(Customer $customer, int $id): ?Services
    {
        return $this->servicesRepository->findOneForCustomer($customer, $id);
    }
}
