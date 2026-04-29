<?php

namespace App\Ml\Preprocessor;

use App\Entity\Listing;
use App\Enum\PropertyType;

class ListingFeatureExtractor
{
    private const PROPERTY_TYPE_MAP = [
        'apartment' => 1,
        'house' => 2,
        'studio' => 3,
        'shared' => 4,
        'villa' => 5,
    ];

    /**
     * Returns a flat feature vector for a Listing, suitable for Rubix ML.
     * Order must remain stable across training and inference.
     *
     * [photo_count, description_word_count, price, bedrooms, bathrooms,
     *  capacity, property_type_enc, has_gender_pref, has_dates, title_length]
     */
    public function extract(Listing $listing): array
    {
        $photoCount = $listing->getImages()->count();
        $descWordCount = $listing->getDescription()
            ? str_word_count(strip_tags($listing->getDescription()))
            : 0;
        $price = (float) ($listing->getPrice() ?? 0);
        $bedrooms = $listing->getBedrooms() ?? 0;
        $bathrooms = $listing->getBathrooms() ?? 0;
        $capacity = $listing->getCapacity() ?? 1;
        $propertyTypeEnc = $this->encodePropertyType($listing->getPropertyType());
        $hasGenderPref = $listing->getGenderPreference() !== null ? 1 : 0;
        $hasDates = ($listing->getAvailableFrom() !== null || $listing->getAvailableUntil() !== null) ? 1 : 0;
        $titleLength = strlen($listing->getTitle() ?? '');

        return [
            (float) $photoCount,
            (float) $descWordCount,
            $price,
            (float) $bedrooms,
            (float) $bathrooms,
            (float) $capacity,
            (float) $propertyTypeEnc,
            (float) $hasGenderPref,
            (float) $hasDates,
            (float) $titleLength,
        ];
    }

    public function featureNames(): array
    {
        return [
            'photo_count', 'description_word_count', 'price',
            'bedrooms', 'bathrooms', 'capacity',
            'property_type_enc', 'has_gender_pref', 'has_dates', 'title_length',
        ];
    }

    private function encodePropertyType(?PropertyType $type): int
    {
        if ($type === null) {
            return 0;
        }
        return self::PROPERTY_TYPE_MAP[strtolower($type->value)] ?? 0;
    }
}
