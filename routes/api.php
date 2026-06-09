<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Admin\AdminProblemController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CommentController;
use App\Http\Controllers\Api\V1\ProblemController;
use App\Http\Controllers\Api\V1\SolutionController;
use App\Http\Controllers\Api\V1\VoteController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {

    // Auth
    Route::prefix('auth')->group(function (): void {
        Route::post('send-otp', [AuthController::class, 'sendOtp']);
        Route::post('verify-otp', [AuthController::class, 'verifyOtp']);
        Route::middleware('auth:sanctum')->get('me', [AuthController::class, 'me']);
    });

    // Problems – public feed
    Route::get('problems', [ProblemController::class, 'index']);
    Route::get('problems/{problem}', [ProblemController::class, 'show']);

    // Solutions & Comments – public reads
    Route::get('problems/{problem}/solutions', [SolutionController::class, 'index']);
    Route::get('solutions/{solution}/comments', [CommentController::class, 'index']);

    // Authenticated routes
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('problems', [ProblemController::class, 'store']);
        Route::post('problems/{problem}/solutions', [SolutionController::class, 'store']);
        Route::post('solutions/{solution}/vote', [VoteController::class, 'vote']);
        Route::post('solutions/{solution}/comments', [CommentController::class, 'store']);
    });

    // Admin routes
    Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function (): void {
        Route::get('problems/pending', [AdminProblemController::class, 'pending']);
        Route::patch('problems/{problem}/status', [AdminProblemController::class, 'updateStatus']);
        Route::patch('problems/{problem}/best-solution', [AdminProblemController::class, 'setBestSolution']);
    });
});
