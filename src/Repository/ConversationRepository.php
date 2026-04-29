<?php

namespace App\Repository;

use App\Entity\Conversation;
use App\Entity\Listing;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Conversation>
 *
 * @method Conversation|null find($id, $lockMode = null, $lockVersion = null)
 * @method Conversation|null findOneBy(array $criteria, array $orderBy = null)
 * @method Conversation[]    findAll()
 * @method Conversation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ConversationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Conversation::class);
    }

    /**
     * Find conversations for a user that have not been deleted by that user.
     *
     * @return Conversation[]
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('(c.user1 = :user AND c.isDeletedUser1 = :notDeleted)')
            ->orWhere('(c.user2 = :user AND c.isDeletedUser2 = :notDeleted)')
            ->setParameter('user', $user)
            ->setParameter('notDeleted', false)
            ->orderBy('c.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find a conversation between two users, optionally scoped to a listing.
     */
    public function findBetweenUsers(User $user1, User $user2, ?Listing $listing = null): ?Conversation
    {
        $qb = $this->createQueryBuilder('c')
            ->andWhere(
                '(c.user1 = :user1 AND c.user2 = :user2) OR ' .
                '(c.user1 = :user2 AND c.user2 = :user1)'
            )
            ->setParameter('user1', $user1)
            ->setParameter('user2', $user2);

        if ($listing !== null) {
            $qb->andWhere('c.listing = :listing')
                ->setParameter('listing', $listing);
        }

        return $qb
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
