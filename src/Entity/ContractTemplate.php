<?php

namespace App\Entity;

use App\Repository\ContractTemplateRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ContractTemplateRepository::class)]
#[ORM\Table(name: 'contract_templates')]
#[ORM\HasLifecycleCallbacks]
class ContractTemplate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $templateName = null;

    #[ORM\Column(length: 100)]
    private ?string $templateType = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $contractContent = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $legalClauses = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $tunisiaSpecificClauses = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTemplateName(): ?string
    {
        return $this->templateName;
    }

    public function setTemplateName(string $templateName): static
    {
        $this->templateName = $templateName;
        return $this;
    }

    public function getTemplateType(): ?string
    {
        return $this->templateType;
    }

    public function setTemplateType(string $templateType): static
    {
        $this->templateType = $templateType;
        return $this;
    }

    public function getContractContent(): ?string
    {
        return $this->contractContent;
    }

    public function setContractContent(string $contractContent): static
    {
        $this->contractContent = $contractContent;
        return $this;
    }

    public function getLegalClauses(): ?string
    {
        return $this->legalClauses;
    }

    public function setLegalClauses(string $legalClauses): static
    {
        $this->legalClauses = $legalClauses;
        return $this;
    }

    public function getTunisiaSpecificClauses(): ?string
    {
        return $this->tunisiaSpecificClauses;
    }

    public function setTunisiaSpecificClauses(string $tunisiaSpecificClauses): static
    {
        $this->tunisiaSpecificClauses = $tunisiaSpecificClauses;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
