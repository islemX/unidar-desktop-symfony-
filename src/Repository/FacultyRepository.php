<?php

namespace App\Repository;

use App\Entity\Faculty;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Faculty>
 *
 * @method Faculty|null find($id, $lockMode = null, $lockVersion = null)
 * @method Faculty|null findOneBy(array $criteria, array $orderBy = null)
 * @method Faculty[]    findAll()
 * @method Faculty[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FacultyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Faculty::class);
    }

    /**
     * @return Faculty[]
     */
    public function findAllOrderedByName(): array
    {
        return $this->createQueryBuilder('f')
            ->orderBy('f.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
