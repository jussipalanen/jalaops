<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_response_asks_search_engines_not_to_index(): void
    {
        foreach (['/', '/api/health', '/api/requests', '/docs', '/api/requests/999', '/api/unknown'] as $url) {
            $this->get($url)->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        }
    }

    public function test_home_page_has_a_noindex_meta_tag(): void
    {
        $this->get('/', ['Accept' => 'text/html'])
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow">', escape: false)
            ->assertSee('<meta name="description"', escape: false);
    }
}
