<?php

declare(strict_types=1);

namespace App\Domain\Registry;

use App\Domain\Enum\Category;

class CategoryFieldRegistry
{
    /**
     * @return array<int, array{key: string, label: string, type: string, required: bool, options?: array<string, string>}>
     */
    public static function getFields(Category $category): array
    {
        return match ($category) {
            Category::Car => [
                ['key' => 'brand', 'label' => 'Značka', 'type' => 'text', 'required' => true],
                ['key' => 'model', 'label' => 'Model', 'type' => 'text', 'required' => true],
                ['key' => 'year', 'label' => 'Rok výroby', 'type' => 'number', 'required' => true],
                ['key' => 'mileage_km', 'label' => 'Najeto (km)', 'type' => 'number', 'required' => false],
                ['key' => 'fuel_type', 'label' => 'Palivo', 'type' => 'select', 'required' => false, 'options' => ['petrol' => 'Benzín', 'diesel' => 'Nafta', 'lpg' => 'LPG', 'electric' => 'Elektro', 'hybrid' => 'Hybrid']],
                ['key' => 'color', 'label' => 'Barva', 'type' => 'text', 'required' => false],
                ['key' => 'displacement_ccm', 'label' => 'Objem (ccm)', 'type' => 'number', 'required' => false],
                ['key' => 'power_kw', 'label' => 'Výkon (kW)', 'type' => 'number', 'required' => false],
                ['key' => 'body_type', 'label' => 'Karoserie', 'type' => 'select', 'required' => false, 'options' => ['sedan' => 'Sedan', 'hatchback' => 'Hatchback', 'combi' => 'Combi', 'suv' => 'SUV', 'mpv' => 'MPV', 'cabrio' => 'Cabrio', 'pickup' => 'Pickup', 'van' => 'Van']],
                ['key' => 'vin', 'label' => 'VIN', 'type' => 'text', 'required' => false],
            ],
            Category::Truck => [
                ['key' => 'brand', 'label' => 'Značka', 'type' => 'text', 'required' => true],
                ['key' => 'model', 'label' => 'Model', 'type' => 'text', 'required' => true],
                ['key' => 'year', 'label' => 'Rok výroby', 'type' => 'number', 'required' => true],
                ['key' => 'mileage_km', 'label' => 'Najeto (km)', 'type' => 'number', 'required' => false],
                ['key' => 'fuel_type', 'label' => 'Palivo', 'type' => 'select', 'required' => false, 'options' => ['petrol' => 'Benzín', 'diesel' => 'Nafta', 'lpg' => 'LPG', 'electric' => 'Elektro', 'hybrid' => 'Hybrid']],
                ['key' => 'payload_kg', 'label' => 'Nosnost (kg)', 'type' => 'number', 'required' => false],
            ],
            Category::Motorcycle => [
                ['key' => 'brand', 'label' => 'Značka', 'type' => 'text', 'required' => true],
                ['key' => 'model', 'label' => 'Model', 'type' => 'text', 'required' => true],
                ['key' => 'year', 'label' => 'Rok výroby', 'type' => 'number', 'required' => true],
                ['key' => 'mileage_km', 'label' => 'Najeto (km)', 'type' => 'number', 'required' => false],
                ['key' => 'displacement_ccm', 'label' => 'Objem (ccm)', 'type' => 'number', 'required' => false],
                ['key' => 'power_kw', 'label' => 'Výkon (kW)', 'type' => 'number', 'required' => false],
                ['key' => 'moto_type', 'label' => 'Typ', 'type' => 'select', 'required' => false, 'options' => ['sport' => 'Sport', 'touring' => 'Touring', 'enduro' => 'Enduro', 'chopper' => 'Chopper', 'naked' => 'Naked', 'scooter' => 'Skútr', 'cross' => 'Cross', 'atv' => 'ATV', 'trial' => 'Trial', 'supermoto' => 'Supermoto']],
            ],
            Category::Electronics, Category::PhotoVideo, Category::Computer => [
                ['key' => 'brand', 'label' => 'Značka', 'type' => 'text', 'required' => true],
                ['key' => 'model', 'label' => 'Model', 'type' => 'text', 'required' => false],
                ['key' => 'color', 'label' => 'Barva', 'type' => 'text', 'required' => false],
            ],
            Category::MobilePhone => [
                ['key' => 'brand', 'label' => 'Značka', 'type' => 'text', 'required' => true],
                ['key' => 'model', 'label' => 'Model', 'type' => 'text', 'required' => true],
                ['key' => 'storage_gb', 'label' => 'Úložiště (GB)', 'type' => 'number', 'required' => false],
                ['key' => 'color', 'label' => 'Barva', 'type' => 'text', 'required' => false],
            ],
            Category::Clothing => [
                ['key' => 'size', 'label' => 'Velikost', 'type' => 'text', 'required' => true],
                ['key' => 'brand', 'label' => 'Značka', 'type' => 'text', 'required' => false],
                ['key' => 'color', 'label' => 'Barva', 'type' => 'text', 'required' => false],
                ['key' => 'material', 'label' => 'Materiál', 'type' => 'text', 'required' => false],
                ['key' => 'gender', 'label' => 'Pohlaví', 'type' => 'select', 'required' => false, 'options' => ['male' => 'Muži', 'female' => 'Ženy', 'unisex' => 'Unisex', 'boy' => 'Chlapci', 'girl' => 'Dívky']],
            ],
            Category::ChildrenGoods => [
                ['key' => 'item_type', 'label' => 'Typ', 'type' => 'text', 'required' => true],
                ['key' => 'brand', 'label' => 'Značka', 'type' => 'text', 'required' => false],
                ['key' => 'age_range', 'label' => 'Věk', 'type' => 'text', 'required' => false],
                ['key' => 'size', 'label' => 'Velikost', 'type' => 'text', 'required' => false],
            ],
            Category::Sport => [
                ['key' => 'item_type', 'label' => 'Typ', 'type' => 'text', 'required' => true],
                ['key' => 'brand', 'label' => 'Značka', 'type' => 'text', 'required' => false],
                ['key' => 'size', 'label' => 'Velikost', 'type' => 'text', 'required' => false],
            ],
            default => [
                ['key' => 'brand', 'label' => 'Značka', 'type' => 'text', 'required' => false],
                ['key' => 'color', 'label' => 'Barva', 'type' => 'text', 'required' => false],
            ],
        };
    }
}
