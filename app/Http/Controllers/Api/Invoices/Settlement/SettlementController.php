<?php

namespace App\Http\Controllers\Api\Invoices\Settlement;

use App\Http\Controllers\Controller;
use App\Models\expense;
use App\Models\inventory;
use App\Models\invoice_item;
use App\Models\settlement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SettlementController extends Controller
{
    ////////////////////////////////////////////////////////////////////////////////////////////
    /////{Index Settlements}///////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////
public function index(Request $request)
{
    $admin = Auth::user();

    if ($admin->role !== 'admin') {
        return response()->json([
            'success' => false,
            'message' => 'غير مصرح لك بالدخول إلى البيانات المالية'
        ], 403);
    }

    $request->validate([
        'from_date' => 'nullable|date',
        'to_date'   => 'nullable|date|after_or_equal:from_date',
    ]);

    // 1. جلب التسويات (Settlements)
    $query = settlement::with([
            'invoice:id,total_amount,type,status', 
            'approver:id,name', 
            'branch:id,name'
        ])
        ->where('branch_id', $admin->branch_id);

    // 2. جلب المصاريف (Expenses) بنفس الفرع
    $expensesQuery = expense::where('branch_id', $admin->branch_id);

    // تطبيق الفلتر الزمني على الطرفين
    if ($request->filled(['from_date', 'to_date'])) {

       $fromDate = Carbon::parse($request->from_date)->startOfDay();
       $toDate   = Carbon::parse($request->to_date)->endOfDay();
        $query->whereBetween('settlement_date', [$fromDate, $toDate]);
        // ملاحظة: المصاريف نستخدم created_at أو تاريخ المصروف المعتمد لديك
        $expensesQuery->whereBetween('created_at', [$fromDate, $toDate]);
    }

    $settlements = $query->latest('settlement_date')->get();
    $totalExpenses = $expensesQuery->sum('amount');

    // الحسابات المالية
    $totalGrossProfit = $settlements->sum('gross_profit'); // الربح من المبيعات فقط
    $realNetProfit = $settlements->sum('net_profit') - $totalExpenses;   // الصافي بعد خصم المصاريف

    $statistics = [
        'settlements_count'   => $settlements->count(),
        'total_gross_revenue' => round($totalGrossProfit, 2),
        'total_expenses'      => round($totalExpenses, 2),     // سطر مضاف
        'total_net_profit'    => round($realNetProfit, 2),      // الربح الحقيقي
        'average_net_profit'  => $settlements->count() > 0 
                                 ? round($realNetProfit / $settlements->count(), 2) 
                                 : 0
    ];

    return response()->json([
        'success'    => true,
        'filters'    => [
            'from' => $request->from_date,
            'to'   => $request->to_date
        ],
        'statistics' => $statistics,
        'data'       => $settlements
    ], 200);
}

 ////////////////////////////////////////////////////////////////////////////////////////////
    /////{reports Settlements}///////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////

     public function reports(Request $request)
{
    $request->validate([
        'period' => 'required|in:today,weekly,monthly,yearly',
    ]);

    $admin = Auth::user();

    if ($admin->role !== 'admin') {
        return response()->json([
            'success' => false,
            'message' => 'غير مصرح لك بالدخول إلى البيانات المالية'
        ], 403);
    }

    // تجهيز الاستعلامات للفرع المحدد
    $settlementQuery = settlement::where('branch_id', $admin->branch_id);
    $expenseQuery = expense::where('branch_id', $admin->branch_id);

    // تطبيق فلتر الفترة الزمنية على المبيعات والمصاريف معاً
    switch ($request->period) {
        case 'today':
            $settlementQuery->whereDate('settlement_date', Carbon::today());
            $expenseQuery->whereDate('created_at', Carbon::today());
            break;
        case 'weekly':
            $start = Carbon::now()->startOfWeek();
            $end = Carbon::now()->endOfWeek();
            $settlementQuery->whereBetween('settlement_date', [$start, $end]);
            $expenseQuery->whereBetween('created_at', [$start, $end]);
            break;
        case 'monthly':
            $settlementQuery->whereMonth('settlement_date', Carbon::now()->month)
                            ->whereYear('settlement_date', Carbon::now()->year);
            $expenseQuery->whereMonth('created_at', Carbon::now()->month)
                         ->whereYear('created_at', Carbon::now()->year);
            break;
        case 'yearly':
            $settlementQuery->whereYear('settlement_date', Carbon::now()->year);
            $expenseQuery->whereYear('created_at', Carbon::now()->year);
            break;
    }

    $settlements = $settlementQuery->get();
    $totalExpenses = $expenseQuery->sum('amount');
    
    $totalGrossProfit = $settlements->sum('gross_profit'); // أرباح المبيعات فقط
    $actualNetProfit = $totalGrossProfit - $totalExpenses; // الربح الحقيقي الصافي
    $netProfit = $settlements->sum('net_profit') - $totalExpenses; // الربح الصافي المسجل في التسويات
    return response()->json([
        'success' => true,
        'period'  => $request->period,
        'summary' => [
            'total_invoices'   => $settlements->count(),
            'net_profit'       => round($netProfit, 2), 
            'gross_revenue'    => round($totalGrossProfit, 2),
            'total_expenses'   => round($totalExpenses, 2),
            'final_gross_profit' => round($actualNetProfit, 2),
            'profit_margin'    => $totalGrossProfit > 0 
                                  ? round(($actualNetProfit / $totalGrossProfit) * 100, 2) . '%' 
                                  : '0%',
            'average_sale'     => $settlements->count() > 0 
                                  ? round($settlements->avg('gross_profit'), 2) 
                                  : 0,
        ]
    ]);
}


    ////////////////////////////////////////////////////////////////////////////////////////////
    /////{inventoryAudit Settlements}///////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////
  public function inventoryAudit()
  {
    $admin = Auth::user();

    if ($admin->role !== 'admin') {
        return response()->json(['success' => false, 'message' => 'غير مصرح لك'], 403);
    }
    
    $inventoryData = inventory::with(['variant.product:id,model_name'])
        ->where('branch_id', $admin->branch_id)
        ->get();

    $groupedItems = [];
    $totalInventoryCost = 0;
    $totalInventoryMarket = 0;

    foreach ($inventoryData as $item) {


        $variant = $item->variant;
        if (!$variant) continue;

        $key = $variant->product_id . '_' . $variant->color . '_' . $variant->variant_name;

        $cost = (float) $variant->cost_price;
        $selling = (float) $variant->selling_price;
        $qty = (int) $item->quantity;

        $totalItemCost = round($qty * $cost, 2);
        $totalItemMarket = round($qty * $selling, 2);
            
        if (!isset($groupedItems[$key])) {
            $groupedItems[$key] = [
                'product_name'       => $variant->product->model_name ?? 'N/A',
                'variant_name'       => $variant->variant_name, 
                'color'              => $variant->color,
                'cost_price'         => $cost,
                'selling_price'      => $selling,
                'total_group_qty'    => 0,
                'total_group_cost'   => 0,
                'total_group_market' => 0,
                'sizes_details'      => [] 
            ];
        }

        $groupedItems[$key]['total_group_qty']    += $qty;
        $groupedItems[$key]['total_group_cost']   += $totalItemCost;
        $groupedItems[$key]['total_group_market'] += $totalItemMarket;
        
        $groupedItems[$key]['sizes_details'][] = [
            'variant_id' => $variant->id,
            'size'       => $variant->size,
            'quantity'   => $qty
        ]; 

        $totalInventoryCost += $totalItemCost;
        $totalInventoryMarket += $totalItemMarket;
    }

    return response()->json([
        'success' => true,
        'statistics' => [
            'total_inventory_cost'   => round($totalInventoryCost, 2),
            'total_inventory_market' => round($totalInventoryMarket, 2),
            'potential_profit'       => round($totalInventoryMarket - $totalInventoryCost, 2),
        ],
        'items' => array_values($groupedItems) 
    ]);
}

        ////////////////////////////////////////////////////////////////////////////////////////////
    /////{show Settlements}///////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////

    public function show(Request $request)
    {
        $request->validate([
            'id'=>'required|exists:settlements,id'
        ]);
        
        $admin = Auth::user();

           if($admin->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بالدخول إلى البيانات المالية'
            ], 403);
        }
        
        $settlement = settlement::with(['invoice.invoiceItems.variant', 'approver'])
            ->where('branch_id', $admin->branch_id)
            ->findOrFail($request->id);

        return response()->json([
            'success' => true,
            'data' => $settlement
        ]);
    }

    ////////////////////////////////////////////////////////////////////////////////////////////
    /////{delete Settlements}///////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////
    public function delete(Request $request)
    {
        $request->validate([
            'id'=>'required|exists:settlements,id'
        ]);
        
        $admin = Auth::user();

           if($admin->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بالدخول إلى البيانات المالية'
            ], 403);
        }
        
        $settlement = settlement::where('branch_id', $admin->branch_id)->findOrFail($request->id);
        $settlement->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف التسوية بنجاح'
        ]);
    }


        ////////////////////////////////////////////////////////////////////////////////////////////
    /////{topProfitable Settlements}///////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////

