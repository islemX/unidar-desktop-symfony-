<?php

namespace App\Security\Voter;

use App\Entity\Verification;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class VerificationVoter extends Voter
{
    public const SUBMIT = 'VERIFICATION_SUBMIT';
    public const REVIEW = 'VERIFICATION_REVIEW';

    public function __construct(
        private Security $security,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::SUBMIT, self::REVIEW], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        return match ($attribute) {
            self::SUBMIT => true,
            self::REVIEW => $this->security->isGranted('ROLE_ADMIN'),
            default => false,
        };
    }
}
