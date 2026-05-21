<?php

namespace App\Controller;

use App\Repository\OrdersRepository;
use App\Repository\ProductsRepository;
use App\Repository\ServicesRepository;
use App\Repository\StaffRepository;
use App\Repository\StocksRepository;
use App\Repository\AdminuserRepository;
use App\Repository\ActivityLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_STAFF')]
final class DashboardController extends AbstractController
{
    #[Route('/admin/dashboard', name: 'app_dashboard')]
    public function index(
        Request $request,
        OrdersRepository $ordersRepository,
        ProductsRepository $productsRepository,
        StaffRepository $staffRepository,
        AdminuserRepository $adminuserRepository,
        ServicesRepository $servicesRepository,
        StocksRepository $stocksRepository,
        ActivityLogRepository $activityLogRepository
    ): Response
    {
        $user = $this->getUser();
        $status = $request->query->get('status', 'All');
        $searchTerm = trim((string) $request->query->get('search', ''));
        $recentOrders = $ordersRepository->findRecent(5, $status, null);

        $searchResults = [
            'products' => [],
            'orders' => [],
        ];

        if ($searchTerm !== '') {
            $searchResults['products'] = $productsRepository->searchByTerm($searchTerm, 5, null);
            $searchResults['orders'] = $ordersRepository->searchByTerm($searchTerm, 5, null);
        }

        $staffCount = $staffRepository->count([]);
        $adminCount = $adminuserRepository->count([]);
        $stats = [
            'users' => $staffCount + $adminCount,
            'staff' => $staffCount,
            'admins' => $adminCount,
            'products' => $productsRepository->count([]),
            'orders' => $ordersRepository->count([]),
            'services' => $servicesRepository->count([]),
            'stocks' => $stocksRepository->count([]),
        ];

        try {
            $recentActivity = $activityLogRepository->findLatest(null, null, null, 5);
            $stockActivity = $activityLogRepository->findLatest(null, 'stock', null, 6);
            $serviceActivity = $activityLogRepository->findLatest(null, 'service', null, 6);
        } catch (\Throwable) {
            $recentActivity = [];
            $stockActivity = [];
            $serviceActivity = [];
        }
        $notifications = [...$stockActivity, ...$serviceActivity];
        \usort($notifications, static fn($a, $b) => $b->getCreatedAt() <=> $a->getCreatedAt());
        $readIds = $request->getSession()->get('notif_read', []);
        $notifUnread = \array_filter($notifications, static fn($log) => !\in_array($log->getId(), $readIds, true));
        $notifUnreadIds = \array_map(static fn($log) => $log->getId(), $notifUnread);
        $notifAlertCount = \count($notifUnread);
        
        return $this->render('dashboard/index.html.twig', [
            'user' => $user,
            'recent_orders' => $recentOrders,
            'status' => $status,
            'search_term' => $searchTerm,
            'search_results' => $searchResults,
            'stats' => $stats,
            'recent_activity' => $recentActivity,
            'stock_activity' => $stockActivity,
            'service_activity' => $serviceActivity,
            'notif_activity' => $notifications,
            'notif_alert_count' => $notifAlertCount,
            'notif_unread_ids' => $notifUnreadIds,
            'stock_alert_count' => $notifAlertCount, // legacy compatibility with template defaults
        ]);
    }

    #[Route('/admin/dashboard', name: 'legacy_app_dashboard')]
    public function legacyDashboardRedirect(): Response
    {
        return $this->redirectToRoute('app_dashboard', [], Response::HTTP_SEE_OTHER);
    }
}
