<?php

namespace App\Http\Controllers\Api\Products\Products_variants;

use App\Http\Controllers\Api\Discounts\DiscountsController;
use App\Http\Controllers\Controller;
use App\Models\Discount;
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
    $admin = Auth::user();

    $variants = product_variant::with(['product:id,model_name', 'variantImages', 'inventories'])
        ->whereRelation('product', 'branch_id', $admin->branch_id)
        ->get();

    $result = [];

    foreach ($variants as $v) {
        $key = $v->product_id . '_' . $v->color . '_' . $v->variant_name;

        $discountedPrice = DiscountsController::applyDiscount($v->id, $admin->branch_id);

        if (!isset($result[$key])) {
            $result[$key] = [
                'id'            => $v->id,
                'product'       => $v->product->model_name ?? 'N/A',
                'variant_name'  => $v->variant_name,
                'color'         => $v->color,
                'cost_price'    => $v->cost_price,
                'selling_price' => $discountedPrice, 
                'sizes'         => [],
                'images'        => []
            ];

            foreach ($v->variantImages as $img) {
                $result[$key]['images'][] = asset('storage/' . $img->image_path);
            }
        }

        $quantity = 0;
        foreach ($v->inventories as $inv) {
            if ($inv->branch_id == $admin->branch_id) {
                $quantity += $inv->quantity;
            }
        }

        $result[$key]['sizes'][] = [
            'id'            => $v->id,
            'size'          => $v->size,
            'quantity'      => $quantity,
            'original_price' => $v->selling_price, 
            'selling_price' => $discountedPrice,  
        ];
    }

    return response()->json(array_values($result));
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
        'colors_groups.*.selling_price' => 'required|numeric|min:0', 
        'colors_groups.*.sku'              => 'required|string', 
        'colors_groups.*.images'           => 'nullable|array',
        'colors_groups.*.images.*'         => 'image|mimes:jpeg,png,jpg|max:2048',
        
        'colors_groups.*.sizes'            => 'required|array|min:1',
        'colors_groups.*.sizes.*.size'     => 'nullable|string',
        'colors_groups.*.sizes.*.quantity' => 'required|integer|min:0',
    ]);

    $product = product::findOrFail($request->id);
    $admin = Auth::user();

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
                        'selling_price'=> $colorData['selling_price'], 
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
            'colors_groups.*.cost_price'       => 'nullable|numeric',
            'colors_groups.*.selling_price' => 'nullable|numeric|min:0', 
            'colors_groups.*.sku'              => 'nullable|string', 
            'colors_groups.*.images'           => 'nullable|array',
            'colors_groups.*.images.*'         => 'image|mimes:jpeg,png,jpg|max:2048',
            
            'colors_groups.*.sizes'            => 'required|array|min:1',
            'colors_groups.*.sizes.*.id'       => 'required|exists:product_variants,id', 
            'colors_groups.*.sizes.*.size'     => 'nullable|string',
            'colors_groups.*.sizes.*.quantity' => 'nullable|integer|min:0',
        ]);

        $product = product::findOrFail($request->id);
        
        $admin = Auth::user();

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
                        'selling_price'=> $colorData['selling_price'] ?? $variant->selling_price, 
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
        
        $admin = Auth::user();
        
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
    
    $admin = Auth::user();
    
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



    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    //////{filter Methoud}//////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////


public function filter(Request $request)
{
    $admin = Auth::user();
    $searchTerm = $request->input('search');
    $now = now()->toDateString();

    $activeDiscounts =Discount::where('branch_id', $admin->branch_id)
        ->where('is_active', true)
        ->whereDate('start_date', '<=', $now)
        ->whereDate('end_date', '>=', $now)
        ->get()
        ->keyBy('product_id');

    $query = product_variant::with(['product:id,model_name', 'variantImages'])
        ->withSum(['inventories as total_quantity' => function($q) use ($admin) {
            $q->where('branch_id', $admin->branch_id);
        }], 'quantity')
        ->whereRelation('product', 'branch_id', $admin->branch_id);

    if ($searchTerm) {
        $query->where(function ($q) use ($searchTerm) {
            $q->where('variant_name', 'LIKE', "%{$searchTerm}%")
              ->orWhere('sku', 'LIKE', "%{$searchTerm}%")
              ->orWhere('color', 'LIKE', "%{$searchTerm}%")
              ->orWhere('size', 'LIKE', "%{$searchTerm}%")
              ->orWhereHas('product', function ($pq) use ($searchTerm) {
                  $pq->where('model_name', 'LIKE', "%{$searchTerm}%");
              });
        });
    }

    $variants = $query->get();

    $result = [];

    foreach ($variants as $v) {
        $key = $v->product_id . '_' . $v->color . '_' . $v->variant_name;

        $discount = $activeDiscounts->get($v->product_id);
        $originalPrice = (float) $v->selling_price;
        $discountedPrice = $originalPrice;

        if ($discount) {
            $discountAmount = ($discount->discount_type === 'percentage') 
                ? $originalPrice * ($discount->discount_value / 100) 
                : $discount->discount_value;
            $discountedPrice = round(max(0, $originalPrice - $discountAmount), 2);
        }

        if (!isset($result[$key])) {
            $result[$key] = [
                'product_id'    => $v->product_id,
                'product_name'  => $v->product->model_name ?? 'N/A',
                'variant_name'  => $v->variant_name,
                'color'         => $v->color,
                'cost_price'    => $v->cost_price,
                'selling_price' => $discountedPrice, 
                'sizes'         => [],
                'images'        => []
            ];

            foreach ($v->variantImages as $img) {
                $result[$key]['images'][] = asset('storage/' . $img->image_path);
            }
        }

        $result[$key]['sizes'][] = [
            'id'             => $v->id,
            'size'           => $v->size,
            'quantity'       => $v->total_quantity ?? 0,
            'original_price' => $originalPrice, 
            'selling_price'  => $discountedPrice,  
        ];
    }

    return response()->json([
        'success' => true,
        'count'   => count($result),
        'data'    => array_values($result)
    ]);
}  


    
}