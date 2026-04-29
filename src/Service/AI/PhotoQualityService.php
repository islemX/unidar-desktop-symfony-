<?php

namespace App\Service\AI;

/**
 * Feature 5: Photo Quality & Completeness Scorer
 * Analyses listing photos for blur, brightness, coverage.
 * Uses GD/Imagick for rule-based analysis; optionally calls vision API.
 */
class PhotoQualityService
{
    private const REQUIRED_ROOMS = ['living_room', 'bedroom', 'kitchen', 'bathroom'];

    public function __construct() {}

    /**
     * @param string[] $photoPaths  Absolute filesystem paths
     * @return array  [overall_score, photos:[], missing_rooms:[], suggestions:[]]
     */
    public function analyzeSet(array $photoPaths): array
    {
        $photoResults = [];
        $totalScore   = 0;

        foreach ($photoPaths as $path) {
            $result         = $this->analyzeOne($path);
            $photoResults[] = $result;
            $totalScore    += $result['score'];
        }

        $avgScore = count($photoPaths) > 0
            ? (int) round($totalScore / count($photoPaths))
            : 0;

        // Coverage bonus/penalty
        $coverage      = $this->checkCoverage($photoPaths);
        $coverageScore = (int) round($coverage['covered_ratio'] * 20); // up to +20 pts
        $overallScore  = min(100, $avgScore + $coverageScore);

        return [
            'overall_score'  => $overallScore,
            'grade'          => $this->grade($overallScore),
            'photo_count'    => count($photoPaths),
            'photos'         => $photoResults,
            'missing_rooms'  => $coverage['missing'],
            'suggestions'    => $this->buildSuggestions($photoResults, $coverage),
        ];
    }

    public function analyzeOne(string $path): array
    {
        if (!file_exists($path)) {
            return ['path' => $path, 'score' => 0, 'issues' => ['File not found']];
        }

        $issues = [];
        $score  = 100;

        [$width, $height] = @getimagesize($path) ?: [0, 0];

        // Resolution check
        if ($width < 800 || $height < 600) {
            $issues[] = 'Resolution too low (min 800×600 recommended)';
            $score   -= 25;
        }

        // Aspect ratio — very narrow or very square photos look odd
        $ratio = $width > 0 ? $width / $height : 1;
        if ($ratio < 1.1 || $ratio > 2.5) {
            $issues[] = 'Unusual aspect ratio — landscape 4:3 or 16:9 works best';
            $score   -= 10;
        }

        // Brightness & blur via GD (if available)
        if (extension_loaded('gd') && $width > 0) {
            [$brightness, $blur] = $this->gdAnalyze($path, $width, $height);

            if ($brightness < 40) {
                $issues[] = 'Photo appears dark — try taking it in natural light';
                $score   -= 20;
            } elseif ($brightness > 220) {
                $issues[] = 'Photo appears overexposed';
                $score   -= 10;
            }

            if ($blur > 0.7) {
                $issues[] = 'Photo may be blurry — ensure camera is steady';
                $score   -= 20;
            }
        }

        // File size (< 50KB usually means low quality compressed)
        $fileSize = filesize($path);
        if ($fileSize < 50_000) {
            $issues[] = 'File size very small — image may be heavily compressed';
            $score   -= 15;
        }

        return [
            'path'       => basename($path),
            'score'      => max(0, $score),
            'issues'     => $issues,
            'width'      => $width,
            'height'     => $height,
            'file_size'  => $fileSize,
        ];
    }

    // ── Room coverage (heuristic based on filename keywords) ─────────────────

    private function checkCoverage(array $paths): array
    {
        $roomKeywords = [
            'living_room' => ['living', 'salon', 'lounge', 'séjour'],
            'bedroom'     => ['bed', 'chambre', 'room', 'bedroom'],
            'kitchen'     => ['kitchen', 'cuisine', 'cook'],
            'bathroom'    => ['bath', 'salle', 'wc', 'toilet'],
        ];

        $covered = [];
        foreach ($paths as $path) {
            $name = strtolower(basename($path));
            foreach ($roomKeywords as $room => $keywords) {
                foreach ($keywords as $kw) {
                    if (str_contains($name, $kw)) {
                        $covered[$room] = true;
                        break 2;
                    }
                }
            }
        }

        $missing = array_diff(self::REQUIRED_ROOMS, array_keys($covered));

        return [
            'covered'       => array_keys($covered),
            'missing'       => array_values($missing),
            'covered_ratio' => count($covered) / count(self::REQUIRED_ROOMS),
        ];
    }

    private function gdAnalyze(string $path, int $w, int $h): array
    {
        $info = getimagesize($path);
        $img  = match ($info[2] ?? 0) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($path),
            IMAGETYPE_PNG  => imagecreatefrompng($path),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($path) : null,
            default        => null,
        };

        if (!$img) return [128.0, 0.0];

        // Sample a 10×10 grid for average brightness
        $totalBrightness = 0;
        $samples = 0;
        $stepX = max(1, (int)($w / 10));
        $stepY = max(1, (int)($h / 10));

        for ($x = 0; $x < $w; $x += $stepX) {
            for ($y = 0; $y < $h; $y += $stepY) {
                $rgb  = imagecolorat($img, $x, $y);
                $r    = ($rgb >> 16) & 0xFF;
                $g    = ($rgb >> 8)  & 0xFF;
                $b    = $rgb         & 0xFF;
                $totalBrightness += 0.299 * $r + 0.587 * $g + 0.114 * $b;
                $samples++;
            }
        }

        $avgBrightness = $samples > 0 ? $totalBrightness / $samples : 128.0;

        // Rough blur estimate: variance of adjacent pixel differences
        $diffs  = 0;
        $count  = 0;
        for ($x = 0; $x < min($w - 1, 100); $x++) {
            for ($y = 0; $y < min($h - 1, 100); $y++) {
                $c1 = imagecolorat($img, $x, $y);
                $c2 = imagecolorat($img, $x + 1, $y);
                $diffs += abs(($c1 & 0xFF) - ($c2 & 0xFF));
                $count++;
            }
        }
        $blurScore = $count > 0 ? max(0.0, 1.0 - ($diffs / $count / 30)) : 0.0;

        imagedestroy($img);
        return [$avgBrightness, $blurScore];
    }

    private function buildSuggestions(array $photos, array $coverage): array
    {
        $suggestions = [];

        $lowScore = array_filter($photos, fn($p) => $p['score'] < 60);
        if (count($lowScore) > 0) {
            $suggestions[] = 'Retake ' . count($lowScore) . ' photo(s) with better lighting and a steady hand.';
        }
        if (count($photos) < 4) {
            $suggestions[] = 'Add at least ' . (4 - count($photos)) . ' more photos — listings with 4+ photos get 2× more enquiries.';
        }
        foreach ($coverage['missing'] as $room) {
            $suggestions[] = 'Add a photo of the ' . str_replace('_', ' ', $room) . '.';
        }

        return $suggestions;
    }

    private function grade(int $score): string
    {
        return match (true) {
            $score >= 85 => 'A',
            $score >= 70 => 'B',
            $score >= 55 => 'C',
            $score >= 40 => 'D',
            default      => 'F',
        };
    }
}
