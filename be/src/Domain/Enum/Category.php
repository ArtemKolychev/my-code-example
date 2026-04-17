<?php

declare(strict_types=1);

namespace App\Domain\Enum;

enum Category: string
{
    case Car = 'car';
    case Truck = 'truck';
    case Motorcycle = 'motorcycle';
    case Electronics = 'electronics';
    case MobilePhone = 'mobile_phone';
    case Clothing = 'clothing';
    case HomeGarden = 'home_garden';
    case ChildrenGoods = 'children_goods';
    case Sport = 'sport';
    case BooksMedia = 'books_media';
    case PhotoVideo = 'photo_video';
    case Computer = 'computer';
    case MotoParts = 'moto_parts';
    case Tools = 'tools';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Car => 'Osobní automobily',
            self::Truck => 'Nákladní vozidla',
            self::Motorcycle => 'Motocykly a skútry',
            self::Electronics => 'Elektronika',
            self::MobilePhone => 'Mobilní telefony',
            self::Clothing => 'Oblečení a obuv',
            self::HomeGarden => 'Dům a zahrada',
            self::ChildrenGoods => 'Dětské zboží',
            self::Sport => 'Sport a outdoor',
            self::BooksMedia => 'Knihy a média',
            self::PhotoVideo => 'Foto a video',
            self::Computer => 'Počítače',
            self::MotoParts => 'Moto díly',
            self::Tools => 'Nářadí a stroje',
            self::Other => 'Ostatní',
        };
    }

    public function isVehicle(): bool
    {
        return in_array($this, [self::Car, self::Truck, self::Motorcycle], true);
    }
}
