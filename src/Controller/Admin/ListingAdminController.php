<?php

namespace App\Controller\Admin;

use App\Entity\Listing;
use App\Repository\ListingRepository;
use App\Service\InAppNotificationService;
use App\Service\MailerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/listings')]
#[IsGranted('ROLE_ADMIN')]
class ListingAdminController extends AbstractController
{
    #[Route('', name: 'admin_listings')]
    public function index(ListingRepository $listingRepo): Response
    {
        return $this->render('admin/listings.html.twig', [
            'pending'  => $listingRepo->findBy(['status' => 'pending'], ['id' => 'DESC']),
            'active'   => $listingRepo->findBy(['status' => 'active'],  ['id' => 'DESC']),
            'rejected' => $listingRepo->findBy(['status' => 'rejected'], ['id' => 'DESC']),
        ]);
    }

    #[Route('/{id}/review', name: 'admin_listing_review', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function review(
        Request $request,
        Listing $listing,
        EntityManagerInterface $em,
        InAppNotificationService $notifier,
        MailerService $mailer,
    ): Response {
        $action = $request->request->getString('action'); // 'approve' or 'reject'
        $reason = $request->request->getString('reason');
        $owner  = $listing->getOwner();

        if ($action === 'approve') {
            $listing->setStatus('active');
            if ($owner) {
                $notifier->notify($owner, sprintf(
                    "Bonjour %s,\n\nVotre annonce « %s » a été approuvée ✅ Elle est maintenant visible par tous les étudiants sur UNIDAR.\n\n— L'équipe UNIDAR",
                    $owner->getFullName(),
                    $listing->getTitle()
                ));
                $mailer->sendListingApproved($owner, $listing);
            }
            $this->addFlash('success', 'Listing approved and published.');
        } else {
            $listing->setStatus('rejected');
            if ($owner) {
                $notifier->notify($owner, sprintf(
                    "Bonjour %s,\n\nVotre annonce « %s » n'a pas été approuvée ❌\nMotif : %s\n\nVeuillez la modifier et la soumettre à nouveau depuis votre tableau de bord.\n\n— L'équipe UNIDAR",
                    $owner->getFullName(),
                    $listing->getTitle(),
                    $reason ?: 'Non conforme aux conditions de la plateforme'
                ));
                $mailer->sendListingRejected($owner, $listing, $reason);
            }
            $this->addFlash('success', 'Listing rejected.');
        }

        $em->flush();
        return $this->redirectToRoute('admin_listings');
    }
}
