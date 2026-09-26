<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->away(
    rtrim(config('app.frontend_url'), '/') . '/login'
));
