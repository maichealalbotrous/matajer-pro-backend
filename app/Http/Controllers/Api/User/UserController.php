<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{


public function addEmployee(Request $request)
{
    $admin = $request->user(); 

    $request->validate([
        'name'     => 'required|string',
        'phone'    => 'required|string|unique:users',
        'password' => 'required|min:6',
    ]);

    $employee = User::create([
        'name'      => $request->name,
        'phone'     => $request->phone,
        'password'  => Hash::make($request->password),
        'branch_id' => $admin->branch_id, 
        'role'      => 'employee',      
    ]);

    return response()->json(['message' => 'تم إضافة الموظف بنجاح', 'employee' => $employee], 201);
}

public function deletEmployee(Request $request, $id)
{

$user = User::where('role', 'employee')->find($id);
if (!$user) {
    return response()->json(['message' => 'الموظف غير موجود'], 404);
}

$user->delete();

return response()->json(['message' => 'تم حذف الموظف بنجاح'], 200);
}


public function updateEmployee(Request $request, $id)
{
$user = User::where('role', 'employee')->find($id);
if (!$user) {
    return response()->json(['message' => 'الموظف غير موجود'], 404);
    
}
$request->validate([
    'name'     => 'sometimes|required|string',
    'phone'    => 'sometimes|required|string|unique:users,phone,' . $id,
    'password' => 'sometimes|required|min:6',
]);
$user->update([
    'name'      => $request->name ?? $user->name,
    'phone'     => $request->phone ?? $user->phone,
    'password'  => $request->password ? Hash::make($request->password) : $user->password,
]);
}}