<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// Let Expo Router handle browser navigation, including direct links and refreshes.
Route::get('/{path?}', function () {
    $index = public_path('app.html');
    abort_unless(is_file($index), 503, 'La interfaz web todavía no está compilada.');

    return response()->file($index, [
        'Content-Type' => 'text/html; charset=UTF-8',
        'Cache-Control' => 'no-store',
    ]);
})->where('path', '(?!(?:api|docs|up|_expo|assets|storage)(?:/|$)|.*\.[^/]+$).*');
