<?php

namespace App\Controller;

use App\Entity\Products;
use App\Entity\Stocks;
use App\Form\StocksType;
use App\Repository\ProductsRepository;
use App\Repository\ActivityLogRepository;
use App\Repository\StocksRepository;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_STAFF')]
#[Route('/admin/stocks', name: 'admin_stocks_')]
final class StocksController extends AbstractController
{
    private const PRODUCT_IMAGE_DIR = 'uploads/products';
    private const PRODUCT_IMAGE_MAX_FILES = 4;

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        StocksRepository $stocksRepository,
        ActivityLogRepository $activityLogRepository
    ): Response
    {
        // Allow staff to view all stocks even if they did not create them.
        $stocks = $stocksRepository->findAll();
        $stockActivity = $activityLogRepository->findLatest(null, 'stock', null, 6);
        $serviceActivity = $activityLogRepository->findLatest(null, 'service', null, 6);
        $notifications = [...$stockActivity, ...$serviceActivity];
        \usort($notifications, static fn($a, $b) => $b->getCreatedAt() <=> $a->getCreatedAt());
        $readIds = $request->getSession()->get('notif_read', []);
        $notifUnread = \array_filter($notifications, static fn($log) => !\in_array($log->getId(), $readIds, true));
        $notifUnreadIds = \array_map(static fn($log) => $log->getId(), $notifUnread);
        $notifAlertCount = \count($notifUnread);

        return $this->render('stocks/index.html.twig', [
            'stocks' => $stocks,
            'stock_activity' => $stockActivity,
            'service_activity' => $serviceActivity,
            'notif_activity' => $notifications,
            'notif_alert_count' => $notifAlertCount,
            'notif_unread_ids' => $notifUnreadIds,
            'stock_alert_count' => $notifAlertCount, // legacy template compatibility
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, ProductsRepository $productsRepository, ActivityLogger $activityLogger): Response
    {
        $currentStaff = $this->currentStaff();
        $stock = new Stocks();
        $stock->setCreatedAt(new \DateTimeImmutable());
        $stock->setUpdatedAt(new \DateTimeImmutable());
        $stock->setOwner($currentStaff);
        
        $form = $this->createForm(StocksType::class, $stock);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle product creation or selection
            $selectedProduct = $form->get('Product')->getData();
            $productName = $form->get('productName')->getData();
            $product = null;
            
            if ($selectedProduct) {
                // Use existing product but only allow updates if owned by current staff or admin
                $product = $selectedProduct;
                if ($this->canMutateProduct($product)) {
                    // Update product fields if provided
                    if ($productName) {
                        $product->setName($productName);
                    }
                    if ($form->get('productColor')->getData()) {
                        $product->setColor($form->get('productColor')->getData());
                    }
                    if ($form->get('productSize')->getData()) {
                        $product->setSize($form->get('productSize')->getData());
                    }
                    if ($form->get('productPrice')->getData() !== null) {
                        $product->setPrice($this->normalizePrice($form->get('productPrice')->getData()));
                    }
                    if ($form->get('productDescription')->getData()) {
                        $product->setDescription($form->get('productDescription')->getData());
                    }
                    $newImagePaths = $this->storeProductImages($form->get('productImages')->getData(), $product);
                    if (!empty($newImagePaths)) {
                        $product->setImages($newImagePaths);
                        $product->setImage($newImagePaths[0] ?? null);
                    }
                }
            } elseif ($productName) {
                // Create new product
                $product = new Products();
                $product->setName($productName);
                $product->setColor($form->get('productColor')->getData() ?? '');
                $product->setSize($form->get('productSize')->getData() ?? '');
                $product->setPrice($this->normalizePrice($form->get('productPrice')->getData()));
                $product->setDescription($form->get('productDescription')->getData() ?? '');
                $product->setOwner($currentStaff);
                $newImagePaths = $this->storeProductImages($form->get('productImages')->getData());
                if (!empty($newImagePaths)) {
                    $product->setImages($newImagePaths);
                    $product->setImage($newImagePaths[0] ?? null);
                }
                $entityManager->persist($product);
                // Flush early so we have a product ID for the log entry
                $entityManager->flush();
                $activityLogger->log(
                    'create',
                    'product',
                    (string) $product->getId(),
                    sprintf('Created product %s.', $product->getName()),
                    $this->formatTargetData('Product', $product->getId(), $product->getName()),
                    [
                        'price' => $product->getPrice(),
                        'color' => $product->getColor(),
                        'size' => $product->getSize(),
                    ],
                    false
                );
            } else {
                $this->addFlash('error', 'Please select an existing product or create a new one.');
                return $this->render('stocks/new.html.twig', [
                    'stock' => $stock,
                    'form' => $form,
                    'existingImages' => [],
                ]);
            }
            
            $stock->setProduct($product);
            $entityManager->persist($stock);
            $entityManager->flush();
            $activityLogger->log(
                'create',
                'stock',
                (string) $stock->getId(),
                sprintf('Created stock entry with quantity %d.', $stock->getQuantity() ?? 0),
                $this->formatTargetData('Stock', $stock->getId(), $product?->getName()),
                [
                    'product' => $product?->getName(),
                    'quantity' => $stock->getQuantity(),
                ]
            );

            $this->addFlash('success', 'Stock entry created successfully.');
            return $this->redirectToRoute('admin_stocks_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('stocks/new.html.twig', [
            'stock' => $stock,
            'form' => $form,
            'products' => $productsRepository->findAll(),
            'existingImages' => [],
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Stocks $stock): Response
    {
        return $this->render('stocks/show.html.twig', [
            'stock' => $stock,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Stocks $stock, EntityManagerInterface $entityManager, ActivityLogger $activityLogger): Response
    {
        $this->assertStockOwnership($stock);
        $form = $this->createForm(StocksType::class, $stock);

        // Snapshot existing values so we can log detailed changes
        $before = [
            'quantity' => $stock->getQuantity(),
            'productName' => $stock->getProduct()?->getName(),
            'color' => $stock->getProduct()?->getColor(),
            'size' => $stock->getProduct()?->getSize(),
            'price' => $stock->getProduct()?->getPrice(),
            'description' => $stock->getProduct()?->getDescription(),
        ];
        
        // Pre-fill product fields
        if ($stock->getProduct()) {
            $form->get('Product')->setData($stock->getProduct());
            $form->get('productName')->setData($stock->getProduct()->getName());
            $form->get('productColor')->setData($stock->getProduct()->getColor());
            $form->get('productSize')->setData($stock->getProduct()->getSize());
            $form->get('productPrice')->setData($stock->getProduct()->getPrice());
            $form->get('productDescription')->setData($stock->getProduct()->getDescription());
        }
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Update product
            $product = $stock->getProduct();
            if ($product) {
                if (!$this->canMutateProduct($product)) {
                    throw $this->createAccessDeniedException('You cannot modify another user\'s product.');
                }
                if ($form->get('productName')->getData()) {
                    $product->setName($form->get('productName')->getData());
                }
                if ($form->get('productColor')->getData()) {
                    $product->setColor($form->get('productColor')->getData());
                }
                if ($form->get('productSize')->getData()) {
                    $product->setSize($form->get('productSize')->getData());
                }
                if ($form->get('productPrice')->getData() !== null) {
                    $product->setPrice($this->normalizePrice($form->get('productPrice')->getData()));
                }
                if ($form->get('productDescription')->getData()) {
                    $product->setDescription($form->get('productDescription')->getData());
                }
                $newImagePaths = $this->storeProductImages($form->get('productImages')->getData(), $product);
                if (!empty($newImagePaths)) {
                    $product->setImages($newImagePaths);
                    $product->setImage($newImagePaths[0] ?? null);
                }
            }
            
            $stock->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            $changes = [];
            $addChange = static function (string $label, mixed $old, mixed $new) use (&$changes): void {
                if ($old !== $new) {
                    $changes[$label] = sprintf('%s → %s', $old ?? '—', $new ?? '—');
                }
            };

            $addChange('quantity', $before['quantity'], $stock->getQuantity());
            $addChange('product', $before['productName'], $stock->getProduct()?->getName());
            $addChange('color', $before['color'], $stock->getProduct()?->getColor());
            $addChange('size', $before['size'], $stock->getProduct()?->getSize());
            $addChange('price', $before['price'], $stock->getProduct()?->getPrice());
            $addChange('description', $before['description'], $stock->getProduct()?->getDescription());

            $activityLogger->log(
                'update',
                'stock',
                (string) $stock->getId(),
                sprintf('Updated stock #%d.', $stock->getId()),
                $this->formatTargetData('Stock', $stock->getId(), $stock->getProduct()?->getName()),
                $changes
            );

            $this->addFlash('success', 'Stock entry updated successfully.');
            return $this->redirectToRoute('admin_stocks_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('stocks/edit.html.twig', [
            'stock' => $stock,
            'form' => $form,
            'existingImages' => $stock->getProduct()?->getImages() ?? [],
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Stocks $stock, EntityManagerInterface $entityManager, ActivityLogger $activityLogger): Response
    {
        $this->assertStockOwnership($stock);
        $submittedToken = $request->request->get('_token');
        if ($this->isCsrfTokenValid('delete'.$stock->getId(), $submittedToken)) {
            $entityManager->remove($stock);
            $activityLogger->log(
                'delete',
                'stock',
                (string) $stock->getId(),
                sprintf('Deleted stock #%d.', $stock->getId()),
                $this->formatTargetData('Stock', $stock->getId(), $stock->getProduct()?->getName())
            );
            $this->addFlash('success', 'Stock entry deleted successfully.');
        }

        return $this->redirectToRoute('admin_stocks_index', [], Response::HTTP_SEE_OTHER);
    }

    private function normalizePrice(mixed $raw): string
    {
        if ($raw === null || $raw === '') {
            return '0.00';
        }

        $normalized = preg_replace('/[^\d.]/', '', (string) $raw);
        if ($normalized === '' || $normalized === '.') {
            $normalized = '0';
        }

        return number_format((float) $normalized, 2, '.', '');
    }

    /**
     * @param UploadedFile[] $uploadedFiles
     * @return string[]
     */
    private function storeProductImages(array $uploadedFiles, ?Products $product = null): array
    {
        $validFiles = array_values(array_filter(
            $uploadedFiles,
            static fn ($file) => $file instanceof UploadedFile
        ));

        if (empty($validFiles)) {
            return [];
        }

        $projectDir = $this->getParameter('kernel.project_dir');
        $targetDir = $projectDir . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . self::PRODUCT_IMAGE_DIR;

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }

        if ($product) {
            $this->deleteProductImages($product->getImages());
        }

        $storedPaths = [];
        foreach (array_slice($validFiles, 0, self::PRODUCT_IMAGE_MAX_FILES) as $file) {
            $stored = $this->moveUploadedFile($file, $targetDir);
            if ($stored) {
                $storedPaths[] = $stored;
            }
        }

        return $storedPaths;
    }

    private function moveUploadedFile(UploadedFile $file, string $targetDir): ?string
    {
        try {
            $extension = $file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin';
            $token = bin2hex(random_bytes(6));
        } catch (\Throwable) {
            $extension = $file->getClientOriginalExtension() ?: 'bin';
            $token = uniqid('', true);
        }

        $fileName = sprintf('%s_%s.%s', date('YmdHis'), $token, $extension);

        try {
            $file->move($targetDir, $fileName);
        } catch (\Throwable) {
            return null;
        }

        return self::PRODUCT_IMAGE_DIR . '/' . $fileName;
    }

    private function deleteProductImages(array $paths): void
    {
        if (empty($paths)) {
            return;
        }

        $publicRoot = $this->getParameter('kernel.project_dir') . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR;
        $uploadsRoot = realpath($publicRoot . self::PRODUCT_IMAGE_DIR);

        if ($uploadsRoot === false) {
            return;
        }

        foreach ($paths as $relativePath) {
            if (!is_string($relativePath) || $relativePath === '') {
                continue;
            }

            $fullPath = realpath($publicRoot . $relativePath);
            if ($fullPath && str_starts_with($fullPath, $uploadsRoot)) {
                @unlink($fullPath);
            }
        }
    }

    private function assertStockOwnership(Stocks $stock): void
    {
        $user = $this->currentStaff();
        if ($user && !$this->isGranted('ROLE_ADMIN')) {
            $ownerId = $stock->getOwner()?->getId();
            if ($ownerId === null || $ownerId !== $user->getId()) {
                throw $this->createAccessDeniedException('You can only manage your own stock records.');
            }
        }
    }

    private function canMutateProduct(Products $product): bool
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return true;
        }

        $user = $this->currentStaff();
        if (!$user) {
            return false;
        }

        $owner = $product->getOwner();
        if ($owner === null) {
            return false; // admin/unowned product
        }

        return $owner->getId() === $user->getId();
    }

    private function formatTargetData(string $entityLabel, ?int $id, ?string $name = null): string
    {
        $parts = array_filter([$entityLabel, $name]);
        $label = !empty($parts) ? implode(': ', $parts) : $entityLabel;

        return sprintf('%s (ID: %s)', $label, $id ?? 'N/A');
    }

    private function currentStaff(): ?\App\Entity\Staff
    {
        $user = $this->getUser();

        return $user instanceof \App\Entity\Staff ? $user : null;
    }
}
