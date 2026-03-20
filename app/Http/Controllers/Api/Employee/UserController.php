<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    
 ////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    //////{Index Methoud}//////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    
    public function index()
    {
      $adminWithBranch = User::with('branch.users')->find(Auth::id());
        if($adminWithBranch->role != 'admin')
            {
                return response()->json(['message' => 'غير مصرح لك بالقيام بهذه العملية'], 403);
            }
            
        $users = $adminWithBranch->branch->users->where('role', 'employee');
            return response()->json($users);
    }

    

     ////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    //////{Add Employee Methoud}//////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    
public function addEmployee(Request $request)
{
      $adminWithBranch = User::with('branch.users')->find(Auth::id());
        if($adminWithBranch->role != 'admin')
            {
                return response()->json(['message' => 'غير مصرح لك بالقيام بهذه العملية'], 403);
            }
    $request->validate([
        'name'     => 'required|string',
        'phone'    => 'required|string|unique:users',
        'password' => 'required|min:6',
    ]);

    $employee = User::create([
        'name'      => $request->name,
        'phone'     => $request->phone,
        'password'  => Hash::make($request->password),
        'branch_id' => $adminWithBranch->branch_id, 
        'role'      => 'employee',      
    ]);

    return response()->json(['message' => 'تم إضافة الموظف بنجاح', 'employee' => $employee], 201);
}

 ////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    //////{delete Employee Methoud}//////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    
public function deletEmployee(Request $request)
{
    $request->validate([
        'id' => 'required|exists:users,id'
    ]);

      $adminWithBranch = User::with('branch.users')->find(Auth::id());

    if ($adminWithBranch->role !== 'admin') {
        return response()->json(['message' => 'غير مصرح لك بالقيام بهذه العملية'], 403);
    }

    $employee = User::where('id', $request->id)
                    ->where('branch_id', $adminWithBranch->branch_id)
                    ->where('role', 'employee')
                    ->first();

    if (!$employee) {
        return response()->json(['message' => 'الموظف غير موجود في فرعك أو غير مصرح لك بحذفه'], 404);
    }

    $employeeName = $employee->name;
    $employee->delete();

    return response()->json([
        'status' => 'success',
        'message' => 'تم حذف الموظف بنجاح: ' . $employeeName
    ], 200);
}


 ////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    //////{Update Employee Methoud}//////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    

public function updateEmployee(Request $request)
{
    $request->validate([
        'id' => 'required|exists:users,id'
    ]);
    
      $adminWithBranch = User::with('branch.users')->find(Auth::id());
    if($adminWithBranch->role != 'admin')
        {
            return response()->json(['message' => 'غير مصرح لك بالقيام بهذه العملية'], 403);
        }

            
$user =$adminWithBranch->branch->users->where('role', 'employee')->find($request->id);

if (!$user) {
    return response()->json(['message' => 'الموظف غير موجود'], 404);
    
}
$request->validate([
    'name'     => 'sometimes|required|string',
    'phone'    => 'sometimes|required|string|unique:users,phone,' . $user->id,
    'password' => 'sometimes|required|min:6',
]);
$user->update([
    'name'      => $request->name ?? $user->name,
    'phone'     => $request->phone ?? $user->phone,
    'password'  => $request->password ? Hash::make($request->password) : $user->password,
]);

return response()->json(['message' => 'تم تحديث بيانات الموظف بنجاح', 'employee' => $user], 200);
}
}