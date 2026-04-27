<?php

namespace App\Service\AI;

/**
 * Feature 10: Lease Risk Analyzer
 * Upload any lease text → NLP extracts clauses, flags risky terms,
 * scores tenant fairness 0–100.
 */
class LeaseRiskAnalyzerService
{
    private const RISK_PATTERNS = [
        'automatic_renewal' => [
            'patterns' => ['automatic renewal', 'auto-renew', 'tacite reconduction', 'renouvellement automatique', 'تجديد تلقائي'],
            'severity' => 'high',
            'advice'   => 'Lease may renew automatically without notice. Add a calendar reminder 2 months before expiry.',
        ],
        'no_maintenance' => [
            'patterns' => ['tenant responsible for all repairs', 'locataire responsable de toutes les réparations', 'no maintenance obligation'],
            'severity' => 'high',
            'advice'   => 'Owner disclaims all maintenance obligations. This may be illegal — check local tenant laws.',
        ],
        'excessive_deposit' => [
            'patterns' => ['three months deposit', 'four months', 'cinq mois', '4 mois de caution', '3 months security'],
            'severity' => 'medium',
            'advice'   => 'Deposit exceeds standard 1–2 months. Negotiate before signing.',
        ],
        'no_notice_period' => [
            'patterns' => ['immediate eviction', 'without notice', 'sans préavis', 'بدون إشعار'],
            'severity' => 'high',
            'advice'   => 'Eviction without notice clause detected. This is often illegal. Seek legal advice.',
        ],
        'unilateral_price_change' => [
            'patterns' => ['landlord may increase rent', 'owner may modify rent', 'le propriétaire peut modifier le loyer'],
            'severity' => 'medium',
            'advice'   => 'Rent can be changed unilaterally. Negotiate a fixed-rate period or annual cap.',
        ],
        'subletting_ban' => [
            'patterns' => ['no subletting', 'no sublease', 'sous-location interdite', 'تأجير من الباطن محظور'],
            'severity' => 'low',
            'advice'   => 'Subletting is prohibited. This is standard but worth noting if you plan to travel.',
        ],
        'no_guest_policy' => [
            'patterns' => ['no overnight guests', 'no visitors', 'pas de visiteurs'],
            'severity' => 'medium',
            'advice'   => 'Strict guest/visitor restrictions. Clarify what counts as a violation before signing.',
        ],
        'utilities_ambiguous' => [
            'patterns' => ['utilities included', 'charges comprises', 'المرافق مشمولة'],
            'severity' => 'info',
            'advice'   => 'Utilities described as "included" — get a written list of exactly what is covered.',
        ],
        'liability_waiver' => [
            'patterns' => ['not liable for any damage', 'non responsable des dommages', 'waives all liability'],
            'severity' => 'high',
            'advice'   => 'Broad liability waiver for the owner. You may have no recourse for property damage.',
        ],
        'early_termination_penalty' => [
            'patterns' => ['early termination fee', 'penalty for early exit', 'pénalité de résiliation anticipée'],
            'severity' => 'medium',
            'advice'   => 'Early termination penalty clause found. Check the fee amount and conditions.',
        ],
    ];

    public function __construct(private readonly LlmService $llm) {}

    /**
     * @param string $leaseText  Raw extracted text from the lease PDF
     * @return array  [score, risk_level, flags, summary, recommendations]
     */
    public function analyze(string $leaseText): array
    {
        // Rule-based detection first
        $flags = $this->detectRisks($leaseText);

        // LLM-powered deep analysis
        $llmResult = $this->llmAnalyze($leaseText, $flags);

        // Compute tenant fairness score (100 = perfectly fair)
        $score = $this->computeScore($flags, $llmResult);

        return [
            'score'           => $score,
            'risk_level'      => $this->riskLevel($score),
            'flags'           => $flags,
            'llm_summary'     => $llmResult['summary']         ?? '',
            'llm_flags'       => $llmResult['additional_risks'] ?? [],
            'recommendations' => $this->buildRecommendations($flags, $llmResult),
            'word_count'      => str_word_count($leaseText),
        ];
    }

    private function detectRisks(string $text): array
    {
        $text  = mb_strtolower($text);
        $found = [];

        foreach (self::RISK_PATTERNS as $key => $def) {
            foreach ($def['patterns'] as $pattern) {
                if (str_contains($text, mb_strtolower($pattern))) {
                    $found[$key] = [
                        'key'      => $key,
                        'label'    => ucwords(str_replace('_', ' ', $key)),
                        'severity' => $def['severity'],
                        'advice'   => $def['advice'],
                        'matched'  => $pattern,
                    ];
                    break;
                }
            }
        }

        return array_values($found);
    }

    private function llmAnalyze(string $text, array $ruleFlags): array
    {
        // Truncate to first 3000 chars for LLM context
        $excerpt = substr($text, 0, 3000);
        $ruleKeys = implode(', ', array_column($ruleFlags, 'key'));

        $prompt = <<<PROMPT
You are a tenant-rights legal assistant specialising in Tunisian housing law.
Analyse this lease excerpt and identify additional risk clauses NOT in this list: {$ruleKeys}

Lease text:
{$excerpt}

Respond with JSON:
{
  "summary": "2-sentence plain-language summary of the overall fairness",
  "additional_risks": [
    {"clause": "brief description", "severity": "high|medium|low", "advice": "what tenant should do"}
  ]
}
PROMPT;

        return $this->llm->completeJson($prompt, ['temperature' => 0.3]);
    }

    private function computeScore(array $flags, array $llmResult): int
    {
        $score = 100;
        $weights = ['high' => 18, 'medium' => 9, 'low' => 4, 'info' => 0];

        foreach ($flags as $flag) {
            $score -= $weights[$flag['severity']] ?? 0;
        }
        foreach ($llmResult['additional_risks'] ?? [] as $risk) {
            $score -= $weights[$risk['severity'] ?? 'low'] ?? 0;
        }

        return max(0, min(100, $score));
    }

    private function riskLevel(int $score): string
    {
        return match (true) {
            $score >= 80 => 'low',
            $score >= 55 => 'medium',
            $score >= 30 => 'high',
            default      => 'critical',
        };
    }

    private function buildRecommendations(array $flags, array $llmResult): array
    {
        $recs = array_column($flags, 'advice');
        foreach ($llmResult['additional_risks'] ?? [] as $risk) {
            if (!empty($risk['advice'])) $recs[] = $risk['advice'];
        }
        return array_unique(array_filter($recs));
    }
}
