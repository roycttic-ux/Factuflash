<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function stats(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $monthStart = now()->startOfMonth();

        // Total del mes en ARS
        $monthlyTotal = Invoice::where('user_id', $userId)
            ->where('created_at', '>=', $monthStart)
            ->sum('total_local');

        // Cantidades
        $invoiceCount = Invoice::where('user_id', $userId)->count();
        $productCount = Product::where('user_id', $userId)->count();

        // Stock bajo
        $lowStockCount = Product::where('user_id', $userId)
            ->whereColumn('stock', '<=', 'min_stock_alert')
            ->count();

        // Últimas facturas
        $recentInvoices = Invoice::with('recipient')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Productos más vendidos
        $topProducts = \App\Models\InvoiceItem::select('product_id', \DB::raw('SUM(quantity) as total_qty'))
            ->whereHas('invoice', function($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->with('product:id,name')
            ->groupBy('product_id')
            ->orderBy('total_qty', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'stats' => [
                'monthly_total' => round($monthlyTotal, 2),
                'invoice_count' => $invoiceCount,
                'product_count' => $productCount,
                'low_stock_count' => $lowStockCount,
            ],
            'recent_invoices' => $recentInvoices,
            'top_products' => $topProducts,
        ]);
    }
}
