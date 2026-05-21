<?php

namespace App\Repository;

use App\Entity\Customer;
use App\Entity\Services;
use App\Entity\Staff;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Services>
 */
class ServicesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Services::class);
    }

    /**
     * Latest service jobs in descending order of updates.
     *
     * @return Services[]
     */
    public function findTransactionFeed(?int $limit = 50, ?Staff $owner = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->orderBy('s.updated_at', 'DESC');

        if ($owner instanceof Staff) {
            $qb->andWhere('s.owner = :owner')
               ->setParameter('owner', $owner);
        }

        if ($limit !== null && $limit > 0) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Summary insights for service jobs grouped by status.
     */
    public function summarizeTransactions(?Staff $owner = null): array
    {
        $criteria = [];
        if ($owner instanceof Staff) {
            $criteria['owner'] = $owner;
        }

        $total = $this->count($criteria);

        $statusRows = $this->createQueryBuilder('s')
            ->select('s.Status AS label', 'COUNT(s.id) AS count')
            ->groupBy('s.Status');

        if ($owner instanceof Staff) {
            $statusRows->andWhere('s.owner = :owner')
                       ->setParameter('owner', $owner);
        }

        $statusRows = $statusRows
            ->orderBy('label', 'ASC')
            ->getQuery()
            ->getResult();

        $statusBreakdown = array_map(static fn (array $row) => [
            'label' => $row['label'] ?? 'Unspecified',
            'count' => (int) $row['count'],
        ], $statusRows);

        return [
            'total' => $total,
            'status' => $statusBreakdown,
        ];
    }

    /**
     * @return Services[]
     */
    public function findForCustomer(Customer $customer): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.customer = :customer')
            ->setParameter('customer', $customer)
            ->orderBy('s.updated_at', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneForCustomer(Customer $customer, int $id): ?Services
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.customer = :customer')
            ->andWhere('s.id = :id')
            ->setParameter('customer', $customer)
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
