<?php

namespace App\Service\AI;

/**
 * Feature 10: Lease Risk Analyzer
 * Analyzes lease text using 25+ multilingual rule patterns.
 * No external LLM required — fully self-contained PHP.
 */
class LeaseRiskAnalyzerService
{
    private const RISK_PATTERNS = [
        // ── High severity ─────────────────────────────────────────────────────
        'automatic_renewal' => [
            'patterns' => [
                'automatic renewal', 'auto-renew', 'automatically renewed',
                'tacite reconduction', 'renouvellement automatique', 'reconduction tacite',
                'تجديد تلقائي', 'يتجدد تلقائيا',
            ],
            'severity' => 'high',
            'category' => 'contract_terms',
            'advice'   => 'Lease may renew automatically without notice. Add a calendar reminder 2 months before expiry to send a written cancellation notice.',
        ],
        'no_maintenance' => [
            'patterns' => [
                'tenant responsible for all repairs', 'tenant bears all maintenance',
                'locataire responsable de toutes les réparations', 'aucune obligation d\'entretien',
                'no maintenance obligation', 'as-is condition', 'repairs at tenant\'s expense',
                'المستأجر مسؤول عن جميع الإصلاحات', 'دون أي التزام صيانة',
            ],
            'severity' => 'high',
            'category' => 'maintenance',
            'advice'   => 'Owner disclaims all maintenance obligations. Under Tunisian law, structural repairs remain the owner\'s responsibility — seek legal advice before signing.',
        ],
        'no_notice_eviction' => [
            'patterns' => [
                'immediate eviction', 'without notice', 'sans préavis',
                'evicted immediately', 'expulsion immédiate', 'résiliation immédiate',
                'بدون إشعار', 'طرد فوري', 'إخلاء فوري',
            ],
            'severity' => 'high',
            'category' => 'eviction',
            'advice'   => 'Eviction-without-notice clause detected. This is typically unlawful. You are entitled to a statutory notice period. Seek legal advice.',
        ],
        'liability_waiver' => [
            'patterns' => [
                'not liable for any damage', 'waives all liability', 'no responsibility for',
                'non responsable des dommages', 'dégage de toute responsabilité',
                'لا يتحمل أي مسؤولية', 'يتنازل عن المسؤولية',
            ],
            'severity' => 'high',
            'category' => 'liability',
            'advice'   => 'Broad liability waiver detected. This may leave you with no recourse if the property causes damage or injury. Verify the clause scope with a lawyer.',
        ],
        'illegal_entry' => [
            'patterns' => [
                'landlord may enter at any time', 'owner reserves right to enter without notice',
                'propriétaire peut entrer à tout moment', 'accès sans préavis du propriétaire',
                'يحق للمالك الدخول في أي وقت', 'دخول بدون إشعار مسبق',
            ],
            'severity' => 'high',
            'category' => 'privacy',
            'advice'   => 'The owner cannot legally enter without reasonable prior notice (typically 24–48 hours). This clause may violate your right to quiet enjoyment.',
        ],
        'penalty_clause' => [
            'patterns' => [
                'liquidated damages', 'penalty clause', 'punitive fee',
                'clause pénale', 'pénalités disproportionnées',
                'شرط جزائي مبالغ فيه', 'غرامة عقوبة',
            ],
            'severity' => 'high',
            'category' => 'financial',
            'advice'   => 'A penalty clause has been detected. Review the trigger conditions and amounts carefully — disproportionate penalties may be unenforceable.',
        ],

        // ── Medium severity ───────────────────────────────────────────────────
        'excessive_deposit' => [
            'patterns' => [
                'three months deposit', 'four months deposit', 'five months deposit',
                '3 months security', '4 months security',
                'trois mois de caution', 'quatre mois de caution', 'cinq mois de caution',
                'ثلاثة أشهر ضمان', 'أربعة أشهر كضمان',
            ],
            'severity' => 'medium',
            'category' => 'financial',
            'advice'   => 'Deposit exceeds the standard 1–2 months. Negotiate to reduce it or request a deposit bond as an alternative.',
        ],
        'unilateral_price_change' => [
            'patterns' => [
                'landlord may increase rent', 'owner may modify rent', 'rent subject to revision',
                'le propriétaire peut modifier le loyer', 'révision unilatérale du loyer',
                'يجوز للمالك زيادة الإيجار', 'تعديل الإيجار من جانب واحد',
            ],
            'severity' => 'medium',
            'category' => 'financial',
            'advice'   => 'Rent can be changed unilaterally. Negotiate a fixed-rate clause or an annual CPI-capped increase instead.',
        ],
        'no_guest_policy' => [
            'patterns' => [
                'no overnight guests', 'no visitors', 'no guests allowed',
                'pas de visiteurs', 'interdiction de recevoir des visiteurs',
                'لا يُسمح بالزوار', 'ممنوع استضافة أحد',
            ],
            'severity' => 'medium',
            'category' => 'lifestyle',
            'advice'   => 'Strict guest/visitor restrictions. Clarify what constitutes a violation and the consequences before signing.',
        ],
        'early_termination_penalty' => [
            'patterns' => [
                'early termination fee', 'penalty for early exit', 'break clause penalty',
                'pénalité de résiliation anticipée', 'indemnité de rupture anticipée',
                'غرامة الإنهاء المبكر', 'رسوم الخروج المبكر',
            ],
            'severity' => 'medium',
            'category' => 'contract_terms',
            'advice'   => 'Early termination penalty detected. Check the fee amount and whether it applies if you give full notice — it may be negotiable.',
        ],
        'hidden_fees' => [
            'patterns' => [
                'administration fee', 'management fee', 'agency fee payable by tenant',
                'frais de dossier à la charge du locataire', 'frais d\'agence locataire',
                'رسوم إدارية', 'عمولة وكالة على المستأجر',
            ],
            'severity' => 'medium',
            'category' => 'financial',
            'advice'   => 'Additional fees beyond rent have been detected. Clarify amounts and payment schedule — some agency fees charged to tenants are legally prohibited.',
        ],
        'rent_arrears_clause' => [
            'patterns' => [
                'rent in advance', 'multiple months upfront', 'two months rent in advance',
                'loyer d\'avance', 'plusieurs mois d\'avance',
                'إيجار مقدم', 'دفع عدة أشهر مسبقاً',
            ],
            'severity' => 'medium',
            'category' => 'financial',
            'advice'   => 'Advance rent payments beyond one month are unusual. Negotiate standard monthly payments and confirm what happens if the tenancy ends early.',
        ],
        'no_alterations' => [
            'patterns' => [
                'no alterations', 'no modifications permitted', 'must not alter',
                'aucune modification autorisée', 'pas de travaux sans accord écrit',
                'لا يُسمح بإجراء أي تعديلات', 'ممنوع تغيير أي شيء',
            ],
            'severity' => 'medium',
            'category' => 'property',
            'advice'   => 'No alterations clause. Even hanging pictures or installing broadband may require written consent — clarify scope to avoid deposit deductions.',
        ],

        // ── Low severity ──────────────────────────────────────────────────────
        'subletting_ban' => [
            'patterns' => [
                'no subletting', 'no sublease', 'subletting prohibited',
                'sous-location interdite', 'interdiction de sous-louer',
                'تأجير من الباطن محظور', 'لا يجوز التأجير من الباطن',
            ],
            'severity' => 'low',
            'category' => 'lifestyle',
            'advice'   => 'Subletting is prohibited. Standard but worth noting if you plan extended travel or study abroad periods.',
        ],
        'utilities_ambiguous' => [
            'patterns' => [
                'utilities included', 'charges comprises', 'bills included',
                'charges incluses', 'المرافق مشمولة',
            ],
            'severity' => 'info',
            'category' => 'utilities',
            'advice'   => '"Utilities included" — request a written list specifying exactly what is covered (electricity, water, internet, etc.) and any usage caps.',
        ],
        'pet_ban' => [
            'patterns' => [
                'no pets', 'no animals allowed', 'pets prohibited',
                'animaux interdits', 'pas d\'animaux',
                'ممنوع الحيوانات', 'لا يُسمح بالحيوانات الأليفة',
            ],
            'severity' => 'low',
            'category' => 'lifestyle',
            'advice'   => 'Pets are prohibited. Violating this clause could result in lease termination.',
        ],
        'smoking_ban' => [
            'patterns' => [
                'no smoking', 'non-smoking property', 'smoking prohibited',
                'interdiction de fumer', 'propriété non-fumeurs',
                'ممنوع التدخين', 'منطقة غير مدخنين',
            ],
            'severity' => 'low',
            'category' => 'lifestyle',
            'advice'   => 'Smoking is prohibited in the property. Violating this clause could lead to deductions from your deposit.',
        ],
        'inspection_clause' => [
            'patterns' => [
                'periodic inspection', 'routine inspection', 'landlord inspection',
                'visite d\'inspection', 'état des lieux intermédiaire',
                'فحص دوري', 'تفتيش من قِبل المالك',
            ],
            'severity' => 'low',
            'category' => 'privacy',
            'advice'   => 'Regular inspections are permitted. Ensure the lease specifies reasonable notice periods (minimum 24 hours) and inspection frequency.',
        ],
        'late_payment_interest' => [
            'patterns' => [
                'interest on late payment', 'late payment charge', 'overdue interest',
                'intérêts de retard', 'pénalité de retard',
                'فائدة على التأخر', 'غرامة التأخر في الدفع',
            ],
            'severity' => 'low',
            'category' => 'financial',
            'advice'   => 'Late payment interest/charges apply. Note the rate and grace period — set up automatic payments to avoid this.',
        ],
    ];

