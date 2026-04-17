<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\SavedListingRepository::class)]
#[ORM\Table(name: 'saved_listings')]
#[ORM\UniqueConstraint(columns: ['user_id', 'listing_id'])]
class SavedListing
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $savedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'savedListings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Listing::class, inversedBy: 'savedListings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Listing $listing = null;

    public function __construct()
    {
        $this->savedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSavedAt(): ?\DateTimeImmutable
    {
        return $this->savedAt;
    }

    public function setSavedAt(\DateTimeImmutable $savedAt): static
    {
        $this->savedAt = $savedAt;
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

    public function getListing(): ?Listing
    {
        return $this->listing;
    }

    public function setListing(?Listing $listing): static
    {
        $this->listing = $listing;
        return $this;
    }
}
