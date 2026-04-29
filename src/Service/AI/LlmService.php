<?php

namespace App\Service\AI;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * LLM gateway — uses Ollama (local, free, no API key).
 * All features degrade gracefully to rule-based fallbacks when Ollama is not running.
 *
 * Install Ollama: https://ollama.com  →  ollama pull llama3
 * Configure via .env:
 *   LLM_BASE_URL=http://localhost:11434
 *   LLM_MODEL=llama3
 */
class LlmService
{
    private string $baseUrl;
    private string $model;

    public function __construct(private readonly HttpClientInterface $http)
    {
        $this->baseUrl = rtrim($_ENV['LLM_BASE_URL'] ?? 'http://localhost:11434', '/');
        $this->model   = $_ENV['LLM_MODEL'] ?? 'llama3';
    }

    /**
     * Send a prompt to the local Ollama server and return the response text.
     * Returns an empty string if Ollama is not running — callers must handle this.
     */
    public function complete(string $prompt, array $options = []): string
    {
        try {
            $body = [
                'model'  => $this->model,
                'prompt' => $prompt,
                'stream' => false,
                'options' => array_filter([
                    'temperature' => $options['temperature'] ?? 0.7,
                    'num_predict' => $options['max_tokens']  ?? 1024,
                ]),
            ];

            if (!empty($options['format'])) {
                $body['format'] = $options['format'];
            }

            $resp = $this->http->request('POST', $this->baseUrl . '/api/generate', [
                'json'    => $body,
                'timeout' => 60,
            ]);

            $data = $resp->toArray();
            return $data['response'] ?? '';

        } catch (\Throwable) {
            // Ollama not running — caller's fallback logic handles this
            return '';
        }
    }

    /**
     * Ask Ollama for a JSON response. Returns decoded array or [] when unavailable.
     */
    public function completeJson(string $prompt, array $options = []): array
    {
        $options['format'] = 'json';
        $text = $this->complete($prompt . "\n\nRespond with valid JSON only.", $options);
        if (empty($text)) return [];

        $text    = preg_replace('/^```json\s*|\s*```$/m', '', trim($text));
        $decoded = json_decode($text, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Check whether the local Ollama server is reachable.
     */
    public function isAvailable(): bool
    {
        try {
            $this->http->request('GET', $this->baseUrl . '/api/tags', ['timeout' => 3])->getStatusCode();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
