<?php

namespace App\Controller;

use App\Entity\Services;
use App\Form\ServicesType;
use App\Repository\ServicesRepository;
use App\Repository\ActivityLogRepository;
use App\Service\ServiceWorkflow;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_STAFF')]
final class ServiceController extends AbstractController
{
    #[Route('/admin/service', name: 'admin_service_index', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        ServicesRepository $servicesRepository,
        EntityManagerInterface $entityManager,
        ActivityLogger $activityLogger,
        ActivityLogRepository $activityLogRepository
    ): Response {
        $currentStaff = $this->currentStaff();
        $service = new Services();
        $service->setStatus(ServiceWorkflow::DEFAULT_STATUS);
        $service->setOwner($currentStaff);

        $form = $this->createForm(ServicesType::class, $service);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $now = new \DateTimeImmutable();
            if (null === $service->getCreatedAt()) {
                $service->setCreatedAt($now);
            }
            $service->setUpdatedAt($now);

            $entityManager->persist($service);
            $entityManager->flush();
            $activityLogger->log(
                'create',
                'service',
                (string) $service->getId(),
                sprintf('Created service job “%s” (%s).', $service->getShoeName(), $service->getStatus()),
                $this->formatTargetData('Service', $service->getId(), $service->getShoeName()),
                [
                    'status' => $service->getStatus(),
                    'type' => $service->getServiceType(),
                ]
            );

            $this->addFlash(
                'success',
                sprintf('“%s” entered the lab queue.', $service->getShoeName())
            );

            return $this->redirectToRoute('admin_service_index');
        }

        $criteria = [];
        if ($currentStaff && !$this->isGranted('ROLE_ADMIN')) {
            $criteria = ['owner' => $currentStaff];
        }
        $activeServices = $servicesRepository->findBy($criteria, ['updated_at' => 'DESC']);
        $stageMeta = ServiceWorkflow::stageMeta();

        $pipelineJobs = array_map(function (Services $job) use ($stageMeta) {
            $status = $job->getStatus() ?: ServiceWorkflow::DEFAULT_STATUS;
            $meta = $stageMeta[$status] ?? $stageMeta[ServiceWorkflow::DEFAULT_STATUS];

            return [
                'id' => $job->getId(),
                'shoe' => $job->getShoeName(),
                'type' => $job->getServiceType(),
                'note' => $job->getNote(),
                'customerEmail' => $job->getCustomerEmail(),
                'source' => $job->getSource(),
                'status' => $status,
                'badge' => $meta['badge'],
                'progress' => $meta['progress'],
                'phase' => $meta['phase'],
                'bucket' => $meta['bucket'],
                'etaHours' => $meta['etaHours'],
                'etaLabel' => $this->formatEta($meta['etaHours']),
                'description' => $meta['description'],
                'updatedAgo' => $this->formatRelativeTime($job->getUpdatedAt()),
                'updatedAt' => $job->getUpdatedAt(),
                'editPath' => $this->generateUrl('admin_service_edit', ['id' => $job->getId()]),
            ];
        }, $activeServices);

        $serviceSummary = $this->buildSummary($pipelineJobs);

        $liveFeed = array_map(
            fn (array $job) => [
                'shoe' => $job['shoe'],
                'status' => $job['status'],
                'badge' => $job['badge'],
                'time' => $job['updatedAgo'],
                'note' => $job['description'],
            ],
            array_slice($pipelineJobs, 0, 5)
        );

        $labMilestones = [
            [
                'icon' => 'droplets',
                'title' => 'Cleaning queue',
                'detail' => sprintf('%d pair%s in wash / dry', $serviceSummary['cleaning'], $serviceSummary['cleaning'] === 1 ? '' : 's'),
                'status' => $serviceSummary['cleaning'] > 0 ? 'In progress' : 'Idle',
            ],
            [
                'icon' => 'wrench',
                'title' => 'Repair queue',
                'detail' => sprintf('%d pair%s on the bench', $serviceSummary['repair'], $serviceSummary['repair'] === 1 ? '' : 's'),
                'status' => $serviceSummary['repair'] > 4 ? 'Busy' : ($serviceSummary['repair'] > 0 ? 'Steady' : 'Clear'),
            ],
            [
                'icon' => 'flag',
                'title' => 'Ready for pickup',
                'detail' => sprintf('%d pair%s awaiting pickup', $serviceSummary['ready'], $serviceSummary['ready'] === 1 ? '' : 's'),
                'status' => $serviceSummary['ready'] > 0 ? 'Notify' : 'Clear',
            ],
        ];

        $stockActivity = $activityLogRepository->findLatest(null, 'stock', null, 6);
        $serviceActivity = $activityLogRepository->findLatest(null, 'service', null, 6);
        $notifications = [...$stockActivity, ...$serviceActivity];
        \usort($notifications, static fn($a, $b) => $b->getCreatedAt() <=> $a->getCreatedAt());
        $readIds = $request->getSession()->get('notif_read', []);
        $notifUnread = \array_filter($notifications, static fn($log) => !\in_array($log->getId(), $readIds, true));
        $notifUnreadIds = \array_map(static fn($log) => $log->getId(), $notifUnread);
        $notifAlertCount = \count($notifUnread);

        return $this->render('service/index.html.twig', [
            'form' => $form->createView(),
            'pipelineJobs' => $pipelineJobs,
            'serviceSummary' => $serviceSummary,
            'liveFeed' => $liveFeed,
            'labMilestones' => $labMilestones,
            'stock_activity' => $stockActivity,
            'service_activity' => $serviceActivity,
            'notif_activity' => $notifications,
            'notif_alert_count' => $notifAlertCount,
            'notif_unread_ids' => $notifUnreadIds,
            'stock_alert_count' => $notifAlertCount,
        ]);
    }

    #[Route('/admin/service/{id}/edit', name: 'admin_service_edit', methods: ['GET', 'POST'])]
    public function edit(
        Services $service,
        Request $request,
        EntityManagerInterface $entityManager,
        ActivityLogger $activityLogger,
        ActivityLogRepository $activityLogRepository
    ): Response {
        $this->assertServiceOwnership($service);
        $form = $this->createForm(ServicesType::class, $service);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $now = new \DateTimeImmutable();
            if (null === $service->getCreatedAt()) {
                $service->setCreatedAt($now);
            }
            $service->setUpdatedAt($now);

            $entityManager->flush();
            $activityLogger->log(
                'update',
                'service',
                (string) $service->getId(),
                sprintf('Updated service #%d to %s.', $service->getId(), $service->getStatus()),
                $this->formatTargetData('Service', $service->getId(), $service->getShoeName()),
                [
                    'status' => $service->getStatus(),
                    'type' => $service->getServiceType(),
                ]
            );

            $this->addFlash(
                'success',
                sprintf('Updated “%s” to %s.', $service->getShoeName(), $service->getStatus())
            );

            return $this->redirectToRoute('admin_service_index');
        }

        $stockActivity = $activityLogRepository->findLatest(null, 'stock', null, 6);
        $serviceActivity = $activityLogRepository->findLatest(null, 'service', null, 6);
        $notifications = [...$stockActivity, ...$serviceActivity];
        \usort($notifications, static fn($a, $b) => $b->getCreatedAt() <=> $a->getCreatedAt());
        $readIds = $request->getSession()->get('notif_read', []);
        $notifUnread = \array_filter($notifications, static fn($log) => !\in_array($log->getId(), $readIds, true));
        $notifUnreadIds = \array_map(static fn($log) => $log->getId(), $notifUnread);
        $notifAlertCount = \count($notifUnread);

        return $this->render('service/edit.html.twig', [
            'form' => $form->createView(),
            'service' => $service,
            'stock_activity' => $stockActivity,
            'service_activity' => $serviceActivity,
            'notif_activity' => $notifications,
            'notif_alert_count' => $notifAlertCount,
            'notif_unread_ids' => $notifUnreadIds,
            'stock_alert_count' => $notifAlertCount,
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $jobs
     *
     * @return array<string, int|string>
     */
    private function buildSummary(array $jobs): array
    {
        $summary = [
            'active' => count($jobs),
            'intake' => 0,
            'cleaning' => 0,
            'repair' => 0,
            'ready' => 0,
            'complete' => 0,
            'avgProgress' => 0,
            'avgEtaLabel' => '—',
        ];

        if ($summary['active'] === 0) {
            return $summary;
        }

        $progressTotal = 0;
        $etaTotal = 0;

        foreach ($jobs as $job) {
            $bucket = $job['bucket'] ?? 'intake';
            if (!isset($summary[$bucket])) {
                $summary[$bucket] = 0;
            }
            $summary[$bucket]++;
            $progressTotal += (int) ($job['progress'] ?? 0);
            $etaTotal += (int) ($job['etaHours'] ?? 0);
        }

        $summary['avgProgress'] = (int) round($progressTotal / $summary['active']);
        $summary['avgEtaLabel'] = $this->formatEta((int) round($etaTotal / $summary['active']));

        return $summary;
    }

    private function formatEta(int $hours): string
    {
        if ($hours <= 0) {
            return 'Ready today';
        }

        $days = intdiv($hours, 24);
        $remainingHours = $hours % 24;

        if ($days > 0 && $remainingHours > 0) {
            return sprintf('%dd %dh', $days, $remainingHours);
        }

        if ($days > 0) {
            return sprintf('%dd', $days);
        }

        return sprintf('%dh', $remainingHours);
    }

    private function formatRelativeTime(?\DateTimeImmutable $timestamp): string
    {
        if (!$timestamp) {
            return 'Just now';
        }

        $now = new \DateTimeImmutable();
        $diff = $now->diff($timestamp);

        if ($diff->y > 0) {
            return sprintf('%dy ago', $diff->y);
        }

        if ($diff->m > 0) {
            return sprintf('%dmo ago', $diff->m);
        }

        if ($diff->d > 0) {
            return sprintf('%dd ago', $diff->d);
        }

        if ($diff->h > 0) {
            return sprintf('%dh ago', $diff->h);
        }

        if ($diff->i > 0) {
            return sprintf('%dm ago', $diff->i);
        }

        return 'Just now';
    }

    private function assertServiceOwnership(Services $service): void
    {
        $user = $this->currentStaff();
        if ($user && !$this->isGranted('ROLE_ADMIN')) {
            $ownerId = $service->getOwner()?->getId();
            if ($ownerId === null || $ownerId !== $user->getId()) {
                throw $this->createAccessDeniedException('You can only manage your own service records.');
            }
        }
    }

    private function currentStaff(): ?\App\Entity\Staff
    {
        $user = $this->getUser();

        return $user instanceof \App\Entity\Staff ? $user : null;
    }

    private function formatTargetData(string $entityLabel, ?int $id, ?string $name = null): string
    {
        $parts = array_filter([$entityLabel, $name]);
        $label = !empty($parts) ? implode(': ', $parts) : $entityLabel;

        return sprintf('%s (ID: %s)', $label, $id ?? 'N/A');
    }
}

