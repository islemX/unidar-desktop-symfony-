<?php

namespace App\Repository;

use App\Entity\Payment;
use App\Enum\PaymentStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Payment>
 *
 * @method Payment|null find($id, $lockMode = null, $lockVersion = null)
 * @method Payment|null findOneBy(array $criteria, array $orderBy = null)
 * @method Payment[]    findAll()
 * @method Payment[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    /**
     * @return Payment[]
     */
    public function findByContractId(int $contractId): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.contract = :contractId')
            ->setParameter('contractId', $contractId)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find payments by filters.
     *
     * @param array $filters Supported keys: period (week, month, year), status (PaymentStatus value)
     * @return Payment[]
     */
    public function findByFilters(array $filters): array
    {
        $qb = $this->createQueryBuilder('p');

        if (isset($filters['period'])) {
            $date = match ($filters['period']) {
                'week' => new \DateTimeImmutable('-1 week'),
                'month' => new \DateTimeImmutable('-1 month'),
                'year' => new \DateTimeImmutable('-1 year'),
                default => null,
            };

            if ($date !== null) {
                $qb->andWhere('p.createdAt >= :since')
                    ->setParameter('since', $date);
            }
        }

        if (isset($filters['status'])) {
            $qb->andWhere('p.status = :status')
                ->setParameter('status', $filters['status']);
        }

        return $qb
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get payment statistics for a given period.
     *
     * @return array{total_count: int, total_amount: float, completed_count: int, completed_amount: float, pending_count: int, failed_count: int}
     */
    public function getStatistics(?string $period = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->select(
                'COUNT(p.id) AS total_count',
                'COALESCE(SUM(p.amount), 0) AS total_amount',
                'SUM(CASE WHEN p.status = :completed THEN 1 ELSE 0 END) AS completed_count',
                'SUM(CASE WHEN p.status = :completed THEN p.amount ELSE 0 END) AS completed_amount',
                'SUM(CASE WHEN p.status = :pending THEN 1 ELSE 0 END) AS pending_count',
                'SUM(CASE WHEN p.status = :failed THEN 1 ELSE 0 END) AS failed_count'
            )
            ->setParameter('completed', PaymentStatus::Completed)
            ->setParameter('pending', PaymentStatus::Pending)
            ->setParameter('failed', PaymentStatus::Failed);

        if ($period !== null) {
            $date = match ($period) {
                'week' => new \DateTimeImmutable('-1 week'),
                'month' => new \DateTimeImmutable('-1 month'),
                'year' => new \DateTimeImmutable('-1 year'),
                default => null,
            };

            if ($date !== null) {
                $qb->andWhere('p.createdAt >= :since')
                    ->setParameter('since', $date);
            }
        }

        $result = $qb->getQuery()->getSingleResult();

        return [
            'total_count' => (int) $result['total_count'],
            'total_amount' => (float) $result['total_amount'],
            'completed_count' => (int) $result['completed_count'],
            'completed_amount' => (float) $result['completed_amount'],
            'pending_count' => (int) $result['pending_count'],
            'failed_count' => (int) $result['failed_count'],
        ];
    }

    /**
     * Get total revenue from completed payments.
     */
    public function getTotalRevenue(): float
    {
        return (float) $this->createQueryBuilder('p')
            ->select('COALESCE(SUM(p.amount), 0)')
            ->andWhere('p.status = :status')
            ->setParameter('status', PaymentStatus::Completed)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
