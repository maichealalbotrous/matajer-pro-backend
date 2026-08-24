<?php

namespace App\Http\Controllers\Api\Invoices\ApprovedInvoices;

use App\Http\Controllers\Controller;
use App\Models\inventory;
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
    $admin = Auth::user();

    if ($admin->role != 'admin') {
        return response()->json(['error' => 'لا تملك صلاحية عرض الفواتير'], 403);
    }

    // 💡 التحقق من صحة البيانات باستخدام validate
    $validated = $request->validate([
        'period' => 'nullable|in:today,weekly,monthly,yearly',
        'status' => 'nullable|string'
    ]);

    $period = $validated['period'] ?? null;
    $status = $validated['status'] ?? null;

    $query = invoice::with(['user:id,name', 'settlement'])
        ->where('branch_id', $admin->branch_id);

    if ($status) {
        $query->where('status', $status);
    }

    if ($period) {
        switch ($period) {
            case 'today':
                $query->whereDate('created_at', Carbon::today());
                break;
            case 'weekly':
                $start = Carbon::now()->startOfWeek();
                $end = Carbon::now()->endOfWeek();
                $query->whereBetween('created_at', [$start, $end]);
                break;
            case 'monthly':
                $query->whereMonth('created_at', Carbon::now()->month)
                      ->whereYear('created_at', Carbon::now()->year);
                break;
            case 'yearly':
                $query->whereYear('created_at', Carbon::now()->year);
                break;
        }
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
}    ////////////////////////////////////////////////////////////////////////////////////////////
    /////{approve Invoice}///////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////
public function approveInvoice(Request $request)
{
    $admin = Auth::user();

    if ($admin->role != 'admin') {
        return response()->json(['error' => 'لا تملك صلاحية اعتماد الفواتير'], 403);
    }

    // 💡 التحقق من استقبال الـ invoiceId في الطلب كما طلبت
    $validated = $request->validate([
        'invoiceId' => 'required|exists:invoices,id',
    ]);

    $invoice = Invoice::with('invoiceItems.variant')->findOrFail($validated['invoiceId']);

    if ($invoice->status !== 'pending') {
        return response()->json(['error' => 'هذه الفاتورة معتمدة مسبقاً أو غير صالحة للاعتماد'], 400);
    }

    return DB::transaction(function () use ($invoice, $admin) {
        $totalCost = 0;

        foreach ($invoice->invoiceItems as $item) {
            if ($item->variant) {
                $totalCost += ($item->variant->cost_price * $item->quantity);
            }
        }

        $isRefund = false;

        if ($invoice->type === 'exchange' && !empty($invoice->notes)) {
            $originalInvoiceId = $invoice->notes; 
            $originalInvoice = Invoice::with('invoiceItems.variant')->find($originalInvoiceId);

            if ($originalInvoice) {
                $originalCost = 0;
                foreach ($originalInvoice->invoiceItems as $origItem) {
                    if ($origItem->variant) {
                        $originalCost += ($origItem->variant->cost_price * $origItem->quantity);
                    }
                }

                $newItemsValue = $invoice->invoiceItems->sum(fn($i) => $i->sold_at_price * $i->quantity);
                $returnedItemsValue = $originalInvoice->invoiceItems->sum(fn($i) => $i->sold_at_price * $i->quantity);
                $rawDiff = $newItemsValue - $returnedItemsValue;

                if ($rawDiff < 0) {
                    $isRefund = true;
                }

                $totalCost = $totalCost - $originalCost;
            }
        }

        $effectiveAmount = $isRefund ? -abs($invoice->total_amount) : abs($invoice->total_amount);
        $multiplier = ($invoice->type === 'return') ? -1 : 1;

        if ($invoice->type === 'exchange') {
            $grossProfit = $effectiveAmount;
            $netProfit = $effectiveAmount - $totalCost;
        } else {
            $grossProfit = $invoice->total_amount * $multiplier;
            $netProfit = ($invoice->total_amount - $totalCost) * $multiplier;
        }

        Settlement::create([
            'branch_id'       => $invoice->branch_id,
            'invoice_id'      => $invoice->id,
            'gross_profit'    => $grossProfit,
            'net_profit'      => $netProfit,
            'approved_by'     => $admin->id,
            'settlement_date' => now(),
        ]);

        $invoice->update(['status' => 'approved']);

        return response()->json([
            'success' => true,
            'message' => 'تم الاعتماد المالي بنجاح',
            'net_profit' => $netProfit
        ]);
    });
}

