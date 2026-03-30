<?php

use App\Support\BiddingSchedule;
use App\Http\Controllers\AdminAuditLogController;
use App\Http\Controllers\AdminAuctionController;
use App\Http\Controllers\AdminBidController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AuctionController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\AuctionImageController;
use App\Http\Controllers\AuctionQuestionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BidController;
use App\Http\Controllers\LeftoverPriceOfferController;
use App\Http\Controllers\LeftoverPurchaseController;
use App\Http\Controllers\PresenceController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\QuotePdfController;
use App\Http\Controllers\SocialiteController;
use Illuminate\Support\Facades\Route;

// Auth (public even when SSO is enabled)
Route::get('/auth/sso/enabled', [SocialiteController::class, 'enabled']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth');
Route::get('/user', [AuthController::class, 'user']);

// All routes below require authentication when SSO is enabled
Route::middleware('sso')->group(function () {
    Route::post('/presence/heartbeat', [PresenceController::class, 'heartbeat']);
    Route::get('/push/config', [PushSubscriptionController::class, 'config'])->middleware('auth');
    Route::put('/push/subscription', [PushSubscriptionController::class, 'store'])->middleware('auth');
    Route::delete('/push/subscription', [PushSubscriptionController::class, 'destroy'])->middleware('auth');

    // Bidding schedule
    Route::get('/schedule', fn () => response()->json(['schedule' => BiddingSchedule::toArray()]));

    // Announcements
    Route::get('/announcement', [AnnouncementController::class, 'active']);
    Route::post('/announcement', [AnnouncementController::class, 'store'])->middleware(['auth', 'admin']);
    Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy'])->middleware(['auth', 'admin']);

    // Categories
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::post('/categories', [CategoryController::class, 'store'])->middleware(['auth', 'admin']);
    Route::put('/categories/{category}', [CategoryController::class, 'update'])->middleware(['auth', 'admin']);
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->middleware(['auth', 'admin']);

    // Auctions
    Route::get('/auctions', [AuctionController::class, 'index']);
    Route::get('/my-auctions', [AuctionController::class, 'myAuctions'])->middleware('auth');
    Route::get('/auctions/ended', [AuctionController::class, 'ended'])->middleware(['auth', 'admin']);
    Route::get('/auctions/{auction}/quotes/{bid}', [QuotePdfController::class, 'download'])->middleware(['auth', 'admin']);
    Route::get('/auctions/{auction}/leftover-purchases/{leftoverPurchase}/quotes', [QuotePdfController::class, 'downloadForLeftoverPurchase'])->middleware(['auth', 'admin']);
    Route::get('/auctions/{auction}/leftover-price-offers/{leftoverPriceOffer}/quotes', [QuotePdfController::class, 'downloadForLeftoverPriceOffer'])->middleware(['auth', 'admin']);
    Route::get('/users/{user}/quotes', [QuotePdfController::class, 'downloadForUser'])->middleware(['auth', 'admin']);
    Route::get('/quotes/{filename}', [QuotePdfController::class, 'downloadStored'])->middleware(['auth', 'admin']);
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

    // Leftover purchases
    Route::post('/auctions/{auction}/leftover-purchases', [LeftoverPurchaseController::class, 'store'])->middleware('auth');

    // Leftover price offers
    Route::post('/auctions/{auction}/leftover-price-offers', [LeftoverPriceOfferController::class, 'store'])->middleware('auth');

    // Auction questions
    Route::get('/questions', [AuctionQuestionController::class, 'index'])->middleware(['auth', 'admin']);
    Route::post('/auctions/{auction}/questions', [AuctionQuestionController::class, 'store'])->middleware('auth');
    Route::put('/questions/{question}', [AuctionQuestionController::class, 'update'])->middleware('auth');
    Route::delete('/questions/{question}', [AuctionQuestionController::class, 'destroy'])->middleware('auth');

    // Admin management
    Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
        Route::get('/users', [AdminBidController::class, 'users']);
        Route::post('/auctions/{auction}/end', [AdminAuctionController::class, 'end']);
        Route::post('/auctions/{auction}/cancel', [AdminAuctionController::class, 'cancel']);
        Route::post('/auctions/{auction}/reactivate', [AdminAuctionController::class, 'reactivate']);
        Route::post('/auctions/{auction}/extend', [AdminAuctionController::class, 'extend']);
        Route::post('/auctions/{auction}/bids', [AdminBidController::class, 'store']);
        Route::put('/bids/{bid}', [AdminBidController::class, 'update']);
        Route::delete('/bids/{bid}', [AdminBidController::class, 'destroy']);
        Route::post('/auctions/{auction}/leftover-purchases', [LeftoverPurchaseController::class, 'adminStore']);
        Route::delete('/leftover-purchases/{leftoverPurchase}', [LeftoverPurchaseController::class, 'destroy']);
        Route::get('/leftover-price-offers', [LeftoverPriceOfferController::class, 'index']);
        Route::post('/leftover-price-offers/request-rebid', [LeftoverPriceOfferController::class, 'requestRebid']);
        Route::post('/leftover-price-offers/{leftoverPriceOffer}/accept', [LeftoverPriceOfferController::class, 'accept']);
        Route::post('/leftover-price-offers/{leftoverPriceOffer}/reject', [LeftoverPriceOfferController::class, 'reject']);
        Route::post('/auctions/{auction}/leftover-price-offers', [LeftoverPriceOfferController::class, 'adminStore']);
        Route::delete('/leftover-price-offers/{leftoverPriceOffer}', [LeftoverPriceOfferController::class, 'destroy']);
        Route::get('/audit-log', [AdminAuditLogController::class, 'index']);
    });
});
