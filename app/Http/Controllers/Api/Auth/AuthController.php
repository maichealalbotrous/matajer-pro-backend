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
        'name'      => 'required|string',
        'phone'     => 'required|string|unique:users',
        'password'  => 'required|min:6',
        'branch_id' => 'required|exists:branches,id'
    ]);

    $existsAdmin = User::where('branch_id', $request->branch_id)
                       ->where('role', 'admin')
                       ->exists();

    if ($existsAdmin) {
        return response()->json([
            'message' => 'هذا الفرع لديه مدير بالفعل. لا يمكن إنشاء مدير آخر.'
        ], 403);
    }

    $user = User::create([
        'name'      => $request->name,
        'phone'     => $request->phone,
        'password'  => Hash::make($request->password),
        'branch_id' => $request->branch_id,
        'role'      => 'admin', 
    ]);

    return response()->json(['message' => 'تم تسجيل المدير بنجاح', 'user' => $user], 201);
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

    $branch = $user->branch; 
    if (!$branch->is_active) {
        return response()->json(['message' => 'اشتراك الفرع غير مفعل أو منتهي'], 403);
    }

    $token = $user->createToken('sand_token')->plainTextToken;

    return response()->json([
        'message' => 'تم تسجيل الدخول بنجاح',
        'user'    => $user,
        'role'    => $user->role, 
        'token'   => $token
    ]);
}

}