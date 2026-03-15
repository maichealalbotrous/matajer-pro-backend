<?php

namespace App\Http\Controllers\Api\AdminDashboard\AdminBranch;

use App\Http\Controllers\Controller;
use App\Models\branche;
use Illuminate\Http\Request;

class AdminBrancheController extends Controller
{
     public function index()
    {
        $branches = branche::all();
        return response()->json($branches);
    }
     public function destroy($id)
    {
        $branch = branche::find($id);
        if (!$branch) return response()->json(['message' => 'الفرع غير موجود'], 404);
        
        $branch->delete();
        return response()->json(['message' => 'تم حذف الفرع']);
    }
}