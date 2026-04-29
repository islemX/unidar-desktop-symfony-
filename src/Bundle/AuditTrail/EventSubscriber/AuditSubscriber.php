<?php

namespace App\Bundle\AuditTrail\EventSubscriber;

use App\Bundle\AuditTrail\Entity\AuditLog;
use App\Bundle\AuditTrail\Service\AuditTrailService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * AuditSubscriber
 * Hooks into Doctrine lifecycle events to auto-record entity changes.
 * Skips AuditLog itself to prevent infinite loops.
 */
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::preRemove)]
class AuditSubscriber
{
    /**
     * Entity classes to audit (short names).
     * Add or remove as needed.
     */
    private const AUDITED = [
        'Listing', 'Contract', 'User', 'Payment', 'Verification',
        'Report', 'Subscription', 'RoommatePreference',
    ];

    public function __construct(
        private readonly AuditTrailService $auditTrail,
        private readonly RequestStack      $requestStack,
        private readonly Security          $security,
    ) {}

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$this->shouldAudit($entity)) return;

        $this->record($entity, 'create', null, $args->getObjectManager());
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$this->shouldAudit($entity)) return;

        $uow     = $args->getObjectManager()->getUnitOfWork();
        $changes = $uow->getEntityChangeSet($entity);
        $diff    = [];

        foreach ($changes as $field => [$old, $new]) {
            // Skip object/collection fields — log only scalars
            if (is_scalar($old) || is_null($old)) {
                $diff[$field] = ['old' => $old, 'new' => $new];
            }
        }

        if (empty($diff)) return;

        $this->record($entity, 'update', $diff, $args->getObjectManager());
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$this->shouldAudit($entity)) return;

        $this->record($entity, 'delete', null, $args->getObjectManager());
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function shouldAudit(object $entity): bool
    {
        if ($entity instanceof AuditLog) return false;
        $short = (new \ReflectionClass($entity))->getShortName();
        return in_array($short, self::AUDITED, true);
    }

    private function getEntityId(object $entity): int
    {
        if (method_exists($entity, 'getId')) {
            return (int) $entity->getId();
        }
        return 0;
    }

    private function record(object $entity, string $action, ?array $changed, $em): void
    {
        $request = $this->requestStack->getCurrentRequest();
        $user    = $this->security->getUser();

        $this->auditTrail->log(
            entityClass:   (new \ReflectionClass($entity))->getShortName(),
            entityId:      $this->getEntityId($entity),
            action:        $action,
            changedFields: $changed,
            userEmail:     $user?->getUserIdentifier(),
            ipAddress:     $request?->getClientIp(),
        );

        // Flush the audit log immediately in a separate unit of work to avoid
        // interfering with the main entity's flush cycle.
        try {
            $em->flush();
        } catch (\Throwable) {
            // Non-blocking — audit failures must never break app flow.
        }
    }
}
