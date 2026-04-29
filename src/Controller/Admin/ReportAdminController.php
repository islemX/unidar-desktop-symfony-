<?php

namespace App\Controller\Admin;

use App\Entity\AdminAction;
use App\Entity\Report;
use App\Enum\AdminActionType;
use App\Enum\ReportStatus;
use App\Enum\UserStatus;
use App\Form\ReportResolutionType;
use App\Repository\ReportRepository;
use App\Service\InAppNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/reports')]
#[IsGranted('ROLE_ADMIN')]
class ReportAdminController extends AbstractController
{
    #[Route('', name: 'admin_reports')]
    public function index(ReportRepository $reportRepo): Response
    {
        return $this->render('admin/reports.html.twig', [
            'open_reports' => $reportRepo->findOpen(),
            'all_reports'  => $reportRepo->findAll(),
        ]);
    }

    #[Route('/{id}/resolve', name: 'admin_report_resolve', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function resolve(
        Request $request,
        Report $report,
        EntityManagerInterface $em,
        InAppNotificationService $notifier,
    ): Response {
        $form = $this->createForm(ReportResolutionType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data   = $form->getData();
            $action = $data['action'];
            $notes  = $data['resolutionNotes'];

            $report->setStatus($action === 'dismiss' ? ReportStatus::Dismissed : ReportStatus::Resolved);
            $report->setResolutionNotes($notes);
            $report->setResolvedBy($this->getUser());

            // Side effects + notifications
            if ($action === 'ban_user' && $report->getReportedUser()) {
                $bannedUser = $report->getReportedUser();
                $bannedUser->setStatus(UserStatus::Banned);
                $notifier->notify($bannedUser, sprintf(
                    "Bonjour %s,\n\nVotre compte UNIDAR a été banni suite à un signalement ❌\nMotif : %s\n\nPour contester cette décision, contactez le support UNIDAR.\n\n— L'équipe UNIDAR",
                    $bannedUser->getFullName(),
                    $notes ?: 'Violation des conditions d\'utilisation'
                ));
            }
            if ($action === 'remove_listing' && $report->getReportedListing()) {
                $listing = $report->getReportedListing();
                $listing->setStatus('removed');
                $owner = $listing->getOwner();
                if ($owner) {
                    $notifier->notify($owner, sprintf(
                        "Bonjour %s,\n\nVotre annonce « %s » a été retirée de la plateforme suite à un signalement ❌\nMotif : %s\n\nPour toute question, contactez le support UNIDAR.\n\n— L'équipe UNIDAR",
                        $owner->getFullName(),
                        $listing->getTitle(),
                        $notes ?: 'Non conforme aux conditions d\'utilisation'
                    ));
                }
            }

            $adminAction = new AdminAction();
            $adminAction->setAdmin($this->getUser());
            $adminAction->setTargetReport($report);
            $adminAction->setActionType(AdminActionType::ResolveReport);
            $adminAction->setNotes($notes);
            $em->persist($adminAction);
            $em->flush();

            $this->addFlash('success', 'Report resolved.');
        }

        return $this->redirectToRoute('admin_reports');
    }
}
