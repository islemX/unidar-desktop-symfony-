<?php

namespace App\Repository;

use App\Entity\ListingImage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ListingImage>
 *
 * @method ListingImage|null find($id, $lockMode = null, $lockVersion = null)
 * @method ListingImage|null findOneBy(array $criteria, array $orderBy = null)
 * @method ListingImage[]    findAll()
 * @method ListingImage[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ListingImageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ListingImage::class);
    }
}
