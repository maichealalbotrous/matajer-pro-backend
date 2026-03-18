<?php

namespace App\Http\Controllers\Api\AdminDashboard\AdminSubscription_keys;

use App\Http\Controllers\Controller;
use App\Models\subscription_keys;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Pest\Support\Str;

class AdminSubscription_keysController extends Controller
{


    public function index()
    {
        if(Auth::user()->phone != '0900000000' || Auth::user()->role != 'super_admin'){
            return response()->json(['message' => 'this section is for admin only , هذا القسم خاص بالمدير فقط (المبرمج)'], 401);
        }
        return response()->json(subscription_keys::all());
    }
    
    
    public function generate(Request $request)
    {
        if(Auth::user()->phone != '0900000000' || Auth::user()->role != 'super_admin'){
            return response()->json(['message' => 'this section is for admin only , هذا القسم خاص بالمدير فقط (المبرمج)'], 401);
        }
        
        $request->validate([
            'count' => 'required|integer|min:1|max:100', 
            'duration_months' => 'required|integer|min:1|max:24', 
        ]);

        $generatedKeys = [];

        for ($i = 0; $i < $request->count; $i++) {
            do {
                $keyText = 'SAND-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4));
            } while (subscription_keys::where('license_key', $keyText)->exists());
            
            $key = subscription_keys::create([
                'license_key' => $keyText,
                'duration_months' => $request->duration_months,
                'is_used' => false,
            ]);

            $generatedKeys[] = $key;
        }

        return response()->json([
            'status' => 'success',
            'message' => 'تم توليد الأكواد بنجاح',
            'keys' => $generatedKeys
        ], 201);
    }

}