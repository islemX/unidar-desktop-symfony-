<?php

namespace App\Controller\Api;

use App\Entity\ListingImage;
use App\Entity\Listing;
use App\Repository\ListingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/api')]
class ListingApiController extends AbstractController
{
    #[Route('/listings', name: 'api_listings', methods: ['GET'])]
    public function index(Request $request, ListingRepository $listingRepo): JsonResponse
    {
        $listings = $listingRepo->findByFilters($request->query->all());
        $data = array_map(fn($l) => [
            'id'           => $l->getId(),
            'title'        => $l->getTitle(),
            'price'        => $l->getPrice(),
            'address'      => $l->getAddress(),
            'latitude'     => $l->getLatitude(),
            'longitude'    => $l->getLongitude(),
            'propertyType' => $l->getPropertyType()->value,
            'bedrooms'     => $l->getBedrooms(),
            'status'       => $l->getStatus(),
            'image'        => count($l->getImages()) > 0 ? '/uploads/listings/' . $l->getImages()->first()->getImagePath() : null,
        ], is_array($listings) ? $listings : iterator_to_array($listings));

        return $this->json($data);
    }

    #[Route('/listings/{id}/images', name: 'api_listing_images', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_OWNER')]
    public function uploadImages(
        Request $request,
        Listing $listing,
        EntityManagerInterface $em,
        SluggerInterface $slugger
    ): JsonResponse {
        $files = $request->files->get('images', []);
        $uploaded = [];

        foreach ((array)$files as $file) {
            $safe = $slugger->slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
            $filename = $safe . '-' . uniqid() . '.' . $file->guessExtension();
            $file->move(
                $this->getParameter('kernel.project_dir') . '/public/uploads/listings',
                $filename
            );
            $img = new ListingImage();
            $img->setImagePath($filename);
            $img->setDisplayOrder(count($listing->getImages()));
            $img->setListing($listing);
            $em->persist($img);
            $uploaded[] = $filename;
        }

        $em->flush();
        return $this->json(['uploaded' => $uploaded]);
    }
}
