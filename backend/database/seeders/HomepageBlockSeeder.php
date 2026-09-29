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
        $store = Store::where('slug', 'eleventory-flagship-store')->first();

        if (! $store) {
            return;
        }

        $defaults = [
            [HomepageBlockTypes::HERO, ['heading' => 'Eleventory Flagship Store', 'subheading' => 'Quality products, cash on delivery, anywhere in Bangladesh.', 'image_url' => null, 'cta_label' => 'Shop All Products', 'cta_url' => '/products', 'secondary_cta_label' => null, 'secondary_cta_url' => null]],
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
                [
                    'title' => 'How to Choose the Right Panjabi for Eid',
                    'slug' => 'choosing-the-right-panjabi-for-eid',
                    'excerpt' => 'A quick guide to fabric, fit, and color for this year\'s Eid collection.',
                    'body' => '<p>Eid shopping season is here, and picking the right panjabi comes down to three things: fabric, fit, and color.</p>'
                        .'<p><strong>Fabric</strong> — Cotton and cotton-blends breathe best for Bangladesh\'s weather, while silk and linen blends suit evening programs. Check the product page\'s material line before you order.</p>'
                        .'<p><strong>Fit</strong> — Our size guide on every product page maps chest and length measurements to S–XXL. When in doubt, size up; a panjabi that\'s slightly loose is easier to wear all day than one that\'s tight.</p>'
                        .'<p><strong>Color</strong> — Pastels and whites are classic for Eid morning prayers, while deeper tones suit evening family gatherings. Either way, cash on delivery means you can order a couple of options and only pay for what you keep.</p>',
                ],
                [
                    'title' => '5 Tips for Faster Cash-on-Delivery Checkout',
                    'slug' => 'faster-cod-checkout-tips',
                    'excerpt' => 'Simple ways to make sure your COD order ships without delay.',
                    'body' => '<p>Cash on delivery is the easiest way to shop with us, and a few small habits make it even smoother:</p>'
                        .'<ol>'
                        .'<li>Double-check your phone number — our courier partner calls before every delivery attempt.</li>'
                        .'<li>Keep your address specific: house/road/area, not just the neighborhood name.</li>'
                        .'<li>Answer unknown numbers around your expected delivery window.</li>'
                        .'<li>Have the exact cash ready — couriers don\'t always carry change.</li>'
                        .'<li>Track your order status from your account so you know exactly when it\'s out for delivery.</li>'
                        .'</ol>'
                        .'<p>Following these keeps your order moving instead of sitting in a failed-delivery queue.</p>',
                ],
                [
                    'title' => 'Behind the Scenes: Our Dhaka Warehouse',
                    'slug' => 'behind-the-scenes-dhaka-warehouse',
                    'excerpt' => 'A look at how orders are picked, packed, and handed to courier partners.',
                    'body' => '<p>Every order placed on our storefront routes through a single warehouse in Dhaka before it ever reaches a courier.</p>'
                        .'<p>Once an order is confirmed, our team picks each item, does a quality check, and packs it the same day for orders placed before our afternoon cutoff. Packed orders are batched by courier zone and handed off to our delivery partners each evening.</p>'
                        .'<p>That same-day packing is what makes our delivery estimates reliable — most Dhaka orders arrive within 1–2 days, and outside-Dhaka orders within 3–5 days.</p>',
                ],
            ];
            foreach ($posts as $data) {
                BlogPost::create([...$data, 'store_id' => $store->id, 'published_at' => now(), 'status' => 'published']);
            }
        }
    }
}
