<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class EmployeeController extends Controller
{


    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    //////{Index Methoud}//////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    
public function getMyEmployees()
{
    $admin = User::find(Auth::id());
    
    $employees = User::where('branch_id', $admin->branch_id)
                     ->where('role', 'employee')
                     ->get();

    return response()->json($employees);
}



    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    //////{store Methoud}//////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    
    public function addEmployee(Request $request)
{
    $request->validate([
        'name'     => 'required|string|max:255',
        'phone'    => 'required|string|unique:users|max:15',
        'password' => 'required|min:6',
    ]);

    $admin = User::find(Auth::id());

if(!$admin || $admin->role !== 'admin') {
    return response()->json([
        'message' => 'فقط المديرين يمكنهم إضافة موظفين.'
    ], 403);
}
    
    if (!$admin->branch_id) {
        return response()->json([
            'message' => 'يجب إنشاء فرع وتفعيله قبل إضافة موظفين.'
        ], 403);
    }

    $employee = User::create([
        'name'      => $request->name,
        'phone'     => $request->phone,
        'password'  => Hash::make($request->password),
        'role'      => 'employee', 
        'branch_id' => $admin->branch_id, 
    ]);

    return response()->json([
        'message' => 'تم إضافة الموظف بنجاح للفرع الخاص بك',
        'employee' => $employee
    ], 201);
}


    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    //////{update Methoud}//////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    

public function updateEmployee(Request $request, $id)
{
    $admin = User::find(Auth::id());

    $employee = User::where('id', $id)
                    ->where('branch_id', $admin->branch_id)
                    ->where('role', 'employee')
                    ->first();

    if (!$employee) {
        return response()->json(['message' => 'الموظف غير موجود أو لا تملك صلاحية تعديله'], 404);
    }

    $request->validate([
        'name'     => 'required|string|max:255',
        'phone'    => 'required|string|max:15|unique:users,phone,' ,
        'password' => 'nullable|min:6',
    ]);

    $employee->name = $request->name;
    $employee->phone = $request->phone;

    if ($request->filled('password')) {
        $employee->password = Hash::make($request->password);
    }

    $employee->save();

    return response()->json([
        'message' => 'تم تحديث بيانات الموظف بنجاح',
        'employee' => $employee
    ]);
}


    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    //////{delete Methoud}//////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    
public function deleteEmployee($id)
{
    $admin = User::find(Auth::id());

    $employee = User::where('id', $id)
                    ->where('branch_id', $admin->branch_id)
                    ->where('role', 'employee')
                    ->first();

    if (!$employee) {
        return response()->json([
            'message' => 'الموظف غير موجود أو لا يتبع لفرعك'
        ], 404);
    }

    $employee->delete();

    return response()->json([
        'message' => 'تم حذف الموظف بنجاح'
    ]);
}


}