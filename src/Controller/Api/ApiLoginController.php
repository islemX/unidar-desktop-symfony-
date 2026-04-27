<?php

namespace App\Controller\Api;

use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class ApiLoginController extends AbstractController
{
    /**
     * POST /api/login
     * Authenticates via email+password and returns a JWT token.
     * The LexikJWT bundle handles credential validation; this action
     * is only reached on success — on failure the bundle returns 401 automatically.
     */
    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(
        #[CurrentUser] ?User $user,
        JWTTokenManagerInterface $jwtManager,
    ): JsonResponse {
        if (!$user) {
            return $this->json(['error' => 'Invalid credentials.'], 401);
        }

        $token = $jwtManager->create($user);

        return $this->json([
            'token' => $token,
            'user' => [
                'id'       => $user->getId(),
                'email'    => $user->getEmail(),
                'fullName' => $user->getFullName(),
                'role'     => $user->getRole()->value,
            ],
        ]);
    }
}
