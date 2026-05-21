<?php

namespace App\Controller;

use App\Entity\Products;
use App\Repository\ProductsRepository;
use Doctrine\DBAL\Exception\ConnectionException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class PublicPagesController extends AbstractController
{
    /** @var list<string> */
    private const BRAND_NAMES = [
        'ADIDAS',
        'ASICS',
        'SENORITOS',
        'MIZUNO',
        'NEW BALANCE',
        'NIKE',
        'ON',
        'PUMA',
    ];

    /** @var array<string, string> brand name => image filename under assets/img */
    private const BRAND_IMAGES = [
        'ADIDAS' => 'adidas displaypng.png',
        'ASICS' => 'asics display.png',
        'SENORITOS' => 'senoritos.png',
        'MIZUNO' => 'Mizuno display.png',
        'NEW BALANCE' => 'NEW BALANCE.png',
        'NIKE' => 'nike display.png',
        'ON' => 'ON display.png',
        'PUMA' => 'puma display.png',
    ];

    private const DEFAULT_BRAND_IMAGE = 'Adi zero white.png';

    /** Max products shown on the public releases page (newest by id). */
    private const RECENT_RELEASES_LIMIT = 50;

    #[Route('/brands', name: 'public_brands', methods: ['GET'])]
    #[IsGranted('ROLE_CUSTOMER')]
    public function brands(): Response
    {
        $brands = self::BRAND_NAMES;
        natcasesort($brands);
        /** @var list<string> $sorted */
        $sorted = array_values($brands);

        $availableLetters = [];
        foreach ($sorted as $name) {
            $ch = mb_substr($name, 0, 1);
            $letter = mb_strtoupper($ch);
            if (1 === preg_match('/^[A-Z]$/', $letter)) {
                $availableLetters[$letter] = true;
            }
        }
        $letterList = array_keys($availableLetters);
        sort($letterList);

        $prevLetter = null;
        $rows = [];
        foreach ($sorted as $name) {
            $ch = mb_substr($name, 0, 1);
            $letter = mb_strtoupper($ch);
            if (1 !== preg_match('/^[A-Z]$/', $letter)) {
                $letter = '#';
            }
            $anchorId = null;
            if ($letter !== $prevLetter) {
                $anchorId = 'alpha-' . $letter;
                $prevLetter = $letter;
            }
            $rows[] = [
                'name' => $name,
                'letter' => $letter,
                'anchorId' => $anchorId,
                'image' => self::BRAND_IMAGES[$name] ?? self::DEFAULT_BRAND_IMAGE,
            ];
        }

        $defaultImage = $rows[0]['image'] ?? self::DEFAULT_BRAND_IMAGE;

        return $this->render('public/brands.html.twig', [
            'brandRows' => $rows,
            'availableLetters' => $letterList,
            'alphabet' => range('A', 'Z'),
            'defaultBrandImage' => $defaultImage,
        ]);
    }

    #[Route('/products/{id}', name: 'public_product_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted('ROLE_CUSTOMER')]
    public function productShow(int $id, ProductsRepository $productsRepository): Response
    {
        $product = $productsRepository->find($id);
        if (!$product instanceof Products) {
            throw $this->createNotFoundException('Product not found.');
        }

        $name = trim((string) $product->getName());
        $brandLabel = $this->resolveBrandLabel($name);

        try {
            $sameShopProducts = $productsRepository->findSameShopProducts($product, 8);
            $suggestedProducts = $productsRepository->findProductSuggestions($brandLabel, (int) $product->getId(), 8);
        } catch (ConnectionException) {
            $sameShopProducts = [];
            $suggestedProducts = [];
        }

        $gallery = $product->getImages();
        if ($gallery === []) {
            $primary = $product->getPrimaryImage();
            if ($primary !== null && $primary !== '') {
                $gallery = [$primary];
            }
        }
        // Atmos-style gallery: vertical thumb strip (pad to 4 when fewer images).
        if ($gallery !== []) {
            $first = $gallery[0];
            while (\count($gallery) < 4) {
                $gallery[] = $first;
            }
        }

        return $this->render('public/product.html.twig', [
            'product' => $product,
            'brandLabel' => $brandLabel,
            'displayName' => mb_strtoupper($name),
            'sku' => sprintf('SRU-%05d', $product->getId()),
            'sizes' => $this->parseProductSizes($product),
            'gallery' => $gallery,
            'colorVariants' => $this->buildColorVariants($product, $brandLabel, $suggestedProducts),
            'sameShopProducts' => $sameShopProducts,
            'suggestedProducts' => $suggestedProducts,
        ]);
    }

    /**
     * @param Products[] $candidates
     *
     * @return list<array{product: Products, color: string, image: string|null, isCurrent: bool}>
     */
    private function buildColorVariants(Products $product, ?string $brandLabel, array $candidates): array
    {
        $variants = [[
            'product' => $product,
            'color' => trim((string) $product->getColor()),
            'image' => $product->getPrimaryImage(),
            'isCurrent' => true,
        ]];

        $seenColors = [mb_strtolower((string) $product->getColor())];

        foreach ($candidates as $candidate) {
            if ($candidate->getId() === $product->getId()) {
                continue;
            }

            $color = trim((string) $candidate->getColor());
            $colorKey = mb_strtolower($color);
            if ($color === '' || \in_array($colorKey, $seenColors, true)) {
                continue;
            }

            $seenColors[] = $colorKey;
            $variants[] = [
                'product' => $candidate,
                'color' => $color,
                'image' => $candidate->getPrimaryImage(),
                'isCurrent' => false,
            ];

            if (\count($variants) >= 4) {
                break;
            }
        }

        return $variants;
    }

    #[Route('/releases', name: 'public_releases', methods: ['GET'])]
    #[IsGranted('ROLE_CUSTOMER')]
    public function releases(ProductsRepository $productsRepository): Response
    {
        try {
            $products = $productsRepository->findRecentlyAdded(self::RECENT_RELEASES_LIMIT);
        } catch (ConnectionException) {
            $products = [];
        } catch (\Exception) {
            $products = [];
        }

        $filterBrands = [];
        $productRows = [];
        foreach ($products as $product) {
            $name = trim((string) $product->getName());
            $brandLabel = $this->resolveBrandLabel($name);
            if ($brandLabel !== null && $brandLabel !== '') {
                $filterBrands[$brandLabel] = true;
            }
            $productRows[] = [
                'product' => $product,
                'brandLabel' => $brandLabel,
                'displayName' => mb_strtoupper($name),
                'filterBrand' => $brandLabel ?? '',
            ];
        }

        $brandFilters = array_keys($filterBrands);
        natcasesort($brandFilters);

        return $this->render('public/releases.html.twig', [
            'productRows' => $productRows,
            'brandFilters' => array_values($brandFilters),
        ]);
    }

    private function resolveBrandLabel(string $productName): ?string
    {
        $normalized = mb_strtoupper(trim($productName));
        if ($normalized === '') {
            return null;
        }

        $brands = self::BRAND_NAMES;
        usort($brands, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        foreach ($brands as $brand) {
            if (str_starts_with($normalized, $brand)) {
                return $brand;
            }
        }

        $parts = preg_split('/\s+/', $normalized, 2);
        $first = is_array($parts) ? ($parts[0] ?? '') : '';

        return $first !== '' ? $first : null;
    }

    /**
     * @return list<string> Numeric US sizes for the size picker (e.g. 8, 9, 10, 11).
     */
    private function parseProductSizes(Products $product): array
    {
        $raw = trim((string) $product->getSize());
        if ($raw === '') {
            return ['8', '9', '10', '11'];
        }

        $normalized = str_replace(['–', '—'], '-', $raw);
        $normalized = preg_replace('/\bUS\s*/i', '', $normalized) ?? $normalized;
        $normalized = trim($normalized);

        if (str_contains($normalized, ',')) {
            $parts = array_map('trim', explode(',', $normalized));
            $sizes = [];
            foreach ($parts as $part) {
                $sizes = array_merge($sizes, $this->expandSizeToken($part));
            }

            return $this->uniqueSizes($sizes);
        }

        return $this->uniqueSizes($this->expandSizeToken($normalized));
    }

    /**
     * @return list<string>
     */
    private function expandSizeToken(string $token): array
    {
        $token = trim($token);
        if ($token === '') {
            return [];
        }

        if (preg_match('/^(\d+(?:\.\d+)?)\s*-\s*(\d+(?:\.\d+)?)$/', $token, $matches)) {
            return $this->expandNumericSizeRange((float) $matches[1], (float) $matches[2]);
        }

        if (preg_match_all('/\d+(?:\.\d+)?/', $token, $found) > 0 && \count($found[0]) >= 2) {
            $nums = array_map(static fn (string $n): float => (float) $n, $found[0]);

            return $this->expandNumericSizeRange(min($nums), max($nums));
        }

        if (preg_match('/^(\d+(?:\.\d+)?)$/', $token, $single)) {
            return [(string) (int) (float) $single[1]];
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private function expandNumericSizeRange(float $start, float $end): array
    {
        if ($end < $start) {
            [$start, $end] = [$end, $start];
        }

        $from = (int) floor($start);
        $to = (int) ceil($end);
        if ($to - $from > 16) {
            return [(string) $from];
        }

        $sizes = [];
        for ($i = $from; $i <= $to; ++$i) {
            $sizes[] = (string) $i;
        }

        return $sizes;
    }

    /**
     * @param list<string> $sizes
     *
     * @return list<string>
     */
    private function uniqueSizes(array $sizes): array
    {
        $sizes = array_values(array_filter($sizes, static fn (string $s): bool => $s !== ''));
        $sizes = array_values(array_unique($sizes));
        usort($sizes, static fn (string $a, string $b): int => (float) $a <=> (float) $b);

        return $sizes !== [] ? $sizes : ['8', '9', '10', '11'];
    }

    #[Route('/about', name: 'public_about', methods: ['GET'])]
    public function about(): Response
    {
        return $this->render('public/about.html.twig');
    }

    #[Route('/contact', name: 'public_contact', methods: ['GET'])]
    public function contact(): Response
    {
        return $this->render('public/contact.html.twig');
    }

    #[Route('/services', name: 'public_services', methods: ['GET'])]
    public function services(): Response
    {
        $configPath = $this->getParameter('kernel.project_dir') . '/config/public_services.php';
        /** @var array<string, mixed> $servicesConfig */
        $servicesConfig = is_file($configPath) ? (require $configPath) : [];

        return $this->render('public/services.html.twig', [
            'servicesHero' => isset($servicesConfig['hero']) && is_array($servicesConfig['hero'])
                ? $servicesConfig['hero']
                : [],
            'servicesStats' => isset($servicesConfig['stats']) && is_array($servicesConfig['stats'])
                ? $servicesConfig['stats']
                : [],
            'servicePackages' => isset($servicesConfig['packages']) && is_array($servicesConfig['packages'])
                ? $servicesConfig['packages']
                : [],
            'serviceProcess' => isset($servicesConfig['process']) && is_array($servicesConfig['process'])
                ? $servicesConfig['process']
                : [],
            'serviceCareNotes' => isset($servicesConfig['care_notes']) && is_array($servicesConfig['care_notes'])
                ? $servicesConfig['care_notes']
                : [],
            'serviceFaq' => isset($servicesConfig['faq']) && is_array($servicesConfig['faq'])
                ? $servicesConfig['faq']
                : [],
        ]);
    }

    #[Route('/reviews', name: 'public_reviews', methods: ['GET'])]
    public function reviews(): Response
    {
        $configPath = $this->getParameter('kernel.project_dir') . '/config/public_reviews.php';
        /** @var array<string, mixed> $reviewsConfig */
        $reviewsConfig = is_file($configPath) ? (require $configPath) : [];

        /** @var array<int, array<string, mixed>> $items */
        $items = isset($reviewsConfig['items']) && is_array($reviewsConfig['items'])
            ? $reviewsConfig['items']
            : [];

        return $this->render('public/reviews.html.twig', [
            'reviewsTitle' => (string) ($reviewsConfig['title'] ?? 'Customer Reviews'),
            'reviewsItems' => $items,
            'reviewsViewMoreLabel' => (string) ($reviewsConfig['view_more_label'] ?? 'View More'),
        ]);
    }
}

