<?php

namespace App\Service\AI;

use App\Entity\Listing;

/**
 * Feature 4: AI Listing Description Generator
 * Generates polished EN/FR/AR descriptions from listing data using a rich
 * template engine — no external LLM required.
 */
class ListingDescriptionGeneratorService
{
    // ── Phrase banks (EN) ─────────────────────────────────────────────────────

    private const INTROS_EN = [
        'studio'    => [
            'Compact and thoughtfully designed, this studio is a perfect base for students who value independence.',
            'A bright, self-contained studio ready for the next chapter of your academic journey.',
            'Efficient and modern, this studio puts everything you need within arm\'s reach.',
        ],
        'apartment' => [
            'Spacious and well-connected, this apartment is ideal for students seeking comfort and convenience.',
            'A welcoming home-away-from-home in the heart of student life.',
            'Light-filled and generously sized, this apartment balances study and relaxation effortlessly.',
        ],
        'room'      => [
            'An affordable private room in a friendly shared flat — the smart choice for budget-conscious students.',
            'Your own peaceful retreat within a well-maintained shared residence.',
            'A cosy private room with everything you need to focus, rest, and thrive.',
        ],
        'villa'     => [
            'A rare find: a full villa offering space and privacy that shared accommodation simply cannot match.',
            'Spacious and private, this villa suits a group of students looking for a premium living experience.',
            'Enjoy the luxury of your own villa while keeping costs manageable when shared with classmates.',
        ],
        'default'   => [
            'A great option for students seeking a comfortable and well-located home in Tunisia.',
            'Well-maintained and move-in ready, this property is everything a student needs.',
        ],
    ];

    private const LOCATION_PHRASES_EN = [
        'Tunis'      => ['in the vibrant capital', 'steps from the city centre', 'with excellent metro access'],
        'Sfax'       => ['in Tunisia\'s second city', 'near the industrial and academic hub', 'in a lively university district'],
        'Sousse'     => ['on the beautiful Sahel coast', 'minutes from the beach and campus', 'in the heart of the tourist and student belt'],
        'Monastir'   => ['by the sea in Monastir', 'close to Monastir University', 'in this charming coastal city'],
        'Ariana'     => ['in the modern suburb of Ariana', 'close to major private universities', 'with quick access to Tunis centre'],
        'default'    => ['in a well-connected neighbourhood', 'near essential amenities', 'in a sought-after student area'],
    ];

    private const PRICE_PHRASES_EN = [
        'budget'   => ['Exceptional value at just {price} TND/month.', 'Hard to beat at {price} TND/month for the quality on offer.'],
        'mid'      => ['Priced at a fair {price} TND/month for everything included.', 'At {price} TND/month this represents solid value for money.'],
        'premium'  => ['Priced at {price} TND/month, reflecting the quality and prime location.', 'A premium option at {price} TND/month — worth every dinar.'],
    ];

    private const CLOSING_EN = [
        'Schedule a viewing today — quality student housing at this price moves fast.',
        'Contact the owner now to arrange a visit before it\'s taken.',
        'Don\'t miss this opportunity — reach out to book your viewing.',
        'Available now. Message the owner to confirm availability and arrange a visit.',
    ];

    private const HIGHLIGHTS_EN = [
        'wifi'       => 'Fast Wi-Fi included',
        'ac'         => 'Air conditioning installed',
        'furnished'  => 'Fully furnished — move in immediately',
        'parking'    => 'Private parking space available',
        'balcony'    => 'Private balcony with open views',
        'security'   => '24/7 building security',
        'elevator'   => 'Lift access',
        'washer'     => 'Washing machine on-site',
        'kitchen'    => 'Fully equipped kitchen',
        'bills'      => 'Bills included in the rent',
    ];

    // ── Phrase banks (FR) ─────────────────────────────────────────────────────

