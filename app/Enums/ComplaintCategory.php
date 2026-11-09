<?php

namespace App\Enums;

enum ComplaintCategory: string
{
    case Electricity = 'electricity';
    case Water = 'water';
    case RoomFacility = 'room_facility';
    case Cleaning = 'cleaning';
    case Security = 'security';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Electricity => 'Listrik',
            self::Water => 'Air',
            self::RoomFacility => 'Fasilitas kamar',
            self::Cleaning => 'Kebersihan',
            self::Security => 'Keamanan',
            self::Other => 'Lainnya',
        };
    }
}
