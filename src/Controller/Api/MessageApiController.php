<?php

namespace App\Controller\Api;

use App\Entity\BlockedUser;
use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\Report;
use App\Repository\ConversationRepository;
use App\Repository\ListingRepository;
use App\Repository\MessageRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api')]
#[IsGranted('ROLE_USER')]
class MessageApiController extends AbstractController
{
    /**
     * GET /api/conversations — list all conversations for the current user
     * Returns format expected by the ChatWidget JS.
     */
    #[Route('/conversations', name: 'api_conversations', methods: ['GET'])]
    public function conversations(ConversationRepository $convRepo, MessageRepository $msgRepo): JsonResponse
    {
        $me = $this->getUser();
        $convs = $convRepo->findByUser($me);

        $data = [];
        foreach ($convs as $c) {
            $isUser1 = $c->getUser1() === $me;
            $other = $isUser1 ? $c->getUser2() : $c->getUser1();
            $isArchived = $isUser1 ? $c->isArchivedUser1() : $c->isArchivedUser2();

            // Get last message
            $lastMsg = $msgRepo->findOneBy(
                ['conversation' => $c],
                ['createdAt' => 'DESC']
            );

            // Count unread
            $unread = $msgRepo->count([
                'conversation' => $c,
                'receiver' => $me,
                'isRead' => false,
            ]);

            $data[] = [
                'id'              => $c->getId(),
                'other_user_name' => $other?->getFullName() ?? 'Unknown',
                'other_user_id'   => $other?->getId(),
                'listing_title'   => $c->getListing()?->getTitle(),
                'last_message'    => $lastMsg?->getMessage(),
                'updated_at'      => $c->getUpdatedAt()?->format('c'),
                'unread_count'    => $unread,
                'is_archived'     => $isArchived ? 1 : 0,
            ];
        }

        return $this->json(['success' => true, 'conversations' => $data]);
    }

    /**
     * POST /api/conversations/create — create or find existing conversation
     */
    #[Route('/conversations/create', name: 'api_conversation_create', methods: ['POST'])]
    public function createConversation(
        Request $request,
        EntityManagerInterface $em,
        UserRepository $userRepo,
        ListingRepository $listingRepo,
        ConversationRepository $convRepo
    ): JsonResponse {
        $body = json_decode($request->getContent(), true);
        $recipientId = (int) ($body['recipient_id'] ?? 0);
        $listingId   = (int) ($body['listing_id'] ?? 0);

        $recipient = $userRepo->find($recipientId);
        if (!$recipient) {
            return $this->json(['success' => false, 'error' => 'User not found'], 404);
        }

        $listing = $listingId ? $listingRepo->find($listingId) : null;
        $me = $this->getUser();

        // Check existing
        $existing = $convRepo->findBetweenUsers($me, $recipient, $listing);
        if ($existing) {
            // Un-delete if previously deleted
            if ($existing->getUser1() === $me && $existing->isDeletedUser1()) {
                $existing->setIsDeletedUser1(false);
            } elseif ($existing->getUser2() === $me && $existing->isDeletedUser2()) {
                $existing->setIsDeletedUser2(false);
            }
            $em->flush();
            return $this->json(['success' => true, 'conversation_id' => $existing->getId()]);
        }

        $conv = new Conversation();
        $conv->setUser1($me);
        $conv->setUser2($recipient);
        $conv->setListing($listing);
        $em->persist($conv);
        $em->flush();

        return $this->json(['success' => true, 'conversation_id' => $conv->getId()], 201);
    }

    /**
     * GET /api/messages/{id} — get messages for a conversation
     */
    #[Route('/messages/{id}', name: 'api_messages', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function messages(Conversation $conversation, MessageRepository $msgRepo): JsonResponse
    {
        $this->denyAccessUnlessGranted('CONVERSATION_VIEW', $conversation);
        $me = $this->getUser();
        $isUser1 = $conversation->getUser1() === $me;
        $other = $isUser1 ? $conversation->getUser2() : $conversation->getUser1();

        $messages = $msgRepo->findByConversation($conversation);
        $data = array_map(fn($m) => [
            'id'          => $m->getId(),
            'message'     => $m->getMessage(),
            'sender_id'   => $m->getSender()?->getId(),
            'receiver_id' => $m->getReceiver()?->getId(),
            'is_read'     => $m->isRead(),
            'created_at'  => $m->getCreatedAt()?->format('c'),
        ], $messages);

        return $this->json([
            'success'         => true,
            'messages'        => $data,
            'other_user_name' => $other?->getFullName() ?? 'Unknown',
            'other_user_id'   => $other?->getId(),
        ]);
    }

    /**
     * POST /api/messages/{id} — send a message in a conversation
     */
    #[Route('/messages/{id}', name: 'api_message_send', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function send(Request $request, Conversation $conversation, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted('CONVERSATION_VIEW', $conversation);
        $me = $this->getUser();
        $body = json_decode($request->getContent(), true);
        $text = trim($body['message'] ?? '');

