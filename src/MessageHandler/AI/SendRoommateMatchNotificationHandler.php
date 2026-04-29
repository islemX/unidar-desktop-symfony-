<?php

namespace App\MessageHandler\AI;

use App\Message\AI\SendRoommateMatchNotificationMessage;
use App\Repository\UserRepository;
use App\Service\InAppNotificationService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class SendRoommateMatchNotificationHandler
{
    public function __construct(
        private readonly InAppNotificationService $notifier,
        private readonly UserRepository $userRepository,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(SendRoommateMatchNotificationMessage $message): void
    {
        $student = $this->userRepository->find($message->studentId);
        if (!$student) {
            $this->logger->warning("SendRoommateMatchNotification: student {$message->studentId} not found.");
            return;
        }

        $this->notifier->notify($student, sprintf(
            "Bonjour %s,\n\n🎉 Bonne nouvelle ! Nous avons trouvé %d colocataire(s) très compatible(s) avec vous (score ≥ 70 %%). Connectez-vous à la section Colocataires pour les découvrir.\n\n— L'équipe UNIDAR",
            $student->getFullName(),
            $message->matchCount
        ));

        $this->logger->info("Roommate match notification sent to student {$message->studentId} ({$message->matchCount} matches).");
    }
}
