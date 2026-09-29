<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * 10 categories, 5 brands, 50 products — all Bangladesh-market-flavored
 * (product names, BDT pricing). Every product is `type => simple`:
 * variants/bundles are separate opt-in features nobody asked this demo
 * catalog to exercise. Opening stock is seeded by DemoPurchasingSeeder
 * (via real purchase-order receipts), not here.
 */
class DemoCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $store = Store::where('slug', 'eleventory-flagship-store')->firstOrFail();

        $brands = collect([
            'Doyel Electronics',
            'Padma Lifestyle',
            'Anondo Fashion House',
            'Shapla Home & Living',
            'Nokshi Beauty Co.',
        ])->map(fn (string $name) => Brand::updateOrCreate(
            ['store_id' => $store->id, 'slug' => Str::slug($name)],
            ['name' => $name, 'status' => 'active'],
        ));

        // name => [products as [name, price_bdt]]
        $catalog = [
            'Mobile & Accessories' => [
                ['Smartphone X12 128GB', 18500],
                ['Smartphone Neo 5G', 24990],
                ['Wireless Earbuds Pro', 2450],
                ['Fast Charger 33W', 950],
                ['Power Bank 20000mAh', 1850],
            ],
            'Electronics & Appliances' => [
                ['LED Television 43-inch', 27500],
                ['Smart Watch Fit', 3200],
                ['Rice Cooker 1.8L', 2650],
                ['Electric Kettle 1.5L', 1450],
                ['Ceiling Fan 56-inch', 3450],
            ],
            "Men's Fashion" => [
                ["Men's Panjabi (Cotton)", 1450],
                ["Men's Casual Shirt", 950],
                ["Men's Formal Trousers", 1250],
                ["Men's Polo T-Shirt", 650],
                ["Men's Denim Jeans", 1650],
            ],
            "Women's Fashion" => [
                ["Women's Three-Piece (Cotton)", 1850],
                ["Women's Saree (Jamdani Print)", 3450],
                ["Women's Kurti", 1150],
                ["Women's Hijab (Chiffon)", 450],
                ["Women's Handbag", 1650],
            ],
            'Home & Kitchen' => [
                ['Non-Stick Frying Pan', 850],
                ['Steel Dinner Set (24-Pcs)', 2450],
                ['Cotton Bedsheet Set (King)', 1650],
                ['Curtain Set (2-Piece)', 1250],
                ['Plastic Storage Container Set', 650],
            ],
            'Beauty & Personal Care' => [
                ['Herbal Face Wash', 350],
                ['Aloe Vera Gel', 280],
                ['Hair Oil (Coconut)', 220],
                ['Sunscreen Lotion SPF50', 650],
                ['Lip Balm Set', 380],
            ],
            'Groceries & Essentials' => [
                ['Miniket Rice 5kg', 420],
                ['Soybean Oil 5L', 890],
                ['Mustard Oil (Sarisha) 1L', 320],
                ['Sugar 1kg Pack', 130],
                ['Red Lentil (Masoor Dal) 1kg', 145],
            ],
            'Books & Stationery' => [
                ['Bangla Notebook (200 Pages)', 60],
                ['Ball Point Pen (Pack of 10)', 120],
                ['Story Book for Kids', 220],
                ['Geometry Box Set', 180],
                ['Crayon Set 24 Colors', 250],
            ],
            'Sports & Outdoor' => [
                ['Football (Size 5)', 850],
                ['Cricket Bat (Kashmir Willow)', 1450],
                ['Badminton Racket Set', 950],
                ['Yoga Mat', 650],
                ['Skipping Rope', 220],
            ],
            'Baby & Toys' => [
                ['Baby Diaper Pack (M)', 850],
                ['Feeding Bottle 250ml', 320],
                ['Remote Control Car Toy', 950],
                ['Building Blocks Set', 750],
                ['Soft Plush Teddy Bear', 550],
            ],
        ];

        $sortOrder = 0;
        $skuCounter = 1;
        $productIndex = 0;
        $featuredEvery = 6; // ~8 of 50 products end up featured

        foreach ($catalog as $categoryName => $products) {
            $category = Category::updateOrCreate(
                ['store_id' => $store->id, 'slug' => Str::slug($categoryName)],
                ['name' => $categoryName, 'sort_order' => $sortOrder++, 'status' => 'active'],
            );

            foreach ($products as [$name, $priceBdt]) {
                $brand = $brands[$productIndex % $brands->count()];
                $priceMinor = $priceBdt * 100;

                Product::updateOrCreate(
                    ['store_id' => $store->id, 'slug' => Str::slug($name)],
                    [
                        'category_id' => $category->id,
                        'brand_id' => $brand->id,
                        'name' => $name,
                        'sku' => sprintf('ELV-%04d', $skuCounter++),
                        'type' => 'simple',
                        'description' => "{$name} — a popular pick in our {$categoryName} range, available with nationwide delivery across Bangladesh.",
                        'short_description' => $name,
                        'currency_code' => 'BDT',
                        'price_amount' => $priceMinor,
                        'cost_price_amount' => (int) round($priceMinor * 0.6),
                        'track_stock' => true,
                        'low_stock_threshold' => 10,
                        'status' => 'active',
                        'featured' => $productIndex % $featuredEvery === 0,
                        'published_at' => now()->subDays(random_int(5, 200)),
                    ],
                );

                $productIndex++;
            }
        }
    }
}
