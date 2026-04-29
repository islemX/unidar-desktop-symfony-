<?php

namespace App\Service\AI;

/**
 * Text → float vector for semantic search & similarity.
 * Uses TF-IDF bag-of-words — fully local, zero external dependencies.
 * If Ollama is running with nomic-embed-text, it upgrades automatically.
 */
class EmbeddingService
{
    private const VOCAB_SIZE = 512;

    private ?string $ollamaUrl;
    private ?string $ollamaModel;

    public function __construct()
    {
        // Optional: point to local Ollama for better embeddings (no API key needed)
        $this->ollamaUrl   = $_ENV['LLM_BASE_URL'] ?? null;
        $this->ollamaModel = 'nomic-embed-text';
    }

    /**
     * @return float[]
     */
    public function embed(string $text): array
    {
        // Try Ollama embeddings if available (purely local)
        if ($this->ollamaUrl) {
            $result = $this->ollamaEmbed($text);
            if (!empty($result)) return $result;
        }

        // Always falls back to TF-IDF (no network needed)
        return $this->tfidfEmbed($text);
    }

    /**
     * Cosine similarity between two embedding vectors.
     */
    public function similarity(array $a, array $b): float
    {
        if (empty($a) || empty($b) || count($a) !== count($b)) return 0.0;

        $dot = 0.0; $na = 0.0; $nb = 0.0;
        foreach ($a as $i => $v) {
            $dot += $v * $b[$i];
            $na  += $v * $v;
            $nb  += $b[$i] * $b[$i];
        }

        $denom = sqrt($na) * sqrt($nb);
        return $denom > 0 ? (float)($dot / $denom) : 0.0;
    }

    /**
     * Rank items by similarity to a query embedding.
     * @return array  [id => score] sorted desc
     */
    public function rank(array $queryEmb, array $itemEmbs): array
    {
        $scores = [];
        foreach ($itemEmbs as $id => $emb) {
            $scores[$id] = $this->similarity($queryEmb, $emb);
        }
        arsort($scores);
        return $scores;
    }

    // ── Ollama local embeddings (optional upgrade) ───────────────────────────

    private function ollamaEmbed(string $text): array
    {
        try {
            $ctx  = stream_context_create(['http' => [
                'method'  => 'POST',
                'header'  => 'Content-Type: application/json',
                'content' => json_encode(['model' => $this->ollamaModel, 'prompt' => $text]),
                'timeout' => 5,
            ]]);
            $raw  = @file_get_contents($this->ollamaUrl . '/api/embeddings', false, $ctx);
            if (!$raw) return [];
            $data = json_decode($raw, true);
            return $data['embedding'] ?? [];
        } catch (\Throwable) {
            return [];
        }
    }

    // ── TF-IDF fallback (pure PHP, no dependencies) ──────────────────────────

    private function tfidfEmbed(string $text): array
    {
        $tokens = $this->tokenize($text);
        $tf     = array_count_values($tokens);
        $total  = count($tokens) ?: 1;
        $vec    = array_fill(0, self::VOCAB_SIZE, 0.0);

        foreach ($tf as $word => $count) {
            $idx        = abs(crc32($word)) % self::VOCAB_SIZE;
            $vec[$idx] += $count / $total;
        }

        // L2-normalise
        $norm = sqrt(array_sum(array_map(fn($v) => $v * $v, $vec))) ?: 1.0;
        return array_map(fn($v) => $v / $norm, $vec);
    }

    private function tokenize(string $text): array
    {
        $text  = mb_strtolower($text);
        $text  = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        $words = preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);

        $stop  = ['the','a','an','and','or','in','on','at','to','for','of','is','it','this','that','with','de','le','la','les','un','une','et','ou','en'];
        return array_values(array_filter($words, fn($w) => !in_array($w, $stop) && mb_strlen($w) > 2));
    }
}
