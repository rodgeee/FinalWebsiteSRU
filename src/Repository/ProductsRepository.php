<?php

namespace App\Repository;

use App\Entity\Products;
use App\Entity\Staff;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Products>
 */
class ProductsRepository extends ServiceEntityRepository
{
    /**
     * Shop catalog brands (same list as storefront / mobile). When search exactly matches
     * one of these, filter by product name prefix instead of substring — otherwise short
     * tokens like "ON" match unrelated names (e.g. CONVERSE, CONDITION).
     *
     * @var list<string>
     */
    private const KNOWN_SHOP_BRAND_LABELS = [
        'ADIDAS',
        'ASICS',
        'SENORITOS',
        'MIZUNO',
        'NEW BALANCE',
        'NIKE',
        'ON',
        'PUMA',
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Products::class);
    }

    /**
     * Fetch products filtered by a search term and sorted by a given field/direction.
     * Allowed sort fields are id, name, color, size, stocks, price.
     *
     * @param string|null $search
     * @param string|null $sortField
     * @param string|null $sortDir
     * @param Staff|null $owner
     * @return Products[]
     */
    public function findForIndex(?string $search, ?string $sortField, ?string $sortDir, ?Staff $owner = null): array
    {
        $qb = $this->createQueryBuilder('p');

        if ($search !== null && $search !== '') {
            $trimmed = trim($search);
            $brandKey = strtoupper($trimmed);
            if (in_array($brandKey, self::KNOWN_SHOP_BRAND_LABELS, true)) {
                if ($brandKey === 'ON') {
                    $qb->andWhere(
                        'LOWER(p.Name) LIKE :onSpace OR LOWER(p.Name) LIKE :onHyphen OR LOWER(p.Name) = :onExact',
                    )
                        ->setParameter('onSpace', 'on %')
                        ->setParameter('onHyphen', 'on-%')
                        ->setParameter('onExact', 'on');
                } else {
                    $qb->andWhere('LOWER(p.Name) LIKE :brandNamePrefix')
                        ->setParameter('brandNamePrefix', strtolower($brandKey) . '%');
                }
            } else {
                $qb->andWhere('p.Name LIKE :q OR p.Color LIKE :q OR p.Size LIKE :q OR p.Description LIKE :q')
                    ->setParameter('q', '%' . $trimmed . '%');
            }
        }

        if ($owner instanceof Staff) {
            $qb->andWhere('p.owner = :owner')
               ->setParameter('owner', $owner);
        }

        $allowed = ['id' => 'p.id', 'name' => 'p.Name', 'color' => 'p.Color', 'size' => 'p.Size', 'price' => 'p.Price'];
        $field = strtolower($sortField ?? 'id');
        $dir = strtoupper($sortDir ?? 'ASC');
        if (!in_array($dir, ['ASC', 'DESC'], true)) {
            $dir = 'ASC';
        }
        $orderBy = $allowed[$field] ?? 'p.id';
        $qb->orderBy($orderBy, $dir);

        return $qb->getQuery()->getResult();
    }

    /**
     * Quick search for dashboard global search.
     *
     * @return Products[]
     */
    public function searchByTerm(string $term, int $limit = 5, ?Staff $owner = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.Name LIKE :term OR p.Color LIKE :term OR p.Size LIKE :term OR p.Description LIKE :term')
            ->setParameter('term', '%'.trim($term).'%')
            ->orderBy('p.Name', 'ASC')
            ->setMaxResults($limit);

        if ($owner instanceof Staff) {
            $qb->andWhere('p.owner = :owner')
               ->setParameter('owner', $owner);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Products ordered newest-first (highest id = most recently added).
     *
     * @return Products[]
     */
    public function findRecentlyAdded(int $limit = 50): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.id', 'DESC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();
    }

    /**
     * Other products from the same shop (owner), excluding the current product.
     *
     * @return Products[]
     */
    public function findSameShopProducts(Products $product, int $limit = 8): array
    {
        $excludeId = $product->getId();
        if ($excludeId === null) {
            return [];
        }

        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.id != :excludeId')
            ->setParameter('excludeId', $excludeId)
            ->orderBy('p.id', 'DESC')
            ->setMaxResults(max(1, $limit));

        $owner = $product->getOwner();
        if ($owner !== null) {
            $qb->andWhere('p.owner = :owner')
                ->setParameter('owner', $owner);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Same style (name + color), all sizes — for storefront size picker.
     *
     * @return Products[]
     */
    public function findSizeVariants(Products $product): array
    {
        $name = $product->getName();
        $color = $product->getColor();
        if ($name === null || $name === '' || $color === null || $color === '') {
            return [$product];
        }

        return $this->createQueryBuilder('p')
            ->andWhere('p.Name = :name')
            ->andWhere('p.Color = :color')
            ->setParameter('name', $name)
            ->setParameter('color', $color)
            ->orderBy('p.Size', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Curated picks: same brand prefix in the product name when possible, otherwise newest items.
     *
     * @return Products[]
     */
    public function findProductSuggestions(?string $brandLabel, int $excludeProductId, int $limit = 8): array
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.id != :excludeId')
            ->setParameter('excludeId', $excludeProductId)
            ->orderBy('p.id', 'DESC')
            ->setMaxResults(max(1, $limit));

        if ($brandLabel !== null && $brandLabel !== '') {
            $qb->andWhere('p.Name LIKE :brandPrefix')
                ->setParameter('brandPrefix', $brandLabel . '%');
        }

        return $qb->getQuery()->getResult();
    }

    //    /**
    //     * @return Products[] Returns an array of Products objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('p.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Products
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
