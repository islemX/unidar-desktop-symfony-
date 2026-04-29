<?php

namespace App\Service;

use App\Repository\PaymentRepository;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvExportService
{
    public function __construct(private PaymentRepository $paymentRepository) {}

    public function exportPayments(array $filters = []): StreamedResponse
    {
        $payments = $this->paymentRepository->findByFilters($filters);

        $response = new StreamedResponse(function () use ($payments) {
            $handle = fopen('php://output', 'w');

            // BOM for Excel UTF-8
            fwrite($handle, "\xEF\xBB\xBF");

            // Header row
            fputcsv($handle, [
                'ID',
                'Contract #',
                'Student',
                'Owner',
                'Amount (TND)',
                'Owner Amount (TND)',
                'Platform Fee (TND)',
                'Status',
                'Payment Method',
                'Transaction ID',
                'Type',
                'Date',
            ]);

            foreach ($payments as $payment) {
                $contract = $payment->getContract();
                fputcsv($handle, [
                    $payment->getId(),
                    $contract ? $contract->getContractNumber() : '',
                    $contract && $contract->getStudent() ? $contract->getStudent()->getFullName() : '',
                    $contract && $contract->getOwner() ? $contract->getOwner()->getFullName() : '',
                    number_format((float)$payment->getAmount(), 2),
                    number_format((float)$payment->getOwnerAmount(), 2),
                    number_format((float)$payment->getPlatformFee(), 2),
                    $payment->getStatus()->value,
                    $payment->getPaymentMethod(),
                    $payment->getTransactionId() ?? '',
                    $payment->getPaymentType()->value,
                    $payment->getCreatedAt()->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($handle);
        });

        $filename = 'payments_export_' . date('Y-m-d_H-i-s') . '.csv';
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }
}
