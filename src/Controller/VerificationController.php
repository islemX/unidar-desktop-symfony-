<?php

namespace App\Controller;

use App\Entity\Verification;
use App\Enum\VerificationStatus;
use App\Form\VerificationSubmitType;
use App\Repository\VerificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/verification')]
#[IsGranted('ROLE_USER')]
class VerificationController extends AbstractController
{
    #[Route('', name: 'verification_submit', methods: ['GET', 'POST'])]
    public function submit(
        Request $request,
        EntityManagerInterface $em,
        VerificationRepository $verificationRepo,
        SluggerInterface $slugger
    ): Response {
        $existingList = $verificationRepo->findByUser($this->getUser());
        $existing = $existingList[0] ?? null;

        $form = $this->createForm(VerificationSubmitType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/verifications';

            $verification = $existing ?? new Verification();
            $verification->setUser($this->getUser());
            $verification->setStatus(VerificationStatus::Pending);
            $verification->setSubmittedAt(new \DateTimeImmutable());

            foreach (['studentIdFile', 'nationalIdFile'] as $field) {
                $file = $form->get($field)->getData();
                if ($file) {
                    $safe = $slugger->slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
                    $filename = $safe . '-' . uniqid() . '.' . $file->guessExtension();
                    try {
                        $file->move($uploadDir, $filename);
                        $setter = 'set' . ucfirst($field);
                        $verification->$setter($filename);
                    } catch (FileException) {}
                }
            }

            $em->persist($verification);
            $em->flush();
            $this->addFlash('success', 'Verification submitted successfully! We will review it soon.');
            return $this->redirectToRoute('verification_submit');
        }

        return $this->render('verification/submit.html.twig', [
            'form'     => $form,
            'existing' => $existingList,
        ]);
    }
}
