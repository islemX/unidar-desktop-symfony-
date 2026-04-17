<?php

namespace App\Controller\Admin;

use App\Entity\AdminAction;
use App\Entity\Report;
use App\Enum\AdminActionType;
use App\Enum\ReportStatus;
use App\Enum\UserStatus;
use App\Form\ReportResolutionType;
use App\Repository\ReportRepository;
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
    public function resolve(Request $request, Report $report, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ReportResolutionType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data   = $form->getData();
            $action = $data['action'];
            $notes  = $data['resolutionNotes'];

            $report->setStatus($action === 'dismiss' ? ReportStatus::Dismissed : ReportStatus::Resolved);
            $report->setResolutionNotes($notes);
            $report->setResolvedBy($this->getUser());

            // Side effects
            if ($action === 'ban_user' && $report->getReportedUser()) {
                $report->getReportedUser()->setStatus(UserStatus::Banned);
            }
            if ($action === 'remove_listing' && $report->getReportedListing()) {
                $report->getReportedListing()->setStatus('removed');
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
