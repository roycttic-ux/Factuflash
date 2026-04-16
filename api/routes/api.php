<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\RecipientController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\ArcaController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Auth (públicas)
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::get('/diagnostics', [\App\Http\Controllers\Api\V1\DiagnosticController::class, 'index']);
    Route::get('/diagnostics/fix', [\App\Http\Controllers\Api\V1\DiagnosticController::class, 'fix']);
    Route::get('/diagnostics/migrate', [\App\Http\Controllers\Api\V1\DiagnosticController::class, 'migrate']);
    Route::get('/diagnostics/test-register', [\App\Http\Controllers\Api\V1\DiagnosticController::class, 'testRegister']);

    // Catálogo Público
    Route::get('/public/catalog/{userId}', [\App\Http\Controllers\Api\V1\CatalogController::class, 'show']);

    // Rutas protegidas
    Route::middleware('auth:sanctum')->group(function () {
        // Auth
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        // Products
        Route::get('/products', [ProductController::class, 'index']);
        Route::post('/products', [ProductController::class, 'store']);
        Route::get('/products/low-stock', [ProductController::class, 'lowStock']);
        Route::get('/products/{id}', [ProductController::class, 'show']);
        Route::put('/products/{id}', [ProductController::class, 'update']);
        Route::post('/products/{id}', [ProductController::class, 'update']); // Support multipart updates
        Route::delete('/products/{id}', [ProductController::class, 'destroy']);

        // Customers/Recipients
        Route::get('/customers', [RecipientController::class, 'index']);
        Route::post('/customers', [RecipientController::class, 'store']);
        Route::get('/customers/{id}', [RecipientController::class, 'show']);
        Route::put('/customers/{id}', [RecipientController::class, 'update']);
        Route::delete('/customers/{id}', [RecipientController::class, 'destroy']);

        // Invoices
        Route::get('/invoices', [InvoiceController::class, 'index']);
        Route::post('/invoices', [InvoiceController::class, 'store']);
        Route::get('/invoices/{id}', [InvoiceController::class, 'show']);
        Route::delete('/invoices/{id}', [InvoiceController::class, 'destroy']);

        // Dashboard
        Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

        // Settings
        Route::get('/settings', [SettingsController::class, 'show']);
        Route::put('/settings', [SettingsController::class, 'update']);
        Route::post('/settings/logo', [SettingsController::class, 'uploadLogo']);

        // ARCA
        Route::post('/arca/csr', [ArcaController::class, 'generateCsr']);
        Route::post('/arca/cert', [ArcaController::class, 'uploadCert']);
        Route::post('/arca/reset', [ArcaController::class, 'reset']);

        // Mercado Pago
        Route::post('/mp/preference', [\App\Http\Controllers\Api\V1\MercadoPagoController::class, 'createPreference']);
    });

    // Webhooks (Públicos)
    Route::post('/webhooks/mp', [\App\Http\Controllers\Api\V1\MercadoPagoController::class, 'webhook'])->name('mp.webhook');
});
