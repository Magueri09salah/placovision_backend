<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\QuotationController;
use App\Http\Controllers\Api\PublicPdfController;
use App\Http\Controllers\Api\Admin\ProductCategoryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::get('/pdf/{token}', [PublicPdfController::class, 'show']);

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/auth/google', [AuthController::class, 'googleAuth']);
// Route::post('/auth/google/complete', [AuthController::class, 'googleAuthComplete']);

// ============ OPTIONS (Public) ============
Route::get('/quotations/options', [QuotationController::class, 'getOptions']);
Route::post('/quotations/simulate', [QuotationController::class, 'simulate']);

/*
|--------------------------------------------------------------------------
| Authenticated user routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/profile', [AuthController::class, 'updateProfile']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    // Route::put('/company/profile', [AuthController::class, 'updateCompanyProfile']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);

    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::get('/projects/{id}', [ProjectController::class, 'show']);
    Route::put('/projects/{id}', [ProjectController::class, 'update']);
    Route::delete('/projects/{id}', [ProjectController::class, 'destroy']);

    Route::get('/quotations/stats', [QuotationController::class, 'stats']);
    Route::get('/quotations', [QuotationController::class, 'index']);
    Route::post('/quotations', [QuotationController::class, 'store']);
    Route::get('/quotations/{id}', [QuotationController::class, 'show']);
    Route::put('/quotations/{id}', [QuotationController::class, 'update']);
    Route::delete('/quotations/{id}', [QuotationController::class, 'destroy']);

     // Actions spéciales sur les devis
    Route::post('/quotations/{id}/duplicate', [QuotationController::class, 'duplicate']);
    Route::patch('/quotations/{id}/status', [QuotationController::class, 'updateStatus']);

    // Modification des items (quantités)
    Route::patch('/quotations/{id}/items/{itemId}', [QuotationController::class, 'updateItem']);
    Route::post('/quotations/{id}/items/{itemId}/reset', [QuotationController::class, 'resetItem']);

    Route::get('/quotations/{id}/pdf', [QuotationController::class, 'exportPdf']);
});

/*
|--------------------------------------------------------------------------
| Admin routes (Spatie Role)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/admin/categories', [ProductCategoryController::class, 'store']);
});
