<?php

namespace App\Controller\Admin;

use App\Entity\AdminAction;
use App\Entity\User;
use App\Enum\AdminActionType;
use App\Enum\UserStatus;
use App\Repository\UserRepository;
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
    public function updateStatus(Request $request, User $user, EntityManagerInterface $em): Response
    {
        $status = $request->request->getString('status');
        $user->setStatus(UserStatus::from($status));

        $action = new AdminAction();
        $action->setAdmin($this->getUser());
        $action->setTargetUser($user);
        $action->setActionType($status === 'banned' ? AdminActionType::BanUser : AdminActionType::SuspendUser);
        $action->setNotes('Status changed to ' . $status);
        $em->persist($action);
        $em->flush();

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
