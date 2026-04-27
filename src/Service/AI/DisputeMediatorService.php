<?php

namespace App\Service\AI;

/**
 * Feature 11: AI Dispute Mediator
 * Reads both sides' statements + contract → neutral summary + resolution suggestion.
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

    public function __construct(private readonly LlmService $llm) {}

    /**
     * @param string $tenantStatement
     * @param string $ownerStatement
     * @param string $contractExcerpt  Relevant contract clause(s)
     * @param string $disputeType
     */
    public function mediate(
        string $tenantStatement,
        string $ownerStatement,
        string $contractExcerpt = '',
        string $disputeType = 'other'
    ): array {
        // Rule-based triage first
        $triage = $this->triage($tenantStatement, $ownerStatement, $disputeType);

        // LLM mediation
        $llmResult = $this->llmMediate($tenantStatement, $ownerStatement, $contractExcerpt, $disputeType);

        return [
            'dispute_type'    => self::DISPUTE_TYPES[$disputeType] ?? $disputeType,
            'triage'          => $triage,
            'summary'         => $llmResult['summary']         ?? $triage['summary'],
            'tenant_points'   => $llmResult['tenant_points']   ?? [],
            'owner_points'    => $llmResult['owner_points']    ?? [],
            'resolution'      => $llmResult['resolution']      ?? $triage['resolution'],
            'recommendation'  => $llmResult['recommendation']  ?? '',
            'severity'        => $triage['severity'],
            'escalate'        => $triage['severity'] === 'high',
            'next_steps'      => $this->nextSteps($triage['severity'], $disputeType),
        ];
    }

    private function llmMediate(
        string $tenant,
        string $owner,
        string $contract,
        string $type
    ): array {
        $contractSection = $contract
            ? "Relevant contract clause:\n{$contract}\n"
            : '';

        $prompt = <<<PROMPT
You are a neutral housing dispute mediator in Tunisia. Analyse this dispute objectively.

Dispute type: {$type}

Tenant's statement:
{$tenant}

Owner's statement:
{$owner}

{$contractSection}

Provide a balanced, neutral analysis. Do not favour either party.

Respond with JSON:
{
  "summary": "2-3 sentence neutral summary of the dispute",
  "tenant_points": ["valid point 1", "valid point 2"],
  "owner_points": ["valid point 1", "valid point 2"],
  "resolution": "Suggested fair resolution",
  "recommendation": "Specific next step for admin"
}
PROMPT;

        return $this->llm->completeJson($prompt, ['temperature' => 0.3]);
    }

    private function triage(string $tenant, string $owner, string $type): array
    {
        $urgentWords = ['illegal', 'assault', 'threat', 'police', 'court', 'lawyer', 'menace', 'محكمة', 'شرطة'];
        $combined    = strtolower($tenant . ' ' . $owner);

        $severity = 'low';
        foreach ($urgentWords as $w) {
            if (str_contains($combined, $w)) { $severity = 'high'; break; }
        }

        if ($type === 'deposit' || $type === 'damage') $severity = max($severity, 'medium');

        return [
            'severity'   => $severity,
            'summary'    => 'Both parties have submitted statements. Review pending.',
            'resolution' => 'Admin review required before proceeding.',
        ];
    }

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

        if ($type === 'deposit') {
            $steps[] = 'Request deposit receipt and move-in inventory photos';
        }
        if ($type === 'maintenance') {
            $steps[] = 'Request timestamped maintenance request records';
        }

        return $steps;
    }
}
