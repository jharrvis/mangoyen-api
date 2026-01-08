<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatController;
use App\Http\Controllers\Api\ShelterController;
use App\Http\Controllers\Api\AdoptionController;
use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\FraudReportController;
use App\Http\Controllers\Api\PaymentController;

/*
|--------------------------------------------------------------------------
| API Routes - MangOyen
|--------------------------------------------------------------------------
*/

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Google OAuth
use App\Http\Controllers\Api\GoogleAuthController;
Route::get('/auth/google', [GoogleAuthController::class, 'redirect']);
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback']);

// Cats (public read)
Route::get('/cats', [CatController::class, 'index']);
Route::get('/cats/breeds', [CatController::class, 'breeds']);
Route::get('/cats/id/{id}', [CatController::class, 'showById']);
Route::get('/cats/{slug}', [CatController::class, 'show']);

// Shelters (public read)
Route::get('/shelters', [ShelterController::class, 'index']);
Route::get('/shelters/{slug}', [ShelterController::class, 'show']);

// Articles (public read)
Route::get('/articles', [ArticleController::class, 'index']);
Route::get('/articles/categories', [ArticleController::class, 'categories']);
Route::get('/articles/{slug}', [ArticleController::class, 'show']);

// Fraud reports (can be submitted without login)
Route::post('/fraud-reports', [FraudReportController::class, 'store']);

// MangOyen Assistant (public - chat with AI)
use App\Http\Controllers\Api\AssistantController;
Route::post('/assistant/chat', [AssistantController::class, 'chat']);

// Membership Tiers (public read)
Route::get('/membership-tiers', [\App\Http\Controllers\Api\MembershipController::class, 'index']);
Route::get('/membership-tiers/{slug}', [\App\Http\Controllers\Api\MembershipController::class, 'show']);

// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::put('/profile/password', [AuthController::class, 'updatePassword']);

    // Cats (CRUD for shelter owners)
    Route::get('/my-cats', [CatController::class, 'myCats']);
    Route::post('/cats', [CatController::class, 'store']);
    Route::put('/cats/{id}', [CatController::class, 'update']);
    Route::delete('/cats/{id}', [CatController::class, 'destroy']);

    // Cat Save/Like
    Route::post('/cats/{id}/save', [CatController::class, 'toggleSave']);
    Route::get('/cats/{id}/saved', [CatController::class, 'checkSaved']);

    // Shelters
    Route::post('/shelters', [ShelterController::class, 'store']);
    Route::put('/shelters/{id}', [ShelterController::class, 'update']);

    // Adoptions
    Route::get('/adoptions', [AdoptionController::class, 'index']);
    Route::post('/adoptions', [AdoptionController::class, 'store']);
    Route::get('/adoptions/{id}', [AdoptionController::class, 'show']);
    Route::post('/adoptions/{id}/confirm-payment', [AdoptionController::class, 'confirmPayment']);
    Route::post('/adoptions/{id}/confirm-received', [AdoptionController::class, 'confirmReceived']);
    Route::post('/adoptions/{id}/cancel', [AdoptionController::class, 'cancel']);
    Route::post('/adoptions/{id}/approve', [AdoptionController::class, 'approve']);
    Route::post('/adoptions/{id}/reject', [AdoptionController::class, 'reject']);
    Route::post('/adoptions/{id}/confirm-shipping', [AdoptionController::class, 'confirmShipping']);
    Route::patch('/adoptions/{id}/final-price', [AdoptionController::class, 'updateFinalPrice']);

    // Payments
    Route::post('/payments/snap-token/{adoptionId}', [PaymentController::class, 'createSnapToken']);

    // Profile
    Route::prefix('profile')->group(function () {
        Route::get('/bank-account', [\App\Http\Controllers\Api\ProfileController::class, 'getBankAccount']);
        Route::put('/bank-account', [\App\Http\Controllers\Api\ProfileController::class, 'updateBankAccount']);
    });

    // KYC (Shelter Verification)
    Route::prefix('kyc')->group(function () {
        Route::post('/submit', [\App\Http\Controllers\Api\KycController::class, 'submit']);
        Route::get('/status', [\App\Http\Controllers\Api\KycController::class, 'status']);
    });

    // Admin KYC Management
    Route::prefix('admin/kyc')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\KycController::class, 'adminList']);
        Route::get('/{id}', [\App\Http\Controllers\Api\KycController::class, 'adminShow']);
        Route::post('/{id}/approve', [\App\Http\Controllers\Api\KycController::class, 'approve']);
        Route::post('/{id}/reject', [\App\Http\Controllers\Api\KycController::class, 'reject']);
    });

    // Notifications
    Route::prefix('notifications')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\NotificationController::class, 'index']);
        Route::get('/recent', [\App\Http\Controllers\Api\NotificationController::class, 'recent']);
        Route::get('/unread-count', [\App\Http\Controllers\Api\NotificationController::class, 'unreadCount']);
        Route::post('/{id}/read', [\App\Http\Controllers\Api\NotificationController::class, 'markAsRead']);
        Route::post('/read-all', [\App\Http\Controllers\Api\NotificationController::class, 'markAllAsRead']);
    });

    // Admin Activity Logs
    Route::prefix('admin/activity-logs')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\ActivityLogController::class, 'index']);
        Route::get('/recent', [\App\Http\Controllers\Api\ActivityLogController::class, 'recent']);
        Route::get('/stats', [\App\Http\Controllers\Api\ActivityLogController::class, 'stats']);
    });

    // Admin Users Management
    Route::prefix('admin/users')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\AdminUserController::class, 'index']);
        Route::get('/{id}', [\App\Http\Controllers\Api\AdminUserController::class, 'show']);
        Route::put('/{id}/status', [\App\Http\Controllers\Api\AdminUserController::class, 'updateStatus']);
        Route::delete('/{id}', [\App\Http\Controllers\Api\AdminUserController::class, 'destroy']);
    });

    // Admin Escrow Transactions (Rekber Pusat)
    Route::get('/admin/escrow-transactions', [\App\Http\Controllers\Api\AdminController::class, 'escrowTransactions']);

    // Wishlist
    Route::prefix('wishlist')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\WishlistController::class, 'index']);
        Route::get('/ids', [\App\Http\Controllers\Api\WishlistController::class, 'ids']);
        Route::get('/check/{catId}', [\App\Http\Controllers\Api\WishlistController::class, 'check']);
        Route::post('/{catId}', [\App\Http\Controllers\Api\WishlistController::class, 'store']);
        Route::delete('/{catId}', [\App\Http\Controllers\Api\WishlistController::class, 'destroy']);
    });

    // Saved Cats (legacy - uses wishlist)
    Route::get('/saved-cats', [CatController::class, 'savedCats']);

    // Chat Messages
    Route::prefix('chat/{adoptionId}')->group(function () {
        Route::get('/messages', [\App\Http\Controllers\Api\MessageController::class, 'index']);
        Route::get('/messages/since', [\App\Http\Controllers\Api\MessageController::class, 'since']);
        Route::post('/messages', [\App\Http\Controllers\Api\MessageController::class, 'store']);
        Route::post('/typing', [\App\Http\Controllers\Api\MessageController::class, 'typing']);
    });
    Route::get('/messages/unread-count', [\App\Http\Controllers\Api\MessageController::class, 'unreadCount']);
});

// Public Payment routes
Route::post('/payments/webhook', [PaymentController::class, 'webhook']);
