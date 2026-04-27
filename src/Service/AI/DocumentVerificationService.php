<?php

namespace App\Service\AI;

/**
 * Feature 14: Document Verification AI
 * OCR + field extraction + validity check for student ID and enrollment certificates.
 */
class DocumentVerificationService
{
    private const KNOWN_UNIVERSITIES = [
        'université de tunis', 'université de carthage', 'université de sousse',
        'université de sfax', 'université de monastir', 'université de gabès',
        'université de jendouba', 'université de gafsa', 'université virtuelle',
        'esprit', 'enit', 'ensi', 'iset', 'ihec', 'isim', 'isg', 'ess', 'fst',
        'ecole polytechnique', 'ecole nationale', 'institut supérieur',
    ];

    public function __construct(private readonly LlmService $llm) {}

    /**
     * Verify a student ID card or enrollment certificate.
     * @param string $imagePath   Absolute path to uploaded document image/PDF
     * @param string $docType     'student_id' | 'enrollment_certificate' | 'national_id'
     * @return array [valid, confidence, fields, flags, result]
     */
    public function verify(string $imagePath, string $docType = 'student_id'): array
    {
        // Extract text via OCR (if available) or rule-based
        $extractedText = $this->extractText($imagePath);
        $fields        = $this->extractFields($extractedText, $docType);
        $flags         = $this->validateFields($fields, $docType);
        $confidence    = $this->computeConfidence($fields, $flags, $docType);

        return [
            'valid'      => $confidence >= 0.65 && empty(array_filter($flags, fn($f) => $f['severity'] === 'critical')),
            'confidence' => round($confidence, 2),
            'doc_type'   => $docType,
            'fields'     => $fields,
            'flags'      => $flags,
            'result'     => $this->resultLabel($confidence),
            'extracted_text' => $extractedText ? substr($extractedText, 0, 300) : null,
        ];
    }

    // ── OCR text extraction ───────────────────────────────────────────────────

    private function extractText(string $path): string
    {
        // Try Tesseract OCR if available
        if ($this->isTesseractAvailable() && file_exists($path)) {
            $escaped  = escapeshellarg($path);
            $output   = shell_exec("tesseract {$escaped} stdout -l ara+fra+eng 2>/dev/null");
            if ($output && strlen(trim($output)) > 20) return $output;
        }

        // Fallback: ask LLM to extract from image description
        // (In production, pass base64-encoded image to a vision model)
        return '';
    }

    // ── Field extraction via NLP ──────────────────────────────────────────────

    private function extractFields(string $text, string $docType): array
    {
        if (empty($text)) return $this->emptyFields($docType);

        $lower  = mb_strtolower($text);
        $fields = [];

        // Name extraction
        if (preg_match('/(?:nom|name|الاسم|إسم)\s*:?\s*([A-ZÀ-ÿ][a-zA-ZÀ-ÿ\s\-]{2,40})/i', $text, $m)) {
            $fields['name'] = trim($m[1]);
        }

        // Student ID number
        if (preg_match('/(?:n°|n\.|num|رقم|matricule)\s*:?\s*([A-Z0-9\-]{4,20})/i', $text, $m)) {
            $fields['student_id'] = trim($m[1]);
        }

        // Academic year
        if (preg_match('/(\d{4})\s*[-\/]\s*(\d{4})/', $text, $m)) {
            $fields['academic_year'] = $m[1] . '–' . $m[2];
            $fields['academic_year_start'] = (int) $m[1];
        }

        // University name
        foreach (self::KNOWN_UNIVERSITIES as $uni) {
            if (str_contains($lower, $uni)) {
                $fields['university'] = ucwords($uni);
                break;
            }
        }

        // Expiry date
        if (preg_match('/(?:expire|valide|valid until|صالح)\s*:?\s*(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})/i', $text, $m)) {
            $fields['expiry'] = $m[1];
        }

        return $fields;
    }

    // ── Validation ────────────────────────────────────────────────────────────

    private function validateFields(array $fields, string $docType): array
    {
        $flags = [];

        if (empty($fields['name'])) {
            $flags[] = ['field' => 'name', 'severity' => 'critical', 'message' => 'Student name not found in document'];
        }

        if ($docType === 'student_id' && empty($fields['student_id'])) {
            $flags[] = ['field' => 'student_id', 'severity' => 'high', 'message' => 'Student ID number not detected'];
        }

        if ($docType === 'enrollment_certificate') {
            if (empty($fields['academic_year'])) {
                $flags[] = ['field' => 'academic_year', 'severity' => 'high', 'message' => 'Academic year not found'];
            } elseif (isset($fields['academic_year_start'])) {
                $currentYear = (int) date('Y');
                if ($fields['academic_year_start'] < $currentYear - 1) {
                    $flags[] = ['field' => 'academic_year', 'severity' => 'critical', 'message' => 'Document is from a previous academic year — may be outdated'];
                }
            }
        }

        if (empty($fields['university'])) {
            $flags[] = ['field' => 'university', 'severity' => 'medium', 'message' => 'University name not recognised — manual review recommended'];
        }

        if (!empty($fields['expiry'])) {
            try {
                $expiry = \DateTime::createFromFormat('d/m/Y', $fields['expiry'])
                       ?? \DateTime::createFromFormat('d-m-Y', $fields['expiry']);
                if ($expiry && $expiry < new \DateTime()) {
                    $flags[] = ['field' => 'expiry', 'severity' => 'critical', 'message' => 'Document is expired'];
                }
            } catch (\Throwable) {}
        }

        return $flags;
    }

    private function computeConfidence(array $fields, array $flags, string $docType): float
    {
        $base = 0.0;
        if (!empty($fields['name']))        $base += 0.30;
        if (!empty($fields['university']))  $base += 0.25;
        if (!empty($fields['student_id']))  $base += 0.20;
        if (!empty($fields['academic_year'])) $base += 0.15;
        if (!empty($fields['expiry']))      $base += 0.10;

        foreach ($flags as $flag) {
            $base -= match ($flag['severity']) {
                'critical' => 0.30,
                'high'     => 0.15,
                'medium'   => 0.05,
                default    => 0.0,
            };
        }

        return max(0.0, min(1.0, $base));
    }

    private function resultLabel(float $confidence): string
    {
        return match (true) {
            $confidence >= 0.85 => '✅ Verified',
            $confidence >= 0.65 => '⚠️ Likely valid — minor issues',
            $confidence >= 0.40 => '🔍 Manual review required',
            default             => '❌ Verification failed',
        };
    }

    private function emptyFields(string $docType): array
    {
        return ['name' => null, 'student_id' => null, 'university' => null, 'academic_year' => null];
    }

    private function isTesseractAvailable(): bool
    {
        return (bool) shell_exec('which tesseract 2>/dev/null');
    }
}
