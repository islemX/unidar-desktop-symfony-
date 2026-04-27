<?php

namespace App\Controller\Admin;

use App\Entity\AdminAction;
use App\Entity\User;
use App\Enum\AdminActionType;
use App\Enum\UserStatus;
use App\Repository\UserRepository;
use App\Service\InAppNotificationService;
use App\Service\MailerService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
class UserManagementController extends AbstractController
{
    #[Route('', name: 'admin_users')]
    public function index(Request $request, UserRepository $userRepo, PaginatorInterface $paginator): Response
    {
        $search = $request->query->getString('search');
        $role   = $request->query->getString('role');
        $users  = $paginator->paginate(
            $userRepo->searchUsers($search ?: null, $role ?: null),
            $request->query->getInt('page', 1),
            20
        );

        return $this->render('admin/users.html.twig', ['users' => $users]);
    }

    #[Route('/{id}/status', name: 'admin_user_update', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function updateStatus(
        Request $request,
        User $user,
        EntityManagerInterface $em,
        InAppNotificationService $notifier,
        MailerService $mailer,
    ): Response {
        $status = $request->request->getString('status');
        $cause  = trim($request->request->getString('cause'));
        $user->setStatus(UserStatus::from($status));

        $actionType = match ($status) {
            'banned'    => AdminActionType::BanUser,
            default     => AdminActionType::SuspendUser,
        };
        $notes = $cause ?: 'Status changed to ' . $status;

        $action = new AdminAction();
        $action->setAdmin($this->getUser());
        $action->setTargetUser($user);
        $action->setActionType($actionType);
        $action->setNotes($notes);
        $em->persist($action);
        $em->flush();

        if (in_array($status, ['banned', 'suspended'], true)) {
            $label = $status === 'banned' ? 'banni' : 'suspendu';
            $notifier->notify($user, sprintf(
                "Bonjour %s,\n\nVotre compte UNIDAR a été %s par notre équipe ❌\nMotif : %s\n\nSi vous pensez qu'il s'agit d'une erreur, contactez le support UNIDAR.\n\n— L'équipe UNIDAR",
                $user->getFullName(),
                $label,
                $notes
            ));

            // Transactional email notification
            if ($status === 'banned') {
                $mailer->sendBanNotification($user, $notes);
            } else {
                $mailer->sendSuspensionNotification($user, $notes);
            }
        }

        $this->addFlash('success', 'User status updated.');
        return $this->redirectToRoute('admin_users');
    }

    #[Route('/{id}/delete', name: 'admin_user_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(User $user, EntityManagerInterface $em): Response
    {
        $em->remove($user);
        $em->flush();
        $this->addFlash('success', 'User deleted.');
        return $this->redirectToRoute('admin_users');
    }
}
