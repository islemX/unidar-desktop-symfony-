<?php

namespace App\Entity;

use App\Enum\ContractStatus;
use App\Repository\ContractRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ContractRepository::class)]
#[ORM\Table(name: 'contracts')]
#[ORM\HasLifecycleCallbacks]
class Contract
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    private ?string $contractNumber = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $contractContent = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $studentSignaturePath = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $ownerSignaturePath = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $contractFilePath = null;

    #[ORM\Column(enumType: ContractStatus::class)]
    private ContractStatus $status = ContractStatus::Draft;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $monthlyRent = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $securityDeposit = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: Listing::class, inversedBy: 'contracts')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Listing $listing = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'studentContracts')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $student = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'ownerContracts')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $owner = null;

    #[ORM\ManyToOne(targetEntity: ContractTemplate::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?ContractTemplate $template = null;

    /** @var Collection<int, Payment> */
    #[ORM\OneToMany(targetEntity: Payment::class, mappedBy: 'contract')]
    private Collection $payments;

    /** @var Collection<int, PlatformCommission> */
    #[ORM\OneToMany(targetEntity: PlatformCommission::class, mappedBy: 'contract')]
    private Collection $commissions;

    /** @var Collection<int, ContractTerminationRequest> */
    #[ORM\OneToMany(targetEntity: ContractTerminationRequest::class, mappedBy: 'contract')]
    private Collection $terminationRequests;

    public function __construct()
    {
        $this->payments = new ArrayCollection();
        $this->commissions = new ArrayCollection();
        $this->terminationRequests = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContractNumber(): ?string
    {
        return $this->contractNumber;
    }

    public function setContractNumber(string $contractNumber): static
    {
        $this->contractNumber = $contractNumber;
        return $this;
    }

    public function getContractContent(): ?string
    {
        return $this->contractContent;
    }

    public function setContractContent(?string $contractContent): static
    {
        $this->contractContent = $contractContent;
        return $this;
    }

    public function getStudentSignaturePath(): ?string
    {
        return $this->studentSignaturePath;
    }

    public function setStudentSignaturePath(?string $studentSignaturePath): static
    {
        $this->studentSignaturePath = $studentSignaturePath;
        return $this;
    }

    public function getOwnerSignaturePath(): ?string
    {
        return $this->ownerSignaturePath;
    }

    public function setOwnerSignaturePath(?string $ownerSignaturePath): static
    {
        $this->ownerSignaturePath = $ownerSignaturePath;
        return $this;
    }

    public function getContractFilePath(): ?string
    {
        return $this->contractFilePath;
    }

    public function setContractFilePath(?string $contractFilePath): static
    {
        $this->contractFilePath = $contractFilePath;
        return $this;
    }

    public function getStatus(): ContractStatus
    {
        return $this->status;
    }

    public function setStatus(ContractStatus $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeInterface $startDate): static
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(\DateTimeInterface $endDate): static
    {
        $this->endDate = $endDate;
        return $this;
    }

    public function getMonthlyRent(): ?string
    {
        return $this->monthlyRent;
    }

    public function setMonthlyRent(string $monthlyRent): static
    {
        $this->monthlyRent = $monthlyRent;
        return $this;
    }

    public function getSecurityDeposit(): ?string
    {
        return $this->securityDeposit;
    }

    public function setSecurityDeposit(string $securityDeposit): static
    {
        $this->securityDeposit = $securityDeposit;
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

    public function getListing(): ?Listing
    {
        return $this->listing;
    }

    public function setListing(?Listing $listing): static
    {
        $this->listing = $listing;
        return $this;
    }

    public function getStudent(): ?User
    {
        return $this->student;
    }

    public function setStudent(?User $student): static
    {
        $this->student = $student;
        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;
        return $this;
    }

    public function getTemplate(): ?ContractTemplate
    {
        return $this->template;
    }

    public function setTemplate(?ContractTemplate $template): static
    {
        $this->template = $template;
        return $this;
    }

    /** @return Collection<int, Payment> */
    public function getPayments(): Collection
    {
        return $this->payments;
    }

    public function addPayment(Payment $payment): static
    {
        if (!$this->payments->contains($payment)) {
            $this->payments->add($payment);
            $payment->setContract($this);
        }
        return $this;
    }

    public function removePayment(Payment $payment): static
    {
        if ($this->payments->removeElement($payment)) {
            if ($payment->getContract() === $this) {
                $payment->setContract(null);
            }
        }
        return $this;
    }

    /** @return Collection<int, PlatformCommission> */
    public function getCommissions(): Collection
    {
        return $this->commissions;
    }

    public function addCommission(PlatformCommission $commission): static
    {
        if (!$this->commissions->contains($commission)) {
            $this->commissions->add($commission);
            $commission->setContract($this);
        }
        return $this;
    }

    public function removeCommission(PlatformCommission $commission): static
    {
        if ($this->commissions->removeElement($commission)) {
            if ($commission->getContract() === $this) {
                $commission->setContract(null);
            }
        }
        return $this;
    }

    /** @return Collection<int, ContractTerminationRequest> */
    public function getTerminationRequests(): Collection
    {
        return $this->terminationRequests;
    }

    public function addTerminationRequest(ContractTerminationRequest $request): static
    {
        if (!$this->terminationRequests->contains($request)) {
            $this->terminationRequests->add($request);
            $request->setContract($this);
        }
        return $this;
    }

    public function removeTerminationRequest(ContractTerminationRequest $request): static
    {
        if ($this->terminationRequests->removeElement($request)) {
            if ($request->getContract() === $this) {
                $request->setContract(null);
            }
        }
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
