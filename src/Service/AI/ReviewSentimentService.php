<?php

namespace App\Service\AI;

/**
 * Feature 18: Review Sentiment & Theme Extractor
 * NLP on listing reviews → key themes, sentiment scores, platform-wide insights.
 */
class ReviewSentimentService
{
    private const THEMES = [
        'cleanliness'   => ['clean', 'dirty', 'spotless', 'filthy', 'propre', 'sale', 'نظيف', 'وسخ'],
        'noise'         => ['quiet', 'noisy', 'loud', 'peaceful', 'calme', 'bruyant', 'هادئ', 'ضوضاء'],
        'location'      => ['location', 'central', 'far', 'close', 'emplacement', 'loin', 'موقع'],
        'value'         => ['value', 'expensive', 'cheap', 'worth', 'prix', 'cher', 'bon rapport'],
        'owner'         => ['owner', 'landlord', 'responsive', 'helpful', 'rude', 'propriétaire', 'صاحب'],
        'wifi'          => ['wifi', 'internet', 'connection', 'fast', 'slow'],
        'maintenance'   => ['repair', 'broken', 'maintenance', 'fixed', 'réparation', 'صيانة'],
        'amenities'     => ['furnished', 'appliances', 'facilities', 'equipment', 'équipements'],
    ];

    private const POSITIVE_WORDS = [
        'great', 'excellent', 'perfect', 'amazing', 'wonderful', 'fantastic', 'good', 'nice', 'love',
        'recommend', 'happy', 'satisfied', 'comfortable', 'clean', 'friendly', 'helpful',
        'super', 'bien', 'excellent', 'parfait', 'agréable', 'recommande', 'ممتاز', 'رائع', 'جيد',
    ];

    private const NEGATIVE_WORDS = [
        'bad', 'terrible', 'awful', 'horrible', 'dirty', 'broken', 'noisy', 'expensive', 'rude',
        'avoid', 'worst', 'disappointed', 'problem', 'issue', 'fake',
        'mauvais', 'sale', 'cher', 'éviter', 'problème', 'سيء', 'مشكلة', 'فاحش',
    ];

    public function __construct() {}

    /**
     * Analyse a single review text.
     */
    public function analyzeReview(string $text): array
    {
        $sentiment = $this->sentimentScore($text);
        $themes    = $this->extractThemes($text);

        return [
            'sentiment_score' => $sentiment['score'],   // -1.0 to +1.0
            'sentiment_label' => $sentiment['label'],   // positive|neutral|negative
            'themes'          => $themes,
            'key_phrases'     => $this->extractKeyPhrases($text),
            'word_count'      => str_word_count($text),
        ];
    }

    /**
     * Analyse a batch of reviews for a listing or the whole platform.
     * @param array $reviews  [['text' => '...', 'rating' => 4, 'id' => 1], ...]
     */
    public function analyzeSet(array $reviews): array
    {
        if (empty($reviews)) {
            return ['total' => 0, 'avg_sentiment' => 0, 'themes' => [], 'insights' => []];
        }

        $sentiments    = [];
        $allThemes     = [];
        $themeSentiments = [];

        foreach ($reviews as $review) {
            $text   = $review['text'] ?? '';
            $result = $this->analyzeReview($text);

            $sentiments[] = $result['sentiment_score'];

            foreach ($result['themes'] as $theme => $score) {
                $allThemes[$theme]          = ($allThemes[$theme] ?? 0) + 1;
                $themeSentiments[$theme][]  = $score;
            }
        }

        // Per-theme sentiment averages
        $themeAnalysis = [];
        foreach ($allThemes as $theme => $count) {
            $scores = $themeSentiments[$theme] ?? [0];
            $themeAnalysis[$theme] = [
                'mentions'        => $count,
                'avg_sentiment'   => round(array_sum($scores) / count($scores), 2),
                'label'           => $this->sentimentLabel(array_sum($scores) / count($scores)),
            ];
        }

        arsort($allThemes);

        return [
            'total'            => count($reviews),
            'avg_sentiment'    => round(array_sum($sentiments) / count($sentiments), 2),
            'sentiment_dist'   => $this->sentimentDistribution($sentiments),
            'top_themes'       => array_slice($themeAnalysis, 0, 5, true),
            'all_themes'       => $themeAnalysis,
            'insights'         => $this->generateInsights($themeAnalysis, $sentiments),
            'llm_summary'      => $this->algorithmicSummary($themeAnalysis, $sentiments, count($reviews)),
        ];
    }

    // ── Sentiment scoring ─────────────────────────────────────────────────────

    private function sentimentScore(string $text): array
    {
        $lower  = mb_strtolower($text);
        $words  = preg_split('/\s+/', $lower);
        $pos    = 0;
        $neg    = 0;
        $negate = false;

        foreach ($words as $word) {
            $clean = preg_replace('/[^\w]/u', '', $word);
            if (in_array($clean, ['not', 'no', 'never', 'pas', 'ne', 'لا', 'ليس'])) {
                $negate = true;
                continue;
            }
            $isPos = in_array($clean, self::POSITIVE_WORDS);
            $isNeg = in_array($clean, self::NEGATIVE_WORDS);

            if ($isPos) { $negate ? $neg++ : $pos++; }
            if ($isNeg) { $negate ? $pos++ : $neg++; }
            $negate = false;
        }

        $total = $pos + $neg;
        $score = $total > 0 ? ($pos - $neg) / $total : 0.0;

        return ['score' => round($score, 2), 'label' => $this->sentimentLabel($score)];
    }

