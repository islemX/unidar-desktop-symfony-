<?php

namespace App\Controller\Api;

use App\Repository\FacultyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
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

    #[Route('/api/faculties/search', name: 'api_faculties_search', methods: ['GET'])]
    public function search(Request $request, FacultyRepository $facultyRepo): JsonResponse
    {
        $query = trim($request->query->get('q', ''));

        if ($query === '') {
            return $this->json([]);
        }

        $faculties = $facultyRepo->searchByName($query);
        $data = array_map(fn($f) => [
            'id'        => $f->getId(),
            'name'      => $f->getName(),
            'address'   => $f->getAddress(),
            'latitude'  => $f->getLatitude(),
            'longitude' => $f->getLongitude(),
        ], $faculties);

        return $this->json($data);
    }
}
