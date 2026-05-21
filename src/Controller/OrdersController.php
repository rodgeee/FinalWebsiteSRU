<?php

namespace App\Controller;

use App\Entity\Orders;
use App\Form\OrdersType;
use App\Form\OrdersCreateType;
use App\Repository\OrdersRepository;
use App\Repository\StocksRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\ActivityLogger;
use App\Service\OrderNumberGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_STAFF')]
#[Route('/admin/orders', name: 'admin_orders_')]
final class OrdersController extends AbstractController
{
    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        ActivityLogger $activityLogger,
        StocksRepository $stocksRepository
    ): Response
    {
        $order = new Orders();
        $form = $this->createForm(OrdersCreateType::class, $order);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $order->setDateCreated(new \DateTimeImmutable());

            // Assign ownership for staff-created orders
            $owner = $this->currentStaff();
            if ($owner && !$this->isGranted('ROLE_ADMIN')) {
                $order->setOwner($owner);
            }

            // Auto-fill price from selected product if not provided
            $selectedProducts = $order->getProducts();
            if ($selectedProducts->count() === 0) {
                $this->addFlash('error', 'Please select at least one product for this order.');

                return $this->render('orders/new.html.twig', [
                    'form' => $form,
                ]);
            }

            if ($selectedProducts->count() > 0 && ($order->getTotalPrice() === null || $order->getTotalPrice() === '')) {
                $firstProduct = $selectedProducts->first();
                if ($firstProduct instanceof \App\Entity\Products) {
                    $order->setTotalPrice($firstProduct->getPrice() ?? '0.00');
                }
            }

            // Reduce stock for each selected product by the ordered quantity.
            $orderedQty = max(0, (int) ($order->getQuantity() ?? 0));
            if ($orderedQty <= 0) {
                $this->addFlash('error', 'Order quantity must be greater than zero.');

                return $this->render('orders/new.html.twig', [
                    'form' => $form,
                ]);
            }

            foreach ($selectedProducts as $product) {
                $stockRows = $stocksRepository->findBy(['Product' => $product], ['id' => 'ASC']);
                $availableQty = 0;
                foreach ($stockRows as $stockRow) {
                    $availableQty += (int) ($stockRow->getQuantity() ?? 0);
                }

                if ($availableQty < $orderedQty) {
                    $this->addFlash(
                        'error',
                        sprintf(
                            'Insufficient stock for %s. Available: %d, required: %d.',
                            method_exists($product, 'getName') ? (string) $product->getName() : 'selected product',
                            $availableQty,
                            $orderedQty
                        )
                    );

                    return $this->render('orders/new.html.twig', [
                        'form' => $form,
                    ]);
                }

                $toDeduct = $orderedQty;
                foreach ($stockRows as $stockRow) {
                    $currentQty = (int) ($stockRow->getQuantity() ?? 0);
                    if ($currentQty <= 0) {
                        continue;
                    }

                    $deductNow = min($currentQty, $toDeduct);
                    $stockRow->setQuantity($currentQty - $deductNow);
                    $stockRow->setUpdatedAt(new \DateTimeImmutable());
                    $toDeduct -= $deductNow;

                    if ($toDeduct <= 0) {
                        break;
                    }
                }
            }

            $createdAt = $order->getDateCreated() ?? new \DateTimeImmutable();
            $order->setOrderNumber($orderNumberGenerator->generate($createdAt));

            $entityManager->persist($order);
            $entityManager->flush();

            $activityLogger->log(
                'create',
                'order',
                (string) $order->getId(),
                sprintf('Created order %s for %s.', $order->getDisplayOrderNumber(), $order->getCustomerName()),
                $this->formatTargetData('Order', $order->getId(), $order->getCustomerName()),
                [
                    'status' => $order->getOrderStatus(),
                    'payment' => $order->getPaymentMethod(),
                    'total' => $order->getTotalPrice(),
                ]
            );

            $this->addFlash('success', 'Order created successfully.');
            return $this->redirectToRoute('admin_orders_show', ['id' => $order->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('orders/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(OrdersRepository $ordersRepository): Response
    {
        $currentStaff = $this->currentStaff();
        $criteria = [];
        if ($currentStaff && !$this->isGranted('ROLE_ADMIN')) {
            $criteria['owner'] = $currentStaff;
        }

        return $this->render('orders/index.html.twig', [
            'orders' => $ordersRepository->findBy($criteria, ['DateCreated' => 'DESC'])
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Orders $order): Response
    {
        $this->assertOrderOwnership($order);
        return $this->render('orders/show.html.twig', [
            'order' => $order,
        ]);
    }

    #[Route('/{id}/status', name: 'update_status', methods: ['POST'])]
    public function updateStatus(Request $request, Orders $order, EntityManagerInterface $entityManager, ActivityLogger $activityLogger): Response
    {
        $this->assignOrAssertOwnership($order);

        if (!$this->isCsrfTokenValid('order_status'.$order->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token. Please try again.');

            return $this->redirectToRoute('admin_orders_show', ['id' => $order->getId()], Response::HTTP_SEE_OTHER);
        }

        $status = trim((string) $request->request->get('status', ''));
        $allowed = ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];
        if (!in_array($status, $allowed, true)) {
            $this->addFlash('error', 'Invalid order status.');

            return $this->redirectToRoute('admin_orders_show', ['id' => $order->getId()], Response::HTTP_SEE_OTHER);
        }

        $order->setOrderStatus($status);
        $entityManager->flush();

        $activityLogger->log(
            'update',
            'order',
            (string) $order->getId(),
            sprintf('Updated order #%d to status %s.', $order->getId(), $order->getOrderStatus()),
            $this->formatTargetData('Order', $order->getId(), $order->getCustomerName()),
            ['status' => $order->getOrderStatus()],
        );

        $this->addFlash('success', 'Order status updated to '.$status.'.');

        return $this->redirectToRoute('admin_orders_show', ['id' => $order->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Orders $order, EntityManagerInterface $entityManager, ActivityLogger $activityLogger): Response
    {
        $this->assignOrAssertOwnership($order);
        $form = $this->createForm(OrdersType::class, $order);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $activityLogger->log(
                'update',
                'order',
                (string) $order->getId(),
                sprintf('Updated order #%d to status %s.', $order->getId(), $order->getOrderStatus()),
                $this->formatTargetData('Order', $order->getId(), $order->getCustomerName()),
                [
                    'status' => $order->getOrderStatus(),
                    'payment' => $order->getPaymentMethod(),
                ]
            );
            $this->addFlash('success', 'Order updated successfully.');
            return $this->redirectToRoute('admin_orders_show', ['id' => $order->getId()], Response::HTTP_SEE_OTHER);
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('error', 'Could not save the order. Please check the form for errors.');
        }

        return $this->render('orders/edit.html.twig', [
            'order' => $order,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Orders $order, EntityManagerInterface $entityManager, ActivityLogger $activityLogger): Response
    {
        $this->assertOrderOwnership($order);
        $token = $request->request->get('_token');
        if ($this->isCsrfTokenValid('delete'.$order->getId(), (string) $token)) {
            $entityManager->remove($order);
            $activityLogger->log(
                'delete',
                'order',
                (string) $order->getId(),
                sprintf('Deleted order #%d.', $order->getId()),
                $this->formatTargetData('Order', $order->getId(), $order->getCustomerName())
            );
            $this->addFlash('success', 'Order deleted.');
        }
        return $this->redirectToRoute('admin_orders_index', [], Response::HTTP_SEE_OTHER);
    }

    private function currentStaff(): ?\App\Entity\Staff
    {
        $user = $this->getUser();

        return $user instanceof \App\Entity\Staff ? $user : null;
    }

    private function assignOrAssertOwnership(Orders $order): void
    {
        $user = $this->currentStaff();
        if (!$user || $this->isGranted('ROLE_ADMIN')) {
            return;
        }

        $owner = $order->getOwner();
        if ($owner === null) {
            $order->setOwner($user);
            return;
        }

        if ($owner->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('You can only edit your own orders.');
        }
    }

    private function assertOrderOwnership(Orders $order): void
    {
        $user = $this->currentStaff();
        if ($user && !$this->isGranted('ROLE_ADMIN')) {
            $owner = $order->getOwner();
            if ($owner && $owner->getId() !== $user->getId()) {
                throw $this->createAccessDeniedException('You can only view your own orders.');
            }
        }
    }

    private function formatTargetData(string $entityLabel, ?int $id, ?string $name = null): string
    {
        $parts = array_filter([$entityLabel, $name]);
        $label = !empty($parts) ? implode(': ', $parts) : $entityLabel;

        return sprintf('%s (ID: %s)', $label, $id ?? 'N/A');
    }
}


