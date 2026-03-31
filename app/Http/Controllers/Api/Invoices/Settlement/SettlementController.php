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
    $admin = User::find(Auth::id());

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
        $query->whereBetween('settlement_date', [$request->from_date, $request->to_date]);
        // ملاحظة: المصاريف نستخدم created_at أو تاريخ المصروف المعتمد لديك
        $expensesQuery->whereBetween('created_at', [$request->from_date, $request->to_date]);
    }

    $settlements = $query->latest('settlement_date')->get();
    $totalExpenses = $expensesQuery->sum('amount');

    // الحسابات المالية
    $totalGrossProfit = $settlements->sum('gross_profit'); // الربح من المبيعات فقط
    $realNetProfit = $totalGrossProfit - $totalExpenses;   // الصافي بعد خصم المصاريف

    $statistics = [
        'total_gross_revenue' => round($totalGrossProfit, 2),
        'total_expenses'      => round($totalExpenses, 2),     // سطر مضاف
        'total_net_profit'    => round($realNetProfit, 2),      // الربح الحقيقي
        'settlements_count'   => $settlements->count(),
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

    $admin = User::find(Auth::id());

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

    return response()->json([
        'success' => true,
        'period'  => $request->period,
        'summary' => [
            'total_invoices'   => $settlements->count(),
            'gross_revenue'    => round($totalGrossProfit, 2),
            'total_expenses'   => round($totalExpenses, 2),
            'final_net_profit' => round($actualNetProfit, 2),
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
    $admin = User::find(Auth::id());

    if($admin->role !== 'admin') {
        return response()->json(['success' => false, 'message' => 'غير مصرح لك'], 403);
    }
    
    $inventory = inventory::with('variant.product')
        ->where('branch_id', $admin->branch_id)
        ->get()
        ->map(function ($item) {
            return [
                'variant_id'   => $item->variant_id,
                'product_name' => $item->variant->product->model_name, 
                'variant_name' => $item->variant->variant_name, 
                'current_stock'=> $item->quantity, 
                'cost_price'   => $item->variant->cost_price,
                'selling_price'=> $item->variant->selling_price, 
                'total_cost_value' => round($item->quantity * $item->variant->cost_price, 2),
                'total_market_value'=> round($item->quantity * $item->variant->selling_price, 2)
            ];
        });

    return response()->json([
        'success' => true,
        'total_inventory_cost'   => round($inventory->sum('total_cost_value'), 2),
        'total_inventory_market' => round($inventory->sum('total_market_value'), 2),
        'items' => $inventory
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
        
        $admin = User::find(Auth::id());

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
        
        $admin = User::find(Auth::id());

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
    $admin = User::find(Auth::id());

    if($admin->role !== ' admin')
        {
            return response()->json(['success' => false, 'message' => 'غير مصرح لك'], 403);
        }

    $topProducts = invoice_item::query()
        ->select('variant_id', DB::raw('SUM((sold_at_price - cost_price) * quantity) as total_profit')) 
        ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
        ->where('invoices.branch_id', $admin->branch_id)
        ->groupBy('variant_id')
        ->with('variant:id,variant_name')
        ->orderByDesc('total_profit')
        ->take(5)
        ->get();

    return response()->json($topProducts);
}

        ////////////////////////////////////////////////////////////////////////////////////////////
    /////{lowStockAlerts Settlements}///////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////


public function lowStockAlerts()
{

    $admin = User::find(Auth::id());

           if($admin->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بالدخول إلى البيانات المالية']);}

                
    $lowStock = Inventory::with('variant')
        ->where('branch_id',$admin->branch_id)
        ->whereColumn('quantity', '<=', 1)
        ->get();

    return response()->json($lowStock);
}
    
}