    private const INTROS_FR = [
        'studio'    => [
            'Studio fonctionnel et lumineux, idéal pour un étudiant souhaitant son indépendance.',
            'Un studio bien conçu, prêt à accueillir votre vie étudiante dès aujourd\'hui.',
            'Compact et moderne, ce studio vous offre tout le confort nécessaire à votre réussite.',
        ],
        'apartment' => [
            'Grand appartement lumineux, parfait pour des étudiants en quête de confort et de praticité.',
            'Un espace de vie agréable et bien situé pour accompagner votre parcours universitaire.',
            'Cet appartement bien entretenu allie espace, lumière et situation idéale.',
        ],
        'room'      => [
            'Une chambre privative abordable dans une colocation conviviale — le bon choix pour les étudiants.',
            'Votre espace personnel et tranquille au sein d\'une résidence partagée bien tenue.',
            'Chambre confortable avec tout le nécessaire pour étudier, se reposer et s\'épanouir.',
        ],
        'villa'     => [
            'Une villa spacieuse et rare, offrant intimité et espace dans un cadre résidentiel privilégié.',
            'Profitez du confort d\'une villa entière tout en maîtrisant votre budget à plusieurs.',
            'Grande villa idéale pour un groupe d\'étudiants cherchant une expérience de vie premium.',
        ],
        'default'   => [
            'Un logement confortable et bien situé pour les étudiants en Tunisie.',
            'Bien entretenu et prêt à emménager, ce logement correspond à toutes vos attentes.',
        ],
    ];

    private const PRICE_PHRASES_FR = [
        'budget'  => ['Excellent rapport qualité/prix à seulement {price} DT/mois.', 'Difficile de trouver mieux à {price} DT/mois pour la qualité proposée.'],
        'mid'     => ['Affiché à {price} DT/mois, un tarif tout à fait raisonnable.', 'À {price} DT/mois, ce logement offre un excellent rapport qualité/prix.'],
        'premium' => ['Proposé à {price} DT/mois, reflet de la qualité et de l\'emplacement.', 'Un logement premium à {price} DT/mois — chaque dinar est justifié.'],
    ];

    private const CLOSING_FR = [
        'Prenez rendez-vous dès maintenant — les bons logements étudiants partent vite.',
        'Contactez le propriétaire aujourd\'hui pour organiser une visite.',
        'Ne ratez pas cette opportunité — écrivez au propriétaire pour confirmer la disponibilité.',
        'Disponible immédiatement. Contactez le propriétaire pour visiter.',
    ];

    private const HIGHLIGHTS_FR = [
        'wifi'      => 'Wi-Fi rapide inclus',
        'ac'        => 'Climatisation installée',
        'furnished' => 'Entièrement meublé — emménagement immédiat',
        'parking'   => 'Place de parking privative',
        'balcony'   => 'Balcon privatif avec vue dégagée',
        'security'  => 'Sécurité 24h/24',
        'elevator'  => 'Ascenseur',
        'washer'    => 'Machine à laver disponible',
        'kitchen'   => 'Cuisine entièrement équipée',
        'bills'     => 'Charges comprises dans le loyer',
    ];

    // ── Phrase banks (AR) ─────────────────────────────────────────────────────

    private const INTROS_AR = [
        'studio'    => [
            'استوديو عملي ومشرق، مثالي لطالب يبحث عن استقلاليته الخاصة.',
            'استوديو حديث ومصمم بعناية، جاهز لاستقبالك في رحلتك الجامعية.',
            'استوديو مريح يوفر كل ما تحتاجه للدراسة والراحة في مكان واحد.',
        ],
        'apartment' => [
            'شقة واسعة ومضيئة، مثالية للطلاب الباحثين عن الراحة والموقع المميز.',
            'مسكن مريح وقريب من أهم مرافق الحياة الجامعية.',
            'شقة مشرقة وفسيحة تجمع بين الدراسة والاسترخاء في أجواء ممتعة.',
        ],
        'room'      => [
            'غرفة خاصة بسعر مناسب في شقة مشتركة ودية — الخيار الذكي للطالب المدروس.',
            'مساحتك الخاصة الهادئة ضمن سكن مشترك منظم ومريح.',
            'غرفة مريحة بكل ما تحتاجه للتركيز والاسترخاء والنجاح.',
        ],
        'villa'     => [
            'فيلا فسيحة ونادرة توفر الخصوصية والمساحة بعيداً عن ضجيج الأحياء.',
            'استمتع بفخامة فيلا كاملة مع إمكانية تقاسم التكاليف مع زملائك.',
            'فيلا واسعة مثالية لمجموعة من الطلاب تبحث عن تجربة سكن متميزة.',
        ],
        'default'   => [
            'سكن مريح وذو موقع ممتاز للطلاب في تونس.',
            'مسكن جاهز للانتقال الفوري يلبي جميع احتياجاتك.',
        ],
    ];

