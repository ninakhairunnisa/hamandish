<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// Serve the Vue single-page app for every non-API route so client-side
// routing (vue-router history mode) works on deep links / refreshes.
Route::get('/{any?}', fn () => view('app'))
    ->where('any', '^(?!api|up|storage|build).*$');
