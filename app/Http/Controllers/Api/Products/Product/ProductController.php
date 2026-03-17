<?php

namespace App\Http\Controllers\Api\Products\Product;

use App\Http\Controllers\Controller;
use App\Models\inventory;
use App\Models\product;
use App\Models\product_variant;
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
        $products = product::with(['variants.images', 'variants.inventories'])
            ->where('branch_id', Auth::user()->branch_id)
            ->get();

        return response()->json($products);
    }

    
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    //////{Store Methoud}//////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    
   public function store(Request $request)
    {
        $request->validate([
            'model_name'               => 'required|string|max:255',
            'model_number'             => 'nullable|string',
            'image'                    => 'nullable|image|mimes:jpeg,png,jpg|max:2048', 
            'variants'                 => 'required|array|min:1',
            'variants.*.variant_name'  => 'nullable|string',
            'variants.*.size'          => 'nullable|string',
            'variants.*.color'         => 'nullable|string',
            'variants.*.cost_price'    => 'required|numeric',
            'variants.*.sku'           => 'nullable|string|unique:product_variants,sku',
            'variants.*.quantity'      => 'required|integer|min:0',
            'variants.*.images'        => 'nullable|array', 
            'variants.*.images.*'      => 'image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $uploadedPaths = [];

        try {
            return DB::transaction(function () use ($request, &$uploadedPaths) {

                $mainImagePath = null;
                if ($request->hasFile('image')) {
                    $mainImagePath = $request->file('image')->store('products/main', 'public');
                    $uploadedPaths[] = $mainImagePath;
                }

                $product = Product::create([
                    'model_name'   => $request->model_name,
                    'model_number' => $request->model_number,
                    'image'        => $mainImagePath,
                    'branch_id'    => Auth::user()->branch_id,
                ]);

                foreach ($request->variants as $index => $vData) {
                    
                    $variant = $product->variants()->create([
                        'variant_name' => $vData['variant_name'] ?? null,
                        'size'         => $vData['size'] ?? null,
                        'color'        => $vData['color'] ?? null,
                        'cost_price'   => $vData['cost_price'],
                        'sku'          => $vData['sku'] ?? null,
                    ]);

                    if ($request->hasFile("variants.$index.images")) {
                        foreach ($request->file("variants.$index.images") as $imgFile) {
                            $path = $imgFile->store('products/variants/gallery', 'public');
                            $uploadedPaths[] = $path;

                            $variant->images()->create([
                                'image_path' => $path
                            ]);
                        }
                    }

                    inventory::create([
                        'branch_id'  => Auth::user()->branch_id,
                        'variant_id' => $variant->id,
                        'quantity'   => $vData['quantity'],
                    ]);
                }

                return response()->json([
                    'status'  => 'success',
                    'message' => 'Product, variants, and gallery images saved successfully.'
                ], 201);
            });

        } catch (\Exception $e) {
            foreach ($uploadedPaths as $path) {
                Storage::disk('public')->delete($path);
            }

            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to save product: ' . $e->getMessage()
            ], 500);
        }
    }

     ////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    //////{Update Methoud}//////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    

 public function update(Request $request, $id)
{
    $product = Product::findOrFail($id);
    
        $request->validate([
            'model_name'               => 'required|string|max:255',
            'model_number'             => 'nullable|string',
            'image'                    => 'nullable|image|mimes:jpeg,png,jpg|max:2048', 
            'variants'                 => 'required|array|min:1',
            'variants.*.variant_name'  => 'nullable|string',
            'variants.*.size'          => 'nullable|string',
            'variants.*.color'         => 'nullable|string',
            'variants.*.cost_price'    => 'required|numeric',
            'variants.*.sku'           => 'nullable|string|unique:product_variants,sku',
            'variants.*.quantity'      => 'required|integer|min:0',
            'variants.*.images'        => 'nullable|array', 
            'variants.*.images.*'      => 'image|mimes:jpeg,png,jpg|max:2048',
        ]);

    try {
        return DB::transaction(function () use ($request, $product) {
            
            if ($request->hasFile('image')) {
                if ($product->image) {
                    Storage::disk('public')->delete($product->image);
                }
                $product->image = $request->file('image')->store('products/main', 'public');
            }

            $product->update([
                'model_name'   => $request->model_name,
                'model_number' => $request->model_number,
            ]);

            
            foreach ($request->variants as $index => $vData) {
                if (isset($vData['id'])) {
                    $variant = product_variant::find($vData['id']);
                    $variant->update([
                        'variant_name' => $vData['variant_name'],
                        'cost_price'   => $vData['cost_price'],
                        'size'         => $vData['size'] ?? $variant->size,
                    ]);

                    if ($request->hasFile("variants.$index.images")) {
                        foreach ($request->file("variants.$index.images") as $imgFile) {
                            $path = $imgFile->store('products/variants/gallery', 'public');
                            $variant->images()->create(['image_path' => $path]);
                        }
                    }
                }
            }

            return response()->json(['message' => 'تم التحديث بنجاح']);
        });
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
}

  ////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    //////{Delete Method}//////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    

public function destroy($id)
{
    $product = Product::with('variants.images')->findOrFail($id);

    try {
        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }

        foreach ($product->variants as $variant) {
            foreach ($variant->images as $image) {
                Storage::disk('public')->delete($image->image_path);
            }
        }

        
        $product->delete();

        return response()->json(['message' => 'تم حذف المنتج وكافة ملحقاته بنجاح']);

    } catch (\Exception $e) {
        return response()->json(['error' => 'فشل الحذف: ' . $e->getMessage()], 500);
    }
}
}