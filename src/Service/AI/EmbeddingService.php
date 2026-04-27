<?php

namespace App\Service\AI;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Converts text → fixed-size float vectors for semantic search & similarity.
 * Falls back to TF-IDF bag-of-words if no embedding server is available.
 */
class EmbeddingService
{
    private const VOCAB_SIZE = 512;

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly string $baseUrl = 'http://localhost:11434',
        private readonly string $model   = 'nomic-embed-text',
    ) {}

    /**
     * @return float[]
     */
    public function embed(string $text): array
    {
        try {
            $resp = $this->http->request('POST', $this->baseUrl . '/api/embeddings', [
                'json'    => ['model' => $this->model, 'prompt' => $text],
                'timeout' => 15,
            ]);
            $data = $resp->toArray();
            return $data['embedding'] ?? $this->tfidfEmbed($text);
        } catch (\Throwable) {
            return $this->tfidfEmbed($text);
        }
    }

    /**
     * Cosine similarity between two embedding vectors.
     */
    public function similarity(array $a, array $b): float
    {
        if (empty($a) || empty($b) || count($a) !== count($b)) {
            return 0.0;
        }

        $dot = 0.0; $na = 0.0; $nb = 0.0;
        foreach ($a as $i => $v) {
            $dot += $v * $b[$i];
            $na  += $v * $v;
            $nb  += $b[$i] * $b[$i];
        }

        $denom = sqrt($na) * sqrt($nb);
        return $denom > 0 ? (float) ($dot / $denom) : 0.0;
    }

    /**
     * Rank items by similarity to a query embedding.
     * @param float[]   $queryEmb
     * @param float[][] $itemEmbs  [id => embedding]
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

    // ── TF-IDF fallback ──────────────────────────────────────────────────────

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
        $text = mb_strtolower($text);
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        $words = preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);

        $stopwords = ['the','a','an','and','or','in','on','at','to','for','of','is','it','this','that','with'];
        return array_values(array_filter($words, fn($w) => !in_array($w, $stopwords) && strlen($w) > 2));
    }
}
