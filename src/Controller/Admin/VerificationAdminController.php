<?php

namespace App\Controller\Admin;

use App\Entity\AdminAction;
use App\Entity\Verification;
use App\Enum\AdminActionType;
use App\Enum\VerificationStatus;
use App\Repository\VerificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/verifications')]
#[IsGranted('ROLE_ADMIN')]
class VerificationAdminController extends AbstractController
{
    #[Route('', name: 'admin_verifications')]
    public function index(VerificationRepository $verificationRepo): Response
    {
        return $this->render('admin/verifications.html.twig', [
            'verifications' => $verificationRepo->findPending(),
            'all'           => $verificationRepo->findAll(),
        ]);
    }

    #[Route('/{id}', name: 'admin_verification_review', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function review(Request $request, Verification $verification, EntityManagerInterface $em): Response
    {
        $action = $request->request->getString('action'); // 'approve' or 'reject'
        $reason = $request->request->getString('rejectionReason');

        if ($action === 'approve') {
            $verification->setStatus(VerificationStatus::Approved);
            $actionType = AdminActionType::VerifyUser;
        } else {
            $verification->setStatus(VerificationStatus::Rejected);
            $verification->setRejectionReason($reason);
            $actionType = AdminActionType::RejectVerification;
        }

        $verification->setReviewedAt(new \DateTimeImmutable());
        $verification->setReviewedBy($this->getUser());

        $adminAction = new AdminAction();
        $adminAction->setAdmin($this->getUser());
        $adminAction->setTargetUser($verification->getUser());
        $adminAction->setActionType($actionType);
        $em->persist($adminAction);
        $em->flush();

        $this->addFlash('success', 'Verification ' . $action . 'd.');
        return $this->redirectToRoute('admin_verifications');
    }
}
