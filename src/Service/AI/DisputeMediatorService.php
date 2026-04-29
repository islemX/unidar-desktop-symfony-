<?php

namespace App\Service\AI;

/**
 * Feature 11: AI Dispute Mediator
 * Rule-based mediation engine. Extracts key claims from both sides,
 * applies per-type resolution knowledge base, and produces a structured
 * neutral analysis — no external LLM required.
 */
class DisputeMediatorService
{
    private const DISPUTE_TYPES = [
        'deposit'     => 'Security deposit dispute',
        'maintenance' => 'Maintenance / repairs dispute',
        'noise'       => 'Noise / disturbance complaint',
        'rent'        => 'Rent payment dispute',
        'damage'      => 'Property damage claim',
        'early_exit'  => 'Early termination dispute',
        'utilities'   => 'Utilities billing dispute',
        'other'       => 'General dispute',
    ];

    // ── Claim signal patterns ─────────────────────────────────────────────────
    // Used to extract "valid points" from free-text statements.

    private const TENANT_SIGNALS = [
        'payment_proof'   => ['receipt', 'bank transfer', 'paid', 'proof of payment', 'reçu', 'virement', 'إيصال', 'تحويل'],
        'prior_notice'    => ['gave notice', 'notified', 'informed', 'prévenu', 'j\'ai informé', 'أبلغت', 'أعطيت إشعاراً'],
        'property_issue'  => ['broken', 'leak', 'mold', 'damaged before', 'already damaged', 'cassé', 'fuite', 'مكسور', 'تسرب'],
        'contract_right'  => ['contract says', 'lease states', 'according to contract', 'le contrat stipule', 'العقد ينص'],
        'uninhabitable'   => ['unsafe', 'uninhabitable', 'health hazard', 'dangereux', 'غير صالح للسكن'],
        'no_response'     => ['no response', 'ignored', 'never replied', 'pas de réponse', 'لم يرد'],
    ];

    private const OWNER_SIGNALS = [
        'damage_evidence'  => ['photos', 'evidence', 'pictures', 'inspection', 'photos à l\'état des lieux', 'صور', 'دليل'],
        'unpaid_rent'      => ['unpaid', 'arrears', 'overdue', 'impayé', 'retard de paiement', 'متأخر عن الدفع'],
        'lease_violation'  => ['violated', 'breach', 'broke the rules', 'violation', 'خرق العقد', 'انتهك الشروط'],
        'repair_request'   => ['requested repair', 'contractor', 'fixed', 'réparation effectuée', 'طلب صيانة', 'أصلحنا'],
        'proper_notice'    => ['notice given', 'written notice', 'préavis envoyé', 'أُرسل إشعار خطي'],
        'contract_right'   => ['contract states', 'lease agreement', 'le bail prévoit', 'العقد ينص'],
    ];

    // ── Resolution knowledge base ─────────────────────────────────────────────