    // Severity → score deduction
    private const DEDUCTIONS = ['high' => 18, 'medium' => 9, 'low' => 4, 'info' => 0];

    // Positive clauses that raise the score
    private const POSITIVE_PATTERNS = [
        'fixed rent'             => 6,
        'loyer fixe'             => 6,
        'إيجار ثابت'             => 6,
        'receipt provided'       => 4,
        'quittance fournie'      => 4,
        'إيصال يُقدَّم'         => 4,
        'written notice required'=> 5,
        'préavis écrit requis'   => 5,
        'إشعار خطي مطلوب'       => 5,
        'deposit refund'         => 5,
        'remboursement de caution' => 5,
        'استرداد الضمان'         => 5,
        'maintenance by landlord'=> 6,
        'entretien à la charge du propriétaire' => 6,
        'الصيانة على المالك'     => 6,
    ];

    // ── Public API ────────────────────────────────────────────────────────────

    public function analyze(string $leaseText): array
    {
        $flags    = $this->detectRisks($leaseText);
        $positive = $this->detectPositives($leaseText);
        $score    = $this->computeScore($flags, $positive);
        $summary  = $this->buildSummary($flags, $score, $leaseText);

        return [
            'score'           => $score,
            'risk_level'      => $this->riskLevel($score),
            'flags'           => $flags,
            'positive_clauses'=> $positive,
            'summary'         => $summary,
            'recommendations' => $this->buildRecommendations($flags),
            'category_breakdown' => $this->categoryBreakdown($flags),
            'word_count'      => str_word_count($leaseText),
            'llm_flags'       => [],   // kept for API compatibility — now always empty
        ];
    }

