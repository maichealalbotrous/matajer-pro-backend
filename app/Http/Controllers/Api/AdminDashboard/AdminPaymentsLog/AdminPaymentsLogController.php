<?php

namespace App\Http\Controllers\Api\AdminDashboard\AdminPaymentsLog;

use App\Http\Controllers\Controller;
use App\Models\payment_log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminPaymentsLogController extends Controller
{
    
     public function index(Request $request)
    {

        $request->validate([
            'payment_method' => 'nullable|string',
            'date_from'      => 'nullable|date',
            'date_to'        => 'nullable|date|after_or_equal:date_from',
            'branch_id'      =>'required|exists:branches,id'
            ]);

        $admin = Auth::user();

        if ($admin->role !== 'super_admin') {
            return response()->json(['error' => 'غير مصرح لك بالوصول للبيانات المالية'], 403);
        }

        $query = payment_log::where('branch_id', $request->branch_id);

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->whereBetween('created_at', [$request->date_from, $request->date_to]);
        }

        $logs = $query->latest()->paginate(15);

        return response()->json([
            'success' => true,
            'total_received' => $query->sum('amount_paid'),
            'data' => $logs
        ]);
    }

   
    public function store(Request $request)
    {
        $request->validate([
            'branch_id'      =>'required|exists:branches,id',
            'amount_paid'    => 'required|numeric|min:0.01',
            'currency'       => 'required|string|max:3',
            'payment_method' => 'required|string', 
            'reference_number' => 'nullable|string|unique:payment_logs,reference_number',
        ]);

       
        $admin = Auth::user();

        if ($admin->role !== 'super_admin') {
            return response()->json(['error' => 'غير مصرح لك بالوصول للبيانات المالية'], 403);
        }

        try {
            $payment = payment_log::create([
                'branch_id'      => $request->branch_id,
                'amount_paid'    => $request->amount_paid,
                'currency'       => strtoupper($request->currency),
                'payment_method' => $request->payment_method,
                'reference_number' => $request->reference_number,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم تسجيل الدفعة بنجاح',
                'data'    => $payment
            ], 201);

        } catch (\Exception $e) {
            return response()->json(['error' => 'فشل تسجيل الدفعة: ' . $e->getMessage()], 500);
        }
    }


    public function update(Request $request)
{
    $request->validate([
        'id'             =>'required|exists:payment_logs,id',
        'branch_id'     =>'required|exists:branches,id',
        'amount_paid'    => 'numeric|min:0.01',
        'currency'       => 'string|max:3',
        'payment_method' => 'string',
        'reference_number' => 'nullable|string|unique:payment_logs,reference_number',
   ]);

    $admin = Auth::user();
    $payment = payment_log::where('branch_id', $request->branch_id)->findOrFail($request->id);


        if ($admin->role !== 'super_admin') {
            return response()->json(['error' => 'غير مصرح لك بالوصول للبيانات المالية'], 403);
        }

    try {
        $payment->update([
            'amount_paid'    => $request->amount_paid ?? $payment->amount_paid,
            'currency'       => $request->currency ? strtoupper($request->currency) : $payment->currency,
            'payment_method' => $request->payment_method ?? $payment->payment_method,
            'reference_number' => $request->reference_number ?? $payment->reference_number,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث بيانات الدفع بنجاح',
            'data'    => $payment
        ]);

    } catch (\Exception $e) {
        return response()->json(['error' => 'فشل التحديث: ' . $e->getMessage()], 500);
    }
}


public function destroy(Request $request)
{
    $request->validate(['id' => 'required|exists:payment_logs,id','branch_id' => 'required|exists:branches,id']);
    
   
        $admin = Auth::user();

        if ($admin->role !== 'super_admin') {
            return response()->json(['error' => 'غير مصرح لك بالوصول للبيانات المالية'], 403);
        }

    $payment = payment_log::where('branch_id', $request->branch_id)->findOrFail($request->id);

    try {
        $payment->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف سجل الدفع بنجاح'
        ]);

    } catch (\Exception $e) {
        return response()->json(['error' => 'فشل عملية الحذف: ' . $e->getMessage()], 500);
    }
}
}