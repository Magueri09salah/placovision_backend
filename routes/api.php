<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\QuotationController;
use App\Http\Controllers\Api\PublicPdfController;
use App\Http\Controllers\Api\Admin\ProductCategoryController;
use App\Http\Controllers\Api\OdooController;
use App\Http\Controllers\Api\FactureController;
use App\Http\Controllers\Api\NotificationController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::get('/pdf/{token}', [PublicPdfController::class, 'show']);

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/auth/google', [AuthController::class, 'googleAuth']);

// ============ OPTIONS (Public) ============
Route::get('/quotations/options', [QuotationController::class, 'getOptions']);
Route::post('/quotations/simulate', [QuotationController::class, 'simulate']);

// ============ ODOO WEBHOOKS (Public — called by Odoo) ============
// Route::middleware('odoo.apikey')->prefix('odoo/webhook')->group(function () {
    Route::post('/status', [OdooController::class, 'handleStatusWebhook']);
    Route::post('/invoice', [OdooController::class, 'handleInvoiceWebhook']);
// });


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

    Route::post('/quotations/{id}/odoo-sync', [OdooController::class, 'sendToOdoo']);
    Route::post('/quotations/{id}/duplicate', [QuotationController::class, 'duplicate']);
    Route::patch('/quotations/{id}/status', [QuotationController::class, 'updateStatus']);
    Route::patch('/quotations/{id}/items/{itemId}', [QuotationController::class, 'updateItem']);
    Route::post('/quotations/{id}/items/{itemId}/reset', [QuotationController::class, 'resetItem']);
    Route::get('/quotations/{id}/pdf', [QuotationController::class, 'exportPdf']);
    Route::post('/quotations/{id}/odoo-accept', [OdooController::class, 'notifyAcceptance']);
    Route::post('/quotations/{id}/odoo-reject', [OdooController::class, 'notifyRejection']);

    // ============ FACTURES ============
    Route::get('/factures/stats', [FactureController::class, 'stats']);
    Route::get('/factures', [FactureController::class, 'index']);
    Route::get('/factures/{id}', [FactureController::class, 'show']);
    Route::patch('/factures/{id}/status', [FactureController::class, 'updateStatus']);

    // ============ NOTIFICATIONS ============
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);
    Route::delete('/notifications/clear-read', [NotificationController::class, 'clearRead']);
});

Broadcast::routes(['middleware' => ['auth:sanctum']]);

/*
|--------------------------------------------------------------------------
| Admin routes (Spatie Role)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/admin/categories', [ProductCategoryController::class, 'store']);
});