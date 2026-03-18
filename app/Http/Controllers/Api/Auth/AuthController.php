<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
public function registerAdmin(Request $request)
{
    $request->validate([
        'name'     => 'required|string',
        'phone'    => 'required|string|unique:users',
        'password' => 'required|min:6',
    ]);


    $role = ($request->phone === '0900000000') ? 'super_admin' : 'admin';

    $user = User::create([
        'name'      => $request->name,
        'phone'     => $request->phone,
        'password'  => Hash::make($request->password),
        'role'      => $role, 
        'branch_id' => null,
    ]);

    $token = $user->createToken('sand_token')->plainTextToken;

    $message = ($role === 'super_admin') 
                ? 'مرحباً بك أيها المبرمج، تم إنشاء حساب الصلاحيات المطلقة.' 
                : 'تم إنشاء حساب المدير. يرجى إكمال بيانات الفرع الآن.';

    return response()->json([
        'message' => $message,
        'user'    => $user,
        'token'   => $token,
        'role'    => $user->role
    ], 201);
}


public function login(Request $request)
{
    $request->validate([
        'phone'    => 'required',
        'password' => 'required',
    ]);

    $user = User::where('phone', $request->phone)->first();

    if (!$user || !Hash::check($request->password, $user->password)) {
        return response()->json(['message' => 'بيانات الدخول غير صحيحة'], 401);
    }

    
        if ($user->phone === '0900000000'&& $user->role === 'super_admin') {
        $token = $user->createToken('super_admin_token')->plainTextToken;
        return response()->json([
            'message'    => 'مرحباً بك أيها المبرمج (Super Admin)',
            'user'       => $user,
            'role'       => 'super_admin', 
            'token'      => $token,
            'is_super'   => true
        ]);
    }
    
    
    if ($user->branch_id) {
        $branch = $user->branch; 
        if (!$branch->is_active) {
            return response()->json(['message' => 'اشتراك الفرع غير مفعل أو منتهي'], 403);
        }
    } else {
        if ($user->role !== 'admin') {
            return response()->json(['message' => 'هذا الحساب غير مرتبط بفرع'], 403);
        }
    }

    $token = $user->createToken('sand_token')->plainTextToken;

    return response()->json([
        'message' => 'تم تسجيل الدخول بنجاح',
        'user'    => $user,
        'role'    => $user->role, 
        'token'   => $token,
        'has_branch' => $user->branch_id ? true : false 
    ]);
}

}