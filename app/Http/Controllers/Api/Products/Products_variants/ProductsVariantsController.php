<?php

namespace App\Http\Controllers\Api\Products\Products_variants;

use App\Http\Controllers\Controller;
use App\Models\inventory;
use App\Models\product;
use App\Models\product_variant;
use App\Models\User;
use App\Models\variant_images;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductsVariantsController extends Controller
{


 ////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    //////{Index Methoud}//////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    
public function index()
{
    $admin = User::find(Auth::id());

    if ($admin->role != 'admin') {
        return response()->json(['error' => 'غير مسموح لك'], 403);
    }

    $variants = product_variant::with(['variantImages', 'inventories', 'product:id,model_name'])
        ->whereRelation('product', 'branch_id', $admin->branch_id)
        ->get();

    $grouped = $variants->groupBy(function ($item) {
        return $item->product_id . '-' . $item->color;
    });

    $result = $grouped->map(function ($group) {
        $first = $group->first(); 

        return [
            'id'           => $first->id,
            'product'      => $first->product->model_name ?? 'N/A',
            'variant_name' => $first->variant_name,
            'sku'          => $first->sku, 
            'color'        => $first->color,
            'cost_price'   => $first->cost_price,
            'size'         => $group->map(function ($v) {
                return [
                    'id'       =>$v->id,
                    'size'     => $v->size,
                    'quantity' => $v->inventories->sum('quantity')
                ];
            })->values(),
            'images'       => $first->variantImages->map(function ($img) {
                return asset('storage/' . $img->image_path);
            }),
        ];
    })->values(); 

    return response()->json($result);
}
 

 ////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    //////{store Methoud}//////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    
public function addVariants(Request $request)
{
    $request->validate([
        'id'                               => 'required|exists:products,id',
        'colors_groups'                    => 'required|array|min:1',
        'colors_groups.*.color'            => 'nullable|string',
        'colors_groups.*.variant_name'     => 'nullable|string', 
        'colors_groups.*.cost_price'       => 'required|numeric',
        'colors_groups.*.sku'              => 'required|string', 
        'colors_groups.*.images'           => 'nullable|array',
        'colors_groups.*.images.*'         => 'image|mimes:jpeg,png,jpg|max:2048',
        
        'colors_groups.*.sizes'            => 'required|array|min:1',
        'colors_groups.*.sizes.*.size'     => 'nullable|string',
        'colors_groups.*.sizes.*.quantity' => 'required|integer|min:0',
    ]);

    $product = product::findOrFail($request->id);
    $admin = User::find(Auth::id());

    if ($admin->role != 'admin' || $product->branch_id != $admin->branch_id) {
        return response()->json(['error' => 'غير مصرح لك بالإدارة لهذا الفرع'], 403);
    }

    try {
        return DB::transaction(function () use ($admin, $request, $product) {
            
            foreach ($request->colors_groups as $colorIndex => $colorData) {
                
                foreach ($colorData['sizes'] as $sizeIndex => $sizeData) {
                    
                    $uniqueSizeSku = $colorData['sku'] . '-' . ($sizeData['size'] ?? $sizeIndex);

                    $variant = $product->variants()->create([
                        'variant_name' => $colorData['variant_name'] ?? $product->model_name,
                        'sku'          => $uniqueSizeSku, 
                        'color'        => $colorData['color'] ?? null,
                        'size'         => $sizeData['size'] ?? null,
                        'cost_price'   => $colorData['cost_price'],
                    ]);

                    if ($request->hasFile("colors_groups.$colorIndex.images")) {
                        foreach ($request->file("colors_groups.$colorIndex.images") as $imgFile) {
                            $path = $imgFile->store('products/variants/gallery', 'public');
                            $variant->variantImages()->create(['image_path' => $path]);
                        }
                    }

                    inventory::create([
                        'branch_id'  => $admin->branch_id,
                        'variant_id' => $variant->id,
                        'quantity'   => $sizeData['quantity'],
                    ]);
                }
            }

            return response()->json(['message' => 'تم إضافة الموديلات بنجاح'], 201);
        });
    } catch (\Exception $e) {
        return response()->json(['error' => 'حدث خطأ: ' . $e->getMessage()], 500);
    }
}

 ////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    //////{update Methoud}//////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    
     public function updateVariant(Request $request)
    {
        $request->validate([
            'id'                               => 'required|exists:products,id',
            'colors_groups'                    => 'required|array|min:1',
            'colors_groups.*.color'            => 'nullable|string',
            'colors_groups.*.variant_name'     => 'nullable|string', 
            'colors_groups.*.cost_price'       => 'required|numeric',
            'colors_groups.*.sku'              => 'required|string', 
            'colors_groups.*.images'           => 'nullable|array',
            'colors_groups.*.images.*'         => 'image|mimes:jpeg,png,jpg|max:2048',
            
            'colors_groups.*.sizes'            => 'required|array|min:1',
            'colors_groups.*.sizes.*.id'       => 'required|exists:product_variants,id', 
            'colors_groups.*.sizes.*.size'     => 'nullable|string',
            'colors_groups.*.sizes.*.quantity' => 'nullable|integer|min:0',
        ]);

        $product = product::findOrFail($request->id);
        
        $admin = User::find(Auth::id());

        if ($admin->role != 'admin' || $product->branch_id != $admin->branch_id) {
            return response()->json(['error' => 'غير مصرح لك بالإدارة'], 403);
        }

        try {
            return DB::transaction(function () use ($admin, $request, $product) {
                
                foreach ($request->colors_groups as $colorIndex => $colorData) {
                    
                    foreach ($colorData['sizes'] as $sizeIndex => $sizeData) {
                        
                        $variant = product_variant::findOrFail($sizeData['id']);

                        $uniqueSku = $colorData['sku'] . '-' . ($sizeData['size'] ?? $sizeIndex);

                       $variant->update([
                        'variant_name' => $colorData['variant_name'] ?? ($variant->variant_name ?? $product->model_name),
                        'sku'          => $uniqueSku,
                        'color'        => $colorData['color'] ?? $variant->color,    
                        'size'         => $sizeData['size'] ?? $variant->size, 
                        'cost_price'   => $colorData['cost_price'] ?? $variant->cost_price,
                    ]);

                  if (array_key_exists('quantity', $sizeData)) {
                        inventory::where('variant_id', $variant->id)
                            ->where('branch_id', $admin->branch_id)
                            ->update([
                                'quantity' => $sizeData['quantity'] 
                            ]);
                    }
                                            
                        if ($request->hasFile("colors_groups.$colorIndex.images")) {
                            foreach ($request->file("colors_groups.$colorIndex.images") as $imgFile) {
                                $path = $imgFile->store('products/variants/gallery', 'public');
                                $variant->variantImages()->create(['image_path' => $path]);
                            }
                        }
                    }
                }

                return response()->json(['message' => 'تم تحديث الموديلات بنجاح'], 200);
            });
        } catch (\Exception $e) {
            return response()->json(['error' => 'خطأ في التحديث: ' . $e->getMessage()], 500);
        }
    }

     ////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    //////{destroy Methoud}//////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    
    public function destroyVariant(Request $request)
    {
        $request->validate(['id' => 'required|exists:product_variants,id']);
        
        $variant = product_variant::with(['variantImages', 'product'])->findOrFail($request->id);
        
        $admin = User::find(Auth::id());
        
        if ($admin->role != 'admin' || $variant->product->branch_id != $admin->branch_id) {
            return response()->json(['error' => 'غير مسموح لك'], 403);
        }

        try {
            foreach ($variant->variantImages as $image) {
                Storage::disk('public')->delete($image->image_path);
            }
            
            $variant->delete();
            return response()->json(['message' => 'تم حذف الموديل بالكامل']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

 ////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    //////{destroy images Methoud}//////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    
    public function destroyVariantImage(Request $request)
{
    $request->validate([
        'image_id' => 'required|exists:variant_images,id',
    ]);

    $image = variant_images::with('productVariant.product')->findOrFail($request->image_id);

    $branchId = $image->productVariant->product->branch_id;
    
    $admin = User::find(Auth::id());
    
    if ($admin->role != 'admin' || $branchId != $admin->branch_id) {
        return response()->json(['error' => 'غير مسموح لك بالقيام بهذه العملية'], 403);
    }

    try {
        if (Storage::disk('public')->exists($image->image_path)) {
            Storage::disk('public')->delete($image->image_path);
        }

        $image->delete();

        return response()->json(['message' => 'تم حذف الصورة بنجاح']);
    } catch (\Exception $e) {
        return response()->json(['error' => 'فشل حذف الصورة: ' . $e->getMessage()], 500);
    }
}
}