public function topProfitable()
{
    $admin = Auth::user();

    if ($admin->role !== 'admin') {
        return response()->json(['success' => false, 'message' => 'غير مصرح لك'], 403);
    }

    $topItems = invoice_item::query()
        ->select(
            'invoice_items.variant_id', 
            DB::raw('SUM((invoice_items.sold_at_price - product_variants.cost_price) * invoice_items.quantity) as total_profit'),
            DB::raw('SUM(invoice_items.quantity) as total_qty_sold')
        )
        ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
        ->join('product_variants', 'invoice_items.variant_id', '=', 'product_variants.id') 
        ->where('invoices.branch_id', $admin->branch_id)
        ->with(['variant.product:id,model_name'])
        ->groupBy('invoice_items.variant_id', 'product_variants.cost_price') 
        ->get();

    $groupedResults = [];

    foreach ($topItems as $item) {
        $variant = $item->variant;
        if (!$variant) continue;

        $key = $variant->product_id . '_' . $variant->color . '_' . $variant->variant_name;

        if (!isset($groupedResults[$key])) {
            $groupedResults[$key] = [
                'product_name' => $variant->product->model_name ?? 'N/A',
                'variant_name' => $variant->variant_name,
                'color'        => $variant->color,
                'total_profit' => 0,
                'total_sold'   => 0,
                'sizes_performance' => []
            ];
        }

        $groupedResults[$key]['total_profit'] += (float) $item->total_profit;
        $groupedResults[$key]['total_sold']   += (int) $item->total_qty_sold;
        
        $groupedResults[$key]['sizes_performance'][] = [
            'size'   => $variant->size,
            'profit' => round($item->total_profit, 2),
            'qty'    => $item->total_qty_sold
        ];
    }

    $finalResult = collect($groupedResults)
        ->sortByDesc('total_profit')
        ->take(5)
        ->values();

    return response()->json([
        'success' => true,
        'data'    => $finalResult
    ]);
}

        ////////////////////////////////////////////////////////////////////////////////////////////
    /////{lowStockAlerts Settlements}///////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////


public function lowStockAlerts()
{
    $admin = Auth::user();

    if($admin->role !== 'admin') {
        return response()->json([
            'success' => false, 
            'message' => 'غير مصرح لك بالدخول إلى البيانات المالية'
        ], 403);
    }

    $lowStock = inventory::with(['variant.product:id,model_name'])
        ->where('branch_id', $admin->branch_id)
        ->where('quantity', '<=', 2)
        ->get();

    $result = $lowStock->map(function($item) {
        return [
            'product_name' => $item->variant->product->model_name ?? 'N/A',
            'variant_name' => $item->variant->variant_name,
            'color'        => $item->variant->color,
            'size'         => $item->variant->size,
            'quantity'     => $item->quantity,
            'status'       => $item->quantity == 0 ? 'نفذت الكمية' : 'كمية منخفضة'
        ];
    });

    return response()->json([
        'success' => true,
        'count'   => $result->count(),
        'items'   => $result
    ]);
}
    
}