<?php

namespace App\Controller;

use App\Entity\RoommatePreference;
use App\Repository\RoommatePreferenceRepository;
use App\Repository\SubscriptionRepository;
use App\Service\RoommateMatchingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/roommates')]
#[IsGranted('ROLE_STUDENT')]
class RoommateController extends AbstractController
{
    // Maps PHP string values ↔ the integer cleanliness level used by the matching service.
    private const CLEAN_TO_INT = ['relaxed' => 1, 'moderate' => 2, 'clean' => 3, 'very_clean' => 4];
    private const INT_TO_CLEAN = [1 => 'relaxed', 2 => 'moderate', 3 => 'clean', 4 => 'very_clean'];

    #[Route('', name: 'roommate_index')]
    public function index(
        RoommateMatchingService      $matchingService,
        RoommatePreferenceRepository $prefRepo,
        SubscriptionRepository       $subscriptionRepo
    ): Response {
        $pref = $prefRepo->findOneBy(['user' => $this->getUser()]);
        $matches = $pref ? $matchingService->findMatches($this->getUser(), $pref) : [];

        return $this->render('roommate/index.html.twig', [
            'matches'          => $matches,
            'preference'       => $pref,
            'cleanliness_map'  => self::INT_TO_CLEAN,
            'has_subscription' => $subscriptionRepo->findActiveByUser($this->getUser()) !== null,
        ]);
    }

    #[Route('/preferences', name: 'roommate_preferences', methods: ['POST'])]
    public function savePreferences(
        Request                      $request,
        EntityManagerInterface       $em,
        RoommatePreferenceRepository $prefRepo
    ): Response {
        $pref = $prefRepo->findOneBy(['user' => $this->getUser()]);
        if (!$pref) {
            $pref = new RoommatePreference();
            $pref->setUser($this->getUser());
        }

        $nullable = fn(string $k): ?string => ($v = trim((string)$request->request->get($k, ''))) === '' ? null : $v;
        // Treat the "Any" option as "no preference" → stored as NULL so the matching service
        // can use it as a wildcard (otherwise strict equality would always fail against a real value).
        $pickOrNull = fn(string $k): ?string => (($v = $nullable($k)) === null || $v === 'no_preference') ? null : $v;

        $pref->setBudgetMin($nullable('budget_min'));
        $pref->setBudgetMax($nullable('budget_max'));
        $pref->setAgeMin($nullable('age_min') !== null ? (int)$nullable('age_min') : null);
        $pref->setAgeMax($nullable('age_max') !== null ? (int)$nullable('age_max') : null);

        $cleanliness = $nullable('cleanliness');
        $pref->setCleanlinessLevel($cleanliness !== null ? (self::CLEAN_TO_INT[$cleanliness] ?? null) : null);

        $pref->setSleepSchedule($pickOrNull('sleep'));
        $pref->setSmokingPreference($pickOrNull('smoking'));
        $pref->setNoiseTolerance($pickOrNull('noise_tolerance'));
        $pref->setGenderPreference($pickOrNull('gender_preference'));
        $pref->setGuests($pickOrNull('guests'));
        $pref->setPets($pickOrNull('pets'));
        $pref->setUpdatedAt(new \DateTimeImmutable());

        $em->persist($pref);
        $em->flush();

        $this->addFlash('success', 'Preferences saved!');
        return $this->redirectToRoute('roommate_index');
    }
}
