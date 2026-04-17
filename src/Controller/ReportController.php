<?php

namespace App\Controller;

use App\Entity\Report;
use App\Form\ReportType;
use App\Repository\ListingRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ReportController extends AbstractController
{
    #[Route('/reports', name: 'report_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        UserRepository $userRepo,
        ListingRepository $listingRepo
    ): Response {
        $report = new Report();
        $form = $this->createForm(ReportType::class, $report);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $report->setReporter($this->getUser());

            $reportedUserId    = $form->get('reportedUserId')->getData();
            $reportedListingId = $form->get('reportedListingId')->getData();

            if ($reportedUserId) {
                $report->setReportedUser($userRepo->find($reportedUserId));
            }
            if ($reportedListingId) {
                $report->setReportedListing($listingRepo->find($reportedListingId));
            }

            $em->persist($report);
            $em->flush();
            $this->addFlash('success', 'Report submitted. Thank you!');
        }

        return $this->redirectToRoute('app_home');
    }
}
