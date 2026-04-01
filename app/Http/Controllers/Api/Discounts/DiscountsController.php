<?php

namespace App\Http\Controllers\Api\Discounts;

use App\Http\Controllers\Controller;
use App\Models\Discount;
use App\Models\product_variant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DiscountsController extends Controller
{

//////////////////////////////////////////////////////////////////////////////////////////////////
/////{index function}/////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////

    public function index()
    {
        $admin = Auth::user();

        if($admin->role != 'admin')
            {
                return response()->json([
                    'success' => false,
                    'message' => 'ليس لديك صلاحية الوصول إلى هذه البيانات'
                ], 403);
            }
        $discounts = Discount::with(['product:id,name'])
            ->where('branch_id', $admin->branch_id)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $discounts
        ]);
    }
//////////////////////////////////////////////////////////////////////////////////////////////////
/////{store function}/////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////

    public function store(Request $request)
    {
        $admin = Auth::user();

        if($admin->role != 'admin')
            {
                return response()->json([
                    'success' => false,
                    'message' => 'ليس لديك صلاحية الوصول إلى هذه البيانات'
                ], 403);
            }

        $validated = $request->validate([
            'product_id'     => 'required|exists:products,id',
            'discount_type'  => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0',
            'start_date'     => 'required|date',
            'end_date'       => 'required|date|after_or_equal:start_date',
        ]);

        $exists = Discount::where('product_id', $request->product_id)
            ->where('branch_id', $admin->branch_id)
            ->where('is_active', true)
            ->where(function($q) use ($request) {
                $q->whereBetween('start_date', [$request->start_date, $request->end_date])
                  ->orWhereBetween('end_date', [$request->start_date, $request->end_date]);
            })->exists();

        if ($exists) {
            return response()->json(['error' => 'يوجد خصم نشط بالفعل لهذا المنتج في نفس الفترة'], 422);
        }

        $discount = Discount::create(array_merge($validated, [
            'branch_id' => $admin->branch_id,
            'is_active' => true
        ]));

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء قاعدة الخصم بنجاح',
            'data'    => $discount
        ], 201);
    }

 //////////////////////////////////////////////////////////////////////////////////////////////////
/////{update function}/////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////

    public function update(Request $request)
    {

     
         $request->validate([
            'id'=>'required|exists:discounts,id'
        ]);
       $admin = Auth::user();

        if($admin->role != 'admin')
            {
                return response()->json([
                    'success' => false,
                    'message' => 'ليس لديك صلاحية الوصول إلى هذه البيانات'
                ], 403);
            }
        $discount = Discount::where('branch_id', $admin->branch_id)->findOrFail($request->id);

        $validated = $request->validate([
            'discount_type'  => 'sometimes|in:percentage,fixed',
            'discount_value' => 'sometimes|numeric|min:0',
            'start_date'     => 'sometimes|date',
            'end_date'       => 'sometimes|date|after_or_equal:start_date',
            'is_active'      => 'sometimes|boolean'
        ]);

        $discount->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث الخصم بنجاح',
            'data'    => $discount
        ]);
    }
//////////////////////////////////////////////////////////////////////////////////////////////////
/////{destroy function}/////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////

    public function destroy(Request $request)
    {
        $request->validate([
            'id'=>'required|exists:discounts,id'
        ]);
        
        $admin = Auth::user();

        if($admin->role != 'admin')
            {
                return response()->json([
                    'success' => false,
                    'message' => 'ليس لديك صلاحية الوصول إلى هذه البيانات'
                ], 403);
            }
        $discount = Discount::where('branch_id', $admin->branch_id)->findOrFail($request->id);
        $discount->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف قاعدة الخصم'
        ]);
    }

//////////////////////////////////////////////////////////////////////////////////////////////////
/////{applyDiscount function}/////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////

    public static function applyDiscount($variantId, $branchId)
    {
         $admin = Auth::user();

        if($admin->role != 'admin')
            {
                return response()->json([
                    'success' => false,
                    'message' => 'ليس لديك صلاحية الوصول إلى هذه البيانات'
                ], 403);
            }
            
        $variant = product_variant::findOrFail($variantId);
        $originalPrice = $variant->selling_price;
        $now = Carbon::now()->toDateString();

        $discount = Discount::where('product_id', $variant->product_id)
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $now)
            ->whereDate('end_date', '>=', $now)
            ->first();

        if (!$discount) {
            return $originalPrice;
        }

        if ($discount->discount_type === 'percentage') {
            $discountAmount = $originalPrice * ($discount->discount_value / 100);
        } else {
            $discountAmount = $discount->discount_value;
        }

        return max(0, $originalPrice - $discountAmount);
    }
}