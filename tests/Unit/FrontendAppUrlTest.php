<?php

namespace Tests\Unit;

use App\Support\FrontendAppUrl;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class FrontendAppUrlTest extends TestCase
{
    public function test_spa_builds_url_with_query(): void
    {
        Config::set('app.frontend_url', 'http://localhost:3000');

        $this->assertSame(
            'http://localhost:3000/customers?sort=name',
            FrontendAppUrl::spa('/customers', ['sort' => 'name'])
        );
    }

    public function test_to_spa_or_route_falls_back_to_named_route(): void
    {
        Config::set('app.frontend_url', null);

        $url = FrontendAppUrl::toSpaOrRoute('/tasks', 'tasks.index');

        $this->assertStringContainsString('/tasks', $url);
        $this->assertStringStartsWith('http', $url);
    }

    public function test_to_spa_or_route_uses_spa_when_configured(): void
    {
        Config::set('app.frontend_url', 'http://spa.example');

        $this->assertSame(
            'http://spa.example/quotes',
            FrontendAppUrl::toSpaOrRoute('/quotes', 'quotes.index')
        );
    }
}