    // ── Risk detection ────────────────────────────────────────────────────────

    private function detectRisks(string $text): array
    {
        $lower = mb_strtolower($text);
        $found = [];

        foreach (self::RISK_PATTERNS as $key => $def) {
            foreach ($def['patterns'] as $pattern) {
                if (str_contains($lower, mb_strtolower($pattern))) {
                    $found[$key] = [
                        'key'      => $key,
                        'label'    => ucwords(str_replace('_', ' ', $key)),
                        'severity' => $def['severity'],
                        'category' => $def['category'],
                        'advice'   => $def['advice'],
                        'matched'  => $pattern,
                    ];
                    break;
                }
            }
        }

        // Sort by severity: high → medium → low → info
        $order = ['high' => 0, 'medium' => 1, 'low' => 2, 'info' => 3];
        uasort($found, fn($a, $b) => $order[$a['severity']] <=> $order[$b['severity']]);

        return array_values($found);
    }

    private function detectPositives(string $text): array
    {
        $lower   = mb_strtolower($text);
        $clauses = [];

        foreach (self::POSITIVE_PATTERNS as $pattern => $boost) {
            if (str_contains($lower, mb_strtolower($pattern))) {
                $clauses[] = ['clause' => $pattern, 'boost' => $boost];
            }
        }

        return $clauses;
    }

