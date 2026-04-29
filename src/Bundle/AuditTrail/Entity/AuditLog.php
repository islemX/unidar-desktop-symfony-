<?php

namespace App\Bundle\AuditTrail\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'audit_logs')]
#[ORM\Index(columns: ['entity_class', 'entity_id'], name: 'idx_audit_entity')]
#[ORM\Index(columns: ['created_at'], name: 'idx_audit_date')]
class AuditLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Short class name, e.g. "Listing" */
    #[ORM\Column(length: 128)]
    private string $entityClass = '';

    #[ORM\Column]
    private int $entityId = 0;

    /** create | update | delete */
    #[ORM\Column(length: 16)]
    private string $action = '';

    /** JSON map of changed fields: {"price": {"old": 500, "new": 600}} */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $changedFields = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $userEmail = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getEntityClass(): string { return $this->entityClass; }
    public function setEntityClass(string $v): static { $this->entityClass = $v; return $this; }

    public function getEntityId(): int { return $this->entityId; }
    public function setEntityId(int $v): static { $this->entityId = $v; return $this; }

    public function getAction(): string { return $this->action; }
    public function setAction(string $v): static { $this->action = $v; return $this; }

    public function getChangedFields(): ?array { return $this->changedFields; }
    public function setChangedFields(?array $v): static { $this->changedFields = $v; return $this; }

    public function getUserEmail(): ?string { return $this->userEmail; }
    public function setUserEmail(?string $v): static { $this->userEmail = $v; return $this; }

    public function getIpAddress(): ?string { return $this->ipAddress; }
    public function setIpAddress(?string $v): static { $this->ipAddress = $v; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
