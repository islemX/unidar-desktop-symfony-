<?php

namespace App\Controller\Admin;

use App\Entity\ContractTerminationRequest;
use App\Enum\ContractStatus;
use App\Repository\ContractRepository;
use App\Repository\ListingRepository;
use App\Repository\PaymentRepository;
use App\Repository\ReportRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;
use App\Repository\VerificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class DashboardController extends AbstractController
{
    #[Route('', name: 'admin_dashboard')]
    public function index(
        Request                $request,
        UserRepository         $userRepo,
        ListingRepository      $listingRepo,
        ContractRepository     $contractRepo,
        PaymentRepository      $paymentRepo,
        VerificationRepository $verificationRepo,
        ReportRepository       $reportRepo,
        SubscriptionRepository $subscriptionRepo,
        EntityManagerInterface $em
    ): Response {
        $search = trim((string) $request->query->get('search', ''));
        $role   = trim((string) $request->query->get('role', ''));

        $users  = $userRepo->searchUsers($search ?: null, $role ?: null);

        // Payments stats
        $payments        = $paymentRepo->findBy([]);
        $totalPayments   = count($payments);
        $totalRevenue    = 0.0;
        $totalCommission = 0.0;
        foreach ($payments as $p) {
            $totalRevenue += (float) $p->getAmount();
            if (method_exists($p, 'getPlatformFee') && $p->getPlatformFee() !== null) {
                $totalCommission += (float) $p->getPlatformFee();
            }
        }
        if ($totalCommission === 0.0) {
            $totalCommission = $totalRevenue * 0.05;
        }

        $contracts = $contractRepo->findBy([], ['createdAt' => 'DESC']);

        // Only non-terminal contracts count toward the KPI stat card
        $terminalStatuses = [ContractStatus::Cancelled, ContractStatus::Completed];
        $activeContractCount = count(array_filter(
            $contracts,
            fn($c) => !in_array($c->getStatus(), $terminalStatuses, true)
        ));

        // Pending termination requests (status = 'pending')
        $terminationRequests = $em->getRepository(ContractTerminationRequest::class)
            ->findBy(['status' => 'pending'], ['createdAt' => 'DESC']);

        $stats = [
            'total_users'           => count($userRepo->findAll()),
            'active_listings'       => $listingRepo->countActive(),
            'pending_verifications' => count($verificationRepo->findPending()),
            'open_reports'          => $reportRepo->countOpen(),
            'active_subscriptions'  => $subscriptionRepo->countActive(),
            'total_contracts'       => $activeContractCount,
            'total_payments'        => $totalPayments,
            'total_revenue'         => $totalRevenue,
            'total_commission'      => $totalCommission,
        ];

        return $this->render('admin/dashboard.html.twig', [
            'stats'         => $stats,
            'verifications' => $verificationRepo->findPending(),
            'reports'       => array_slice($reportRepo->findOpen(), 0, 10),
            'users'         => $users,
            'contracts'     => array_slice($contracts, 0, 12),
            'termination_requests' => $terminationRequests,
            'search'        => $search,
            'role_filter'   => $role,
        ]);
    }
}
