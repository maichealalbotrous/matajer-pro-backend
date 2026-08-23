<?php

namespace App\Http\Controllers\Api\Invoices\Invoices;

use App\Http\Controllers\Api\Discounts\DiscountsController;
use App\Http\Controllers\Controller;
use App\Models\inventory;
use App\Models\invoice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InvoicesController extends Controller
{

public function getEmployeeInvoices(Request $request)
{
    $user = Auth::user();

    $invoices = invoice::with('invoiceItems.variant.product')
        ->where('branch_id', $user->branch_id)
        ->where('user_id', $user->id)
        ->latest() 
        ->get();

    $totalSales = $invoices->where('type', 'sale')->sum('total_amount'); // عدل الحقول بناءً على مسميات قاعدة بياناتك
    $totalReturns = $invoices->where('type', 'return')->count();

    $formattedInvoices = $invoices->map(function($invoice) {
        return [
            'id' => $invoice->id,
            'total_amount' => $invoice->total_amount ?? $invoice->total,
            'notes' => $invoice->notes,
            'created_at' => $invoice->created_at->format('Y-m-d H:i'),
            'items' => $invoice->invoiceItems->map(function($item) {
                return [
                    'variant_id' => $item->variant_id,
                    'quantity' => $item->quantity,
                    'sold_at_price' => $item->sold_at_price,
                    'variant_name' => ($item->variant->product->name ?? $item->variant->variant_name) . ' - ' . ($item->variant->size ?? '')
                ];
            })
        ];
    });

    return response()->json([
        'success' => true,
        'statistics' => [
            'total_sales' => $totalSales,
            'total_returns' => $totalReturns,
        ],
        'data' => $formattedInvoices
    ], 200);
}
////////////////////////////////////////////////////////////////////////////////////////////
/////{storeInvoice function}///////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////////

    public function storeInvoice(Request $request)
{
    $request->validate([
        'notes'                 => 'string|nullable',   
        'items'                 => 'required|array|min:1',
        'items.*.variant_id'    => 'required|exists:product_variants,id',
        'items.*.quantity'      => 'required|integer|min:1',
        'items.*.sold_at_price' => 'required|numeric|min:0',
        'items.*.item_notes'    => 'string|nullable',

    ]);

    $admin = Auth::user();

    try {
        return DB::transaction(function () use ($admin, $request) {
            
            $calculatedTotal = collect($request->items)->sum(function ($item) {
                return $item['quantity'] * $item['sold_at_price'];
            });

            $invoice = invoice::create([
                'branch_id'    => $admin->branch_id,
                'user_id'      => $admin->id,
                'total_amount' => $calculatedTotal,
                'status'       => 'pending',
                'type'         => 'sale',
                'notes'        => $request->notes,
                'invoice_date' => now()->format('Y-m-d'), // ✅ التاريخ الحقيقي وقت إصدار الفاتورة فعلياً
            ]);

            foreach ($request->items as $item) {
            
                $inventory = inventory::where('variant_id', $item['variant_id'])
                    ->where('branch_id', $admin->branch_id)
                    ->first();

                if (!$inventory || $inventory->quantity < $item['quantity']) {
                    throw new \Exception("عذراً، الكمية غير كافية للموديل رقم: " . $item['variant_id']);
                }

                $inventory->decrement('quantity', $item['quantity']);

                $invoice->invoiceItems()->create([
                    'variant_id'    => $item['variant_id'],
                    'sold_at_price' => $item['sold_at_price'],
                    'quantity'      => $item['quantity'],
                    'item_notes'    => $item['item_notes'] ?? null,
                ]);
            }

            return response()->json([
                'message' => 'تم إصدار الفاتورة بنجاح وهي قيد التدقيق',
                'invoice_total' => $calculatedTotal
            ], 201);
        });
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
}

////////////////////////////////////////////////////////////////////////////////////////////
/////{returnInvoice function}///////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////////


public function returnInvoice(Request $request)
{
    $request->validate([
        'original_invoice_id' => 'required|exists:invoices,id',
        'items'               => 'required|array',
        'items.*.variant_id'  => 'required|exists:product_variants,id',
        'items.*.quantity'    => 'required|integer|min:1',
    ]);

    return DB::transaction(function () use ($request) {
        $admin = Auth::user();   ;
        $originalInvoice = Invoice::findOrFail($request->original_invoice_id);

        $returnInvoice = Invoice::create([
            'branch_id'    => $admin->branch_id,
            'user_id'      => $admin->id,
            'total_amount' => 0, 
            'status'       => 'pending',
            'type'         => 'return',
            'notes'        => "مرتجع للفاتورة رقم: " . $originalInvoice->id,  
          'invoice_date' => now()->format('Y-m-d'), // ✅ التاريخ الحقيقي وقت إصدار الفاتورة فعلياً

        ]);

        $totalReturnAmount = 0;

        foreach ($request->items as $item) {
            $originalItem = $originalInvoice->invoiceItems()
                ->where('variant_id', $item['variant_id'])->first();

            if (!$originalItem) throw new \Exception("هذا المنتج غير موجود في الفاتورة الأصلية");

            Inventory::where('variant_id', $item['variant_id'])
                ->where('branch_id', $admin->branch_id)
                ->increment('quantity', $item['quantity']);

            $returnInvoice->invoiceItems()->create([
                'variant_id'    => $item['variant_id'],
                'sold_at_price' => $originalItem->sold_at_price,
                'quantity'      => $item['quantity'],
            ]);

            $totalReturnAmount += ($originalItem->sold_at_price * $item['quantity']);
        }

        $returnInvoice->update(['total_amount' => $totalReturnAmount]);

        return response()->json(['message' => 'تم تسجيل المرتجع بنجاح وهو بانتظار اعتماد المدير']);
    });
}

////////////////////////////////////////////////////////////////////////////////////////////
/////{exchangeInvoice function}///////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////////


public function exchangeInvoice(Request $request)
{
    $request->validate([
        'original_invoice_id' => 'required|exists:invoices,id',
        'returned_items'      => 'required|array',
        'new_items'           => 'required|array',
    ]);

    return DB::transaction(function () use ($request) {
        $admin = Auth::user();
        $originalInvoice = Invoice::findOrFail($request->original_invoice_id);

        $totalReturnedValue = 0;
        foreach ($request->returned_items as $item) {
            $oldItem = $originalInvoice->invoiceItems()
                ->where('variant_id', $item['variant_id'])->first();
            
            if (!$oldItem) throw new \Exception("الصنف المرتجع غير موجود بالفاتورة الأصلية");

            $totalReturnedValue += ($oldItem->sold_at_price * $item['quantity']);

            Inventory::where('variant_id', $item['variant_id'])
                ->where('branch_id', $admin->branch_id)
                ->increment('quantity', $item['quantity']);
        }


        //new invoices and invoisces_Items 
        $totalNewValue = 0;
        foreach ($request->new_items as $item) {
            $totalNewValue += ($item['sold_at_price'] * $item['quantity']);
            
            Inventory::where('variant_id', $item['variant_id'])
                ->where('branch_id', $admin->branch_id)
                ->decrement('quantity', $item['quantity']);
        }

       
        $finalTotal = $totalNewValue - $totalReturnedValue;

        $exchangeInvoice = Invoice::create([
            'branch_id'    => $admin->branch_id,
            'user_id'      => $admin->id,
            'total_amount' => $finalTotal, 
            'status'       => 'pending', 
            'type'         => 'exchange',
            'notes'        => $request->original_invoice_id, 
            'invoice_date' => now()->format('Y-m-d'), // ✅ التاريخ الحقيقي وقت إصدار الفاتورة فعلياً

        ]);

        foreach ($request->new_items as $item) {
            $exchangeInvoice->invoiceItems()->create([
                'variant_id'    => $item['variant_id'],
                'sold_at_price' => $item['sold_at_price'],
                'quantity'      => $item['quantity'],
                'item_notes'    => 'صنف جديد في عملية استبدال'
            ]);
        }

        return response()->json([
            'message' => 'تمت العملية، المبلغ المطلوب من الزبون (أو له): ' . $finalTotal,
            'invoice_id' => $exchangeInvoice->id
        ]);
    });
}
}