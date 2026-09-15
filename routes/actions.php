<?php

use Illuminate\Support\Facades\Route;
use Redcodede\QrGen\Statamic\Http\Controllers\ImageController;

// Landet unter /!/qr-gen/image. Signiert, siehe Symbols::imageUrl().
//
// Der Name bekommt von Statamic noch `statamic.` vorangestellt; vollstaendig
// steht er in ImageController::ROUTE.
Route::get('image', [ImageController::class, 'show'])->name('qr-gen.image');