    private const PRICE_PHRASES_AR = [
        'budget'  => ['قيمة استثنائية بسعر {price} دينار فقط في الشهر.', 'يصعب إيجاد ما يضاهيه بسعر {price} دينار شهرياً.'],
        'mid'     => ['بسعر عادل يبلغ {price} دينار في الشهر مقابل كل ما هو متاح.', 'بـ {price} دينار شهرياً، هذا السكن يمثل قيمة جيدة للمال.'],
        'premium' => ['بسعر {price} دينار شهرياً يعكس الجودة والموقع المميز.', 'خيار متميز بـ {price} دينار شهرياً — يستحق كل دينار.'],
    ];

    private const CLOSING_AR = [
        'احجز موعدك للمعاينة اليوم — السكن الجيد للطلاب يُحجز بسرعة.',
        'تواصل مع المالك الآن لترتيب زيارة قبل أن يُحجز.',
        'لا تفوّت الفرصة — راسل المالك لتأكيد الإتاحة وترتيب المعاينة.',
        'متاح الآن. تواصل مع المالك لتحديد موعد الزيارة.',
    ];

    private const HIGHLIGHTS_AR = [
        'wifi'      => 'إنترنت سريع مشمول',
        'ac'        => 'تكييف هواء مثبّت',
        'furnished' => 'مفروش بالكامل — انتقل فوراً',
        'parking'   => 'موقف سيارة خاص',
        'balcony'   => 'شرفة خاصة مع إطلالة مفتوحة',
        'security'  => 'حراسة على مدار الساعة',
        'elevator'  => 'مصعد كهربائي',
        'washer'    => 'غسالة ملابس متاحة',
        'kitchen'   => 'مطبخ مجهز بالكامل',
        'bills'     => 'الفواتير مشمولة في الإيجار',
    ];

    // ── Public API ────────────────────────────────────────────────────────────

    public function generate(Listing $listing, string $lang = 'en'): array
    {
        return match ($lang) {
            'fr'    => $this->buildDescription($listing, 'fr'),
            'ar'    => $this->buildDescription($listing, 'ar'),
            default => $this->buildDescription($listing, 'en'),
        };
    }

    public function generateAll(Listing $listing): array
    {
        return [
            'en' => $this->generate($listing, 'en'),
            'fr' => $this->generate($listing, 'fr'),
            'ar' => $this->generate($listing, 'ar'),
        ];
    }

    // ── Builder ───────────────────────────────────────────────────────────────

    private function buildDescription(Listing $listing, string $lang): array
    {
        $type      = $listing->getPropertyType()?->value ?? 'default';
        $city      = $listing->getCity() ?? '';
        $price     = (float) ($listing->getPrice() ?? 0);
        $bedrooms  = (int) ($listing->getBedrooms() ?? 1);
        $bathrooms = (int) ($listing->getBathrooms() ?? 1);
        $capacity  = (int) ($listing->getCapacity() ?? $bedrooms);
        $amenities = method_exists($listing, 'getAmenities') ? ($listing->getAmenities() ?? []) : [];

        $priceKey = match (true) {
            $price < 400  => 'budget',
            $price < 900  => 'mid',
            default       => 'premium',
        };

        [$intros, $locationPhrases, $pricePhrases, $closings, $highlightMap] = match ($lang) {
            'fr'    => [self::INTROS_FR,    null,                        self::PRICE_PHRASES_FR, self::CLOSING_FR, self::HIGHLIGHTS_FR],
            'ar'    => [self::INTROS_AR,    null,                        self::PRICE_PHRASES_AR, self::CLOSING_AR, self::HIGHLIGHTS_AR],
            default => [self::INTROS_EN,    self::LOCATION_PHRASES_EN,   self::PRICE_PHRASES_EN, self::CLOSING_EN, self::HIGHLIGHTS_EN],
        };

        // Pick templates deterministically based on listing id for consistency
        $seed = (int) ($listing->getId() ?? mt_rand(0, 9999));

        $introPool  = $intros[$type] ?? $intros['default'];
        $intro      = $introPool[$seed % count($introPool)];

        $pricePool  = $pricePhrases[$priceKey];
        $priceLine  = str_replace('{price}', number_format($price, 0), $pricePool[$seed % count($pricePool)]);

        $closing    = $closings[$seed % count($closings)];

        // Location sentence (EN only; FR/AR use city inline)
        $locationLine = '';
        if ($lang === 'en') {
            $cityKey  = array_key_exists($city, self::LOCATION_PHRASES_EN) ? $city : 'default';
            $pool     = self::LOCATION_PHRASES_EN[$cityKey];
            $locationLine = ucfirst($pool[$seed % count($pool)]) . '.';
        }

        // Middle paragraph about specifics
        $middle = $this->buildMiddleParagraph($bedrooms, $bathrooms, $capacity, $price, $city, $lang);

        // Assemble paragraphs
        $paragraphs = array_filter([$intro, $locationLine, $middle, $priceLine, $closing]);
        $description = implode(' ', $paragraphs);

        // Highlights
        $highlights = $this->buildHighlights($amenities, $highlightMap);

        // Title
        $title = $this->buildTitle($listing, $lang);

        return [
            'title'       => $title,
            'description' => $description,
            'highlights'  => $highlights,
            'lang'        => $lang,
            'generated'   => true,
        ];
    }

