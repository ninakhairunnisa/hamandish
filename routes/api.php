<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Admin\AdminDashboardController;
use App\Http\Controllers\Api\V1\Admin\AdminProblemController;
use App\Http\Controllers\Api\V1\Admin\AssemblyAdminController;
use App\Http\Controllers\Api\V1\Admin\OfficialController;
use App\Http\Controllers\Api\V1\AssemblyMembershipController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\Integrations\MessengerWebhookController;
use App\Http\Controllers\Api\V1\MessengerAuthController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CommentController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ProblemController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\SolutionController;
use App\Http\Controllers\Api\V1\SupportController;
use App\Http\Controllers\Api\V1\VoteController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {

    // ---------------------------------------------------------------------
    // Auth
    // ---------------------------------------------------------------------
    Route::prefix('auth')->group(function (): void {
        // Defense-in-depth IP throttle on top of the per-phone limiter / attempt counter.
        Route::post('send-otp', [AuthController::class, 'sendOtp'])->middleware('throttle:10,1');
        Route::post('verify-otp', [AuthController::class, 'verifyOtp'])->middleware('throttle:10,1');
        Route::middleware('auth:sanctum')->get('me', [AuthController::class, 'me']);

        // Bale / Eitaa mini-app login (validates signed init-data).
        Route::post('messenger', [MessengerAuthController::class, 'authenticate'])
            ->middleware('throttle:30,1');
        // Completes login with the signed contact response from requestContact().
        Route::post('messenger/contact', [MessengerAuthController::class, 'contact'])
            ->middleware('throttle:30,1');
    });

    // Bot webhooks (contact sharing). Secured by per-provider secret in the query.
    Route::post('integrations/{provider}/webhook', [MessengerWebhookController::class, 'handle']);

    // ---------------------------------------------------------------------
    // Categories (public)
    // ---------------------------------------------------------------------
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('settings', fn () => response()->json([
        'comments_enabled' => \App\Models\Setting::getBool('comments_enabled'),
    ]));

    // ---------------------------------------------------------------------
    // Problems – public feed (supports ?search= &category_id= &sort=popular|latest)
    // ---------------------------------------------------------------------
    Route::get('problems/featured', [ProblemController::class, 'featured']);
    Route::get('problems/popular', [ProblemController::class, 'popular']);
    Route::get('problems', [ProblemController::class, 'index']);
    Route::get('problems/{problem}', [ProblemController::class, 'show']);

    // Solutions & Comments – public reads
    Route::get('problems/{problem}/solutions', [SolutionController::class, 'index']);
    Route::get('problems/{problem}/comments', [CommentController::class, 'indexForProblem']);
    Route::get('solutions/{solution}/comments', [CommentController::class, 'indexForSolution']);

    // ---------------------------------------------------------------------
    // Authenticated user actions
    // ---------------------------------------------------------------------
    Route::middleware('auth:sanctum')->group(function (): void {
        // Problems
        Route::post('problems', [ProblemController::class, 'store']);
        Route::post('problems/{problem}/support', [SupportController::class, 'toggle']);
        Route::post('problems/{problem}/comments', [CommentController::class, 'storeForProblem']);

        // Solutions
        Route::post('problems/{problem}/solutions', [SolutionController::class, 'store']);
        Route::patch('solutions/{solution}', [SolutionController::class, 'update']);
        Route::post('solutions/{solution}/vote', [VoteController::class, 'vote']);
        Route::delete('solutions/{solution}/vote', [VoteController::class, 'destroy']);
        Route::post('solutions/{solution}/comments', [CommentController::class, 'storeForSolution']);
        Route::patch('comments/{comment}', [CommentController::class, 'update']);
        Route::post('comments/{comment}/replies', [CommentController::class, 'storeReply']);

        // Assembly membership
        Route::get('assembly/form', [AssemblyMembershipController::class, 'form']);
        Route::get('assembly/my', [AssemblyMembershipController::class, 'show']);
        Route::post('assembly', [AssemblyMembershipController::class, 'store']);

        // Profile
        Route::get('profile', [ProfileController::class, 'show']);
        Route::match(['put', 'post'], 'profile', [ProfileController::class, 'update']);
        Route::get('profile/problems', [ProfileController::class, 'problems']);
        Route::get('profile/comments', [ProfileController::class, 'comments']);

        // Notifications
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::patch('notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::patch('notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    });

    // ---------------------------------------------------------------------
    // Admin
    // ---------------------------------------------------------------------
    Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function (): void {
        Route::get('problems/pending', [AdminProblemController::class, 'pending']);
        Route::patch('problems/{problem}/status', [AdminProblemController::class, 'updateStatus']);
        Route::patch('problems/{problem}/featured', [AdminProblemController::class, 'setFeatured']);
        Route::patch('problems/{problem}/best-solution', [AdminProblemController::class, 'setBestSolution']);
        Route::get('problems', [AdminProblemController::class, 'index']);
        Route::delete('problems/{problem}', [AdminProblemController::class, 'destroy']);
        Route::get('stats', [AdminDashboardController::class, 'stats']);
        Route::get('users', [AdminDashboardController::class, 'users']);
        Route::patch('users/{user}/role', [AdminDashboardController::class, 'setRole']);
        Route::patch('users/{user}/label', [AdminDashboardController::class, 'setLabel']);
        Route::patch('comments/{comment}/pin', [AdminDashboardController::class, 'pinComment']);
        Route::patch('solutions/{solution}/pin', [AdminDashboardController::class, 'pinSolution']);
        Route::get('settings', [AdminDashboardController::class, 'getSettings']);
        Route::patch('settings', [AdminDashboardController::class, 'updateSettings']);

        // Officials
        Route::get('officials', [OfficialController::class, 'index']);
        Route::post('officials', [OfficialController::class, 'store']);
        Route::patch('officials/{official}', [OfficialController::class, 'update']);
        Route::delete('officials/{official}', [OfficialController::class, 'destroy']);
        Route::get('officials/search-user', [OfficialController::class, 'searchUser']);
        Route::get('problems/{problem}/referrals', [OfficialController::class, 'referrals']);
        Route::post('problems/{problem}/referrals', [OfficialController::class, 'sendReferral']);

        // Assembly admin
        Route::get('assembly/roles', [AssemblyAdminController::class, 'roles']);
        Route::post('assembly/roles', [AssemblyAdminController::class, 'storeRole']);
        Route::patch('assembly/roles/{role}', [AssemblyAdminController::class, 'updateRole']);
        Route::delete('assembly/roles/{role}', [AssemblyAdminController::class, 'destroyRole']);
        Route::get('assembly/settings', [AssemblyAdminController::class, 'getSettings']);
        Route::patch('assembly/settings', [AssemblyAdminController::class, 'updateSettings']);
        Route::get('assembly/memberships', [AssemblyAdminController::class, 'memberships']);
        Route::patch('assembly/memberships/{membership}', [AssemblyAdminController::class, 'updateMembership']);
        Route::get('assembly/memberships/export', [AssemblyAdminController::class, 'exportCsv']);
        Route::get('assembly/stats', [AssemblyAdminController::class, 'stats']);
    });
});
