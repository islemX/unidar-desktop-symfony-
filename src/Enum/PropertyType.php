<?php

namespace App\Enum;

enum PropertyType: string
{
    case Apartment = 'apartment';
    case House = 'house';
    case Studio = 'studio';
    case SharedRoom = 'shared_room';

    public function getLabel(): string
    {
        return match ($this) {
            self::Apartment => 'Apartment',
            self::House => 'House',
            self::Studio => 'Studio',
            self::SharedRoom => 'Shared Room',
        };
    }
}
