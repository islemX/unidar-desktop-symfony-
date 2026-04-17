<?php

namespace App\Controller;

use App\Entity\Listing;
use App\Entity\ListingImage;
use App\Entity\SavedListing;
use App\Form\ListingFilterType;
use App\Form\ListingType;
use App\Repository\ListingRepository;
use App\Repository\SavedListingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

class ListingController extends AbstractController
{
    #[Route('/listings', name: 'listing_index')]
    public function index(
        Request $request,
        ListingRepository $listingRepo,
        PaginatorInterface $paginator
    ): Response {
        $filterForm = $this->createForm(ListingFilterType::class, null, [
            'method' => 'GET',
        ]);
        $filterForm->handleRequest($request);

        $filters = $filterForm->isSubmitted() && $filterForm->isValid()
            ? $filterForm->getData()
            : [];

        $query = $listingRepo->findByFilters($filters);

        $listings = $paginator->paginate($query, $request->query->getInt('page', 1), 12);

        return $this->render('listing/index.html.twig', [
            'listings'    => $listings,
            'filter_form' => $filterForm,
        ]);
    }

    #[Route('/listings/new', name: 'listing_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_OWNER')]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger
    ): Response {
        $listing = new Listing();
        $form = $this->createForm(ListingType::class, $listing);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $listing->setOwner($this->getUser());

            // Owner signature is required on creation
            $signature = (string) $form->get('ownerSignature')->getData();
            if ($signature === '') {
                $this->addFlash('error', 'A digital signature is required to publish a listing.');
                return $this->render('listing/new.html.twig', ['form' => $form]);
            }
            $signaturePath = $this->saveOwnerSignature($signature);
            if ($signaturePath !== null) {
                $listing->setOwnerSignaturePath($signaturePath);
            }

            $imageFiles = $form->get('images')->getData();
            $order = 0;
            foreach ($imageFiles as $imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('kernel.project_dir') . '/public/uploads/listings',
                        $newFilename
                    );
                    $img = new ListingImage();
                    $img->setImagePath($newFilename);
                    $img->setDisplayOrder($order++);
                    $img->setListing($listing);
                    $listing->addImage($img);
                    $em->persist($img);
                } catch (FileException) {
                    // ignore upload errors
                }
            }

            $em->persist($listing);
            $em->flush();

            $this->addFlash('success', 'Listing created successfully!');
            return $this->redirectToRoute('listing_show', ['id' => $listing->getId()]);
        }

        return $this->render('listing/new.html.twig', ['form' => $form]);
    }

    #[Route('/listings/{id}/edit', name: 'listing_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Listing $listing,
        EntityManagerInterface $em,
        SluggerInterface $slugger
    ): Response {
        $this->denyAccessUnlessGranted('LISTING_EDIT', $listing);

        $form = $this->createForm(ListingType::class, $listing);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // If the owner re-signed, replace the stored signature.
            $signature = (string) $form->get('ownerSignature')->getData();
            if ($signature !== '') {
                $signaturePath = $this->saveOwnerSignature($signature);
                if ($signaturePath !== null) {
                    $listing->setOwnerSignaturePath($signaturePath);
                }
            }

            $imageFiles = $form->get('images')->getData();
            $order = count($listing->getImages());
            foreach ($imageFiles as $imageFile) {
                $safeFilename = $slugger->slug(pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME));
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();
                try {
                    $imageFile->move(
                        $this->getParameter('kernel.project_dir') . '/public/uploads/listings',
                        $newFilename
                    );
                    $img = new ListingImage();
                    $img->setImagePath($newFilename);
                    $img->setDisplayOrder($order++);
                    $img->setListing($listing);
                    $listing->addImage($img);
                    $em->persist($img);
                } catch (FileException) {}
            }

            $em->flush();
            $this->addFlash('success', 'Listing updated!');
            return $this->redirectToRoute('listing_show', ['id' => $listing->getId()]);
        }

        return $this->render('listing/edit.html.twig', ['form' => $form, 'listing' => $listing]);
    }

    #[Route('/listings/{id}', name: 'listing_show', requirements: ['id' => '\d+'])]
    public function show(
        Listing $listing,
        SavedListingRepository $savedRepo
    ): Response {
        $isSaved = false;
        if ($this->getUser()) {
            $saved = $savedRepo->findOneBy(['user' => $this->getUser(), 'listing' => $listing]);
            $isSaved = $saved !== null;
        }

        return $this->render('listing/show.html.twig', [
            'listing'  => $listing,
            'is_saved' => $isSaved,
        ]);
    }

    #[Route('/listings/{id}/delete', name: 'listing_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Listing $listing, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('LISTING_DELETE', $listing);

        if ($this->isCsrfTokenValid('delete' . $listing->getId(), $request->getPayload()->getString('_token'))) {
            $listing->setStatus('removed');
            $em->flush();
            $this->addFlash('success', 'Listing removed.');
        }

        return $this->redirectToRoute('listing_my');
    }

    #[Route('/my-listings', name: 'listing_my')]
    #[IsGranted('ROLE_OWNER')]
    public function myListings(ListingRepository $listingRepo): Response
    {
        $listings = $listingRepo->findByOwner($this->getUser());
        return $this->render('listing/my_listings.html.twig', ['listings' => $listings]);
    }

    #[Route('/listings/{id}/save', name: 'listing_save', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function toggleSave(Listing $listing, EntityManagerInterface $em, SavedListingRepository $savedRepo): Response
    {
        $user = $this->getUser();
        $saved = $savedRepo->findOneBy(['user' => $user, 'listing' => $listing]);

        if ($saved) {
            $em->remove($saved);
            $this->addFlash('success', 'Removed from saved listings.');
        } else {
            $save = new SavedListing();
            $save->setUser($user);
            $save->setListing($listing);
            $em->persist($save);
            $this->addFlash('success', 'Listing saved!');
        }

        $em->flush();
        return $this->redirectToRoute('listing_show', ['id' => $listing->getId()]);
    }

    #[Route('/saved-listings', name: 'listing_saved')]
    #[IsGranted('ROLE_USER')]
    public function savedListings(SavedListingRepository $savedRepo): Response
    {
        $saved = $savedRepo->findBy(['user' => $this->getUser()]);
        return $this->render('listing/saved.html.twig', ['saved' => $saved]);
    }

    /**
     * Decode a data-URI base64 PNG and persist it to /public/uploads/signatures/.
     * Returns the path relative to /public, or null on failure.
     */
    private function saveOwnerSignature(string $dataUri): ?string
    {
        $payload = $dataUri;
        if (str_contains($payload, ',')) {
            $payload = substr($payload, strpos($payload, ',') + 1);
        }
        // Some browsers urlencode "+" as space; restore.
        $payload = str_replace(' ', '+', $payload);

        $bytes = base64_decode($payload, true);
        if ($bytes === false || $bytes === '') {
            return null;
        }

        $dir = $this->getParameter('kernel.project_dir') . '/public/uploads/signatures';
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return null;
        }

        $userId   = $this->getUser()?->getId() ?? 0;
        $filename = sprintf('owner_%d_%d.png', $userId, time());
        if (file_put_contents($dir . '/' . $filename, $bytes) === false) {
            return null;
        }
        return 'uploads/signatures/' . $filename;
    }
}