    private const RESOLUTIONS = [
        'deposit' => [
            'standard' => 'Conduct a joint property inspection within 5 business days. Compare the move-in and move-out inventory reports. Deductions must correspond to documented damage beyond normal wear-and-tear. Refund the balance within 30 days.',
            'no_inventory' => 'No move-in inventory was referenced by either party. Without this document, damage claims by the owner are difficult to enforce. Consider a 50/50 split of the disputed amount as a fair compromise.',
        ],
        'maintenance' => [
            'standard' => 'The owner bears responsibility for structural and habitability repairs under Tunisian law. The tenant must provide evidence of prior written maintenance requests. A qualified contractor should assess the issue within 7 days.',
            'tenant_caused' => 'If the tenant has caused or accelerated the damage through misuse, a proportional contribution to repair costs may be fair. Document findings with photos and a contractor assessment.',
        ],
        'noise' => [
            'standard' => 'Issue a formal written warning to the relevant party. Reference quiet-hours provisions in the lease or local building rules. A second incident within 30 days should trigger a formal dispute escalation.',
            'third_party' => 'If the noise originates from outside the tenant\'s unit (neighbours, street), the owner should intervene with building management and the tenant cannot be held liable.',
        ],
        'rent' => [
            'standard' => 'The tenant should provide payment proof (bank transfer records, receipts). If payment was genuinely late, any contractual late fees should be assessed. Establish a payment plan if there are financial hardship circumstances.',
            'dispute_amount' => 'Both parties should review the signed lease to confirm the agreed rent amount, any permitted increases, and whether charges are included. A neutral account review of the past 6 months should resolve discrepancies.',
        ],
        'damage' => [
            'standard' => 'Both parties should submit photographic evidence with timestamps. A neutral property inspector should assess replacement/repair cost. Normal wear-and-tear (paint fading, minor scuffs) is not chargeable to the tenant.',
            'no_evidence' => 'Neither party has provided photographic evidence. Without documentation, the dispute cannot be fairly resolved. Request both parties to submit timestamped photos within 48 hours.',
        ],
        'early_exit' => [
            'standard' => 'Review the lease for break-clause conditions, notice requirements, and any applicable penalty. If the tenant gave proper written notice as specified in the lease, no penalty should apply. If notice was insufficient, a proportional fee may be warranted.',
            'force_majeure' => 'If early termination was caused by uninhabitable conditions, job relocation, or medical circumstances, the penalty may be waivable. Request supporting documentation.',
        ],
        'utilities' => [
            'standard' => 'Request copies of all utility bills for the disputed period. Compare actual consumption to the lease\'s description of what is included. Any cap on included utilities should have been clearly stated at signing.',
            'retroactive' => 'Retroactive utility charges for past periods are generally unenforceable unless the lease explicitly allows them and the tenant was informed in writing.',
        ],
        'other' => [
            'standard' => 'Both parties should submit a written summary of their position with any supporting documents within 3 business days. An admin mediator will review and issue a recommendation within 5 business days.',
        ],
    ];

    // ── Public API ────────────────────────────────────────────────────────────

    public function mediate(
        string $tenantStatement,
        string $ownerStatement,
        string $contractExcerpt = '',
        string $disputeType = 'other'
    ): array {
        $triage        = $this->triage($tenantStatement, $ownerStatement, $disputeType);
        $tenantPoints  = $this->extractPoints($tenantStatement, self::TENANT_SIGNALS, 'tenant');
        $ownerPoints   = $this->extractPoints($ownerStatement,  self::OWNER_SIGNALS,  'owner');
        $resolution    = $this->resolveDispute($disputeType, $tenantStatement, $ownerStatement, $contractExcerpt);
        $recommendation = $this->adminRecommendation($triage['severity'], $disputeType, $tenantPoints, $ownerPoints);

        return [
            'dispute_type'   => self::DISPUTE_TYPES[$disputeType] ?? $disputeType,
            'triage'         => $triage,
            'summary'        => $this->buildSummary($tenantPoints, $ownerPoints, $disputeType, $triage),
            'tenant_points'  => $tenantPoints,
            'owner_points'   => $ownerPoints,
            'resolution'     => $resolution,
            'recommendation' => $recommendation,
            'severity'       => $triage['severity'],
            'escalate'       => $triage['severity'] === 'high',
            'next_steps'     => $this->nextSteps($triage['severity'], $disputeType),
            'balance_score'  => $this->balanceScore($tenantPoints, $ownerPoints),
        ];
    }

    // ── Point extraction ──────────────────────────────────────────────────────

