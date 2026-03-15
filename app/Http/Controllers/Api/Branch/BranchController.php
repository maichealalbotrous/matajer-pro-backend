<?php

namespace App\Http\Controllers\Api\Branch;

use App\Http\Controllers\Controller;
use App\Models\branche;
use App\Models\subscription_keys;
use Illuminate\Http\Request;

class BranchController extends Controller
{

   
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'nullable|string',
        ]);

        $branch = branche::create([
            'name' => $request->name,
            'location' => $request->location,
            'subscription_code' => null, 
            'expiry_date' => now(),      
            'is_active' => false,        
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'تم تسجيل الفرع بنجاح، يرجى الانتقال لخطوة التفعيل',
            'branch_id' => $branch->id
        ], 201);
    }

    public function activate(Request $request)
    {
        $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'activation_code' => 'required|string',
        ]);

        $key = subscription_keys::where('license_key', $request->activation_code)
                               ->where('is_used', false)
                               ->first();

        if (!$key) {
            return response()->json([
                'status' => 'error',
                'message' => 'كود التفعيل غير صحيح أو تم استخدامه مسبقاً'
            ], 422);
        }

        $branch = branche::find($request->branch_id);
        $branch->update([
            'subscription_code' => $key->license_key,
            'expiry_date' => now()->addMonths($key->duration_months),
            'is_active' => true,
        ]);

        $key->update([
            'is_used' => true,
            'branch_id' => $branch->id,
            'activated_at' => now(),
            'deactivated_at' => now()->addMonths($key->duration_months),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'تم تفعيل الفرع بنجاح! يمكنك الآن إنشاء حساب المدير العام لهذا الفرع.',
            'expiry_date' => $branch->expiry_date
        ]);
    }


   
}