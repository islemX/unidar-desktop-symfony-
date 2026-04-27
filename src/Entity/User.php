<?php

namespace App\Entity;

use App\Enum\UserRole;
use App\Enum\UserStatus;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private ?string $email = null;

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 255)]
    private ?string $fullName = null;

    #[ORM\Column(enumType: UserRole::class)]
    private ?UserRole $role = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $university = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $gender = null;

    #[ORM\Column(nullable: true)]
    private ?float $preferredLat = null;

    #[ORM\Column(nullable: true)]
    private ?float $preferredLng = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $preferredAddress = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $universityAddress = null;

    #[ORM\Column(enumType: UserStatus::class)]
    private UserStatus $status = UserStatus::Active;

    #[ORM\Column(options: ['default' => false])]
    private bool $isEmailVerified = false;

    #[ORM\Column(length: 100, nullable: true, unique: true)]
    private ?string $emailVerificationToken = null;

    /** 6-digit OTP shown in the verification email */
    #[ORM\Column(length: 6, nullable: true)]
    private ?string $emailVerificationCode = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $emailVerificationCodeExpiresAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    /** @var Collection<int, Listing> */
    #[ORM\OneToMany(targetEntity: Listing::class, mappedBy: 'owner')]
    private Collection $listings;

    /** @var Collection<int, Contract> */
    #[ORM\OneToMany(targetEntity: Contract::class, mappedBy: 'student')]
    private Collection $studentContracts;

    /** @var Collection<int, Contract> */
    #[ORM\OneToMany(targetEntity: Contract::class, mappedBy: 'owner')]
    private Collection $ownerContracts;

    #[ORM\OneToOne(targetEntity: RoommatePreference::class, mappedBy: 'user')]
    private ?RoommatePreference $roommatePreference = null;

    #[ORM\OneToOne(targetEntity: Verification::class, mappedBy: 'user')]
    private ?Verification $verification = null;

    /** @var Collection<int, Subscription> */
    #[ORM\OneToMany(targetEntity: Subscription::class, mappedBy: 'user')]
    private Collection $subscriptions;

    /** @var Collection<int, SavedListing> */
    #[ORM\OneToMany(targetEntity: SavedListing::class, mappedBy: 'user')]
    private Collection $savedListings;

    /** @var Collection<int, Report> */
    #[ORM\OneToMany(targetEntity: Report::class, mappedBy: 'reporter')]
    private Collection $reports;

    /** @var Collection<int, AdminAction> */
    #[ORM\OneToMany(targetEntity: AdminAction::class, mappedBy: 'admin')]
    private Collection $adminActions;

    public function __construct()
    {
        $this->listings = new ArrayCollection();
        $this->studentContracts = new ArrayCollection();
        $this->ownerContracts = new ArrayCollection();
        $this->subscriptions = new ArrayCollection();
        $this->savedListings = new ArrayCollection();
        $this->reports = new ArrayCollection();
        $this->adminActions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function getFullName(): ?string
    {
        return $this->fullName;
    }

    public function setFullName(string $fullName): static
    {
        $this->fullName = $fullName;
        return $this;
    }

    public function getRole(): ?UserRole
    {
        return $this->role;
    }

    public function setRole(UserRole $role): static
    {
        $this->role = $role;
        return $this;
    }

    public function getUniversity(): ?string
    {
        return $this->university;
    }

    public function setUniversity(?string $university): static
    {
        $this->university = $university;
        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;
        return $this;
    }

    public function getGender(): ?string
    {
        return $this->gender;
    }

    public function setGender(?string $gender): static
    {
        $this->gender = $gender;
        return $this;
    }

    public function getPreferredLat(): ?float
    {
        return $this->preferredLat;
    }

    public function setPreferredLat(?float $preferredLat): static
    {
        $this->preferredLat = $preferredLat;
        return $this;
    }

    public function getPreferredLng(): ?float
    {
        return $this->preferredLng;
    }

    public function setPreferredLng(?float $preferredLng): static
    {
        $this->preferredLng = $preferredLng;
        return $this;
    }

    public function getPreferredAddress(): ?string
    {
        return $this->preferredAddress;
    }

    public function setPreferredAddress(?string $preferredAddress): static
    {
        $this->preferredAddress = $preferredAddress;
        return $this;
    }

    public function getUniversityAddress(): ?string
    {
        return $this->universityAddress;
    }

    public function setUniversityAddress(?string $universityAddress): static
    {
        $this->universityAddress = $universityAddress;
        return $this;
    }

    public function getStatus(): UserStatus
    {
        return $this->status;
    }

    public function setStatus(UserStatus $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function isEmailVerified(): bool
    {
        return $this->isEmailVerified;
    }

    public function setIsEmailVerified(bool $isEmailVerified): static
    {
        $this->isEmailVerified = $isEmailVerified;
        return $this;
    }

    public function getEmailVerificationToken(): ?string
    {
        return $this->emailVerificationToken;
    }

    public function setEmailVerificationToken(?string $token): static
    {
        $this->emailVerificationToken = $token;
        return $this;
    }

    public function getEmailVerificationCode(): ?string
    {
        return $this->emailVerificationCode;
    }

    public function setEmailVerificationCode(?string $code): static
    {
        $this->emailVerificationCode = $code;
        return $this;
    }

    public function getEmailVerificationCodeExpiresAt(): ?\DateTimeImmutable
    {
        return $this->emailVerificationCodeExpiresAt;
    }

    public function setEmailVerificationCodeExpiresAt(?\DateTimeImmutable $at): static
    {
        $this->emailVerificationCodeExpiresAt = $at;
        return $this;
    }

    public function isVerificationCodeValid(string $code): bool
    {
        return $this->emailVerificationCode === $code
            && $this->emailVerificationCodeExpiresAt !== null
            && $this->emailVerificationCodeExpiresAt > new \DateTimeImmutable();
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

    /** @return Collection<int, Listing> */
    public function getListings(): Collection
    {
        return $this->listings;
    }

    public function addListing(Listing $listing): static
    {
        if (!$this->listings->contains($listing)) {
            $this->listings->add($listing);
            $listing->setOwner($this);
        }
        return $this;
    }

    public function removeListing(Listing $listing): static
    {
        if ($this->listings->removeElement($listing)) {
            if ($listing->getOwner() === $this) {
                $listing->setOwner(null);
            }
        }
        return $this;
    }

    /** @return Collection<int, Contract> */
    public function getStudentContracts(): Collection
    {
        return $this->studentContracts;
    }

    public function addStudentContract(Contract $contract): static
    {
        if (!$this->studentContracts->contains($contract)) {
            $this->studentContracts->add($contract);
            $contract->setStudent($this);
        }
        return $this;
    }

    public function removeStudentContract(Contract $contract): static
    {
        if ($this->studentContracts->removeElement($contract)) {
            if ($contract->getStudent() === $this) {
                $contract->setStudent(null);
            }
        }
        return $this;
    }

    /** @return Collection<int, Contract> */
    public function getOwnerContracts(): Collection
    {
        return $this->ownerContracts;
    }

    public function addOwnerContract(Contract $contract): static
    {
        if (!$this->ownerContracts->contains($contract)) {
            $this->ownerContracts->add($contract);
            $contract->setOwner($this);
        }
        return $this;
    }

    public function removeOwnerContract(Contract $contract): static
    {
        if ($this->ownerContracts->removeElement($contract)) {
            if ($contract->getOwner() === $this) {
                $contract->setOwner(null);
            }
        }
        return $this;
    }

    public function getRoommatePreference(): ?RoommatePreference
    {
        return $this->roommatePreference;
    }

    public function setRoommatePreference(?RoommatePreference $roommatePreference): static
    {
        if ($roommatePreference !== null && $roommatePreference->getUser() !== $this) {
            $roommatePreference->setUser($this);
        }
        $this->roommatePreference = $roommatePreference;
        return $this;
    }

    public function getVerification(): ?Verification
    {
        return $this->verification;
    }

    public function setVerification(?Verification $verification): static
    {
        if ($verification !== null && $verification->getUser() !== $this) {
            $verification->setUser($this);
        }
        $this->verification = $verification;
        return $this;
    }

    /** @return Collection<int, Subscription> */
    public function getSubscriptions(): Collection
    {
        return $this->subscriptions;
    }

    public function addSubscription(Subscription $subscription): static
    {
        if (!$this->subscriptions->contains($subscription)) {
            $this->subscriptions->add($subscription);
            $subscription->setUser($this);
        }
        return $this;
    }

    public function removeSubscription(Subscription $subscription): static
    {
        if ($this->subscriptions->removeElement($subscription)) {
            if ($subscription->getUser() === $this) {
                $subscription->setUser(null);
            }
        }
        return $this;
    }

    /** @return Collection<int, SavedListing> */
    public function getSavedListings(): Collection
    {
        return $this->savedListings;
    }

    public function addSavedListing(SavedListing $savedListing): static
    {
        if (!$this->savedListings->contains($savedListing)) {
            $this->savedListings->add($savedListing);
            $savedListing->setUser($this);
        }
        return $this;
    }

    public function removeSavedListing(SavedListing $savedListing): static
    {
        if ($this->savedListings->removeElement($savedListing)) {
            if ($savedListing->getUser() === $this) {
                $savedListing->setUser(null);
            }
        }
        return $this;
    }

    /** @return Collection<int, Report> */
    public function getReports(): Collection
    {
        return $this->reports;
    }

    public function addReport(Report $report): static
    {
        if (!$this->reports->contains($report)) {
            $this->reports->add($report);
            $report->setReporter($this);
        }
        return $this;
    }

    public function removeReport(Report $report): static
    {
        if ($this->reports->removeElement($report)) {
            if ($report->getReporter() === $this) {
                $report->setReporter(null);
            }
        }
        return $this;
    }

    /** @return Collection<int, AdminAction> */
    public function getAdminActions(): Collection
    {
        return $this->adminActions;
    }

    public function addAdminAction(AdminAction $adminAction): static
    {
        if (!$this->adminActions->contains($adminAction)) {
            $this->adminActions->add($adminAction);
            $adminAction->setAdmin($this);
        }
        return $this;
    }

    public function removeAdminAction(AdminAction $adminAction): static
    {
        if ($this->adminActions->removeElement($adminAction)) {
            if ($adminAction->getAdmin() === $this) {
                $adminAction->setAdmin(null);
            }
        }
        return $this;
    }

    /** @return array<string> */
    public function getRoles(): array
    {
        return ['ROLE_' . strtoupper($this->role->value)];
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function eraseCredentials(): void
    {
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
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
