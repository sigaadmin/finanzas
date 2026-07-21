<?php

use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;

test('redirects a request with an expired session to its previous page', function () {
    Route::post('/test-session-expired', function (): void {
        throw new TokenMismatchException('CSRF token mismatch.');
    });

    $this->withHeader('referer', url('/'))
        ->post('/test-session-expired')
        ->assertRedirect(url('/'));
});
