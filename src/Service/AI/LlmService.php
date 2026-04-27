<?php

namespace App\Service\AI;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Central LLM gateway — supports Ollama (local) and OpenAI-compatible APIs.
 * Configure via env:
 *   LLM_PROVIDER=ollama  LLM_BASE_URL=http://localhost:11434  LLM_MODEL=llama3
 *   LLM_PROVIDER=openai  LLM_BASE_URL=https://api.openai.com  LLM_API_KEY=sk-...
 */
class LlmService
{
    private string $provider;
    private string $baseUrl;
    private string $model;
    private ?string $apiKey;

    public function __construct(private readonly HttpClientInterface $http)
    {
        $this->provider = $_ENV['LLM_PROVIDER'] ?? 'ollama';
        $this->baseUrl  = rtrim($_ENV['LLM_BASE_URL'] ?? 'http://localhost:11434', '/');
        $this->model    = $_ENV['LLM_MODEL'] ?? 'llama3';
        $this->apiKey   = $_ENV['LLM_API_KEY'] ?? null;
    }

    /**
     * Send a prompt and return the response text.
     * @param string $prompt
     * @param array  $options  [temperature, max_tokens, system]
     */
    public function complete(string $prompt, array $options = []): string
    {
        try {
            return match ($this->provider) {
                'openai' => $this->openAiComplete($prompt, $options),
                default  => $this->ollamaComplete($prompt, $options),
            };
        } catch (\Throwable $e) {
            return $this->ruleBasedFallback($prompt);
        }
    }

    /**
     * Structured JSON completion — returns decoded array or [] on failure.
     */
    public function completeJson(string $prompt, array $options = []): array
    {
        $options['format'] = 'json';
        $text = $this->complete($prompt . "\n\nRespond with valid JSON only.", $options);

        // Strip markdown code fences if present
        $text = preg_replace('/^```json\s*|\s*```$/m', '', trim($text));
        $decoded = json_decode($text, true);

        return is_array($decoded) ? $decoded : [];
    }

    // ── Providers ────────────────────────────────────────────────────────────

    private function ollamaComplete(string $prompt, array $options): string
    {
        $body = array_filter([
            'model'  => $this->model,
            'prompt' => $prompt,
            'stream' => false,
            'options' => array_filter([
                'temperature' => $options['temperature'] ?? 0.7,
                'num_predict' => $options['max_tokens'] ?? 1024,
            ]),
            'format' => $options['format'] ?? null,
        ]);

        $resp = $this->http->request('POST', $this->baseUrl . '/api/generate', [
            'json'    => $body,
            'timeout' => 60,
        ]);

        $data = $resp->toArray();
        return $data['response'] ?? '';
    }

    private function openAiComplete(string $prompt, array $options): string
    {
        $messages = [];
        if (!empty($options['system'])) {
            $messages[] = ['role' => 'system', 'content' => $options['system']];
        }
        $messages[] = ['role' => 'user', 'content' => $prompt];

        $resp = $this->http->request('POST', $this->baseUrl . '/v1/chat/completions', [
            'headers' => ['Authorization' => 'Bearer ' . $this->apiKey],
            'json'    => [
                'model'       => $this->model,
                'messages'    => $messages,
                'temperature' => $options['temperature'] ?? 0.7,
                'max_tokens'  => $options['max_tokens'] ?? 1024,
            ],
            'timeout' => 60,
        ]);

        $data = $resp->toArray();
        return $data['choices'][0]['message']['content'] ?? '';
    }

    // ── Rule-based fallback (no LLM available) ───────────────────────────────

    private function ruleBasedFallback(string $prompt): string
    {
        return '[AI service unavailable — using rule-based fallback]';
    }

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
