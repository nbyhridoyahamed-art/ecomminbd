<?php

namespace Database\Seeders;

use App\Models\BlogPost;
use App\Models\HomepageBlock;
use App\Models\Store;
use App\Models\Testimonial;
use App\Support\HomepageBlockTypes;
use Illuminate\Database\Seeder;

/**
 * Seeds the default homepage exactly as it looked before Phase 13 (hero +
 * category grid + featured products, all live) so the storefront keeps
 * working once the hardcoded JSX in app/(storefront)/page.tsx is removed
 * — now as real, editable blocks instead. Also seeds a small demo library
 * of testimonials/blog posts so those block types have real content to
 * show the moment a staff member adds one, per spec section 138.
 */
class HomepageBlockSeeder extends Seeder
{
    public function run(): void
    {
        $store = Store::where('slug', 'nby-flagship-store')->first();

        if (! $store) {
            return;
        }

        $defaults = [
            [HomepageBlockTypes::HERO, ['heading' => 'NBY Flagship Store', 'subheading' => 'Quality products, cash on delivery, anywhere in Bangladesh.', 'image_url' => null, 'cta_label' => 'Shop All Products', 'cta_url' => '/products', 'secondary_cta_label' => null, 'secondary_cta_url' => null]],
            [HomepageBlockTypes::CATEGORY_GRID, ['heading' => 'Shop by Category', 'mode' => 'auto', 'limit' => 6, 'category_ids' => []]],
            [HomepageBlockTypes::FEATURED_PRODUCTS, ['heading' => 'Featured Products', 'mode' => 'auto', 'limit' => 5, 'product_ids' => []]],
        ];

        foreach ($defaults as $index => [$type, $settings]) {
            HomepageBlock::firstOrCreate(
                ['store_id' => $store->id, 'type' => $type],
                ['settings' => $settings, 'sort_order' => $index, 'is_active' => true],
            );
        }

        if (Testimonial::where('store_id', $store->id)->doesntExist()) {
            $testimonials = [
                ['name' => 'Farhana Akter', 'role' => 'Verified Customer', 'quote' => 'Ordered a phone case and it arrived in two days. Cash on delivery made it so easy to trust a new store.', 'rating' => 5],
                ['name' => 'Rakibul Hasan', 'role' => 'Verified Customer', 'quote' => 'Great quality panjabi for the price. Will definitely order again for Eid.', 'rating' => 5],
                ['name' => 'Nusrat Jahan', 'role' => 'Repeat Customer', 'quote' => 'Customer support helped me exchange a size within minutes. Very responsive team.', 'rating' => 4],
            ];
            foreach ($testimonials as $index => $data) {
                Testimonial::create([...$data, 'store_id' => $store->id, 'sort_order' => $index, 'is_active' => true]);
            }
        }

        if (BlogPost::where('store_id', $store->id)->doesntExist()) {
            $posts = [
                ['title' => 'How to Choose the Right Panjabi for Eid', 'slug' => 'choosing-the-right-panjabi-for-eid', 'excerpt' => 'A quick guide to fabric, fit, and color for this year\'s Eid collection.'],
                ['title' => '5 Tips for Faster Cash-on-Delivery Checkout', 'slug' => 'faster-cod-checkout-tips', 'excerpt' => 'Simple ways to make sure your COD order ships without delay.'],
                ['title' => 'Behind the Scenes: Our Dhaka Warehouse', 'slug' => 'behind-the-scenes-dhaka-warehouse', 'excerpt' => 'A look at how orders are picked, packed, and handed to courier partners.'],
            ];
            foreach ($posts as $data) {
                BlogPost::create([...$data, 'store_id' => $store->id, 'published_at' => now(), 'is_active' => true]);
            }
        }
    }
}
