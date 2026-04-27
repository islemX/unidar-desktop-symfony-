<?php

namespace App\Entity;

use App\Enum\PropertyType;
use App\Repository\ListingRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: ListingRepository::class)]
#[ORM\Table(name: 'listings')]
#[ORM\HasLifecycleCallbacks]
#[Gedmo\SoftDeleteable(fieldName: 'deletedAt', timeAware: false, hardDelete: false)]
class Listing
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    #[ORM\Column(length: 500)]
    private ?string $address = null;

    #[ORM\Column]
    private ?float $latitude = null;

    #[ORM\Column]
    private ?float $longitude = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $price = null;

    #[ORM\Column]
    private ?int $bedrooms = null;

    #[ORM\Column]
    private ?int $bedsCount = null;

    #[ORM\Column]
    private ?int $capacity = null;

    #[ORM\Column]
    private ?int $bathrooms = null;

    #[ORM\Column(enumType: PropertyType::class)]
    private ?PropertyType $propertyType = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $genderPreference = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $availableFrom = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $availableUntil = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $ownerSignaturePath = null;

    #[ORM\Column(length: 20, options: ['default' => 'active'])]
    private string $status = 'active';

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'listings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $owner = null;

    /** @var Collection<int, ListingImage> */
    #[ORM\OneToMany(targetEntity: ListingImage::class, mappedBy: 'listing', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['displayOrder' => 'ASC'])]
    private Collection $images;

    /** @var Collection<int, Contract> */
    #[ORM\OneToMany(targetEntity: Contract::class, mappedBy: 'listing')]
    private Collection $contracts;

    /** @var Collection<int, Conversation> */
    #[ORM\OneToMany(targetEntity: Conversation::class, mappedBy: 'listing')]
    private Collection $conversations;

    /** @var Collection<int, SavedListing> */
    #[ORM\OneToMany(targetEntity: SavedListing::class, mappedBy: 'listing')]
    private Collection $savedListings;

    public function __construct()
    {
        $this->images = new ArrayCollection();
        $this->contracts = new ArrayCollection();
        $this->conversations = new ArrayCollection();
        $this->savedListings = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(string $address): static
    {
        $this->address = $address;
        return $this;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function setLatitude(float $latitude): static
    {
        $this->latitude = $latitude;
        return $this;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function setLongitude(float $longitude): static
    {
        $this->longitude = $longitude;
        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(string $price): static
    {
        $this->price = $price;
        return $this;
    }

    public function getBedrooms(): ?int
    {
        return $this->bedrooms;
    }

    public function setBedrooms(int $bedrooms): static
    {
        $this->bedrooms = $bedrooms;
        return $this;
    }

    public function getBedsCount(): ?int
    {
        return $this->bedsCount;
    }

    public function setBedsCount(int $bedsCount): static
    {
        $this->bedsCount = $bedsCount;
        return $this;
    }

    public function getCapacity(): ?int
    {
        return $this->capacity;
    }

    public function setCapacity(int $capacity): static
    {
        $this->capacity = $capacity;
        return $this;
    }

    public function getBathrooms(): ?int
    {
        return $this->bathrooms;
    }

    public function setBathrooms(int $bathrooms): static
    {
        $this->bathrooms = $bathrooms;
        return $this;
    }

    public function getPropertyType(): ?PropertyType
    {
        return $this->propertyType;
    }

    public function setPropertyType(PropertyType $propertyType): static
    {
        $this->propertyType = $propertyType;
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

    public function getAvailableFrom(): ?\DateTimeInterface
    {
        return $this->availableFrom;
    }

    public function setAvailableFrom(?\DateTimeInterface $availableFrom): static
    {
        $this->availableFrom = $availableFrom;
        return $this;
    }

    public function getAvailableUntil(): ?\DateTimeInterface
    {
        return $this->availableUntil;
    }

    public function setAvailableUntil(?\DateTimeInterface $availableUntil): static
    {
        $this->availableUntil = $availableUntil;
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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
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

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;
        return $this;
    }

    /** @return Collection<int, ListingImage> */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function addImage(ListingImage $image): static
    {
        if (!$this->images->contains($image)) {
            $this->images->add($image);
            $image->setListing($this);
        }
        return $this;
    }

    public function removeImage(ListingImage $image): static
    {
        if ($this->images->removeElement($image)) {
            if ($image->getListing() === $this) {
                $image->setListing(null);
            }
        }
        return $this;
    }

    /** @return Collection<int, Contract> */
    public function getContracts(): Collection
    {
        return $this->contracts;
    }

    public function addContract(Contract $contract): static
    {
        if (!$this->contracts->contains($contract)) {
            $this->contracts->add($contract);
            $contract->setListing($this);
        }
        return $this;
    }

    public function removeContract(Contract $contract): static
    {
        if ($this->contracts->removeElement($contract)) {
            if ($contract->getListing() === $this) {
                $contract->setListing(null);
            }
        }
        return $this;
    }

    /** @return Collection<int, Conversation> */
    public function getConversations(): Collection
    {
        return $this->conversations;
    }

    public function addConversation(Conversation $conversation): static
    {
        if (!$this->conversations->contains($conversation)) {
            $this->conversations->add($conversation);
            $conversation->setListing($this);
        }
        return $this;
    }

    public function removeConversation(Conversation $conversation): static
    {
        if ($this->conversations->removeElement($conversation)) {
            if ($conversation->getListing() === $this) {
                $conversation->setListing(null);
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
            $savedListing->setListing($this);
        }
        return $this;
    }

    public function removeSavedListing(SavedListing $savedListing): static
    {
        if ($this->savedListings->removeElement($savedListing)) {
            if ($savedListing->getListing() === $this) {
                $savedListing->setListing(null);
            }
        }
        return $this;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeImmutable $deletedAt): static
    {
        $this->deletedAt = $deletedAt;
        return $this;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
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
