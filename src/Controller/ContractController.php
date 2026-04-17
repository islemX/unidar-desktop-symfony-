<?php

namespace App\Controller;

use App\Entity\Contract;
use App\Entity\ContractTerminationRequest;
use App\Form\ContractGenerateType;
use App\Form\PaymentType;
use App\Form\SignatureType;
use App\Repository\ContractRepository;
use App\Repository\ListingRepository;
use App\Service\ContractManager;
use App\Service\PaymentProcessor;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/contracts')]
class ContractController extends AbstractController
{
    #[Route('', name: 'contract_index')]
    #[IsGranted('ROLE_USER')]
    public function index(ContractRepository $repo): Response
    {
        $contracts = $repo->findByUser($this->getUser());
        return $this->render('contract/index.html.twig', ['contracts' => $contracts]);
    }

    #[Route('/{id}', name: 'contract_show', requirements: ['id' => '\d+'])]
    public function show(Contract $contract): Response
    {
        $this->denyAccessUnlessGranted('CONTRACT_VIEW', $contract);
        return $this->render('contract/show.html.twig', ['contract' => $contract]);
    }

    #[Route('/generate', name: 'contract_generate', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_OWNER')]
    public function generate(
        Request $request,
        ContractManager $contractManager,
        ListingRepository $listingRepo
    ): Response {
        $form = $this->createForm(ContractGenerateType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $listing = $listingRepo->find($data['listingId']);

            if (!$listing) {
                $this->addFlash('error', 'Listing not found.');
                return $this->redirectToRoute('listing_my');
            }

            // Find student - for now redirect with error if no student
            $this->addFlash('error', 'Please initiate a contract from the listing page.');
            return $this->redirectToRoute('listing_my');
        }

        return $this->render('contract/generate.html.twig', ['form' => $form]);
    }

    #[Route('/generate-from-listing/{id}', name: 'contract_generate_from_listing', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_STUDENT')]
    public function generateFromListing(
        Request $request,
        \App\Entity\Listing $listing,
        ContractManager $contractManager
    ): Response {
        $startMonth = $request->request->get('startMonth');
        $duration = (int) $request->request->get('duration', 9);

        if (!$startMonth) {
            $this->addFlash('error', 'Please select a move-in month.');
            return $this->redirectToRoute('listing_show', ['id' => $listing->getId()]);
        }

        $startDate = new \DateTimeImmutable($startMonth . '-01');
        $contract = $contractManager->generateContract($listing, $this->getUser(), $startDate, $duration);

        $this->addFlash('success', 'Contract generated successfully! Please review and sign.');
        return $this->redirectToRoute('contract_show', ['id' => $contract->getId()]);
    }

    #[Route('/{id}/sign', name: 'contract_sign', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function sign(Request $request, Contract $contract, ContractManager $contractManager): Response
    {
        $this->denyAccessUnlessGranted('CONTRACT_SIGN', $contract);

        $signatureData = $request->request->get('signature');
        if ($signatureData) {
            $contractManager->signContract($contract, $this->getUser(), $signatureData);
            $this->addFlash('success', 'Contract signed successfully!');
        } else {
            $this->addFlash('error', 'No signature provided.');
        }

        return $this->redirectToRoute('contract_show', ['id' => $contract->getId()]);
    }

    #[Route('/{id}/download', name: 'contract_download', requirements: ['id' => '\d+'])]
    public function download(Contract $contract, ContractManager $contractManager): Response
    {
        $this->denyAccessUnlessGranted('CONTRACT_VIEW', $contract);

        $content = $contractManager->downloadContract($contract);
        return new Response($content, 200, [
            'Content-Type'        => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="contract-' . $contract->getContractNumber() . '.html"',
        ]);
    }

    #[Route('/{id}/pay', name: 'contract_pay', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_STUDENT')]
    public function pay(Request $request, Contract $contract, PaymentProcessor $paymentProcessor): Response
    {
        $this->denyAccessUnlessGranted('CONTRACT_VIEW', $contract);

        if ($request->isMethod('POST')) {
            // Method comes from either the simulated card flow (hidden input "method=card")
            // or the transfer button (method=transfer). Default to card.
            $method = $request->request->get('method', 'card');
            if (!in_array($method, ['card', 'transfer'], true)) {
                $method = 'card';
            }

            $payment = $paymentProcessor->processPayment($contract, $method);

            if ($payment->getStatus()->value === 'completed') {
                $this->addFlash('success', 'Payment successful! Contract is now active.');
            } else {
                $this->addFlash('error', 'Payment failed. Please try again.');
            }

            return $this->redirectToRoute('contract_show', ['id' => $contract->getId()]);
        }

        return $this->render('contract/pay.html.twig', [
            'contract' => $contract,
        ]);
    }

    #[Route('/{id}/terminate', name: 'contract_terminate', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function terminate(Contract $contract, ContractManager $contractManager): Response
    {
        $this->denyAccessUnlessGranted('CONTRACT_TERMINATE', $contract);
        $contractManager->terminateContract($contract);
        $this->addFlash('success', 'Contract terminated.');
        return $this->redirectToRoute('contract_show', ['id' => $contract->getId()]);
    }

    #[Route('/{id}/request-termination', name: 'contract_request_termination', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function requestTermination(Request $request, Contract $contract, ContractManager $contractManager): Response
    {
        $this->denyAccessUnlessGranted('CONTRACT_REQUEST_TERMINATION', $contract);
        $reason = $request->request->get('reason', 'No reason provided.');
        $contractManager->requestTermination($contract, $this->getUser(), $reason);
        $this->addFlash('success', 'Termination request submitted.');
        return $this->redirectToRoute('contract_show', ['id' => $contract->getId()]);
    }

    #[Route('/{id}/approve-termination/{requestId}', name: 'contract_approve_termination', requirements: ['id' => '\d+', 'requestId' => '\d+'], methods: ['POST'])]
    public function approveTermination(
        Contract $contract,
        #[\Symfony\Bridge\Doctrine\Attribute\MapEntity(mapping: ['requestId' => 'id'])]
        ContractTerminationRequest $terminationRequest,
        ContractManager $contractManager
    ): Response {
        $this->denyAccessUnlessGranted('CONTRACT_TERMINATE', $contract);
        $contractManager->approveTermination($terminationRequest);
        $this->addFlash('success', 'Termination approved.');
        return $this->redirectToRoute('contract_show', ['id' => $contract->getId()]);
    }

    #[Route('/{id}/reject-termination/{requestId}', name: 'contract_reject_termination', requirements: ['id' => '\d+', 'requestId' => '\d+'], methods: ['POST'])]
    public function rejectTermination(
        Contract $contract,
        #[\Symfony\Bridge\Doctrine\Attribute\MapEntity(mapping: ['requestId' => 'id'])]
        ContractTerminationRequest $terminationRequest,
        ContractManager $contractManager
    ): Response {
        $this->denyAccessUnlessGranted('CONTRACT_TERMINATE', $contract);
        $contractManager->rejectTermination($terminationRequest);
        $this->addFlash('success', 'Termination rejected.');
        return $this->redirectToRoute('contract_show', ['id' => $contract->getId()]);
    }
}
