<?php

namespace App\Controller;

use App\Entity\BlockedUser;
use App\Entity\Conversation;
use App\Entity\Message;
use App\Form\MessageType;
use App\Repository\ConversationRepository;
use App\Repository\ListingRepository;
use App\Repository\MessageRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/messages')]
#[IsGranted('ROLE_USER')]
class MessageController extends AbstractController
{
    #[Route('', name: 'message_index')]
    public function index(ConversationRepository $convRepo): Response
    {
        $conversations = $convRepo->findByUser($this->getUser());
        return $this->render('message/index.html.twig', ['conversations' => $conversations]);
    }

    #[Route('/new', name: 'message_create', methods: ['POST'])]
    public function createConversation(
        Request $request,
        EntityManagerInterface $em,
        UserRepository $userRepo,
        ListingRepository $listingRepo,
        ConversationRepository $convRepo
    ): Response {
        $recipientId = $request->request->getInt('recipientId');
        $listingId   = $request->request->getInt('listingId');

        $recipient = $userRepo->find($recipientId);
        if (!$recipient) {
            $this->addFlash('error', 'User not found.');
            return $this->redirectToRoute('message_index');
        }

        $listing = $listingId ? $listingRepo->find($listingId) : null;
        $me = $this->getUser();

        // Check existing conversation
        $existing = $convRepo->findBetweenUsers($me, $recipient, $listing);
        if ($existing) {
            return $this->redirectToRoute('message_conversation', ['id' => $existing->getId()]);
        }

        $conv = new Conversation();
        $conv->setUser1($me);
        $conv->setUser2($recipient);
        $conv->setListing($listing);
        $em->persist($conv);
        $em->flush();

        return $this->redirectToRoute('message_conversation', ['id' => $conv->getId()]);
    }

    #[Route('/{id}', name: 'message_conversation', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function conversation(
        Request $request,
        Conversation $conversation,
        MessageRepository $msgRepo,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('CONVERSATION_VIEW', $conversation);

        // Mark all messages as read
        $me = $this->getUser();
        $unread = $msgRepo->findBy(['conversation' => $conversation, 'receiver' => $me, 'isRead' => false]);
        foreach ($unread as $msg) {
            $msg->setIsRead(true);
            $msg->setReadAt(new \DateTimeImmutable());
        }

        $form = $this->createForm(MessageType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $recipient = $conversation->getUser1() === $me ? $conversation->getUser2() : $conversation->getUser1();
            $message = new Message();
            $message->setConversation($conversation);
            $message->setSender($me);
            $message->setReceiver($recipient);
            $message->setMessage($form->get('message')->getData());
            $em->persist($message);
            $em->flush();

            return $this->redirectToRoute('message_conversation', ['id' => $conversation->getId()]);
        }

        $em->flush();

        $messages = $msgRepo->findByConversation($conversation);

        return $this->render('message/conversation.html.twig', [
            'conversation' => $conversation,
            'messages'     => $messages,
            'form'         => $form,
        ]);
    }

    #[Route('/{id}/archive', name: 'message_archive', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function archive(Conversation $conversation, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('CONVERSATION_VIEW', $conversation);

        $me = $this->getUser();
        if ($conversation->getUser1() === $me) {
            $conversation->setIsArchivedUser1(true);
        } else {
            $conversation->setIsArchivedUser2(true);
        }

        $em->flush();
        $this->addFlash('success', 'Conversation archived.');
        return $this->redirectToRoute('message_index');
    }

    #[Route('/{id}/delete', name: 'message_delete_conversation', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteConversation(Conversation $conversation, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('CONVERSATION_VIEW', $conversation);

        $me = $this->getUser();
        if ($conversation->getUser1() === $me) {
            $conversation->setIsDeletedUser1(true);
        } else {
            $conversation->setIsDeletedUser2(true);
        }

        $em->flush();
        $this->addFlash('success', 'Conversation deleted.');
        return $this->redirectToRoute('message_index');
    }

    #[Route('/block/{id}', name: 'message_block', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function block(int $id, UserRepository $userRepo, EntityManagerInterface $em): Response
    {
        $blockedUser = $userRepo->find($id);
        if ($blockedUser) {
            $block = new BlockedUser();
            $block->setBlocker($this->getUser());
            $block->setBlocked($blockedUser);
            $em->persist($block);
            $em->flush();
            $this->addFlash('success', 'User blocked.');
        }

        return $this->redirectToRoute('message_index');
    }
}