        if (!$text) {
            return $this->json(['success' => false, 'error' => 'Message cannot be empty'], 400);
        }

        $recipient = $conversation->getUser1() === $me ? $conversation->getUser2() : $conversation->getUser1();
        $message = new Message();
        $message->setConversation($conversation);
        $message->setSender($me);
        $message->setReceiver($recipient);
        $message->setMessage($text);
        $em->persist($message);

        // Update conversation timestamp
        $conversation->setUpdatedAt(new \DateTimeImmutable());
        $em->flush();

        return $this->json([
            'success'    => true,
            'id'         => $message->getId(),
            'message'    => $message->getMessage(),
            'sender_id'  => $me->getId(),
            'created_at' => $message->getCreatedAt()?->format('c'),
        ], 201);
    }

    /**
     * PUT /api/messages/{id}/read — mark all messages in conversation as read
     */
    #[Route('/messages/{id}/read', name: 'api_message_read', requirements: ['id' => '\d+'], methods: ['PUT'])]
    public function markRead(Conversation $conversation, MessageRepository $msgRepo, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted('CONVERSATION_VIEW', $conversation);
        $unread = $msgRepo->findBy(['conversation' => $conversation, 'receiver' => $this->getUser(), 'isRead' => false]);
        foreach ($unread as $msg) {
            $msg->setIsRead(true);
            $msg->setReadAt(new \DateTimeImmutable());
        }
        $em->flush();
        return $this->json(['success' => true, 'marked' => count($unread)]);
    }

    /**
     * GET /api/messages/unread-count — total unread for current user
     */
    #[Route('/messages/unread-count', name: 'api_unread_count', methods: ['GET'])]
    public function unreadCount(MessageRepository $msgRepo): JsonResponse
    {
        return $this->json(['success' => true, 'count' => $msgRepo->countUnreadByUser($this->getUser())]);
    }

    /**
     * POST /api/conversations/{id}/archive — toggle archive for current user
     */
    #[Route('/conversations/{id}/archive', name: 'api_conversation_archive', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function archive(Request $request, Conversation $conversation, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted('CONVERSATION_VIEW', $conversation);
        $me = $this->getUser();
        $body = json_decode($request->getContent(), true);
        $archive = (bool) ($body['archive'] ?? true);

        if ($conversation->getUser1() === $me) {
            $conversation->setIsArchivedUser1($archive);
        } else {
            $conversation->setIsArchivedUser2($archive);
        }

        $em->flush();
        return $this->json(['success' => true]);
    }

    /**
     * POST /api/conversations/{id}/delete — soft-delete for current user
     */
    #[Route('/conversations/{id}/delete', name: 'api_conversation_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteConversation(Conversation $conversation, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted('CONVERSATION_VIEW', $conversation);
        $me = $this->getUser();

        if ($conversation->getUser1() === $me) {
            $conversation->setIsDeletedUser1(true);
        } else {
            $conversation->setIsDeletedUser2(true);
        }

        $em->flush();
        return $this->json(['success' => true]);
    }

    /**
     * POST /api/users/{id}/block — block a user
     */
    #[Route('/users/{id}/block', name: 'api_user_block', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function block(int $id, UserRepository $userRepo, EntityManagerInterface $em): JsonResponse
    {
        $blockedUser = $userRepo->find($id);
        if (!$blockedUser) {
            return $this->json(['success' => false, 'error' => 'User not found'], 404);
        }

        $block = new BlockedUser();
        $block->setBlocker($this->getUser());
        $block->setBlocked($blockedUser);
        $em->persist($block);
        $em->flush();

        return $this->json(['success' => true]);
    }

    /**
     * POST /api/reports — create a report
     */
    #[Route('/reports', name: 'api_report_create', methods: ['POST'])]
    public function report(Request $request, UserRepository $userRepo, ListingRepository $listingRepo, EntityManagerInterface $em): JsonResponse
    {
        $body = json_decode($request->getContent(), true);
        $me = $this->getUser();

        $report = new Report();
        $report->setReporter($me);
        $report->setReportType($body['report_type'] ?? 'user');
        $report->setReason($body['reason'] ?? 'other');
        $report->setDescription($body['description'] ?? '');
        $report->setPriority($body['priority'] ?? 'medium');

        if (!empty($body['reported_user_id'])) {
            $reportedUser = $userRepo->find((int) $body['reported_user_id']);
            if ($reportedUser) {
                $report->setReportedUser($reportedUser);
            }
        }

        if (!empty($body['reported_listing_id'])) {
            $reportedListing = $listingRepo->find((int) $body['reported_listing_id']);
            if ($reportedListing) {
                $report->setReportedListing($reportedListing);
            }
        }

        $em->persist($report);
        $em->flush();

        return $this->json(['success' => true], 201);
    }
}
