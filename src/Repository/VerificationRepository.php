<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\Verification;
use App\Enum\VerificationStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Verification>
 *
 * @method Verification|null find($id, $lockMode = null, $lockVersion = null)
 * @method Verification|null findOneBy(array $criteria, array $orderBy = null)
 * @method Verification[]    findAll()
 * @method Verification[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class VerificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Verification::class);
    }

    /**
     * @return Verification[]
     */
    public function findPending(): array
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.status = :status')
            ->setParameter('status', VerificationStatus::Pending)
            ->orderBy('v.submittedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Verification[]
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.user = :user')
            ->setParameter('user', $user)
            ->orderBy('v.submittedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
