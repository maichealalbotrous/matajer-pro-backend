<?php

namespace App\Http\Controllers\Api\AdminDashboard\AdminGlobal_configs;

use App\Http\Controllers\Controller;
use App\Models\branche;
use App\Models\payment_log;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AdminGlobal_configsController extends Controller
{

    // ==========================================
    // 2. (Users/Staff)
    // ==========================================

    public function indexUsers(Request $request)
    {
        $request->validate([
            'branch_id' => 'nullable|exists:branches,id',
        ]); 

        $superAdmin = Auth::user();

        if($superAdmin->role !== 'super_admin') {
            return response()->json(['success' => false, 'message' => 'غير مصرح لك بالوصول إلى هذه البيانات'], 403);
        }

        $query = User::with('branch:id,name');

        if ($request->has('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        return response()->json(['success' => true, 'data' => $query->get()]);
    }

    public function storeUser(Request $request)
    {
        $request->validate([
            'name'      => 'required|string',
            'phone'     => 'required|unique:users,phone',
            'password'  => 'required|min:6',
            'role'      => 'required|in:admin,super_admin,employee',
            'branch_id' => 'required|exists:branches,id',
        ]);
        
        $sueprAdmin = Auth::user();

        if($sueprAdmin->role !== 'super_admin') {
            return response()->json(['success' => false, 'message' => 'غير مصرح لك بالوصول إلى هذه البيانات'], 403);
        }
        
        $user = User::create([
            'name'      => $request->name,
            'phone'     => $request->phone,
            'password'  => Hash::make($request->password),
            'role'      => $request->role,
            'branch_id' => $request->branch_id,
        ]);

        return response()->json(['message' => 'تم إضافة المستخدم بنجاح', 'data' => $user], 201);
    }

    public function updateUser(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:users,id',     
            'phone' => 'nullable|unique:users,phone,' ,
            'password' => 'nullable|min:6',
            'name'   => 'nullable|string',
            'role'   => 'nullable|in:admin,staff',
            'branch_id' => 'nullable|exists:branches,id',

        ]);

        
        $sueprAdmin = Auth::user();

        if($sueprAdmin->role !== 'super_admin') {
            return response()->json(['success' => false, 'message' => 'غير مصرح لك بالوصول إلى هذه البيانات'], 403);
        }
        
        $user = User::findOrFail($request->id);

        $user->update([
                'name' => $request->name ?? $user->name,
                'phone' => $request->phone ?? $user->phone,
                'role' => $request->role ?? $user->role,
                'branch_id' => $request->branch_id ?? $user->branch_id,
            ]);

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
            $user->save();
        }

        return response()->json(['message' => 'تم تحديث بيانات المستخدم']);
    }

    public function destroyUser(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:users,id',
        ]);

        
        $sueprAdmin = Auth::user();

        if($sueprAdmin->role !== 'super_admin') {
            return response()->json(['success' => false, 'message' => 'غير مصرح لك بالوصول إلى هذه البيانات'], 403);
        }

        
        $user = User::findOrFail($request->id);
        $user->delete();
        return response()->json(['message' => 'تم حذف المستخدم بنجاح']);
    }
}