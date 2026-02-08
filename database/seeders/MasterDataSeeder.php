<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Attribute;
use App\Models\AttributeItem;
use App\Models\Category;
use App\Models\SubCategory;
use App\Models\Brand;
use App\Models\Gender;
use App\Models\ProductModel;
use Illuminate\Support\Str;

class MasterDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing data
        $this->clearData();

        // Seed Genders
        $this->seedGenders();

        // Seed Categories and SubCategories
        $this->seedCategoriesAndSubCategories();

        // Seed Brands
        $this->seedBrands();

        // Seed Product Models
        $this->seedProductModels();

        // Seed Attributes and Attribute Items
        $this->seedAttributesAndItems();
    }

    /**
     * Clear existing data
     */
    private function clearData(): void
    {
        Gender::query()->delete();
        Category::query()->delete();
        SubCategory::query()->delete();
        Brand::query()->delete();
        ProductModel::query()->delete();
        Attribute::query()->delete();
        AttributeItem::query()->delete();
    }

    /**
     * Seed Gender data
     */
    private function seedGenders(): void
    {
        $genders = [
            ['name' => 'Male', 'slug' => 'male'],
            ['name' => 'Female', 'slug' => 'female']
        ];

        foreach ($genders as $gender) {
            Gender::create($gender);
        }

        $this->command->info('Genders seeded successfully!');
    }

    /**
     * Seed Categories and SubCategories
     */
    private function seedCategoriesAndSubCategories(): void
    {
        $categories = [
            [
                'name' => 'Clothing',
                'slug' => 'clothing',
                'description' => 'All types of clothing items',
                'image_url' => null,
                'image_cloudinary_id' => null,
                'sort_order' => 1,
                'is_active' => true,
                'sub_categories' => [
                    ['name' => 'T-Shirts', 'slug' => 't-shirts'],
                    ['name' => 'Jeans', 'slug' => 'jeans'],
                    ['name' => 'Shirts', 'slug' => 'shirts'],
                ]
            ],
            [
                'name' => 'Footwear',
                'slug' => 'footwear',
                'description' => 'All types of footwear',
                'image_url' => null,
                'image_cloudinary_id' => null,
                'sort_order' => 2,
                'is_active' => true,
                'sub_categories' => [
                    ['name' => 'Sneakers', 'slug' => 'sneakers'],
                    ['name' => 'Sandals', 'slug' => 'sandals'],
                    ['name' => 'Formal Shoes', 'slug' => 'formal-shoes'],
                ]
            ],
            [
                'name' => 'Electronics',
                'slug' => 'electronics',
                'description' => 'Electronic devices and accessories',
                'image_url' => null,
                'image_cloudinary_id' => null,
                'sort_order' => 3,
                'is_active' => true,
                'sub_categories' => [
                    ['name' => 'Smartphones', 'slug' => 'smartphones'],
                    ['name' => 'Laptops', 'slug' => 'laptops'],
                    ['name' => 'Headphones', 'slug' => 'headphones'],
                ]
            ],
        ];

        foreach ($categories as $categoryData) {
            $subCategories = $categoryData['sub_categories'];
            unset($categoryData['sub_categories']);

            $category = Category::create($categoryData);

            foreach ($subCategories as $subCat) {
                SubCategory::create([
                    'category_id' => $category->id,
                    'name' => $subCat['name'],
                    'slug' => $subCat['slug'],
                    'description' => $subCat['name'] . ' under ' . $category->name,
                    'image_url' => null,
                    'image_cloudinary_id' => null,
                    'sort_order' => 1,
                    'is_active' => true,
                ]);
            }
        }

        $this->command->info('Categories and SubCategories seeded successfully!');
    }

    /**
     * Seed Brands
     */
    private function seedBrands(): void
    {
        $brands = [
            [
                'name' => 'Nike',
                'slug' => 'nike',
                'description' => 'Just Do It',
                'image_url' => null,
                'image_cloudinary_id' => null,
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 1,
            ],
            [
                'name' => 'Adidas',
                'slug' => 'adidas',
                'description' => 'Impossible is Nothing',
                'image_url' => null,
                'image_cloudinary_id' => null,
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 2,
            ]
        ];

        foreach ($brands as $brand) {
            Brand::create($brand);
        }

        $this->command->info('Brands seeded successfully!');
    }

    /**
     * Seed Product Models
     */
    private function seedProductModels(): void
    {
        $models = [
            ['name' => 'iPhone 15', 'slug' => 'iphone-15'],
            ['name' => 'Air Max', 'slug' => 'air-max']
        ];

        foreach ($models as $model) {
            ProductModel::create($model);
        }

        $this->command->info('Product Models seeded successfully!');
    }

    /**
     * Seed Attributes and Attribute Items
     */
    private function seedAttributesAndItems(): void
    {
        $attributes = [
            [
                'name' => 'Color',
                'slug' => 'color',
                'items' => [
                    ['name' => 'Red', 'slug' => 'red'],
                    ['name' => 'Blue', 'slug' => 'blue'],
                    ['name' => 'Green', 'slug' => 'green'],
                    ['name' => 'Black', 'slug' => 'black'],
                    ['name' => 'White', 'slug' => 'white'],
                ]
            ],
            [
                'name' => 'Size',
                'slug' => 'size',
                'items' => [
                    ['name' => 'S', 'slug' => 's'],
                    ['name' => 'M', 'slug' => 'm'],
                    ['name' => 'L', 'slug' => 'l'],
                    ['name' => 'XL', 'slug' => 'xl'],
                    ['name' => 'XXL', 'slug' => 'xxl'],
                ]
            ]
        ];

        foreach ($attributes as $attributeData) {
            $items = $attributeData['items'];
            unset($attributeData['items']);

            $attribute = Attribute::create($attributeData);

            foreach ($items as $item) {
                AttributeItem::create([
                    'attribute_id' => $attribute->id,
                    'name' => $item['name'],
                    'slug' => $item['slug'],
                    'is_active' => true,
                ]);
            }
        }

        $this->command->info('Attributes and Attribute Items seeded successfully!');
    }
}