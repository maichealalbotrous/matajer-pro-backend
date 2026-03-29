<?php

namespace App\Http\Controllers\Api\Invoices\Settlement;

use App\Http\Controllers\Controller;
use App\Models\settlement;
use App\Models\User;
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
            'approver:id,name', // تأكد أن الاسم يطابق ما في الموديل (approver أو approvedBy)
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
}