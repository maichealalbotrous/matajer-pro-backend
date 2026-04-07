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
    // 1. إدارة الفروع (Branches)
    // ==========================================

    public function indexBranches()
    {
        // جلب الفروع مع عدد الموظفين في كل فرع كإحصائية سريعة
        $branches = branche::withCount('users')->get();
        return response()->json(['success' => true, 'data' => $branches]);
    }

    public function storeBranch(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:branches,name',
            'location' => 'nullable|string',
        ]);

        $branch = branche::create([
            'name' => $request->name,
            'location' => $request->location,
            'is_active' => true // افتراضياً الفرع نشط عند الإنشاء
        ]);

        return response()->json(['message' => 'تم إنشاء الفرع بنجاح', 'data' => $branch], 201);
    }

    public function toggleBranchStatus($id)
    {
        $branch = branche::findOrFail($id);
        $branch->is_active = !$branch->is_active; // عكس الحالة الحالية
        $branch->save();

        $status = $branch->is_active ? 'تفعيل' : 'تعطيل';
        return response()->json(['message' => "تم $status الفرع بنجاح", 'is_active' => $branch->is_active]);
    }


    public function deleteBranch(Request $request)
    {
        
        $request->validate([
            'branch_id' => 'required|exists:branches,id',
        ]);

        $branch = branche::findOrFail($request->branch_id);

        // تحقق من وجود موظفين مرتبطين بالفرع
        if ($branch->users()->count() > 0) {
            return response()->json(['message' => 'لا يمكن حذف الفرع لأنه يحتوي على موظفين مرتبطين. يرجى نقل الموظفين أولاً.'], 400);
        }

        $branch->delete();
        return response()->json(['message' => 'تم حذف الفرع بنجاح']);
    }
    // ==========================================
    // 2. إدارة المستخدمين (Users/Staff)
    // ==========================================

    public function indexUsers(Request $request)
    {
        // إمكانية عرض موظفي فرع معين أو الكل
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
            'email'     => 'required|email|unique:users,email',
            'password'  => 'required|min:6',
            'role'      => 'required|in:admin,staff',
            'branch_id' => 'required|exists:branches,id',
        ]);

        $user = User::create([
            'name'      => $request->name,
            'email'     => $request->email,
            'password'  => Hash::make($request->password),
            'role'      => $request->role,
            'branch_id' => $request->branch_id,
        ]);

        return response()->json(['message' => 'تم إضافة المستخدم بنجاح', 'data' => $user], 201);
    }

    public function updateUser(Request $request, $id)
    {
        $user = User::findOrFail($id);
        
        $request->validate([
            'email' => 'unique:users,email,' . $id,
            'branch_id' => 'exists:branches,id',
        ]);

        $user->update($request->only(['name', 'email', 'role', 'branch_id']));

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
            $user->save();
        }

        return response()->json(['message' => 'تم تحديث بيانات المستخدم']);
    }

    public function destroyUser($id)
    {
        $user = User::findOrFail($id);
        $user->delete();
        return response()->json(['message' => 'تم حذف المستخدم بنجاح']);
    }
}