    private function sentimentLabel(float $score): string
    {
        return match (true) {
            $score >  0.2 => 'positive',
            $score < -0.2 => 'negative',
            default       => 'neutral',
        };
    }

    private function extractThemes(string $text): array
    {
        $lower  = mb_strtolower($text);
        $found  = [];

        foreach (self::THEMES as $theme => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($lower, $kw)) {
                    // Assign rough sentiment for this theme mention
                    $context = $this->extractContext($lower, $kw);
                    $found[$theme] = $this->sentimentScore($context)['score'];
                    break;
                }
            }
        }

        return $found;
    }

    private function extractContext(string $text, string $keyword): string
    {
        $pos   = strpos($text, $keyword);
        $start = max(0, $pos - 50);
        $end   = min(strlen($text), $pos + strlen($keyword) + 50);
        return substr($text, $start, $end - $start);
    }

    private function extractKeyPhrases(string $text): array
    {
        // Bigrams that appear in the text
        $words  = preg_split('/\s+/', mb_strtolower(preg_replace('/[^\w\s]/u', '', $text)));
        $bigrams = [];
        for ($i = 0; $i < count($words) - 1; $i++) {
            if (strlen($words[$i]) > 3 && strlen($words[$i + 1]) > 3) {
                $bigrams[] = $words[$i] . ' ' . $words[$i + 1];
            }
        }
        return array_unique(array_slice($bigrams, 0, 5));
    }

    private function sentimentDistribution(array $scores): array
    {
        $pos = count(array_filter($scores, fn($s) => $s > 0.2));
        $neu = count(array_filter($scores, fn($s) => $s >= -0.2 && $s <= 0.2));
        $neg = count(array_filter($scores, fn($s) => $s < -0.2));
        $t   = count($scores) ?: 1;
        return [
            'positive' => (int) round($pos / $t * 100),
            'neutral'  => (int) round($neu / $t * 100),
            'negative' => (int) round($neg / $t * 100),
        ];
    }

    private function generateInsights(array $themeAnalysis, array $sentiments): array
    {
        $insights = [];
        foreach ($themeAnalysis as $theme => $data) {
            if ($data['avg_sentiment'] < -0.3 && $data['mentions'] >= 2) {
                $insights[] = '⚠️ Repeated negative feedback about ' . ucfirst($theme) . ' — owner should address this';
            }
            if ($data['avg_sentiment'] > 0.5 && $data['mentions'] >= 3) {
                $insights[] = '⭐ ' . ucfirst($theme) . ' is a consistently praised feature';
            }
        }
        $avg = count($sentiments) > 0 ? array_sum($sentiments) / count($sentiments) : 0;
        if ($avg < -0.1) {
            $insights[] = '🔴 Overall sentiment trending negative — review listing quality';
        } elseif ($avg > 0.4) {
            $insights[] = '🟢 Strong overall satisfaction score';
        }
        return $insights;
    }

    /**
     * Algorithmic summary generated from theme analysis and sentiment distribution.
     * Produces a readable 2-sentence overview without any external LLM call.
     */
    private function algorithmicSummary(array $themeAnalysis, array $sentiments, int $total): string
    {
        if ($total < 3 || empty($sentiments)) {
            return '';
        }

        $avg = array_sum($sentiments) / count($sentiments);

        // Overall tone
        $overallTone = match (true) {
            $avg >  0.4 => 'overwhelmingly positive',
            $avg >  0.1 => 'generally positive',
            $avg > -0.1 => 'mixed',
            $avg > -0.4 => 'generally negative',
            default     => 'predominantly negative',
        };

        // Top praised theme
        $praised = array_filter($themeAnalysis, fn($t) => $t['avg_sentiment'] > 0.3 && $t['mentions'] >= 2);
        uasort($praised, fn($a, $b) => $b['avg_sentiment'] <=> $a['avg_sentiment']);
        $topPraise = array_key_first($praised);

        // Top criticised theme
        $criticised = array_filter($themeAnalysis, fn($t) => $t['avg_sentiment'] < -0.2 && $t['mentions'] >= 2);
        uasort($criticised, fn($a, $b) => $a['avg_sentiment'] <=> $b['avg_sentiment']);
        $topCritique = array_key_first($criticised);

        $sentence1 = sprintf(
            'Across %d review%s, sentiment is %s.',
            $total,
            $total > 1 ? 's' : '',
            $overallTone
        );

        $parts = [];
        if ($topPraise) {
            $parts[] = ucfirst($topPraise) . ' is frequently praised';
        }
        if ($topCritique) {
            $parts[] = ucfirst($topCritique) . ' draws the most criticism';
        }

        if (empty($parts)) {
            $sentence2 = 'No single theme dominates — feedback is broadly distributed across multiple aspects.';
        } elseif (count($parts) === 1) {
            $sentence2 = $parts[0] . '.';
        } else {
            $sentence2 = $parts[0] . ', while ' . lcfirst($parts[1]) . '.';
        }

        return $sentence1 . ' ' . $sentence2;
    }
}
