<?php

namespace App\Repository;

use App\Entity\Customer;
use App\Entity\CustomerAddress;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CustomerAddress>
 */
class CustomerAddressRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CustomerAddress::class);
    }

    /**
     * @return list<CustomerAddress>
     */
    public function findForCustomer(Customer $customer): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.customer = :customer')
            ->setParameter('customer', $customer)
            ->orderBy('a.isDefault', 'DESC')
            ->addOrderBy('a.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneForCustomer(Customer $customer, int $id): ?CustomerAddress
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.customer = :customer')
            ->andWhere('a.id = :id')
            ->setParameter('customer', $customer)
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