    /**
     * Extract actionable valid points from a statement by matching signal patterns.
     */
    private function extractPoints(string $statement, array $signalMap, string $side): array
    {
        $lower  = mb_strtolower($statement);
        $points = [];

        $labels = [
            // Tenant signals
            'payment_proof'   => 'Has provided or references payment proof',
            'prior_notice'    => 'Claims to have given proper prior notice',
            'property_issue'  => 'Reports a pre-existing or ongoing property defect',
            'contract_right'  => 'References a specific lease clause in their favour',
            'uninhabitable'   => 'Claims the property was/is in an uninhabitable condition',
            'no_response'     => 'States the other party failed to respond to communications',
            // Owner signals
            'damage_evidence' => 'References photographic or inspection evidence of damage',
            'unpaid_rent'     => 'Claims rent payments are overdue or missing',
            'lease_violation' => 'Alleges a breach of the lease terms by the tenant',
            'repair_request'  => 'States a repair request was received and acted upon',
            'proper_notice'   => 'Claims proper written notice was served',
        ];

        foreach ($signalMap as $signalKey => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($lower, mb_strtolower($kw))) {
                    $points[] = $labels[$signalKey] ?? ucwords(str_replace('_', ' ', $signalKey));
                    break;
                }
            }
        }

        // Add a generic point if statement is substantial but no signals matched
        if (empty($points) && str_word_count($statement) > 20) {
            $points[] = ucfirst($side) . ' has submitted a detailed written account';
        }

        return array_values(array_unique($points));
    }

    // ── Dispute resolution ────────────────────────────────────────────────────

    private function resolveDispute(
        string $type,
        string $tenantStmt,
        string $ownerStmt,
        string $contract
    ): string {
        $resolutions = self::RESOLUTIONS[$type] ?? self::RESOLUTIONS['other'];

        // Pick the most appropriate resolution variant
        $lower = mb_strtolower($tenantStmt . ' ' . $ownerStmt);

        $variant = match ($type) {
            'deposit'    => str_contains($lower, 'inventory') || str_contains($lower, 'état des lieux')
                            ? 'standard' : 'no_inventory',
            'maintenance'=> str_contains($lower, 'misuse') || str_contains($lower, 'abuse')
                            ? 'tenant_caused' : 'standard',
            'noise'      => str_contains($lower, 'neighbour') || str_contains($lower, 'voisin') || str_contains($lower, 'الجيران')
                            ? 'third_party' : 'standard',
            'rent'       => str_contains($lower, 'amount') || str_contains($lower, 'montant') || str_contains($lower, 'المبلغ')
                            ? 'dispute_amount' : 'standard',
            'damage'     => !str_contains($lower, 'photo') && !str_contains($lower, 'evidence') && !str_contains($lower, 'proof')
                            ? 'no_evidence' : 'standard',
            'early_exit' => str_contains($lower, 'medical') || str_contains($lower, 'job') || str_contains($lower, 'relocation')
                            ? 'force_majeure' : 'standard',
            'utilities'  => str_contains($lower, 'retroactive') || str_contains($lower, 'rétroactif') || str_contains($lower, 'retroactively')
                            ? 'retroactive' : 'standard',
            default      => 'standard',
        };

        $text = $resolutions[$variant] ?? $resolutions['standard'];

        // Append contract reference if provided
        if (!empty(trim($contract))) {
            $text .= ' The submitted contract excerpt should be consulted to verify exact terms before issuing a final determination.';
        }

        return $text;
    }

    // ── Triage ────────────────────────────────────────────────────────────────

    private function triage(string $tenant, string $owner, string $type): array
    {
        $urgentWords = [
            'illegal', 'assault', 'threat', 'police', 'court', 'lawyer', 'violence', 'criminal',
            'menace', 'tribunal', 'avocat', 'illégal', 'محكمة', 'شرطة', 'محامي', 'تهديد', 'جريمة',
        ];

        $combined = mb_strtolower($tenant . ' ' . $owner);
        $severity = 'low';

        foreach ($urgentWords as $w) {
            if (str_contains($combined, $w)) {
                $severity = 'high';
                break;
            }
        }

        if ($severity !== 'high') {
            if (in_array($type, ['deposit', 'damage', 'early_exit'], true)) {
                $severity = 'medium';
            }
        }

        return [
            'severity'   => $severity,
            'summary'    => 'Statements from both parties have been received and analysed.',
            'resolution' => 'Admin review required before a final determination.',
        ];
    }

    // ── Summary builder ───────────────────────────────────────────────────────

    private function buildSummary(
        array $tenantPoints,
        array $ownerPoints,
        string $type,
        array $triage
    ): string {
        $typeLabel = self::DISPUTE_TYPES[$type] ?? 'dispute';
        $sevMap    = ['high' => 'a high-priority', 'medium' => 'a moderate', 'low' => 'a routine'];
        $sevLabel  = $sevMap[$triage['severity']] ?? 'a';

        $tCount = count($tenantPoints);
        $oCount = count($ownerPoints);

        $tenantDesc = $tCount > 0
            ? "The tenant has raised $tCount identifiable claim" . ($tCount > 1 ? 's' : '')
            : 'The tenant has submitted a general statement';

        $ownerDesc = $oCount > 0
            ? "the owner has presented $oCount identifiable point" . ($oCount > 1 ? 's' : '')
            : 'the owner has provided a general response';

        return "This is $sevLabel $typeLabel. $tenantDesc and $ownerDesc. "
            . 'Both submissions have been reviewed and a structured resolution path has been identified below.';
    }

    // ── Balance score ─────────────────────────────────────────────────────────

    /**
     * Returns a rough indicator of which side has stronger documented claims.
     * Positive = tenant has more documented points. Negative = owner has more.
     */
    private function balanceScore(array $tenantPoints, array $ownerPoints): int
    {
        return count($tenantPoints) - count($ownerPoints);
    }

    // ── Admin recommendation ──────────────────────────────────────────────────

    private function adminRecommendation(
        string $severity,
        string $type,
        array $tenantPoints,
        array $ownerPoints
    ): string {
        $balance = count($tenantPoints) - count($ownerPoints);

        $baseRec = match ($severity) {
            'high'   => 'Flag immediately for senior admin review. Suspend the listing if property condition is in question.',
            'medium' => 'Assign to a mediator within 48 hours. Request supporting documents from both parties.',
            default  => 'Standard 5-day resolution window applies. Send templated communications to both parties.',
        };

        $typeRec = match ($type) {
            'deposit'     => ' Request move-in and move-out inventory reports from both sides.',
            'maintenance' => ' Request timestamped maintenance communication logs.',
            'rent'        => ' Request bank statements or payment receipts for the disputed period.',
            'damage'      => ' Request timestamped photographs from both parties within 24 hours.',
            default       => '',
        };

        $balanceRec = match (true) {
            $balance >= 2  => ' Tenant appears to have stronger documented evidence — weigh accordingly.',
            $balance <= -2 => ' Owner appears to have stronger documented evidence — weigh accordingly.',
            default        => ' Neither party has a clear evidentiary advantage at this stage.',
        };

        return $baseRec . $typeRec . $balanceRec;
    }

    // ── Next steps ────────────────────────────────────────────────────────────

    private function nextSteps(string $severity, string $type): array
    {
        $steps = match ($severity) {
            'high'   => [
                'Flag for immediate admin review',
                'Suspend listing pending investigation',
                'Notify both parties within 24 hours',
            ],
            'medium' => [
                'Admin review within 48 hours',
                'Request supporting documents from both parties',
                'Offer mediation call if unresolved in 3 days',
            ],
            default  => [
                'Standard 5-day resolution window',
                'Send automated communication template to both parties',
            ],
        };

        if ($type === 'deposit')     { $steps[] = 'Request deposit receipt and move-in inventory photos'; }
        if ($type === 'maintenance') { $steps[] = 'Request timestamped maintenance request records'; }
        if ($type === 'rent')        { $steps[] = 'Request bank statements or receipts for last 3 months'; }
        if ($type === 'damage')      { $steps[] = 'Request timestamped before/after photos from both parties'; }

        return $steps;
    }
}
