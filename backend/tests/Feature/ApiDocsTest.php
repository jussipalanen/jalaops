<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiDocsTest extends TestCase
{
    public function test_docs_page_is_available(): void
    {
        $this->get('/docs')
            ->assertOk()
            ->assertSee('JalaOps API');
    }

    public function test_openapi_specification_lists_the_request_endpoints(): void
    {
        $this->getJson('/docs/api.json')
            ->assertOk()
            ->assertJsonPath('info.title', 'JalaOps API')
            ->assertJsonStructure(['paths' => ['/requests', '/requests/{serviceRequest}', '/health']]);
    }
}
