<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class BrowserSession
{
    public static function persistSessionsInDatabase(): void
    {
        config(['session.driver' => 'database']);
    }

    public static function carryCookie(TestCase $test, TestResponse $response): void
    {
        $name = config('session.cookie');

        app('auth')->forgetGuards();
        app('session')->forgetDrivers();
        app()->forgetInstance('session.store');

        $test->withCredentials()->withCookie($name, $response->getCookie($name)->getValue());
    }
}
