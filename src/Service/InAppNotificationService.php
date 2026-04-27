<?php

namespace App\Service;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\User;
use App\Enum\UserRole;
use App\Enum\UserStatus;
use App\Repository\ConversationRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class InAppNotificationService
{
    private const ADMIN_EMAIL = 'admin@unidar.app';
    private const ADMIN_NAME  = 'Unidar Admin';

    public function __construct(
        private EntityManagerInterface     $em,
        private UserRepository             $userRepository,
        private ConversationRepository     $conversationRepository,
        private UserPasswordHasherInterface $hasher,
        private LoggerInterface            $logger,
    ) {
    }

    public function notify(User $recipient, string $text): void
    {
        try {
            $admin        = $this->getOrCreateAdminUser();
            $conversation = $this->getOrCreateConversation($admin, $recipient);

            $msg = new Message();
            $msg->setConversation($conversation);
            $msg->setSender($admin);
            $msg->setReceiver($recipient);
            $msg->setMessage($text);

            $conversation->setUpdatedAt(new \DateTimeImmutable());

            $this->em->persist($msg);
            $this->em->flush();
        } catch (\Throwable $e) {
            $this->logger->error('InAppNotification failed for user ' . $recipient->getId() . ': ' . $e->getMessage());
        }
    }

    private function getOrCreateAdminUser(): User
    {
        $admin = $this->userRepository->findOneBy(['email' => self::ADMIN_EMAIL]);
        if ($admin) {
            return $admin;
        }

        $admin = new User();
        $admin->setEmail(self::ADMIN_EMAIL);
        $admin->setFullName(self::ADMIN_NAME);
        $admin->setRole(UserRole::Admin);
        $admin->setStatus(UserStatus::Active);
        $admin->setIsEmailVerified(true);
        $admin->setPassword($this->hasher->hashPassword($admin, bin2hex(random_bytes(16))));

        $this->em->persist($admin);
        $this->em->flush();

        return $admin;
    }

    private function getOrCreateConversation(User $admin, User $recipient): Conversation
    {
        $existing = $this->conversationRepository->findBetweenUsers($admin, $recipient);
        if ($existing) {
            return $existing;
        }

        $conv = new Conversation();
        $conv->setUser1($admin);
        $conv->setUser2($recipient);

        $this->em->persist($conv);
        $this->em->flush();

        return $conv;
    }
}
