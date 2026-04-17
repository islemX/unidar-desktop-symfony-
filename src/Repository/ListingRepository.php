<?php

namespace App\Repository;

use App\Entity\Listing;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Listing>
 *
 * @method Listing|null find($id, $lockMode = null, $lockVersion = null)
 * @method Listing|null findOneBy(array $criteria, array $orderBy = null)
 * @method Listing[]    findAll()
 * @method Listing[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ListingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Listing::class);
    }

    /**
     * @return Listing[]
     */
    public function findActiveListings(): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.status = :active')
            ->setParameter('active', 'active')
            ->orderBy('l.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find listings by various filters including geolocation with Haversine formula.
     *
     * @param array $filters Supported keys: min_price, max_price, bedrooms, property_type, gender_preference, lat, lng, max_distance (km)
     * @return Listing[]
     */
    public function findByFilters(array $filters): array
    {
        $qb = $this->createQueryBuilder('l')
            ->andWhere('l.status = :active')
            ->setParameter('active', 'active');

        if (isset($filters['min_price'])) {
            $qb->andWhere('l.price >= :minPrice')
                ->setParameter('minPrice', $filters['min_price']);
        }

        if (isset($filters['max_price'])) {
            $qb->andWhere('l.price <= :maxPrice')
                ->setParameter('maxPrice', $filters['max_price']);
        }

        if (isset($filters['bedrooms'])) {
            $qb->andWhere('l.bedrooms = :bedrooms')
                ->setParameter('bedrooms', $filters['bedrooms']);
        }

        if (isset($filters['property_type'])) {
            $qb->andWhere('l.propertyType = :propertyType')
                ->setParameter('propertyType', $filters['property_type']);
        }

        if (isset($filters['gender_preference'])) {
            $qb->andWhere('l.genderPreference = :genderPreference')
                ->setParameter('genderPreference', $filters['gender_preference']);
        }

        // Geolocation filtering using Haversine formula
        if (isset($filters['lat'], $filters['lng'], $filters['max_distance'])) {
            $lat = (float) $filters['lat'];
            $lng = (float) $filters['lng'];
            $maxDistance = (float) $filters['max_distance'];

            $haversine = '(6371 * ACOS(
                COS(RADIANS(:lat)) * COS(RADIANS(l.latitude)) *
                COS(RADIANS(l.longitude) - RADIANS(:lng)) +
                SIN(RADIANS(:lat)) * SIN(RADIANS(l.latitude))
            ))';

            $qb->andWhere($haversine . ' <= :maxDistance')
                ->setParameter('lat', $lat)
                ->setParameter('lng', $lng)
                ->setParameter('maxDistance', $maxDistance);
        }

        return $qb
            ->orderBy('l.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Listing[]
     */
    public function findByOwner(User $owner): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('l.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countActive(): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.status = :active')
            ->setParameter('active', 'active')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
