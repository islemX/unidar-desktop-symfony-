<?php

namespace App\Service\AI;

use App\Entity\Listing;

/**
 * Feature 4: AI Listing Description Generator
 * Owner fills in basic details → AI writes polished EN/FR/AR descriptions.
 */
class ListingDescriptionGeneratorService
{
    private const LANGS = ['en' => 'English', 'fr' => 'French', 'ar' => 'Arabic'];

    public function __construct(private readonly LlmService $llm) {}

    /**
     * Generate a full description in all three languages.
     */
    public function generate(Listing $listing, string $lang = 'en'): array
    {
        $prompt = $this->buildPrompt($listing, $lang);
        $result = $this->llm->completeJson($prompt, ['temperature' => 0.8]);

        if (empty($result)) {
            return $this->fallbackDescription($listing, $lang);
        }

        return [
            'description' => $result['description'] ?? $this->fallbackDescription($listing, $lang)['description'],
            'title'       => $result['title']       ?? $listing->getTitle(),
            'highlights'  => $result['highlights']  ?? [],
            'lang'        => $lang,
            'generated'   => true,
        ];
    }

    /**
     * Generate descriptions in all three languages at once.
     */
    public function generateAll(Listing $listing): array
    {
        $results = [];
        foreach (array_keys(self::LANGS) as $lang) {
            $results[$lang] = $this->generate($listing, $lang);
        }
        return $results;
    }

    private function buildPrompt(Listing $listing, string $lang): string
    {
        $langName = self::LANGS[$lang] ?? 'English';
        $amenities = implode(', ', $listing->getAmenities() ?? []);

        return <<<PROMPT
You are a professional real-estate copywriter specialising in student housing in Tunisia.
Write a compelling listing for a student housing platform in {$langName}.

Property details:
- Type: {$listing->getPropertyType()}
- City: {$listing->getCity()}
- Bedrooms: {$listing->getBedrooms()}
- Bathrooms: {$listing->getBathrooms()}
- Price: {$listing->getPrice()} TND/month
- Area: {$listing->getArea()} m²
- Amenities: {$amenities}
- Nearby universities: {$listing->getNearbyUniversities()}
- Owner notes: {$listing->getOwnerNotes()}

Write an honest, engaging property description targeting university students.
Emphasise location, value for money, and student-friendly features.

Respond with JSON:
{
  "title": "catchy listing title (max 60 chars)",
  "description": "3-4 paragraph description",
  "highlights": ["bullet 1", "bullet 2", "bullet 3"]
}
PROMPT;
    }

    private function fallbackDescription(Listing $listing, string $lang): array
    {
        $templates = [
            'en' => "Bright {bedrooms}-bedroom {type} available in {city} for {price} TND/month. Ideal for students. {amenities}",
            'fr' => "Bel appartement {bedrooms} chambre(s) à {city} pour {price} TND/mois. Idéal pour les étudiants. {amenities}",
            'ar' => "شقة {bedrooms} غرفة نوم متاحة في {city} بسعر {price} دينار/شهر. مثالية للطلاب. {amenities}",
        ];

        $tpl = $templates[$lang] ?? $templates['en'];
        $desc = str_replace(
            ['{bedrooms}', '{type}', '{city}', '{price}', '{amenities}'],
            [
                $listing->getBedrooms() ?? 1,
                $listing->getPropertyType() ?? 'apartment',
                $listing->getCity() ?? '',
                $listing->getPrice() ?? 0,
                implode(', ', array_slice($listing->getAmenities() ?? [], 0, 3)),
            ],
            $tpl
        );

        return [
            'description' => $desc,
            'title'       => $listing->getTitle() ?? 'Student Housing',
            'highlights'  => [],
            'lang'        => $lang,
            'generated'   => false,
        ];
    }
}
