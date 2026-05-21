<?php

namespace App\Controller;

use App\Repository\ActivityLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class NotificationController extends AbstractController
{
    #[Route('/notification/go/{id}', name: 'notification_go', methods: ['GET'])]
    public function go(int $id, Request $request, ActivityLogRepository $activityLogRepository): Response
    {
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_STAFF')) {
            throw $this->createAccessDeniedException();
        }

        $log = $activityLogRepository->find($id);
        if (!$log) {
            return $this->redirectToRoute('app_dashboard');
        }

        // Mark as read in session (lightweight)
        $session = $request->getSession();
        $readIds = $session->get('notif_read', []);
        if (!\in_array($log->getId(), $readIds, true)) {
            $readIds[] = $log->getId();
            $session->set('notif_read', $readIds);
        }

        // Compute target route
        $entityType = $log->getEntityType();
        $entityId = $log->getEntityId();

        if ($entityType === 'service' && $entityId) {
            return $this->redirectToRoute('admin_service_edit', ['id' => $entityId]);
        }

        if ($entityType === 'stock' && $entityId) {
            return $this->redirectToRoute('admin_stocks_show', ['id' => $entityId]);
        }

        // Fallbacks
        if ($entityType === 'service') {
            return $this->redirectToRoute('admin_service_index');
        }

        return $this->redirectToRoute('admin_stocks_index');
    }
}

