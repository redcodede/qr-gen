<?php

use Illuminate\Support\Facades\Route;
use Redcodede\QrGen\Statamic\Http\Controllers\CP\SettingsController;

// Landet unter dem CP-Prefix der Seite, also normalerweise /cp/qr-gen.
// Die Namen bekommen von Statamic `statamic.cp.` vorangestellt, `cp_route()`
// setzt das selbst davor.
Route::get('qr-gen', [SettingsController::class, 'edit'])->name('qr-gen.settings');
Route::patch('qr-gen', [SettingsController::class, 'update'])->name('qr-gen.settings.update');
