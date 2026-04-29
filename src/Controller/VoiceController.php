<?php

namespace App\Controller;

use App\Service\VoiceIntentClassifier;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/voice')]
class VoiceController extends AbstractController
{
    public function __construct(
        private readonly VoiceIntentClassifier $classifier,
        private readonly HttpClientInterface $http,
        private readonly string $voiceServiceUrl = '',
    ) {}

    /**
     * Resolve an intent from a transcript.
     * Body: { text: string, route?: string, locale?: string, pageContext?: object }
     */
    #[Route('/intent', name: 'voice_intent', methods: ['POST'])]
    public function intent(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $text = trim((string)($data['text'] ?? ''));
        if ($text === '') {
            return $this->json(['error' => 'empty transcript'], 400);
        }
        $route       = (string)($data['route']  ?? '');
        $locale      = (string)($data['locale'] ?? 'en');
        $pageContext = (array)($data['pageContext'] ?? []);

        $result = $this->classifier->classify($text, $route, $locale, $pageContext);

        // Resolve URLs server-side so the JS doesn't need to know routes.
        $result['url']          = $this->urlForIntent($result['intent'], $result['params'], $route, $pageContext);
        $result['authRequired'] = $this->authRequired($result['intent']);

        return $this->json($result);
    }

    /**
     * Optional: proxy to the local Python microservice for high-quality STT.
     * If the service isn't configured or reachable, JS falls back to the
     * browser's built-in Web Speech API.
     */
    #[Route('/transcribe', name: 'voice_transcribe', methods: ['POST'])]
    public function transcribe(Request $request): Response
    {
        if ($this->voiceServiceUrl === '') {
            return $this->json(['error' => 'voice service not configured'], 503);
        }
        $audio = $request->files->get('audio');
        if (!$audio) {
            return $this->json(['error' => 'missing audio file'], 400);
        }
        try {
            $response = $this->http->request('POST', rtrim($this->voiceServiceUrl, '/') . '/transcribe', [
                'body' => [
                    'audio'    => fopen($audio->getPathname(), 'r'),
                    'language' => (string) $request->request->get('language', ''),
                ],
                'timeout' => 30,
            ]);
            return new JsonResponse($response->getContent(false), $response->getStatusCode(), [], true);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'voice service unreachable', 'detail' => $e->getMessage()], 503);
        }
    }

    /**
     * Optional: proxy to Kokoro/pyttsx3 TTS. Falls back to browser speechSynthesis on the client.
     */
    #[Route('/speak', name: 'voice_speak', methods: ['POST'])]
    public function speak(Request $request): Response
    {
        if ($this->voiceServiceUrl === '') {
            return $this->json(['error' => 'voice service not configured'], 503);
        }
        $data = json_decode($request->getContent(), true) ?? [];
        try {
            $response = $this->http->request('POST', rtrim($this->voiceServiceUrl, '/') . '/speak', [
                'body'    => [
                    'text'     => (string)($data['text']     ?? ''),
                    'language' => (string)($data['language'] ?? 'en'),
                    'voice'    => (string)($data['voice']    ?? ''),
                ],
                'timeout' => 30,
            ]);
            return new Response($response->getContent(false), $response->getStatusCode(), [
                'Content-Type' => $response->getHeaders(false)['content-type'][0] ?? 'audio/wav',
            ]);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'voice service unreachable'], 503);
        }
    }

    /**
     * Lightweight probe so the orb can show whether premium STT/TTS is online.
     */
    #[Route('/health', name: 'voice_health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        $native = [
            'intent'  => true,
            'locales' => ['en', 'fr', 'ar'],
        ];
        $premium = ['available' => false];
        if ($this->voiceServiceUrl !== '') {
            try {
                $r = $this->http->request('GET', rtrim($this->voiceServiceUrl, '/') . '/health', ['timeout' => 2]);
                $premium['available'] = $r->getStatusCode() === 200;
            } catch (\Throwable) {}
        }
        return $this->json(['native' => $native, 'premium' => $premium]);
    }

    private function authRequired(string $intent): bool
    {
        return in_array($intent, [
            'NAVIGATE_DASHBOARD',
            'NAVIGATE_MESSAGES',
            'NAVIGATE_CONTRACTS',
            'NAVIGATE_MY_LISTINGS',
            'NAVIGATE_SAVED',
            'NAVIGATE_VERIFICATION',
            'LOGOUT',
            'REPORT',
            // New intents requiring authentication
            'GENERATE_CONTRACT',
            'SEND_MESSAGE',
            'SAVE_LISTING',
            'NEW_LISTING',
        ], true);
    }

    /**
     * @param array<string,mixed> $params
     * @param array<string,mixed> $pageContext
     */
    private function urlForIntent(string $intent, array $params, string $currentRoute = '', array $pageContext = []): ?string
    {
        $gen = fn(string $name, array $p = []) => $this->generateUrl($name, $p);

        return match ($intent) {
            // ── Navigation ──
            'NAVIGATE_HOME'         => $gen('app_home'),
            'NAVIGATE_LISTINGS'     => $gen('listing_index'),
            'NAVIGATE_MY_LISTINGS'  => $gen('listing_my'),
            'NAVIGATE_SAVED'        => $gen('listing_saved'),
            'NAVIGATE_ROOMMATES'    => $gen('roommate_index'),
            'NAVIGATE_MESSAGES'     => $gen('message_index'),
            'NAVIGATE_CONTRACTS'    => $gen('contract_index'),
            'NAVIGATE_DASHBOARD'    => $gen('dashboard_student'),
            'NAVIGATE_PREMIUM'      => $gen('subscription_index'),
            'NAVIGATE_VERIFICATION' => $gen('verification_submit'),
            // ── Auth ──
            'LOGIN'                 => $gen('app_login'),
            'REGISTER'              => $gen('app_register'),
            'LOGOUT'                => $gen('app_logout'),
            // ── Search / filter ──
            'SEARCH'                => $gen('listing_index') . '?q=' . urlencode((string)($params['query'] ?? '')),
            'FILTER_LISTINGS'       => $gen('listing_index') . '?' . http_build_query($params),
            // ── New listing ──
            'NEW_LISTING'           => $gen('listing_new'),
            // ── Actions handled client-side (JS has page context) ──
            // GENERATE_CONTRACT: requires POST to /contracts/generate-from-listing/{id}.
            //   JS resolves the listing ID from the current URL and issues the POST.
            'GENERATE_CONTRACT'     => null,
            // SEND_MESSAGE, SAVE_LISTING: JS opens modal / toggles bookmark contextually.
            'SEND_MESSAGE'          => null,
            'SAVE_LISTING'          => null,
            // Form / field / dictation / select / smart-query intents are all JS-side.
            'FILL_FIELD'            => null,
            'CLEAR_FIELD'           => null,
            'READ_FIELD'            => null,
            'DICTATION_START'       => null,
            'DICTATION_STOP'        => null,
            'DICTATE_TEXT'          => null,
            'PICK_OPTION'           => null,
            'NEXT_FIELD'            => null,
            'PREV_FIELD'            => null,
            'FORM_SUBMIT'           => null,
            'FORM_CLEAR'            => null,
            'CLICK_ELEMENT'         => null,
            'SELECT_MODE_ON'        => null,
            'SELECT_MODE_OFF'       => null,
            'CLICK_N'               => null,
            'COUNT_ITEMS'           => null,
            'WHAT_PAGE'             => null,
            'WHAT_COMMANDS'         => null,
            'CONFIRM_YES'           => null,
            'CONFIRM_NO'            => null,
            default                 => null,
        };
    }
}
