<?php

namespace App\Controller\Api;

use App\Repository\FacultyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class FacultyApiController extends AbstractController
{
    #[Route('/api/faculties', name: 'api_faculties', methods: ['GET'])]
    public function index(FacultyRepository $facultyRepo): JsonResponse
    {
        $faculties = $facultyRepo->findAllOrderedByName();
        $data = array_map(fn($f) => [
            'id'          => $f->getId(),
            'name'        => $f->getName(),
            'city'        => $f->getCity(),
            'governorate' => $f->getGovernorate(),
            'address'     => $f->getAddress(),
            'latitude'    => $f->getLatitude(),
            'longitude'   => $f->getLongitude(),
        ], $faculties);

        return $this->json($data);
    }
}
