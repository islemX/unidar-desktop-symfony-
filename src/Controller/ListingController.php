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

        $rawFilters = $filterForm->isSubmitted() && $filterForm->isValid()
            ? (array) $filterForm->getData()
            : [];

        // Map form camelCase keys to repository snake_case keys, dropping null/empty values
        $keyMap = [
            'minPrice'         => 'min_price',
            'maxPrice'         => 'max_price',
            'bedrooms'         => 'bedrooms',
            'propertyType'     => 'property_type',
            'genderPreference' => 'gender_preference',
            'lat'              => 'lat',
            'lng'              => 'lng',
        ];
        $filters = [];
        foreach ($keyMap as $formKey => $repoKey) {
            if (array_key_exists($formKey, $rawFilters)
                && $rawFilters[$formKey] !== null
                && $rawFilters[$formKey] !== ''
            ) {
                $filters[$repoKey] = $rawFilters[$formKey];
            }
        }

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

            // Location is required — map must have been clicked to set lat/lng
            if ($listing->getLatitude() === null || $listing->getLongitude() === null) {
                $this->addFlash('error', 'Please select a location on the map before publishing.');
                return $this->render('listing/new.html.twig', [
                    'form' => $form,
                    'my_listings' => $em->getRepository(Listing::class)->findBy(['owner' => $this->getUser()], ['createdAt' => 'DESC'], 6),
                ]);
            }

            // Fallback: if reverse-geocode didn't populate address, derive from coords
            if (!$listing->getAddress()) {
                $listing->setAddress(sprintf('%.4f, %.4f', $listing->getLatitude(), $listing->getLongitude()));
            }

            // Owner signature is required on creation
            $signature = (string) $form->get('ownerSignature')->getData();
            if ($signature === '') {
                $this->addFlash('error', 'A digital signature is required to publish a listing.');
                return $this->render('listing/new.html.twig', [
                    'form' => $form,
                    'my_listings' => $em->getRepository(Listing::class)->findBy(['owner' => $this->getUser()], ['createdAt' => 'DESC'], 6),
                ]);
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

        return $this->render('listing/new.html.twig', [
            'form' => $form,
            'my_listings' => $em->getRepository(Listing::class)->findBy(['owner' => $this->getUser()], ['createdAt' => 'DESC'], 6)
        ]);
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
        SavedListingRepository $savedRepo,
        \App\Repository\SubscriptionRepository $subscriptionRepo
    ): Response {
        $isSaved = false;
        if ($this->getUser()) {
            $saved = $savedRepo->findOneBy(['user' => $this->getUser(), 'listing' => $listing]);
            $isSaved = $saved !== null;
        }

        // Occupancy: who currently holds a signed & paid contract on this listing?
        // Once at least one student has a contract in Active/Paid state, additional students
        // must contact the existing tenant(s) instead of the owner, and they can no longer
        // generate a new contract from this page.
        $activeStatuses = [
            \App\Enum\ContractStatus::Active,
            \App\Enum\ContractStatus::Paid,
        ];
        $occupantContracts = [];
        foreach ($listing->getContracts() as $c) {
            if (in_array($c->getStatus(), $activeStatuses, true) && $c->getStudent() !== null) {
                $occupantContracts[] = $c;
            }
        }
        // Sort by creation date ASC so the "first signer" comes out on top.
        usort($occupantContracts, fn($a, $b) => $a->getCreatedAt() <=> $b->getCreatedAt());

        $firstTenant     = !empty($occupantContracts) ? $occupantContracts[0]->getStudent() : null;
        $occupiedCount   = count($occupantContracts);
        $capacity        = $listing->getCapacity() ?? 1;
        $isFull          = $occupiedCount >= $capacity;
        $viewerIsTenant  = $this->getUser() !== null
            && $firstTenant !== null
            && array_filter($occupantContracts, fn($c) => $c->getStudent() === $this->getUser());

        // Subscription status (students only — owners/admins are not gated).
        $hasSubscription = true;
        if ($this->getUser() && $this->isGranted('ROLE_STUDENT')
            && !$this->isGranted('ROLE_OWNER') && !$this->isGranted('ROLE_ADMIN')
        ) {
            $hasSubscription = $subscriptionRepo->findActiveByUser($this->getUser()) !== null;
        }

        return $this->render('listing/show.html.twig', [
            'listing'          => $listing,
            'is_saved'         => $isSaved,
            'first_tenant'     => $firstTenant,
            'occupied_count'   => $occupiedCount,
            'is_full'          => $isFull,
            'viewer_is_tenant' => (bool) $viewerIsTenant,
            'has_subscription' => $hasSubscription,
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
