<?php

namespace App\Entity;

use App\Enum\PaymentStatus;
use App\Enum\PaymentType;
use App\Repository\PaymentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PaymentRepository::class)]
#[ORM\Table(name: 'payments')]
#[ORM\HasLifecycleCallbacks]
class Payment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $amount = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $ownerAmount = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $platformFee = null;

    #[ORM\Column(enumType: PaymentStatus::class)]
    private PaymentStatus $status = PaymentStatus::Pending;

    #[ORM\Column(length: 50)]
    private ?string $paymentMethod = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $transactionId = null;

    #[ORM\Column(enumType: PaymentType::class)]
    private ?PaymentType $paymentType = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: Contract::class, inversedBy: 'payments')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Contract $contract = null;

    /** @var Collection<int, PaymentTransaction> */
    #[ORM\OneToMany(targetEntity: PaymentTransaction::class, mappedBy: 'payment')]
    private Collection $transactions;

    #[ORM\OneToOne(targetEntity: PlatformCommission::class, mappedBy: 'payment')]
    private ?PlatformCommission $commission = null;

    public function __construct()
    {
        $this->transactions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): static
    {
        $this->amount = $amount;
        return $this;
    }

    public function getOwnerAmount(): ?string
    {
        return $this->ownerAmount;
    }

    public function setOwnerAmount(string $ownerAmount): static
    {
        $this->ownerAmount = $ownerAmount;
        return $this;
    }

    public function getPlatformFee(): ?string
    {
        return $this->platformFee;
    }

    public function setPlatformFee(string $platformFee): static
    {
        $this->platformFee = $platformFee;
        return $this;
    }

    public function getStatus(): PaymentStatus
    {
        return $this->status;
    }

    public function setStatus(PaymentStatus $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(string $paymentMethod): static
    {
        $this->paymentMethod = $paymentMethod;
        return $this;
    }

    public function getTransactionId(): ?string
    {
        return $this->transactionId;
    }

    public function setTransactionId(?string $transactionId): static
    {
        $this->transactionId = $transactionId;
        return $this;
    }

    public function getPaymentType(): ?PaymentType
    {
        return $this->paymentType;
    }

    public function setPaymentType(PaymentType $paymentType): static
    {
        $this->paymentType = $paymentType;
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

    public function getContract(): ?Contract
    {
        return $this->contract;
    }

    public function setContract(?Contract $contract): static
    {
        $this->contract = $contract;
        return $this;
    }

    /** @return Collection<int, PaymentTransaction> */
    public function getTransactions(): Collection
    {
        return $this->transactions;
    }

    public function addTransaction(PaymentTransaction $transaction): static
    {
        if (!$this->transactions->contains($transaction)) {
            $this->transactions->add($transaction);
            $transaction->setPayment($this);
        }
        return $this;
    }

    public function removeTransaction(PaymentTransaction $transaction): static
    {
        if ($this->transactions->removeElement($transaction)) {
            if ($transaction->getPayment() === $this) {
                $transaction->setPayment(null);
            }
        }
        return $this;
    }

    public function getCommission(): ?PlatformCommission
    {
        return $this->commission;
    }

    public function setCommission(?PlatformCommission $commission): static
    {
        if ($commission !== null && $commission->getPayment() !== $this) {
            $commission->setPayment($this);
        }
        $this->commission = $commission;
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
