<?php

namespace Tests\Feature\Storefront;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontBlogTest extends TestCase
{
    use RefreshDatabase;

    private function activeStore(): Store
    {
        return Store::factory()->create(['status' => 'active']);
    }

    public function test_index_only_lists_published_and_due_posts(): void
    {
        $store = $this->activeStore();
        BlogPost::factory()->for($store)->create(['status' => 'published', 'published_at' => now()->subDay()]);
        BlogPost::factory()->for($store)->create(['status' => 'draft', 'published_at' => null]);
        BlogPost::factory()->for($store)->create(['status' => 'published', 'published_at' => now()->addWeek()]);

        $this->getJson('/api/v1/storefront/blog')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_show_returns_the_post_and_related_posts_from_the_same_category(): void
    {
        $store = $this->activeStore();
        $category = BlogCategory::factory()->for($store)->create();
        BlogPost::factory()->for($store)->create([
            'slug' => 'main-post',
            'blog_category_id' => $category->id,
            'body' => str_repeat('word ', 400),
        ]);
        $related = BlogPost::factory()->for($store)->create(['blog_category_id' => $category->id]);
        BlogPost::factory()->for($store)->create(); // no category — not related

        $response = $this->getJson('/api/v1/storefront/blog/main-post')->assertOk();

        $response->assertJsonPath('data.post.slug', 'main-post');
        $response->assertJsonPath('data.post.reading_time_minutes', 2);
        $response->assertJsonCount(1, 'data.related_posts');
        $response->assertJsonPath('data.related_posts.0.id', $related->id);
    }

    public function test_a_draft_post_404s_on_the_public_endpoint(): void
    {
        $store = $this->activeStore();
        BlogPost::factory()->for($store)->create(['slug' => 'coming-soon', 'status' => 'draft']);

        $this->getJson('/api/v1/storefront/blog/coming-soon')->assertNotFound();
    }

    public function test_excerpt_falls_back_to_a_truncated_body_when_not_set(): void
    {
        $store = $this->activeStore();
        BlogPost::factory()->for($store)->create([
            'slug' => 'no-excerpt',
            'excerpt' => null,
            'body' => '<p>'.str_repeat('word ', 60).'</p>',
        ]);

        $response = $this->getJson('/api/v1/storefront/blog/no-excerpt')->assertOk();

        $excerpt = $response->json('data.post.excerpt');
        $this->assertNotNull($excerpt);
        $this->assertLessThan(300, strlen($excerpt));
        $this->assertStringNotContainsString('<p>', $excerpt);
    }

    public function test_category_archive_lists_only_that_categorys_published_posts(): void
    {
        $store = $this->activeStore();
        $category = BlogCategory::factory()->for($store)->create(['slug' => 'news']);
        BlogPost::factory()->for($store)->create(['blog_category_id' => $category->id]);
        BlogPost::factory()->for($store)->create(); // no category

        $response = $this->getJson('/api/v1/storefront/blog/category/news')->assertOk();

        $response->assertJsonPath('data.category.slug', 'news');
        $response->assertJsonCount(1, 'data.posts');
    }

    public function test_tag_archive_lists_only_posts_with_that_tag(): void
    {
        $store = $this->activeStore();
        $tag = BlogTag::factory()->for($store)->create(['slug' => 'eid']);
        $tagged = BlogPost::factory()->for($store)->create();
        $tagged->tags()->attach($tag);
        BlogPost::factory()->for($store)->create(); // untagged

        $response = $this->getJson('/api/v1/storefront/blog/tag/eid')->assertOk();

        $response->assertJsonPath('data.tag.slug', 'eid');
        $response->assertJsonCount(1, 'data.posts');
    }

    public function test_rss_feed_returns_valid_xml_of_published_posts_only(): void
    {
        $store = $this->activeStore();
        BlogPost::factory()->for($store)->create(['title' => 'Feed Me', 'status' => 'published', 'published_at' => now()->subHour()]);
        BlogPost::factory()->for($store)->create(['status' => 'draft']);

        $response = $this->get('/api/v1/storefront/blog/rss');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8');

        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml, 'RSS feed is not well-formed XML');
        $this->assertCount(1, $xml->channel->item);
        $this->assertSame('Feed Me', (string) $xml->channel->item[0]->title);
    }
}
