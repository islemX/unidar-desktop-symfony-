<?php

namespace App\Security;

use App\Entity\User;
use App\Enum\UserStatus;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (!$user->isEmailVerified()) {
            throw new CustomUserMessageAccountStatusException(
                'email_not_verified'
            );
        }

        if ($user->getStatus() === UserStatus::Banned) {
            throw new CustomUserMessageAccountStatusException('Your account has been banned.');
        }

        if ($user->getStatus() === UserStatus::Suspended) {
            throw new CustomUserMessageAccountStatusException('Your account has been suspended.');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        // No post-authentication checks needed.
    }
}
