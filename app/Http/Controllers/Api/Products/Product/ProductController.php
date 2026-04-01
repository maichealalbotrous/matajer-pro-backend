<?php

namespace App\Http\Controllers\Api\Products\Product;

use App\Http\Controllers\Controller;
use App\Models\product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{

 ////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    //////{Index Methoud}//////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    public function index()
{
    $admin = Auth::user();

    if ($admin->role != 'admin') {
        return response()->json(['error' => 'غير مسموح لك للقيام بهذه العملية'], 403);
    }

    $products = product::with(['variants.variantImages', 'variants.inventories'])
        ->where('branch_id', $admin->branch_id)
        ->get();

    $formattedData = $products->map(function ($product) {
        $groupedByColor = $product->variants->groupBy(function ($variant) {
            return $variant->color ?? 'No Color';
        });

        return [
            'product_id'   => $product->id,
            'product_name' => $product->model_name,
            'branch_id'    => $product->branch_id,
            'variants_groups' => $groupedByColor->map(function ($variants, $colorName) use ($product) {
                $firstVariant = $variants->first(); 

                return [
                    'color'        => $colorName,
                    'variant_name' => $firstVariant->variant_name ?? $product->model_name,
                    'cost_price'   => $firstVariant->cost_price,
                    'sku'          => explode('-', $firstVariant->sku)[0], 
                    'sizes'        => $variants->map(function ($v) {
                        return [
                            'id'       => $v->id,
                            'size'     => $v->size,
                            'quantity' => $v->inventories->sum('quantity'),
                        ];
                    })->values(),
                    'images'       => $variants->flatMap->variantImages->pluck('image_path')->unique()->map(function ($path) {
                        return asset('storage/' . $path);
                    })->values(),
                ];
            })->values(),
        ];
    });

    return response()->json($formattedData);
}


     ////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    //////{store Methoud}//////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    
    public function store(Request $request)
    {
        $request->validate([
            'model_name'   => 'required|string|max:255',
            'model_number' => 'nullable|string',
            'image'        => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

       $admin = Auth::user();
        if ( $admin->role != 'admin') {
            return response()->json(['error' => 'غير مسموح لك للقيام بهذه العملية'], 403);
        }
        try {
            $mainImagePath = null;
            if ($request->hasFile('image')) {
                $mainImagePath = $request->file('image')->store('products/main', 'public');
            }

            $product = product::create([
                'model_name'   => $request->model_name,
                'model_number' => $request->model_number,
                'image'        => $mainImagePath,
                'branch_id'    => $admin->branch_id,
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => 'تم إنشاء المنتج بنجاح',
                'product' => $product
            ], 201);

        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

     ////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    //////{update Methoud}//////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    
    public function update(Request $request)
    {
        $request->validate([
            'id'           => 'required|integer|exists:products,id',
            'model_name'   => 'required|string|max:255',
            'model_number' => 'nullable|string',
            'image'        => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $product = product::findOrFail($request->id);
        
        $admin = Auth::user();
        
        if ( $admin->role != 'admin') {
            return response()->json(['error' => 'غير مسموح لك للقيام بهذه العملية'], 403);
        }
        
        try {
            if ($request->hasFile('image')) {
                if ($product->image) {
                    Storage::disk('public')->delete($product->image);
                }
                $product->image = $request->file('image')->store('products/main', 'public');
            }

            $product->update([
                'model_name'   => $request->model_name,
                'model_number' => $request->model_number,
                'image'        => $product->image,
            ]);

            return response()->json(['message' => 'تم تحديث بيانات المنتج بنجاح', 'product' => $product]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

     ////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    //////{destroy Methoud}//////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    
   public function destroy(Request $request)
{
    $request->validate(['id' => 'required|exists:products,id']);

    $product = product::findOrFail($request->id);
    $admin = User::find(Auth::id())   ;

    if ($admin->role != 'admin' || $product->branch_id != $admin->branch_id) {
        return response()->json(['error' => 'غير مصرح لك'], 403);
    }

    try {
        $product->delete();

        return response()->json(['message' => 'تم حذف المنتج وكافة ملحقاته بنجاح']);
    } catch (\Exception $e) {
        return response()->json(['error' => 'فشل الحذف: ' . $e->getMessage()], 500);
    }
}
}