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
        $admin = Auth::user();

        if ($admin->role != 'admin') {
            return response()->json(['error' => 'لا تملك صلاحية اعتماد الفواتير'], 403);
        }

        $pendingInvoices = invoice::with('invoiceItems.variant')
            ->where('branch_id', $admin->branch_id)
            ->where('status', 'pending')
            ->get();

        if ($pendingInvoices->isEmpty()) {
            return response()->json(['message' => 'لا توجد فواتير معلقة بانتظار الاعتماد المالي حالياً.'], 200);
        }

        return DB::transaction(function () use ($pendingInvoices, $admin) {
            $approvedCount = 0;
            $netProfit = 0; // لتعريف المتغير وتجنب أي خطأ في الـ scope

            foreach ($pendingInvoices as $invoice) {
                $totalCost = 0;

                // حساب تكلفة الأصناف الجديدة
                foreach ($invoice->invoiceItems as $item) {
                    if ($item->variant) {
                        $totalCost += ($item->variant->cost_price * $item->quantity);
                    }
                }

                // 💡 معالجة فواتير الاستبدال (exchange) وحساب صافي التكلفة بدقة
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
                        // صافي التكلفة = تكلفة الأصناف الجديدة - تكلفة الأصناف المرتجعة القديمة
                        $totalCost = $totalCost - $originalCost;
                    }
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
                $approvedCount++;
            }

            return response()->json([
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

        $pendingInvoices = invoice::with('invoiceItems.variant')
            ->where('branch_id', $admin->branch_id)
            ->where('status', 'pending')
            ->get();

        if ($pendingInvoices->isEmpty()) {
            return response()->json(['message' => 'لا توجد فواتير معلقة بانتظار الاعتماد المالي حالياً.'], 200);
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

                // 💡 معالجة فواتير الاستبدال (exchange) وحساب صافي التكلفة بدقة في الاعتماد الجماعي أيضاً
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
                        $totalCost = $totalCost - $originalCost;
                    }
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
                $approvedCount++;
            }

            return response()->json([
                'success' => true,
                'message' => "تم الاعتماد الجماعي بنجاح! تم ترحيل واحتساب أرباح ({$approvedCount}) فاتورة بنجاح."
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