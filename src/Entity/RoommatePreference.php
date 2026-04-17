<?php

namespace App\Entity;

use App\Repository\RoommatePreferenceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RoommatePreferenceRepository::class)]
#[ORM\Table(name: 'roommate_preferences')]
class RoommatePreference
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $budgetMin = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $budgetMax = null;

    #[ORM\Column(nullable: true)]
    private ?int $cleanlinessLevel = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $smokingPreference = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $noiseTolerance = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $sleepSchedule = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $genderPreference = null;

    #[ORM\Column(nullable: true)]
    private ?int $ageMin = null;

    #[ORM\Column(nullable: true)]
    private ?int $ageMax = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $guests = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $pets = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\OneToOne(targetEntity: User::class, inversedBy: 'roommatePreference')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBudgetMin(): ?string
    {
        return $this->budgetMin;
    }

    public function setBudgetMin(?string $budgetMin): static
    {
        $this->budgetMin = $budgetMin;
        return $this;
    }

    public function getBudgetMax(): ?string
    {
        return $this->budgetMax;
    }

    public function setBudgetMax(?string $budgetMax): static
    {
        $this->budgetMax = $budgetMax;
        return $this;
    }

    public function getCleanlinessLevel(): ?int
    {
        return $this->cleanlinessLevel;
    }

    public function setCleanlinessLevel(?int $cleanlinessLevel): static
    {
        $this->cleanlinessLevel = $cleanlinessLevel;
        return $this;
    }

    public function getSmokingPreference(): ?string
    {
        return $this->smokingPreference;
    }

    public function setSmokingPreference(?string $smokingPreference): static
    {
        $this->smokingPreference = $smokingPreference;
        return $this;
    }

    public function getNoiseTolerance(): ?string
    {
        return $this->noiseTolerance;
    }

    public function setNoiseTolerance(?string $noiseTolerance): static
    {
        $this->noiseTolerance = $noiseTolerance;
        return $this;
    }

    public function getSleepSchedule(): ?string
    {
        return $this->sleepSchedule;
    }

    public function setSleepSchedule(?string $sleepSchedule): static
    {
        $this->sleepSchedule = $sleepSchedule;
        return $this;
    }

    public function getGenderPreference(): ?string
    {
        return $this->genderPreference;
    }

    public function setGenderPreference(?string $genderPreference): static
    {
        $this->genderPreference = $genderPreference;
        return $this;
    }

    public function getAgeMin(): ?int
    {
        return $this->ageMin;
    }

    public function setAgeMin(?int $ageMin): static
    {
        $this->ageMin = $ageMin;
        return $this;
    }

    public function getAgeMax(): ?int
    {
        return $this->ageMax;
    }

    public function setAgeMax(?int $ageMax): static
    {
        $this->ageMax = $ageMax;
        return $this;
    }

    public function getGuests(): ?string
    {
        return $this->guests;
    }

    public function setGuests(?string $guests): static
    {
        $this->guests = $guests;
        return $this;
    }

    public function getPets(): ?string
    {
        return $this->pets;
    }

    public function setPets(?string $pets): static
    {
        $this->pets = $pets;
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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }
}
