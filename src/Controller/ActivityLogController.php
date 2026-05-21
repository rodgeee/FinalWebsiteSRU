<?php

namespace App\Controller;

use App\Repository\ActivityLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class ActivityLogController extends AbstractController
{
    #[Route('/admin/activity-log', name: 'admin_activity_log', methods: ['GET'])]
    public function index(Request $request, ActivityLogRepository $activityLogRepository): Response
    {
        $action = (string) $request->query->get('action', '');
        $entity = (string) $request->query->get('entity', '');
        $search = trim((string) $request->query->get('q', ''));
        $actor = trim((string) $request->query->get('user', ''));
        $fromParam = (string) $request->query->get('from', '');
        $toParam = (string) $request->query->get('to', '');
        $limitParam = (int) $request->query->get('limit', 100);
        $limit = $limitParam > 0 ? min($limitParam, 500) : 100;

        $action = $action !== '' ? $action : null;
        $entity = $entity !== '' ? $entity : null;
        $actor = $actor !== '' ? $actor : null;

        $from = null;
        if ($fromParam !== '') {
            try {
                $from = (new \DateTimeImmutable($fromParam))->setTime(0, 0, 0);
            } catch (\Throwable) {
                $from = null;
            }
        }

        $to = null;
        if ($toParam !== '') {
            try {
                $to = (new \DateTimeImmutable($toParam))->setTime(23, 59, 59);
            } catch (\Throwable) {
                $to = null;
            }
        }

        $logs = $activityLogRepository->findLatest($action, $entity, $search ?: null, $limit, $actor, $from, $to);

        return $this->render('activity_log/index.html.twig', [
            'logs' => $logs,
            'filters' => [
                'action' => $action ?? '',
                'entity' => $entity ?? '',
                'search' => $search,
                'user' => $actor ?? '',
                'from' => $fromParam,
                'to' => $toParam,
                'limit' => $limit,
            ],
        ]);
    }

    #[Route('/admin/activity-log/clear', name: 'admin_activity_log_clear', methods: ['POST'])]
    public function clear(Request $request, EntityManagerInterface $entityManager): Response
    {
        $submittedToken = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('clear_activity_log', $submittedToken)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $entityManager->createQuery('DELETE FROM App\Entity\ActivityLog l')->execute();

        $this->addFlash('success', 'Activity log has been cleared.');

        return $this->redirectToRoute('admin_activity_log');
    }
}


