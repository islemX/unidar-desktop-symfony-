<?php

namespace App\Controller\Admin;

use App\Entity\Payment;
use App\Repository\PaymentRepository;
use App\Service\CsvExportService;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/payments')]
#[IsGranted('ROLE_ADMIN')]
class PaymentController extends AbstractController
{
    #[Route('', name: 'admin_payments')]
    public function index(Request $request, PaymentRepository $paymentRepo, PaginatorInterface $paginator): Response
    {
        $filters  = $request->query->all();
        $payments = $paginator->paginate(
            $paymentRepo->findByFilters($filters),
            $request->query->getInt('page', 1),
            20
        );

        $stats = $paymentRepo->getStatistics($filters['period'] ?? null);

        return $this->render('admin/payments.html.twig', [
            'payments' => $payments,
            'stats'    => $stats,
        ]);
    }

    #[Route('/statistics', name: 'admin_payment_stats')]
    public function statistics(PaymentRepository $paymentRepo): Response
    {
        return $this->render('admin/payment_stats.html.twig', [
            'stats' => $paymentRepo->getStatistics(),
        ]);
    }

    #[Route('/export', name: 'admin_payment_export')]
    public function export(Request $request, CsvExportService $csvService): Response
    {
        return $csvService->exportPayments($request->query->all());
    }

    #[Route('/{id}', name: 'admin_payment_detail', requirements: ['id' => '\d+'])]
    public function detail(Payment $payment): Response
    {
        return $this->render('admin/payment_detail.html.twig', ['payment' => $payment]);
    }
}
