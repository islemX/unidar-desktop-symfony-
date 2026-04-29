<?php

namespace App\Security\Voter;

use App\Entity\Listing;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class ListingVoter extends Voter
{
    public const CREATE = 'LISTING_CREATE';
    public const EDIT = 'LISTING_EDIT';
    public const DELETE = 'LISTING_DELETE';

    public function __construct(
        private Security $security,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        if ($attribute === self::CREATE) {
            return true;
        }

        return in_array($attribute, [self::EDIT, self::DELETE], true)
            && $subject instanceof Listing;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        return match ($attribute) {
            self::CREATE => $this->security->isGranted('ROLE_OWNER'),
            self::EDIT, self::DELETE => $this->canEditOrDelete($subject, $user),
            default => false,
        };
    }

    private function canEditOrDelete(Listing $listing, User $user): bool
    {
        return $listing->getOwner() === $user;
    }
}
