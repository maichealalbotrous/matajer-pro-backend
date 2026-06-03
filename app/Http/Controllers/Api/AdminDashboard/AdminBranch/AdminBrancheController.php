<?php

namespace App\Http\Controllers\Api\AdminDashboard\AdminBranch;

use App\Http\Controllers\Controller;
use App\Models\branche;
use App\Models\User;
use Carbon\Carbon;
use Carbon\Constants\Format;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminBrancheController extends Controller
{
/////////////////////////////////////////////////////////////////////////////////
/////{INDEX FUNCTION}////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////////
public function index()
{
    $user =Auth::user();

    if ($user->role != 'super_admin') {
        return response()->json(['message' => 'غير مصرح لك بالقيام بهذه العملية'], 403);
    }

    $branches = branche::withCount('users')->get();

    $formattedBranches = $branches->map(function ($branch) {
        return [
            'id'                => $branch->id,
            'name'              => $branch->name,
            'is_active'         => (bool)$branch->is_active,
            'expiry_date'       => $branch->expiry_date ? Carbon::parse($branch->expiry_date)->format('Y/m/d') : null,
            'subscription_code' => $branch->subscription_code,
            'users_count'       => $branch->users_count, 
        ];
    });

    return response()->json($formattedBranches);
}
    
/////////////////////////////////////////////////////////////////////////////////
/////{DESTROY FUNCTION}////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////////

    
     public function destroy(Request $request)
    {
    $request->validate([
        'id' => 'required'
    ]);

    $user = Auth::user();
    
    if(!$user->role == 'super_admin') {
        return response()->json(['message' => 'غير مصرح لك بحذف الفروع'], 403);
    }
        $branch = branche::find($request->id);
        if (!$branch) return response()->json(['message' => 'الفرع غير موجود'], 404);
        
        $branch->delete();
        return response()->json(['message' => 'تم حذف الفرع']);
    }

 

    /////////////////////////////////////////////////////////////////////////////////
    /////{Active and Deactivate The Branch FUNCTION}////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////////

    public function toggleStatus(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:branches,id'
        ]);

        if (Auth::User()->role !== 'super_admin') {
            return response()->json(['message' => 'غير مصرح لك بالقيام بهذه العملية'], 403);
        }

        $branch = branche::find($request->id);
        $now = Carbon::now();


        if (!$branch->is_active && $branch->expiry_date <= $now) {
            return response()->json([
                'status' => 'error',
                'message' => 'لا يمكن تفعيل فرع منتهي الصلاحية. يرجى تجديد الاشتراك أولا .'
            ], 422);
        }

        $branch->is_active = !$branch->is_active;

        if (!$branch->is_active) {
            if ($branch->expiry_date <= $now) {
                $branch->subscription_code = null; 
            }
        }

        $branch->save();

        return response()->json([
            'status' => 'success',
            'message' => $branch->is_active ? 'تم تفعيل الفرع بنجاح' : 'تم إيقاف تفعيل الفرع بنجاح',
            'data' => [
                'id' => $branch->id,
                'name' => $branch->name,
                'is_active' => (bool)$branch->is_active,
                'expiry_date' => Carbon::parse($branch->expiry_date)->format('Y/m/d'),
                'subscription_code' => $branch->subscription_code
            ]
        ]);
    }


    /////////////////////////////////////////////////////////////////////////////////
    /////{MANUAL RENEW FUNCTION}////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////////

    public function manualRenew(Request $request)
    {
        
        $request->validate([
            'id' => 'required|exists:branches,id',
            'months' => 'required|integer|min:1'
        ]);

        if (Auth::User()->role !== 'super_admin') {
            return response()->json(['message' => 'صلاحية غير كافية'], 403);
        }

        $branch = branche::find($request->id);
        
        $startDate = $branch->expiry_date > now() ? Carbon::parse($branch->expiry_date) : now();
        
        $branch->update([
            'expiry_date' => $startDate->addMonths((int)$request->months),
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'تم تجديد الاشتراك يدوياً بنجاح',
            'expiry_date' => Carbon::parse($branch->expiry_date)->format('Y/m/d'),
        ]);
    }

   /////////////////////////////////////////////////////////////////////////////////
    /////{checkExpirations FUNCTION}////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////////

//     public function checkExpirations()
// {
//       if (Auth::User()->role !== 'super_admin') {
//             return response()->json(['message' => 'غير مصرح لك بالقيام بهذه العملية'], 403);
//         }
        
//     $expiredBranches = branche::where('is_active', true)
//                                ->where('expiry_date', '<', now())
//                                ->update(['is_active' => false]);

//     return response()->json(['message' => 'تم تحديث حالة الفروع المنتهية الصلاحية']);
// }

}