<?php

namespace Database\Seeders;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\Coupon;
use App\Models\Page;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The storefront-facing content a real store launches with: blog
 * categories/tags/posts, the standard legal/info pages, and a handful of
 * coupons (including one disabled and one date-expired, so the Coupons
 * list isn't uniformly "active").
 */
class DemoContentSeeder extends Seeder
{
    public function run(): void
    {
        $store = Store::where('slug', 'eleventory-flagship-store')->firstOrFail();
        $admin = User::where('email', 'admin@eleventory.test')->firstOrFail();

        $this->seedBlog($store, $admin);
        $this->seedPages($store, $admin);
        $this->seedCoupons($store);
    }

    private function seedBlog(Store $store, User $admin): void
    {
        $categories = collect(['Shopping Tips', 'Product Guides', 'Fashion Trends', 'Company News'])
            ->mapWithKeys(fn (string $name) => [$name => BlogCategory::updateOrCreate(
                ['store_id' => $store->id, 'slug' => Str::slug($name)],
                ['name' => $name, 'description' => "Articles about {$name}."],
            )]);

        $tags = collect(['Eid', 'Discount', 'New Arrival', 'Guide', 'Electronics', 'Fashion', 'Ramadan', 'Tips'])
            ->mapWithKeys(fn (string $name) => [$name => BlogTag::updateOrCreate(
                ['store_id' => $store->id, 'slug' => Str::slug($name)],
                ['name' => $name],
            )]);

        $posts = [
            ['Top 10 Eid Fashion Trends This Year', 'Fashion Trends', ['Eid', 'Fashion', 'New Arrival']],
            ['How to Choose the Right Smartphone Under ৳20,000', 'Product Guides', ['Electronics', 'Guide']],
            ['Pohela Boishakh Shopping Guide 2026', 'Shopping Tips', ['Discount', 'Guide']],
            ['5 Tips for Faster Delivery During Ramadan Rush', 'Shopping Tips', ['Ramadan', 'Tips']],
            ['Monsoon Fashion: Staying Stylish in the Rain', 'Fashion Trends', ['Fashion', 'Tips']],
            ['Home Decor Ideas for Small Dhaka Apartments', 'Product Guides', ['Guide', 'Tips']],
            ["Understanding Cash on Delivery: A Buyer's Guide", 'Shopping Tips', ['Guide']],
            ['Winter Skincare Routine for Bangladeshi Weather', 'Product Guides', ['Tips', 'New Arrival']],
        ];

        foreach ($posts as $index => [$title, $categoryName, $tagNames]) {
            $publishedAt = now()->subDays(random_int(3, 180));

            $post = BlogPost::updateOrCreate(
                ['store_id' => $store->id, 'slug' => Str::slug($title)],
                [
                    'blog_category_id' => $categories[$categoryName]->id,
                    'created_by' => $admin->id,
                    'title' => $title,
                    'excerpt' => "A quick, practical read for shoppers in Bangladesh: {$title}.",
                    'body' => "<p>{$title}</p><p>".implode('</p><p>', [
                        'At Eleventory, we want every order to feel like the best decision you made this week.',
                        'Here is what our team recommends based on what customers across Dhaka, Chattogram, and Sylhet tell us.',
                        'As always, Cash on Delivery is available nationwide, and our support team is ready on live chat.',
                    ]).'</p>',
                    'status' => $index === 7 ? 'draft' : 'published',
                    'published_at' => $index === 7 ? null : $publishedAt,
                ],
            );

            $post->tags()->sync(collect($tagNames)->map(fn (string $name) => $tags[$name]->id));
        }
    }

    private function seedPages(Store $store, User $admin): void
    {
        $pages = [
            ['About Us', 'Eleventory is Bangladesh\'s trusted online store for electronics, fashion, home goods, and everyday essentials — delivering nationwide with reliable Cash on Delivery.'],
            ['Contact Us', 'Reach our support team at support@eleventory.test or call 09610-000000 (9am–9pm, 7 days a week).'],
            ['Privacy Policy', 'We collect only the information needed to process and deliver your order, and never sell your data to third parties.'],
            ['Terms & Conditions', 'By placing an order on Eleventory, you agree to our pricing, delivery, and return terms as described on this page.'],
            ['Shipping Policy', 'We deliver across all 8 divisions of Bangladesh. Inside Dhaka orders typically arrive within 1–2 days; outside Dhaka within 3–5 days.'],
            ['Return & Refund Policy', 'Most items can be returned within 7 days of delivery in original condition. Refunds are issued to the original payment method or as store credit.'],
            ['FAQ', 'Frequently asked questions about ordering, Cash on Delivery, delivery timelines, and returns.'],
        ];

        foreach ($pages as [$title, $content]) {
            Page::updateOrCreate(
                ['store_id' => $store->id, 'slug' => Str::slug($title)],
                [
                    'title' => $title,
                    'content' => "<h2>{$title}</h2><p>{$content}</p>",
                    'status' => 'published',
                    'created_by' => $admin->id,
                ],
            );
        }
    }

    private function seedCoupons(Store $store): void
    {
        $coupons = [
            ['EIDSALE10', 'percentage', 10, null, 1000, null, null, 'active', null, null],
            ['NEWUSER50', 'fixed', null, 5000, 500, null, 1, 'active', null, null],
            ['FREESHIP', 'fixed', null, 8000, 800, null, null, 'active', null, null],
            ['WINTER15', 'percentage', 15, null, 1500, null, null, 'active', null, null],
            ['BOISHAKH100', 'fixed', null, 10000, 2000, null, null, 'active', null, null],
            ['FLASH20', 'percentage', 20, null, 1000, 50, null, 'active', null, null],
            ['VIP500', 'fixed', null, 50000, 5000, null, 1, 'active', null, null],
            ['OFFSEASON10', 'percentage', 10, null, 500, null, null, 'active', null, now()->subDays(10)],
            ['OLDPROMO', 'percentage', 25, null, 1000, null, null, 'inactive', null, null],
        ];

        foreach ($coupons as [$code, $type, $pct, $fixed, $minOrder, $usageLimit, $perCustomer, $status, $startsAt, $expiresAt]) {
            Coupon::updateOrCreate(
                ['store_id' => $store->id, 'code' => $code],
                [
                    'discount_type' => $type,
                    'percentage_value' => $pct,
                    'fixed_discount_amount' => $fixed,
                    'currency_code' => 'BDT',
                    'minimum_order_amount' => $minOrder,
                    'usage_limit' => $usageLimit,
                    'per_customer_limit' => $perCustomer,
                    'starts_at' => $startsAt,
                    'expires_at' => $expiresAt,
                    'status' => $status,
                ],
            );
        }
    }
}
