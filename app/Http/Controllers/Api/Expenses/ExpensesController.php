<?php

namespace App\Http\Controllers\Api\Expenses;

use App\Http\Controllers\Controller;
use App\Models\expense;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExpensesController extends Controller
{

   public function index()
    {
        $admin = Auth::user();
        if($admin->role !== 'admin') {
            return response()->json(['error' => 'غير مصرح لك يالقيام بهذه العملية'], 403);
        }
        
        $expenses = expense::where('branch_id', )
                           ->orderBy('created_at', 'desc')
                           ->get();
                           
        return response()->json($expenses);
    }
    
    public function store(Request $request)
    {
        $request->validate([
            'type'       => 'required|string',
            'amount'     => 'required|numeric|min:0',
            'notes'      => 'nullable|string',
        ]);

          $admin = Auth::user();
          
        if($admin->role !== 'admin') {
            return response()->json(['error' => 'غير مصرح لك يالقيام بهذه العملية'], 403);
        };

      

        $expense = Expense::create([
            'branch_id'  => $admin->branch_id,
            'type'       => $request->type,
            'amount'     => $request->amount,
            'notes'      => $request->notes,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل المصروف بنجاح',
            'data'    => $expense
        ], 201);
    }

 public function update(Request $request)
    {
        $request->validate([
            'id'     => 'required|exists:expenses,id',
            'type'   => 'required|string',
            'amount' => 'required|numeric|min:0',
            'notes'  => 'nullable|string',
        ]);

        $admin = Auth::user();
        $expense = expense::findOrFail($request->id);

        if ($admin->role !== 'admin' || $expense->branch_id !== $admin->branch_id) {
            return response()->json(['error' => 'غير مصرح لك بتعديل هذا المصروف'], 403);
        }

        $expense->update([
            'type'   => $request->type,
            'amount' => $request->amount,
            'notes'  => $request->notes,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث المصروف بنجاح',
            'data'    => $expense
        ]);
    }

    public function destroy(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:expenses,id',
        ]);

        $admin = Auth::user();
        
        
        ;
        $expense = expense::findOrFail($request->id);

        if ($admin->role !== 'admin' || $expense->branch_id !== $admin->branch_id) {
            return response()->json(['error' => 'غير مصرح لك بحذف هذا المصروف'], 403);
        }

        $expense->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف المصروف بنجاح'
        ]);
    }
}