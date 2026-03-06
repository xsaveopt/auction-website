<?php

use App\Http\Controllers\SocialiteController;
use Illuminate\Support\Facades\Route;

Route::get('/auth/microsoft/redirect', [SocialiteController::class, 'redirect'])->name('auth.microsoft.redirect');
Route::get('/api/auth/microsoft/callback', [SocialiteController::class, 'callback'])->name('auth.microsoft.callback');

Route::get('/{any?}', fn () => view('app'))->where('any', '.*');
