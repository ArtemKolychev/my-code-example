<?php

declare(strict_types=1);

namespace App\Tests\Unit\Registry;

use App\Domain\Enum\Category;
use App\Domain\Registry\CategoryFieldRegistry;
use PHPUnit\Framework\TestCase;

class CategoryFieldRegistryTest extends TestCase
{
    public function testCarHasRequiredBrandModelYear(): void
    {
        $fields = CategoryFieldRegistry::getFields(Category::Car);
        $required = $this->requiredKeys($fields);

        $this->assertContains('brand', $required);
        $this->assertContains('model', $required);
        $this->assertContains('year', $required);
    }

    public function testCarHasOptionalFieldsWithOptions(): void
    {
        $fields = CategoryFieldRegistry::getFields(Category::Car);
        $byKey = array_column($fields, null, 'key');

        $this->assertArrayHasKey('fuel_type', $byKey);
        $this->assertSame('select', $byKey['fuel_type']['type']);
        $this->assertArrayHasKey('options', $byKey['fuel_type']);
        $this->assertArrayHasKey('petrol', $byKey['fuel_type']['options'] ?? []);

        $this->assertArrayHasKey('body_type', $byKey);
        $this->assertSame('select', $byKey['body_type']['type']);
        $this->assertArrayHasKey('suv', $byKey['body_type']['options'] ?? []);
    }

    public function testTruckHasRequiredBrandModelYear(): void
    {
        $fields = CategoryFieldRegistry::getFields(Category::Truck);
        $required = $this->requiredKeys($fields);

        $this->assertContains('brand', $required);
        $this->assertContains('model', $required);
        $this->assertContains('year', $required);
    }

    public function testMotorcycleHasMotoTypeSelect(): void
    {
        $fields = CategoryFieldRegistry::getFields(Category::Motorcycle);
        $byKey = array_column($fields, null, 'key');

        $this->assertArrayHasKey('moto_type', $byKey);
        $this->assertSame('select', $byKey['moto_type']['type']);
        $this->assertArrayHasKey('scooter', $byKey['moto_type']['options'] ?? []);
    }

    public function testClothingRequiresSizeNotBrand(): void
    {
        $fields = CategoryFieldRegistry::getFields(Category::Clothing);
        $required = $this->requiredKeys($fields);

        $this->assertContains('size', $required);
        $this->assertNotContains('brand', $required);
    }

    public function testClothingHasGenderSelectWithOptions(): void
    {
        $fields = CategoryFieldRegistry::getFields(Category::Clothing);
        $byKey = array_column($fields, null, 'key');

        $this->assertArrayHasKey('gender', $byKey);
        $this->assertSame('select', $byKey['gender']['type']);
        $this->assertArrayHasKey('female', $byKey['gender']['options'] ?? []);
    }

    public function testMobilePhoneRequiresBrandAndModel(): void
    {
        $fields = CategoryFieldRegistry::getFields(Category::MobilePhone);
        $required = $this->requiredKeys($fields);

        $this->assertContains('brand', $required);
        $this->assertContains('model', $required);
    }

    public function testElectronicsRequiresBrand(): void
    {
        $fields = CategoryFieldRegistry::getFields(Category::Electronics);
        $required = $this->requiredKeys($fields);

        $this->assertContains('brand', $required);
    }

    public function testElectronicsPhotoVideoComputerShareSameFields(): void
    {
        $electronics = $this->fieldKeys(CategoryFieldRegistry::getFields(Category::Electronics));
        $photo = $this->fieldKeys(CategoryFieldRegistry::getFields(Category::PhotoVideo));
        $computer = $this->fieldKeys(CategoryFieldRegistry::getFields(Category::Computer));

        $this->assertSame($electronics, $photo);
        $this->assertSame($electronics, $computer);
    }

    public function testChildrenGoodsRequiresItemType(): void
    {
        $fields = CategoryFieldRegistry::getFields(Category::ChildrenGoods);
        $required = $this->requiredKeys($fields);

        $this->assertContains('item_type', $required);
    }

    public function testSportRequiresItemType(): void
    {
        $fields = CategoryFieldRegistry::getFields(Category::Sport);
        $required = $this->requiredKeys($fields);

        $this->assertContains('item_type', $required);
    }

    public function testDefaultCategoriesReturnBrandAndColor(): void
    {
        foreach ([Category::HomeGarden, Category::BooksMedia, Category::MotoParts, Category::Tools, Category::Other] as $cat) {
            $keys = $this->fieldKeys(CategoryFieldRegistry::getFields($cat));
            $this->assertContains('brand', $keys, "Expected 'brand' for {$cat->value}");
            $this->assertContains('color', $keys, "Expected 'color' for {$cat->value}");
        }
    }

    public function testAllFieldsHaveRequiredKeyLabelType(): void
    {
        foreach (Category::cases() as $category) {
            $fields = CategoryFieldRegistry::getFields($category);
            foreach ($fields as $field) {
                $this->assertArrayHasKey('key', $field, "Missing 'key' in {$category->value}");
                $this->assertArrayHasKey('label', $field, "Missing 'label' in {$category->value}");
                $this->assertArrayHasKey('type', $field, "Missing 'type' in {$category->value}");
                $this->assertArrayHasKey('required', $field, "Missing 'required' in {$category->value}");
            }
        }
    }

    /**
     * @param array<int, array<string, mixed>> $fields
     *
     * @return list<string>
     */
    private function fieldKeys(array $fields): array
    {
        /** @var list<string> $keys */
        $keys = array_column($fields, 'key');

        return $keys;
    }

    /**
     * @param array<int, array<string, mixed>> $fields
     *
     * @return list<string>
     */
    private function requiredKeys(array $fields): array
    {
        /** @var list<string> $keys */
        $keys = array_column(
            array_filter($fields, static fn (array $f): bool => (bool) ($f['required'] ?? false)),
            'key'
        );

        return $keys;
    }
}
