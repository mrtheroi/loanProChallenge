<?php

declare(strict_types=1);

it('issues the XSRF-TOKEN cookie for the SPA', function (): void {
    $this->withHeader('Referer', 'http://localhost:8000')
        ->get('/sanctum/csrf-cookie')
        ->assertNoContent()
        ->assertCookie('XSRF-TOKEN');
});
