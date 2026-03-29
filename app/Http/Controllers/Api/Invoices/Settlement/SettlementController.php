<?php

namespace App\Http\Controllers\Api\Invoices\Settlement;

use App\Http\Controllers\Controller;
use App\Models\inventory;
use App\Models\settlement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

    $query = settlement::with([
            'invoice:id,total_amount,type,status', 
            'approver:id,name', 
            'branch:id,name'
        ])
        ->where('branch_id', $admin->branch_id);

    if ($request->filled(['from_date', 'to_date'])) {
        $query->whereBetween('settlement_date', [$request->from_date, $request->to_date]);
    }

    $settlements = $query->latest('settlement_date')->get();

    $statistics = [
        'total_gross_revenue' => round($settlements->sum('gross_profit'), 2),
        'total_net_profit'    => round($settlements->sum('net_profit'), 2),
        'settlements_count'   => $settlements->count(),
        'average_profit'      => $settlements->count() > 0 
                                 ? round($settlements->avg('net_profit'), 2) 
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

        $query = settlement::where('branch_id', $admin->branch_id);

        if($admin->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بالدخول إلى البيانات المالية'
            ], 403);
        }
        
        switch ($request->period) {
            case 'today':
                $query->whereDate('settlement_date', Carbon::today());
                break;
            case 'weekly':
                $query->whereBetween('settlement_date', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                break;
            case 'monthly':
                $query->whereMonth('settlement_date', Carbon::now()->month)
                      ->whereYear('settlement_date', Carbon::now()->year);
                break;
            case 'yearly':
                $query->whereYear('settlement_date', Carbon::now()->year);
                break;
        }

        $settlements = $query->get();

        return response()->json([
            'success' => true,
            'period'  => $request->period,
            'summary' => [
                'total_invoices' => $settlements->count(),
                'gross_revenue'  => round($settlements->sum('gross_profit'), 2),
                'net_profit'     => round($settlements->sum('net_profit'), 2),
                'average_sale'   => $settlements->count() > 0 ? round($settlements->avg('gross_profit'), 2) : 0,
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
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بالدخول إلى البيانات المالية'
            ], 403);
        }
        
        $inventory = inventory::with('variant.product')
            ->where('branch_id', $admin->branch_id)
            ->get()
            ->map(function ($item) {
                return [
                    'variant_id'   => $item->variant_id,
                    'product_name' => $item->variant->product->name,
                    'variant_name' => $item->variant->name,
                    'current_stock' => $item->quantity, 
                    'cost_price'   => $item->variant->cost_price,
                    'total_value'  => $item->quantity * $item->variant->cost_price 
                ];
            });

        return response()->json([
            'success' => true,
            'branch_id' => $admin->branch_id,
            'total_inventory_value' => round($inventory->sum('total_value'), 2),
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

           if($admin->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بالدخول إلى البيانات المالية']);}

      $topProducts = Settlement::with('invoice.invoiceItems.variant')
        ->where('branch_id', $admin->branch_id)
        ->get()
        ->flatMap(fn($s) => $s->invoice->invoiceItems)
        ->groupBy('variant_id')
        ->map(function ($items) {
            return [
                'name' => $items->first()->variant->name,
                'total_profit' => $items->sum(fn($i) => ($i->sold_at_price - $i->variant->cost_price) * $i->quantity)
            ];
        })->sortByDesc('total_profit')->take(5);

    return response()->json($topProducts->values());
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