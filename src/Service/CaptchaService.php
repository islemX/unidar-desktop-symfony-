<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class CaptchaService
{
    private const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $secretKey,
        private readonly string $siteKey,
    ) {}

    /**
     * Validates a reCAPTCHA v2 response token from the form submission.
     * Returns true when the token is valid, false otherwise.
     */
    public function isValid(?string $token, ?string $remoteIp = null): bool
    {
        if (empty($token)) {
            return false;
        }

        try {
            $params = ['secret' => $this->secretKey, 'response' => $token];
            if ($remoteIp) {
                $params['remoteip'] = $remoteIp;
            }

            $response = $this->httpClient->request('POST', self::VERIFY_URL, [
                'body' => $params,
            ]);

            $data = $response->toArray();
            return ($data['success'] ?? false) === true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function getSiteKey(): string
    {
        return $this->siteKey;
    }
}
