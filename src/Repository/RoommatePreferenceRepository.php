<?php

namespace App\Repository;

use App\Entity\RoommatePreference;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RoommatePreference>
 *
 * @method RoommatePreference|null find($id, $lockMode = null, $lockVersion = null)
 * @method RoommatePreference|null findOneBy(array $criteria, array $orderBy = null)
 * @method RoommatePreference[]    findAll()
 * @method RoommatePreference[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RoommatePreferenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RoommatePreference::class);
    }

    /**
     * Find all roommate preferences except for the given user.
     *
     * @return RoommatePreference[]
     */
    public function findAllExceptUser(User $user): array
    {
        return $this->createQueryBuilder('rp')
            ->andWhere('rp.user != :user')
            ->setParameter('user', $user)
            ->orderBy('rp.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
