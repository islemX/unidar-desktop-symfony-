<?php

namespace App\Repository;

use App\Entity\ContractTemplate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ContractTemplate>
 *
 * @method ContractTemplate|null find($id, $lockMode = null, $lockVersion = null)
 * @method ContractTemplate|null findOneBy(array $criteria, array $orderBy = null)
 * @method ContractTemplate[]    findAll()
 * @method ContractTemplate[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ContractTemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContractTemplate::class);
    }

    /**
     * @return ContractTemplate[]
     */
    public function findActiveByType(string $type): array
    {
        return $this->createQueryBuilder('ct')
            ->andWhere('ct.type = :type')
            ->andWhere('ct.isActive = :active')
            ->setParameter('type', $type)
            ->setParameter('active', true)
            ->orderBy('ct.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
