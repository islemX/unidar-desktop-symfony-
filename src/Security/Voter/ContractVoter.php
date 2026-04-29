<?php

namespace App\Security\Voter;

use App\Entity\Contract;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class ContractVoter extends Voter
{
    public const VIEW = 'CONTRACT_VIEW';
    public const SIGN = 'CONTRACT_SIGN';
    public const TERMINATE = 'CONTRACT_TERMINATE';
    public const REQUEST_TERMINATION = 'CONTRACT_REQUEST_TERMINATION';

    public function __construct(
        private Security $security,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [
            self::VIEW,
            self::SIGN,
            self::TERMINATE,
            self::REQUEST_TERMINATION,
        ], true) && $subject instanceof Contract;
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

        /** @var Contract $contract */
        $contract = $subject;

        return match ($attribute) {
            self::VIEW => $this->canView($contract, $user),
            self::SIGN => $this->canSign($contract, $user),
            self::TERMINATE => $this->canTerminate($contract, $user),
            self::REQUEST_TERMINATION => $this->canRequestTermination($contract, $user),
            default => false,
        };
    }

    private function canView(Contract $contract, User $user): bool
    {
        return $contract->getStudent() === $user
            || $contract->getOwner() === $user;
    }

    private function canSign(Contract $contract, User $user): bool
    {
        return $contract->getStudent() === $user
            || $contract->getOwner() === $user;
    }

    private function canTerminate(Contract $contract, User $user): bool
    {
        return $contract->getOwner() === $user;
    }

    private function canRequestTermination(Contract $contract, User $user): bool
    {
        return $contract->getStudent() === $user;
    }
}
