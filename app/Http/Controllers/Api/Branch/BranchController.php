<?php

namespace App\Http\Controllers\Api\Branch;

use App\Http\Controllers\Controller;
use App\Models\branche;
use App\Models\subscription_keys;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class BranchController extends Controller
{

   public function index()
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return response()->json([
                'message' => 'هذا الإجراء مخصص فقط لمديري الفروع'
            ], 403);
        }

        $branch = branche::find($user->branch_id);

        if (!$branch) {
            return response()->json([
                'message' => 'لم يتم العثور على فرع مرتبط بحسابك'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $branch
        ]);
    }

    public function store(Request $request)
    {
       $request->validate([
        'name' => 'required|string|max:255',
        'location' => 'nullable|string',
    ]);

    $user = User::find(Auth::id());

    if ($user->role !== 'admin') {
    return response()->json([
        'message' => 'هذا الإجراء مخصص فقط لمديري الفروع'
    ], 403);
     }  

     
    if ($user->branch_id) {
        return response()->json(['message' => 'لديك فرع مسجل بالفعل لهذا الحساب'], 403);
    }

    $branch = branche::create([
        'name' => $request->name,
        'location' => $request->location,
        'subscription_code' => null, 
'expiry_date' => now()->format('Y/m/d'),
        'is_active' => false,        
    ]);

    $user->update([
        'branch_id' => $branch->id
    ]);

    return response()->json([
        'status' => 'success',
        'message' => 'تم إنشاء الفرع وربطه بحسابك. يرجى التفعيل بكود الاشتراك.',
        'branch_id' => $branch->id
    ], 201);
    }

    public function activate(Request $request)
    {
        $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'activation_code' => 'required|string',
        ]);

           $user = Auth::user();

          if ($user->role !== 'admin') {
            return response()->json([
                'message' => 'هذا الإجراء مخصص فقط لمديري الفروع'
            ], 403);
             }  

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
            'message' => 'تم تفعيل الفرع بنجاح! يمكنك الآن التحكم بالفرع واضافة الموظغين واللمنتجات',
            'expiry_date' => $branch->expiry_date
        ]);
    }


   
}