<?php

namespace App\Controller;

use App\Enum\ContractStatus;
use App\Repository\ContractRepository;
use App\Repository\ListingRepository;
use App\Repository\MessageRepository;
use App\Repository\SavedListingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'dashboard_student')]
    #[IsGranted('ROLE_STUDENT')]
    public function student(
        ContractRepository $contractRepo,
        MessageRepository $messageRepo,
        SavedListingRepository $savedRepo
    ): Response {
        $user = $this->getUser();

        // Admins (who inherit ROLE_STUDENT via role hierarchy) should go to /admin
        if ($user && $user->getRole()?->value === 'admin') {
            return $this->redirectToRoute('admin_dashboard');
        }
        // Owners (if they land here somehow) go to their dedicated dashboard
        if ($user && $user->getRole()?->value === 'owner') {
            return $this->redirectToRoute('dashboard_owner');
        }
        $contracts   = $contractRepo->findByUser($user);
        $unreadCount = $messageRepo->countUnreadByUser($user);
        $savedCount  = count($savedRepo->findBy(['user' => $user]));

        return $this->render('dashboard/student.html.twig', [
            'contracts'    => $contracts,
            'unread_count' => $unreadCount,
            'saved_count'  => $savedCount,
        ]);
    }

    /**
     * Owner-facing dashboard: KPIs, listing portfolio, contract pipeline.
     */
    #[Route('/dashboard/owner', name: 'dashboard_owner')]
    #[IsGranted('ROLE_OWNER')]
    public function owner(
        ListingRepository $listingRepo,
        ContractRepository $contractRepo,
        MessageRepository $messageRepo,
    ): Response {
        $user = $this->getUser();

        $listings  = $listingRepo->findByOwner($user);
        $contracts = $contractRepo->findByUser($user);

        // ── KPIs ──
        $activeListings = 0;
        $rentedListings = 0;
        foreach ($listings as $l) {
            if ($l->getStatus() === 'active')  { $activeListings++; }
            if ($l->getStatus() === 'rented')  { $rentedListings++; }
        }

        $contractsByStatus = [
            'draft'        => 0,
            'pending_sig'  => 0,
            'active'       => 0,
            'paid'         => 0,
            'completed'    => 0,
            'cancelled'    => 0,
        ];
        $monthlyRevenue = 0.0;
        $needsSignature = []; // contracts the owner still has to sign
        foreach ($contracts as $c) {
            $st = $c->getStatus();
            switch ($st) {
                case ContractStatus::Draft:            $contractsByStatus['draft']++;       break;
                case ContractStatus::SignedByStudent:  $contractsByStatus['pending_sig']++;
                                                       $needsSignature[] = $c;             break;
                case ContractStatus::SignedByBoth:
                case ContractStatus::Active:           $contractsByStatus['active']++;
                                                       $monthlyRevenue += (float) $c->getMonthlyRent(); break;
                case ContractStatus::Paid:             $contractsByStatus['paid']++;
                                                       $monthlyRevenue += (float) $c->getMonthlyRent(); break;
                case ContractStatus::Completed:        $contractsByStatus['completed']++;   break;
                case ContractStatus::Cancelled:        $contractsByStatus['cancelled']++;   break;
            }
        }

        $unreadMessages = $messageRepo->countUnreadByUser($user);

        return $this->render('dashboard/owner.html.twig', [
            'listings'           => $listings,
            'recent_listings'    => array_slice($listings, 0, 4),
            'recent_contracts'   => array_slice($contracts, 0, 6),
            'needs_signature'    => $needsSignature,
            'kpi_active'         => $activeListings,
            'kpi_rented'         => $rentedListings,
            'kpi_contracts'      => count($contracts),
            'kpi_monthly_rev'    => $monthlyRevenue,
            'kpi_unread'         => $unreadMessages,
            'contracts_by_status'=> $contractsByStatus,
        ]);
    }
}
