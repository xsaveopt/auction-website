<?php

use App\Support\BiddingSchedule;
use App\Http\Controllers\AuctionController;
use App\Http\Controllers\AuctionImageController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BidController;
use App\Http\Controllers\SocialiteController;
use App\Http\Controllers\StatsController;
use Illuminate\Support\Facades\Route;

// Auth (public even when SSO is enabled)
Route::get('/auth/sso/enabled', [SocialiteController::class, 'enabled']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth');
Route::get('/user', [AuthController::class, 'user']);

// All routes below require authentication when SSO is enabled
Route::middleware('sso')->group(function () {
    // Stats
    Route::get('/stats', [StatsController::class, 'index']);

    // Bidding schedule
    Route::get('/schedule', fn () => response()->json(['schedule' => BiddingSchedule::toArray()]));

    // Auctions
    Route::get('/auctions', [AuctionController::class, 'index']);
    Route::get('/auctions/ended', [AuctionController::class, 'ended'])->middleware(['auth', 'admin']);
    Route::get('/auctions/{auction}', [AuctionController::class, 'show']);
    Route::post('/auctions', [AuctionController::class, 'store'])->middleware(['auth', 'admin']);
    Route::put('/auctions/{auction}', [AuctionController::class, 'update'])->middleware(['auth', 'admin']);
    Route::delete('/auctions/{auction}', [AuctionController::class, 'destroy'])->middleware(['auth', 'admin']);

    // Auction images
    Route::get('/images/{image}', [AuctionImageController::class, 'show']);
    Route::post('/auctions/{auction}/images', [AuctionImageController::class, 'store'])->middleware(['auth', 'admin']);
    Route::delete('/images/{image}', [AuctionImageController::class, 'destroy'])->middleware(['auth', 'admin']);

    // Bids
    Route::post('/auctions/{auction}/bids', [BidController::class, 'store'])->middleware('auth');
    Route::delete('/auctions/{auction}/bids', [BidController::class, 'destroy'])->middleware('auth');
});
