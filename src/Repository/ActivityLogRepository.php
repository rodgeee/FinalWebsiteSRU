<?php

namespace App\Repository;

use App\Entity\ActivityLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ActivityLog>
 */
class ActivityLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivityLog::class);
    }

    /**
     * @return ActivityLog[]
     */
    public function findLatest(
        ?string $action = null,
        ?string $entityType = null,
        ?string $search = null,
        int $limit = 200,
        ?string $actor = null,
        ?\DateTimeImmutable $from = null,
        ?\DateTimeImmutable $to = null
    ): array
    {
        $qb = $this->createQueryBuilder('log')
            ->leftJoin('log.actor', 'actor')
            ->addSelect('actor')
            ->orderBy('log.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($action) {
            $qb->andWhere('log.action = :action')
                ->setParameter('action', $action);
        }

        if ($entityType) {
            $qb->andWhere('log.entityType = :entityType')
                ->setParameter('entityType', $entityType);
        }

        if ($search) {
            $qb->andWhere(
                'log.description LIKE :search OR log.actorName LIKE :search OR log.actorEmail LIKE :search OR log.entityId LIKE :search OR log.actorRole LIKE :search OR log.targetData LIKE :search OR log.actorId LIKE :search'
            )
                ->setParameter('search', sprintf('%%%s%%', $search));
        }

        if ($actor) {
            $qb->andWhere('log.actorEmail LIKE :actor OR log.actorName LIKE :actor OR log.actorId LIKE :actor')
                ->setParameter('actor', sprintf('%%%s%%', $actor));
        }

        if ($from) {
            $qb->andWhere('log.createdAt >= :from')
                ->setParameter('from', $from);
        }

        if ($to) {
            $qb->andWhere('log.createdAt <= :to')
                ->setParameter('to', $to);
        }

        return $qb->getQuery()->getResult();
    }
}


