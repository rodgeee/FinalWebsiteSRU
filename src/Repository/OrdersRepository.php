<?php

namespace App\Repository;

use App\Entity\Orders;
use App\Entity\Staff;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Orders>
 */
class OrdersRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Orders::class);
    }

    /**
     * @return Orders[]
     */
    public function findForCustomerEmail(string $email, int $limit = 50): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('LOWER(o.Email) = LOWER(:email)')
            ->setParameter('email', trim($email))
            ->orderBy('o.DateCreated', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countWithOrderNumberPrefix(string $prefix): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.orderNumber LIKE :prefix')
            ->setParameter('prefix', $prefix.'%')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return Orders[] Most recent orders, optionally filtered by status
     */
    public function findRecent(int $limit = 5, ?string $status = null, ?Staff $owner = null): array
    {
        $qb = $this->createQueryBuilder('o')
            ->orderBy('o.DateCreated', 'DESC')
            ->setMaxResults($limit);

        if ($status && $status !== 'All') {
            $qb->andWhere('o.OrderStatus = :status')
               ->setParameter('status', $status);
        }

        if ($owner instanceof Staff) {
            $qb->andWhere('o.owner = :owner')
               ->setParameter('owner', $owner);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Quick search utility for dashboard global search.
     *
     * @return Orders[]
     */
    public function searchByTerm(string $term, int $limit = 5, ?Staff $owner = null): array
    {
        $qb = $this->createQueryBuilder('o')
            ->andWhere('o.CustomerName LIKE :term OR o.Email LIKE :term OR o.OrderStatus LIKE :term OR o.TrackingNumber LIKE :term')
            ->setParameter('term', '%'.trim($term).'%')
            ->orderBy('o.DateCreated', 'DESC')
            ->setMaxResults($limit);

        if (is_numeric($term)) {
            $qb->orWhere('o.id = :exactId')
               ->setParameter('exactId', (int) $term);
        }

        if ($owner instanceof Staff) {
            $qb->andWhere('o.owner = :owner')
               ->setParameter('owner', $owner);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Paginated collection of transactions (orders) with optional filters.
     *
     * @return Orders[]
     */
    public function findTransactions(?string $status = 'All', ?string $paymentMethod = 'All', ?string $searchTerm = '', ?int $limit = 50, ?Staff $owner = null): array
    {
        $qb = $this->createQueryBuilder('o')
            ->orderBy('o.DateCreated', 'DESC');

        $this->applyTransactionFilters($qb, $status, $paymentMethod, $searchTerm, $owner);

        if ($limit !== null && $limit > 0) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Aggregate insights for transactions with the same filters.
     */
    public function summarizeTransactions(?string $status = 'All', ?string $paymentMethod = 'All', ?string $searchTerm = '', ?Staff $owner = null): array
    {
        $summaryQb = $this->createQueryBuilder('o')
            ->select('COUNT(o.id) AS totalCount', 'COALESCE(SUM(o.TotalPrice), 0) AS totalVolume', 'COALESCE(AVG(o.TotalPrice), 0) AS averageOrderValue');

        $this->applyTransactionFilters($summaryQb, $status, $paymentMethod, $searchTerm, $owner);
        $totals = $summaryQb->getQuery()->getSingleResult();

        $statusQb = $this->createQueryBuilder('o')
            ->select('o.OrderStatus AS label', 'COUNT(o.id) AS count')
            ->groupBy('o.OrderStatus');
        $this->applyTransactionFilters($statusQb, $status, $paymentMethod, $searchTerm, $owner);
        $statusBreakdown = array_map(static fn (array $row) => [
            'label' => $row['label'] ?? 'Unknown',
            'count' => (int) $row['count'],
        ], $statusQb->getQuery()->getResult());

        $paymentQb = $this->createQueryBuilder('o')
            ->select('o.PaymentMethod AS label', 'COUNT(o.id) AS count', 'COALESCE(SUM(o.TotalPrice), 0) AS volume')
            ->groupBy('o.PaymentMethod');
        $this->applyTransactionFilters($paymentQb, $status, $paymentMethod, $searchTerm, $owner);
        $paymentBreakdown = array_map(static fn (array $row) => [
            'label' => $row['label'] ?? 'Unknown',
            'count' => (int) $row['count'],
            'volume' => (float) $row['volume'],
        ], $paymentQb->getQuery()->getResult());

        return [
            'totals' => [
                'count' => (int) ($totals['totalCount'] ?? 0),
                'volume' => (float) ($totals['totalVolume'] ?? 0),
                'average' => (float) ($totals['averageOrderValue'] ?? 0),
            ],
            'status' => $statusBreakdown,
            'payment' => $paymentBreakdown,
        ];
    }

    /**
     * Apply reusable filters for transaction queries.
     */
    private function applyTransactionFilters(QueryBuilder $qb, ?string $status, ?string $paymentMethod, ?string $searchTerm, ?Staff $owner = null): void
    {
        if ($status && $status !== 'All') {
            $qb->andWhere('o.OrderStatus = :txStatus')
               ->setParameter('txStatus', $status);
        }

        if ($paymentMethod && $paymentMethod !== 'All') {
            $qb->andWhere('o.PaymentMethod = :txPayment')
               ->setParameter('txPayment', $paymentMethod);
        }

        $searchTerm = trim((string) $searchTerm);
        if ($searchTerm !== '') {
            $expr = $qb->expr();
            $searchExpr = $expr->orX(
                $expr->like('o.CustomerName', ':txTerm'),
                $expr->like('o.Email', ':txTerm'),
                $expr->like('o.PaymentMethod', ':txTerm'),
                $expr->like('o.TrackingNumber', ':txTerm')
            );

            if (is_numeric($searchTerm)) {
                $searchExpr->add($expr->eq('o.id', ':txId'));
                $qb->setParameter('txId', (int) $searchTerm);
            }

            $qb->andWhere($searchExpr)
               ->setParameter('txTerm', '%'.$searchTerm.'%');
        }

        if ($owner instanceof Staff) {
            $qb->andWhere('o.owner = :txOwner')
               ->setParameter('txOwner', $owner);
        }
    }
}


