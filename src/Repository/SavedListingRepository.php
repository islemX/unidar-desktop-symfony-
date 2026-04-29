<?php

namespace App\Repository;

use App\Entity\SavedListing;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SavedListing>
 *
 * @method SavedListing|null find($id, $lockMode = null, $lockVersion = null)
 * @method SavedListing|null findOneBy(array $criteria, array $orderBy = null)
 * @method SavedListing[]    findAll()
 * @method SavedListing[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SavedListingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SavedListing::class);
    }
}
