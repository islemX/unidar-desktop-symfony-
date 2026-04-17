<?php

namespace App\Security\Voter;

use App\Entity\Conversation;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class MessageVoter extends Voter
{
    public const VIEW = 'CONVERSATION_VIEW';
    public const SEND = 'MESSAGE_SEND';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::SEND], true)
            && $subject instanceof Conversation;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        /** @var Conversation $conversation */
        $conversation = $subject;

        return match ($attribute) {
            self::VIEW, self::SEND => $this->isParticipant($conversation, $user),
            default => false,
        };
    }

    private function isParticipant(Conversation $conversation, User $user): bool
    {
        return $conversation->getUser1() === $user
            || $conversation->getUser2() === $user;
    }
}
