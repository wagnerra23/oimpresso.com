<?php

namespace Tests\Unit;

use App\Http\Middleware\Timezone;
use Illuminate\Http\Request;
use Tests\TestCase;

class TimezoneMiddlewareTest extends TestCase
{
    public function test_prefers_business_timezone_session_key_over_everything(): void
    {
        $request = Request::create('/');
        $request->setLaravelSession($this->app['session']->driver());
        $request->session()->put('business_timezone', 'America/Sao_Paulo');
        $request->session()->put('business', ['time_zone' => 'UTC']);

        (new Timezone)->handle($request, fn ($r) => $r);

        $this->assertSame('America/Sao_Paulo', config('app.timezone'));
        $this->assertSame('America/Sao_Paulo', date_default_timezone_get());
    }

    public function test_falls_back_to_business_object_time_zone_when_dedicated_key_missing(): void
    {
        $request = Request::create('/');
        $request->setLaravelSession($this->app['session']->driver());
        $business = new \stdClass;
        $business->time_zone = 'Europe/Lisbon';
        $request->session()->put('business', $business);

        (new Timezone)->handle($request, fn ($r) => $r);

        $this->assertSame('Europe/Lisbon', config('app.timezone'));
    }

    public function test_falls_back_to_business_array_time_zone(): void
    {
        $request = Request::create('/');
        $request->setLaravelSession($this->app['session']->driver());
        $request->session()->put('business', ['time_zone' => 'America/Manaus']);

        (new Timezone)->handle($request, fn ($r) => $r);

        $this->assertSame('America/Manaus', config('app.timezone'));
    }

    public function test_keeps_config_default_when_nothing_is_set_and_user_is_guest(): void
    {
        config(['app.timezone' => 'UTC']);
        $request = Request::create('/');
        $request->setLaravelSession($this->app['session']->driver());

        (new Timezone)->handle($request, fn ($r) => $r);

        $this->assertSame('UTC', config('app.timezone'));
    }
}
