<?php

namespace App\Http\Controllers\Api\Invoices\ApprovedInvoices;

use App\Http\Controllers\Controller;
use App\Models\invoice;
use App\Models\settlement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ApprovedInvoicesController extends Controller
{
    ////////////////////////////////////////////////////////////////////////////////////////////
    /////{Index Invoice according to Status}///////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////
  public function index(Request $request)
{
    $admin = User::find(Auth::id());

    if ($admin->role != 'admin') {
        return response()->json(['error' => 'لا تملك صلاحية عرض الفواتير'], 403);
    }

    $query = invoice::with(['user:id,name', 'settlement'])
        ->where('branch_id', $admin->branch_id);

    if ($request->has('status')) {
        $query->where('status', $request->status);
    }

    $invoices = $query->orderBy('created_at', 'desc')->get();

    return response()->json([
        'success' => true,
        'statistics' => [
            'total_count' => $invoices->count(),
            'pending_count' => $invoices->where('status', 'pending')->count(),
            'approved_count' => $invoices->where('status', 'approved')->count(),
        ],
        'data' => $invoices
    ]);
}
    
    ////////////////////////////////////////////////////////////////////////////////////////////
    /////{approve Invoice}///////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////
    public function approveInvoice(Request $request)
    {
        $request->validate([
            'invoiceId' => 'required|exists:invoices,id',
        ]);

        $admin = User::find(Auth::id());

        if ($admin->role != 'admin') {
            return response()->json(['error' => 'لا تملك صلاحية اعتماد الفواتير'], 403);
        }

        $invoice = invoice::with('invoiceItems.variant')->findOrFail($request->invoiceId);

        if ($invoice->status === 'approved') {
            return response()->json(['error' => 'الفاتورة معتمدة بالفعل'], 400);
        }

        return DB::transaction(function () use ($invoice, $admin) {
            $totalCost = 0;

            foreach ($invoice->invoiceItems as $item) {
                $totalCost += ($item->variant->cost_price * $item->quantity);
            }

            $multiplier = ($invoice->type === 'return') ? -1 : 1;

            $grossProfit = $invoice->total_amount * $multiplier;
            $netProfit = ($invoice->total_amount - $totalCost) * $multiplier;

            settlement::create([
                'branch_id'       => $invoice->branch_id,
                'invoice_id'      => $invoice->id,
                'gross_profit'    => $grossProfit,
                'net_profit'      => $netProfit,
                'approved_by'     => $admin->id,
                'settlement_date' => now(),
            ]);

            $invoice->update(['status' => 'approved']);

            return response()->json([
                'message' => 'تم الاعتماد المالي بنجاح',
                'net_profit' => $netProfit
            ]);
        });
    }
}