////////////////////////////////////////////////////////////////////////////////////////////
    /////{💡 approve ALL pending Invoices - اعتماد الكل}/////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////
 public function approveAllInvoices(Request $request)
{
    $admin = Auth::user();

    if ($admin->role != 'admin') {
        return response()->json(['error' => 'لا تملك صلاحية اعتماد الفواتير'], 403);
    }

    $validated = $request->validate([
        'period' => 'nullable|in:today,weekly,monthly,yearly',
    ]);

    $period = $validated['period'] ?? null;

    $query = Invoice::with('invoiceItems.variant')
        ->where('branch_id', $admin->branch_id)
        ->where('status', 'pending');

    if ($period) {
        switch ($period) {
            case 'today':
                $query->whereDate('created_at', Carbon::today());
                break;
            case 'weekly':
                $start = Carbon::now()->startOfWeek();
                $end = Carbon::now()->endOfWeek();
                $query->whereBetween('created_at', [$start, $end]);
                break;
            case 'monthly':
                $query->whereMonth('created_at', Carbon::now()->month)
                      ->whereYear('created_at', Carbon::now()->year);
                break;
            case 'yearly':
                $query->whereYear('created_at', Carbon::now()->year);
                break;
        }
    }

    $pendingInvoices = $query->get();

    if ($pendingInvoices->isEmpty()) {
        return response()->json(['message' => 'لا توجد فواتير معلقة ضمن هذه الفترة بانتظار الاعتماد المالي حالياً.'], 200);
    }

    return DB::transaction(function () use ($pendingInvoices, $admin) {
        $approvedCount = 0;

        foreach ($pendingInvoices as $invoice) {
            $totalCost = 0;

            foreach ($invoice->invoiceItems as $item) {
                if ($item->variant) {
                    $totalCost += ($item->variant->cost_price * $item->quantity);
                }
            }

            $isRefund = false;

            if ($invoice->type === 'exchange' && !empty($invoice->notes)) {
                $originalInvoiceId = $invoice->notes; 
                $originalInvoice = Invoice::with('invoiceItems.variant')->find($originalInvoiceId);

                if ($originalInvoice) {
                    $originalCost = 0;
                    foreach ($originalInvoice->invoiceItems as $origItem) {
                        if ($origItem->variant) {
                            $originalCost += ($origItem->variant->cost_price * $origItem->quantity);
                        }
                    }

                    $newItemsValue = $invoice->invoiceItems->sum(fn($i) => $i->sold_at_price * $i->quantity);
                    $returnedItemsValue = $originalInvoice->invoiceItems->sum(fn($i) => $i->sold_at_price * $i->quantity);
                    $rawDiff = $newItemsValue - $returnedItemsValue;

                    if ($rawDiff < 0) {
                        $isRefund = true;
                    }

                    $totalCost = $totalCost - $originalCost;
                }
            }

            $effectiveAmount = $isRefund ? -abs($invoice->total_amount) : abs($invoice->total_amount);
            $multiplier = ($invoice->type === 'return') ? -1 : 1;

            if ($invoice->type === 'exchange') {
                $grossProfit = $effectiveAmount;
                $netProfit = $effectiveAmount - $totalCost;
            } else {
                $grossProfit = $invoice->total_amount * $multiplier;
                $netProfit = ($invoice->total_amount - $totalCost) * $multiplier;
            }

            Settlement::create([
                'branch_id'       => $invoice->branch_id,
                'invoice_id'      => $invoice->id,
                'gross_profit'    => $grossProfit,
                'net_profit'      => $netProfit,
                'approved_by'     => $admin->id,
                'settlement_date' => now(),
            ]);

            $invoice->update(['status' => 'approved']);
            $approvedCount++;
        }

        return response()->json([
            'success' => true,
            'message' => "تم الاعتماد الجماعي بنجاح! تم ترحيل واعتماد ({$approvedCount}) فاتورة للفترة المحددة."
        ]);
    });
}
    
  ////////////////////////////////////////////////////////////////////////////////////////////
    /////{revertApproval Invoice}///////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////


    public function revertApproval(Request $request)
{

$request->validate([
        'invoiceId' => 'required|exists:invoices,id',
    ]);

    
    $admin = Auth::user();
    
    if($admin->role != 'admin') {
        return response()->json(['error' => 'لا تملك صلاحية تراجع عن اعتماد الفواتير'], 403);
    }
    
    $invoice = invoice::where('branch_id', $admin->branch_id)->findOrFail($request->invoiceId);

    if ($invoice->status !== 'approved') {
        return response()->json(['error' => 'الفاتورة ليست معتمدة لتتمكن من تراجع عنها'], 400);
    }

    return DB::transaction(function () use ($invoice) {
        settlement::where('invoice_id', $invoice->id)->delete();
        
        $invoice->update(['status' => 'pending']);

        return response()->json(['message' => 'تم التراجع عن الاعتماد المالي بنجاح']);
    });
}


  ////////////////////////////////////////////////////////////////////////////////////////////
    /////{show Invoice}///////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////

