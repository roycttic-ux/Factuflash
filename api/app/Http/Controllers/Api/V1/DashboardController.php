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
        $ownerId = $request->user()->system_owner_id;
        $userRole = $request->user()->role;
        $userId = $request->user()->id;

        $monthStart = now()->startOfMonth();

        // Total del mes en ARS
        $monthlyTotalQuery = Invoice::where('user_id', $ownerId)
            ->where('status', '!=', 'annulled')
            ->where('created_at', '>=', $monthStart);
        if ($userRole === 'cashier') $monthlyTotalQuery->where('cashier_id', $userId);
        $monthlyTotal = $monthlyTotalQuery->sum('total_local');

        // Cantidades
        $invoiceCountQuery = Invoice::where('user_id', $ownerId)
            ->where('status', '!=', 'annulled');
        if ($userRole === 'cashier') $invoiceCountQuery->where('cashier_id', $userId);
        $invoiceCount = $invoiceCountQuery->count();

        // El cajero ve todo el catálogo
        $productCount = Product::where('user_id', $ownerId)->count();

        // Stock bajo
        $lowStockCount = Product::where('user_id', $ownerId)
            ->whereColumn('stock', '<=', 'min_stock_alert')
            ->count();

        // Últimas facturas
        $recentInvoicesQuery = Invoice::with('recipient', 'cashier')->where('user_id', $ownerId);
        if ($userRole === 'cashier') $recentInvoicesQuery->where('cashier_id', $userId);
        $recentInvoices = $recentInvoicesQuery->orderBy('created_at', 'desc')->limit(5)->get();

        // Productos más vendidos
        $topProductsQuery = \App\Models\InvoiceItem::select('product_id', \DB::raw('SUM(quantity) as total_qty'))
            ->whereHas('invoice', function($q) use ($ownerId, $userRole, $userId) {
                $q->where('user_id', $ownerId)->where('status', '!=', 'annulled');
                if ($userRole === 'cashier') $q->where('cashier_id', $userId);
            })
            ->with('product:id,name')
            ->groupBy('product_id')
            ->orderBy('total_qty', 'desc')
            ->limit(5);
        $topProducts = $topProductsQuery->get();

        // Ventas por Caja (Solo Jefe o Admin)
        $salesByCashier = [];
        if (in_array($userRole, ['owner', 'admin'])) {
            $salesByCashier = Invoice::select('cashier_id', \DB::raw('SUM(total_local) as total_sales'), \DB::raw('COUNT(id) as total_invoices'))
                ->where('user_id', $ownerId)
                ->where('status', '!=', 'annulled')
                ->groupBy('cashier_id')
                ->with('cashier:id,name,cash_register_name')
                ->get();
        }

        return response()->json([
            'stats' => [
                'monthly_total' => round($monthlyTotal, 2),
                'invoice_count' => $invoiceCount,
                'product_count' => $productCount,
                'low_stock_count' => $lowStockCount,
            ],
            'recent_invoices' => $recentInvoices,
            'top_products' => $topProducts,
            'sales_by_cashier' => $salesByCashier,
        ]);
    }
}
