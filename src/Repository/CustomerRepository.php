<?php

namespace App\Repository;

use App\Entity\Customer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Customer>
 */
class CustomerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Customer::class);
    }

    /**
     * Match Google / stored email regardless of DB collation (e.g. mixed case in DB vs lowercase from Google).
     */
    public function findOneByEmailCanonical(string $email): ?Customer
    {
        $normalized = mb_strtolower(trim($email));

        return $this->createQueryBuilder('c')
            ->where('LOWER(c.email) = :email')
            ->setParameter('email', $normalized)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}

