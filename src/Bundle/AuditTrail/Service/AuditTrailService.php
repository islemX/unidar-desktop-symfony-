<?php

namespace App\Bundle\AuditTrail\Service;

use App\Bundle\AuditTrail\Entity\AuditLog;
use Doctrine\ORM\EntityManagerInterface;

/**
 * AuditTrailService
 * Records entity changes and provides query helpers.
 */
class AuditTrailService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    public function log(
        string $entityClass,
        int    $entityId,
        string $action,
        ?array $changedFields = null,
        ?string $userEmail    = null,
        ?string $ipAddress    = null,
    ): void {
        $log = (new AuditLog())
            ->setEntityClass($entityClass)
            ->setEntityId($entityId)
            ->setAction($action)
            ->setChangedFields($changedFields)
            ->setUserEmail($userEmail)
            ->setIpAddress($ipAddress);

        $this->em->persist($log);
        // flush is handled by the subscriber after the unit of work is complete
    }

    /**
     * Get full change history for a specific entity record.
     * @return AuditLog[]
     */
    public function getHistory(string $entityClass, int $entityId, int $limit = 50): array
    {
        return $this->em->getRepository(AuditLog::class)
            ->createQueryBuilder('a')
            ->where('a.entityClass = :cls AND a.entityId = :id')
            ->setParameter('cls', $entityClass)
            ->setParameter('id', $entityId)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get recent audit logs across all entities.
     * @return AuditLog[]
     */
    public function getRecentLogs(int $limit = 100): array
    {
        return $this->em->getRepository(AuditLog::class)
            ->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
