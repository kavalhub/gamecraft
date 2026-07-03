<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('game');
});

Route::get('/play', function () {
    return view('play');
});

Route::get('/zone-editor', function () {
    return view('zone-editor');
});

Route::get('/zone-editor/sprites', function () {
    return view('zone-editor-sprites');
});

Route::get('/zone-editor/{slug}', function (string $slug) {
    return view('zone-editor', ['slug' => $slug]);
});