    private function buildMiddleParagraph(
        int $bedrooms, int $bathrooms, int $capacity,
        float $price, string $city, string $lang
    ): string {
        return match ($lang) {
            'fr' => sprintf(
                'Ce logement de %d chambre%s et %d salle%s de bain peut accueillir jusqu\'à %d personne%s. '
                . 'Idéalement situé à %s, il vous garantit un cadre de vie studieux et agréable.',
                $bedrooms, $bedrooms > 1 ? 's' : '',
                $bathrooms, $bathrooms > 1 ? 's' : '',
                $capacity, $capacity > 1 ? 's' : '',
                $city ?: 'proximité'
            ),
            'ar' => sprintf(
                'يضم هذا السكن %d غرفة نوم و%d حمام ويتسع لـ %d ساكن. '
                . 'يتمتع بموقع ممتاز %s، مما يجعله بيئة مثالية للدراسة والراحة.',
                $bedrooms, $bathrooms, $capacity,
                $city ? "في $city" : 'في موقع مركزي'
            ),
            default => sprintf(
                'The property features %d bedroom%s and %d bathroom%s, '
                . 'comfortably accommodating up to %d student%s. '
                . 'Everything you need for a productive academic year is right here%s.',
                $bedrooms, $bedrooms > 1 ? 's' : '',
                $bathrooms, $bathrooms > 1 ? 's' : '',
                $capacity, $capacity > 1 ? 's' : '',
                $city ? " in $city" : ''
            ),
        };
    }

    private function buildHighlights(array $amenities, array $highlightMap): array
    {
        $amenitiesLower = array_map('strtolower', $amenities);
        $result = [];

        foreach ($highlightMap as $key => $label) {
            foreach ($amenitiesLower as $amenity) {
                if (str_contains($amenity, $key)) {
                    $result[] = $label;
                    break;
                }
            }
            if (count($result) >= 4) break;
        }

        return $result;
    }

    private function buildTitle(Listing $listing, string $lang): string
    {
        $type      = $listing->getPropertyType()?->value ?? 'property';
        $bedrooms  = (int) ($listing->getBedrooms() ?? 1);
        $city      = $listing->getCity() ?? '';
        $price     = (int) ($listing->getPrice() ?? 0);

        return match ($lang) {
            'fr' => sprintf(
                '%s %d chambre%s à %s — %d DT/mois',
                ucfirst($type), $bedrooms, $bedrooms > 1 ? 's' : '',
                $city ?: 'Tunisie', $price
            ),
            'ar' => sprintf(
                '%s بـ %d غرفة في %s — %d دينار/شهر',
                $this->typeAr($type), $bedrooms,
                $city ?: 'تونس', $price
            ),
            default => sprintf(
                '%d-Bedroom %s in %s — %d TND/mo',
                $bedrooms, ucfirst($type),
                $city ?: 'Tunisia', $price
            ),
        };
    }

    private function typeAr(string $type): string
    {
        return match ($type) {
            'studio'    => 'استوديو',
            'room'      => 'غرفة',
            'villa'     => 'فيلا',
            default     => 'شقة',
        };
    }
}