public function show(Request $request)
{

$request->validate([
        'id' => 'required|exists:invoices,id',
    ]);

    $admin = Auth::user();

    if($admin->role != 'admin') {
        return response()->json(['error' => 'لا تملك صلاحية عرض تفاصيل الفواتير'], 403);
    }
    
    $invoice = invoice::with(['invoiceItems.variant.product', 'user', 'settlement'])
        ->where('branch_id', $admin->branch_id)
        ->findOrFail($request->id);

    return response()->json([
        'success' => true,
        'data' => $invoice
    ]);
}


  ////////////////////////////////////////////////////////////////////////////////////////////
    /////{delete Invoice}///////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////

    

    public function deleteInvoice(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:invoices,id',
        ]);

        $admin = Auth::user();

        if ($admin->role != 'admin') {
            return response()->json(['error' => 'لا تملك صلاحية حذف الفواتير'], 403);
        }

        $invoice = invoice::with('invoiceItems')->where('branch_id', $admin->branch_id)->findOrFail($request->id);

        return DB::transaction(function () use ($invoice) {
            
            // 1. إعادة الكميات إلى المخزون بناءً على نوع الفاتورة
            foreach ($invoice->invoiceItems as $item) {
                $inventory = inventory::where('branch_id', $invoice->branch_id)
                    ->where('variant_id', $item->variant_id)
                    ->first();

                if ($inventory) {
                    if ($invoice->type === 'sale') {
                        // فاتورة بيع: عند الحذف، نعيد الكمية المباعة للمخزون
                        $inventory->increment('quantity', $item->quantity);
                    } 
                    elseif ($invoice->type === 'return') {
                        // فاتورة مرتجع: عند الحذف، نسحب الكمية المرتجعة من المخزون
                        $inventory->decrement('quantity', $item->quantity);
                    }
                    elseif ($invoice->type === 'exchange') {
                        // فاتورة استبدال: الأصناف الموجودة في فاتورة الاستبدال هي أصناف خرجت للعميل (بيع جديد)، 
                        // فعند الحذف نعيدها للمخزون.
                        $inventory->increment('quantity', $item->quantity);
                    }
                }
            }

            // 💡 إذا كانت فاتورة استبدال مرتبطة بفاتورة قديمة وتم إرجاع أصناف قديمة للمخزون وقتها،
            // يجب علينا أيضاً عكس تأثير الأصناف المرتجعة القديمة!
            if ($invoice->type === 'exchange' && !empty($invoice->notes)) {
                $originalInvoiceId = $invoice->notes;
                $originalInvoice = Invoice::with('invoiceItems')->find($originalInvoiceId);

                if ($originalInvoice) {
                    foreach ($originalInvoice->invoiceItems as $origItem) {
                        $origInventory = Inventory::where('branch_id', $invoice->branch_id)
                            ->where('variant_id', $origItem->variant_id)
                            ->first();

                        if ($origInventory) {
                            // الأصناف القديمة التي دخلت المخزون وقت الاستبدال، عند حذف الاستبدال يجب سحبها مرة أخرى
                            $origInventory->decrement('quantity', $origItem->quantity);
                        }
                    }
                }
            }

            // 2. إذا كانت الفاتورة معتمدة، نحذف التسوية المالية المرتبطة بها
            if ($invoice->status === 'approved') {
                settlement::where('invoice_id', $invoice->id)->delete();
            }

            // 3. حذف عناصر الفاتورة
            $invoice->invoiceItems()->delete();

            // 4. حذف الفاتورة نهائياً
            $invoice->delete();

            return response()->json([
                'success' => true,
                'message' => 'تم حذف الفاتورة، ضبط المخزون بدقة، وتحديث الحسابات المالية بنجاح.'
            ]);
        });
    }
}