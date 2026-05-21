<?php

namespace App\Controller;

use App\Entity\Services;
use App\Repository\OrdersRepository;
use App\Repository\ServicesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_STAFF')]
#[Route('/admin/transactions', name: 'admin_transactions_')]
final class TransactionsController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        OrdersRepository $ordersRepository,
        ServicesRepository $servicesRepository
    ): Response
    {
        $currentStaff = $this->currentStaff();
        $type = (string) $request->query->get('type', '');
        $type = in_array($type, ['sales', 'services'], true) ? $type : '';

        if ($type === '') {
            return $this->render('transactions/index.html.twig', [
                'mode' => 'chooser',
            ]);
        }

        $statusFilter = $request->query->get('status', 'All');
        $paymentFilter = $request->query->get('payment', 'All');
        $searchTerm = trim((string) $request->query->get('search', ''));

        $limitParam = (int) $request->query->get('limit', 50);
        if ($limitParam <= 0) {
            $limit = null;
        } else {
            $limit = max(10, min(200, $limitParam));
        }

        if ($type === 'services') {
            $rawServices = $servicesRepository->findTransactionFeed($limit, $this->isGranted('ROLE_ADMIN') ? null : $currentStaff);
            $serviceSummary = $servicesRepository->summarizeTransactions($this->isGranted('ROLE_ADMIN') ? null : $currentStaff);
            $serviceFeed = array_map(static function (Services $job): array {
                return [
                    'id' => $job->getId(),
                    'shoe' => $job->getShoeName(),
                    'type' => $job->getServiceType(),
                    'status' => $job->getStatus(),
                    'note' => $job->getNote(),
                    'createdAt' => $job->getCreatedAt(),
                    'updatedAt' => $job->getUpdatedAt(),
                ];
            }, $rawServices);

            return $this->render('transactions/index.html.twig', [
                'mode' => 'services',
                'serviceFeed' => $serviceFeed,
                'serviceSummary' => $serviceSummary,
                'limit' => $limit,
            ]);
        }

        $ownerFilter = $this->isGranted('ROLE_ADMIN') ? null : $currentStaff;
        $transactions = $ordersRepository->findTransactions($statusFilter, $paymentFilter, $searchTerm, $limit, $ownerFilter);
        $insights = $ordersRepository->summarizeTransactions($statusFilter, $paymentFilter, $searchTerm, $ownerFilter);

        return $this->render('transactions/index.html.twig', [
            'mode' => 'sales',
            'transactions' => $transactions,
            'statusFilter' => $statusFilter,
            'paymentFilter' => $paymentFilter,
            'searchTerm' => $searchTerm,
            'insights' => $insights,
            'limit' => $limit,
        ]);
    }

    private function currentStaff(): ?\App\Entity\Staff
    {
        $user = $this->getUser();

        return $user instanceof \App\Entity\Staff ? $user : null;
    }
}

