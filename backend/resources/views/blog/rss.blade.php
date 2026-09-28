<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/">
<channel>
    <title>{{ $store->name }} Blog</title>
    <link>{{ rtrim(config('frontend.url'), '/') }}/blog</link>
    <description>Latest posts from {{ $store->name }}</description>
    <language>en</language>
    <lastBuildDate>{{ now()->toRssString() }}</lastBuildDate>
@foreach ($posts as $post)
    <item>
        <title>{{ $post->title }}</title>
        <link>{{ rtrim(config('frontend.url'), '/') }}/blog/{{ $post->slug }}</link>
        <guid isPermaLink="true">{{ rtrim(config('frontend.url'), '/') }}/blog/{{ $post->slug }}</guid>
        <pubDate>{{ $post->published_at->toRssString() }}</pubDate>
        <description>{{ $post->displayExcerpt() }}</description>
        <content:encoded><![CDATA[{!! $post->body !!}]]></content:encoded>
    </item>
@endforeach
</channel>
</rss>
