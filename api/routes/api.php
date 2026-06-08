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

    // Catálogo Público
    Route::get('/public/catalog/{userId}', [\App\Http\Controllers\Api\V1\CatalogController::class, 'show']);

    // Rutas protegidas
    Route::middleware('auth:sanctum')->group(function () {
        // Auth
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        Route::get('/products/tags', [ProductController::class, 'getTags']);
        Route::get('/products', [ProductController::class, 'index']);
        Route::post('/products/inflation', [ProductController::class, 'applyInflation']);
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

        // Employees / Cashiers
        Route::get('/cashiers', [\App\Http\Controllers\Api\V1\CashierController::class, 'index']);
        Route::post('/cashiers', [\App\Http\Controllers\Api\V1\CashierController::class, 'store']);
        Route::delete('/cashiers/{id}', [\App\Http\Controllers\Api\V1\CashierController::class, 'destroy']);

        // Settings
        Route::get('/settings', [SettingsController::class, 'show']);
        Route::put('/settings', [SettingsController::class, 'update']);
        Route::post('/settings/logo', [SettingsController::class, 'uploadLogo']);

        // ARCA
        Route::post('/arca/csr', [ArcaController::class, 'generateCsr']);
        Route::post('/arca/cert', [ArcaController::class, 'uploadCert']);
        Route::post('/arca/reset', [ArcaController::class, 'reset']);

        // POS Devices
        Route::get('/pos-devices', [\App\Http\Controllers\Api\V1\PosController::class, 'index']);
        Route::post('/pos-devices', [\App\Http\Controllers\Api\V1\PosController::class, 'store']);
        Route::post('/pos-devices/confirm', [\App\Http\Controllers\Api\V1\PosController::class, 'confirmPayment']);
        Route::get('/pos-devices/status/{externalId}', [\App\Http\Controllers\Api\V1\PosController::class, 'checkStatus']);
        Route::delete('/pos-devices/{id}', [\App\Http\Controllers\Api\V1\PosController::class, 'destroy']);

        // POS Bridge Webhook (llamado por factuflash_agent.exe con Bearer token)
        Route::post('/pos/webhook', [\App\Http\Controllers\Api\V1\PosWebhookController::class, 'handle']);

        // Mercado Pago
        Route::post('/mp/preference', [\App\Http\Controllers\Api\V1\MercadoPagoController::class, 'createPreference']);
        Route::post('/mp/point-intent', [\App\Http\Controllers\Api\V1\MercadoPagoController::class, 'createPointPaymentIntent']);
        Route::post('/mp/subscription-preference', [\App\Http\Controllers\Api\V1\MercadoPagoController::class, 'createSubscriptionPreference']);

        // Suscripciones
        Route::get('/subscription/plans', [\App\Http\Controllers\Api\V1\SubscriptionController::class, 'plans']);
        Route::post('/subscription/checkout', [\App\Http\Controllers\Api\V1\SubscriptionController::class, 'createCheckout']);
        Route::get('/subscription/status', [\App\Http\Controllers\Api\V1\SubscriptionController::class, 'status']);
        Route::post('/subscription/cancel', [\App\Http\Controllers\Api\V1\SubscriptionController::class, 'cancel']);

        // Admin
        Route::get('/admin/users', [\App\Http\Controllers\Api\V1\AdminController::class, 'users']);
        Route::delete('/admin/users/{id}', [\App\Http\Controllers\Api\V1\AdminController::class, 'deleteUser']);
        Route::post('/admin/users/{id}/activate-subscription', [\App\Http\Controllers\Api\V1\AdminController::class, 'activateSubscription']);
    });

    // Webhooks (Públicos)
    Route::post('/webhooks/mp', [\App\Http\Controllers\Api\V1\MercadoPagoController::class, 'webhook'])->name('mp.webhook');
    Route::post('/webhooks/subscription', [\App\Http\Controllers\Api\V1\SubscriptionController::class, 'webhook'])->name('subscription.webhook');

    // Descarga del Agente POS Bridge
    Route::get('/pos-devices/download-agent/{platform}', [\App\Http\Controllers\Api\V1\PosController::class, 'downloadAgent']);
    Route::get('/download/pos-agent', function () {
        $file = base_path('../factuflash_agent/factuflash_agent.py');
        if (!file_exists($file)) {
            return response()->json(['error' => 'Archivo no encontrado'], 404);
        }
        return response()->download($file, 'factuflash_agent.py', [
            'Content-Type' => 'text/plain',
        ]);
    });
});