    // ── Scoring ───────────────────────────────────────────────────────────────

    private function computeScore(array $flags, array $positives): int
    {
        $score = 100;

        foreach ($flags as $flag) {
            $score -= self::DEDUCTIONS[$flag['severity']] ?? 0;
        }
        foreach ($positives as $p) {
            $score += $p['boost'];
        }

        // Length bonus: longer leases tend to be more detailed/protective
        // (no deduction — just a balanced baseline)

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

    // ── Summary generator ─────────────────────────────────────────────────────

    private function buildSummary(array $flags, int $score, string $text): string
    {
        $highCount   = count(array_filter($flags, fn($f) => $f['severity'] === 'high'));
        $medCount    = count(array_filter($flags, fn($f) => $f['severity'] === 'medium'));
        $wordCount   = str_word_count($text);
        $riskLabel   = $this->riskLevel($score);

        $intro = match (true) {
            $score >= 80 => 'This lease appears generally fair and well-balanced for the tenant.',
            $score >= 55 => 'This lease is broadly acceptable but contains clauses worth negotiating.',
            $score >= 30 => 'This lease contains several unfavourable clauses that require attention before signing.',
            default      => 'This lease contains multiple high-risk clauses and should be reviewed by a legal professional before signing.',
        };

        $detail = '';
        if ($highCount > 0) {
            $detail .= " $highCount high-severity risk" . ($highCount > 1 ? 's were' : ' was') . ' detected.';
        }
        if ($medCount > 0) {
            $detail .= " $medCount medium-severity concern" . ($medCount > 1 ? 's' : '') . ' also noted.';
        }
        if (empty($flags)) {
            $detail = ' No standard risk patterns were identified — the document may use non-standard language; manual review is still advised.';
        }

        $lengthNote = match (true) {
            $wordCount < 300  => ' The document is unusually short — key clauses may be missing.',
            $wordCount > 3000 => ' The document is comprehensive in length, which is generally a positive sign.',
            default           => '',
        };

        return $intro . $detail . $lengthNote;
    }

    // ── Category breakdown ────────────────────────────────────────────────────

    private function categoryBreakdown(array $flags): array
    {
        $breakdown = [];
        foreach ($flags as $flag) {
            $cat = $flag['category'];
            $breakdown[$cat] ??= ['count' => 0, 'max_severity' => 'info'];
            $breakdown[$cat]['count']++;

            $order = ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1, 'info' => 0];
            if (($order[$flag['severity']] ?? 0) > ($order[$breakdown[$cat]['max_severity']] ?? 0)) {
                $breakdown[$cat]['max_severity'] = $flag['severity'];
            }
        }
        return $breakdown;
    }

    // ── Recommendations ───────────────────────────────────────────────────────

    private function buildRecommendations(array $flags): array
    {
        $recs = array_column($flags, 'advice');

        // Always append general advice
        $recs[] = 'Request all verbal promises in writing as a lease addendum before signing.';
        $recs[] = 'Photograph the property thoroughly on move-in day and send copies to the owner.';

        if (count(array_filter($flags, fn($f) => $f['severity'] === 'high')) >= 2) {
            $recs[] = 'Given the number of high-risk clauses, consider having a legal professional review this lease before signing.';
        }

        return array_unique(array_filter($recs));
    }
}
