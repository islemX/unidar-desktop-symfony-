<?php

namespace App\Entity;

use App\Enum\VerificationStatus;
use App\Repository\VerificationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VerificationRepository::class)]
#[ORM\Table(name: 'verifications')]
class Verification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $studentIdFile = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $nationalIdFile = null;

    #[ORM\Column(enumType: VerificationStatus::class)]
    private VerificationStatus $status = VerificationStatus::Pending;

    #[ORM\Column]
    private ?\DateTimeImmutable $submittedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $reviewedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $rejectionReason = null;

    #[ORM\OneToOne(targetEntity: User::class, inversedBy: 'verification')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $reviewedBy = null;

    public function __construct()
    {
        $this->submittedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStudentIdFile(): ?string
    {
        return $this->studentIdFile;
    }

    public function setStudentIdFile(?string $studentIdFile): static
    {
        $this->studentIdFile = $studentIdFile;
        return $this;
    }

    public function getNationalIdFile(): ?string
    {
        return $this->nationalIdFile;
    }

    public function setNationalIdFile(?string $nationalIdFile): static
    {
        $this->nationalIdFile = $nationalIdFile;
        return $this;
    }

    public function getStatus(): VerificationStatus
    {
        return $this->status;
    }

    public function setStatus(VerificationStatus $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getSubmittedAt(): ?\DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function setSubmittedAt(\DateTimeImmutable $submittedAt): static
    {
        $this->submittedAt = $submittedAt;
        return $this;
    }

    public function getReviewedAt(): ?\DateTimeImmutable
    {
        return $this->reviewedAt;
    }

    public function setReviewedAt(?\DateTimeImmutable $reviewedAt): static
    {
        $this->reviewedAt = $reviewedAt;
        return $this;
    }

    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function setRejectionReason(?string $rejectionReason): static
    {
        $this->rejectionReason = $rejectionReason;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getReviewedBy(): ?User
    {
        return $this->reviewedBy;
    }

    public function setReviewedBy(?User $reviewedBy): static
    {
        $this->reviewedBy = $reviewedBy;
        return $this;
    }
}
