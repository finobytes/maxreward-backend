<?php

namespace App\Http\Controllers\Api\Merchant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\ProductVariationAttribute;
use App\Helpers\CloudinaryHelper;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * Create a new product (with variations support)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'merchant_id' => 'required|integer|exists:merchants,id',
            'category_id' => 'nullable|integer|exists:categories,id',
            'subcategory_id' => 'nullable|integer|exists:sub_categories,id',
            'brand_id' => 'nullable|integer|exists:brands,id',
            'model_id' => 'nullable|integer|exists:models,id',
            'gender_id' => 'nullable|integer|exists:genders,id',
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:300|unique:products,slug',
            'sku_short_code' => 'required|string|max:50|unique:products,sku_short_code',
            'type' => 'required|in:simple,variable',
            'status' => 'nullable|in:active,inactive,draft,out_of_stock',
            'short_description' => 'nullable|string|max:500',
            'description' => 'nullable|string',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',

            // Simple product fields (required if type is simple)
            'regular_price' => 'required_if:type,simple|nullable|numeric|min:0',
            'regular_point' => 'required_if:type,simple|nullable|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
            'sale_point' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'unit_weight' => 'nullable|numeric|min:0',

            // Variations (required if type is variable)
            'variations' => 'required_if:type,variable|array|min:1',
            'variations.*.sku' => 'required_with:variations|string|max:100|distinct',
            'variations.*.regular_price' => 'required_with:variations|numeric|min:0',
            'variations.*.regular_point' => 'required_with:variations|numeric|min:0',
            'variations.*.sale_price' => 'nullable|numeric|min:0',
            'variations.*.sale_point' => 'nullable|numeric|min:0',
            'variations.*.cost_price' => 'nullable|numeric|min:0',
            'variations.*.actual_quantity' => 'required_with:variations|integer|min:0',
            'variations.*.low_stock_threshold' => 'nullable|integer|min:0',
            'variations.*.ean_no' => 'nullable|string|max:50',
            'variations.*.unit_weight' => 'nullable|numeric|min:0',
            'variations.*.images' => 'nullable|array',
            'variations.*.images.*' => 'image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'variations.*.attributes' => 'required_with:variations|array|min:1',
            'variations.*.attributes.*.attribute_id' => 'required_with:variations.*.attributes|exists:attributes,id',
            'variations.*.attributes.*.attribute_item_id' => 'required_with:variations.*.attributes|exists:attribute_items,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Generate slug if not provided
            $slug = $request->slug ?? Str::slug($request->name);
            $originalSlug = $slug;
            $counter = 1;
            while (Product::where('slug', $slug)->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }

            // Handle product images upload
            $productImages = [];
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $uploadResult = CloudinaryHelper::uploadImage(
                        $image,
                        'maxreward/products'
                    );
                    $productImages[] = [
                        'url' => $uploadResult['url'],
                        'public_id' => $uploadResult['public_id']
                    ];
                }
            }

            // Create product
            $product = Product::create([
                'merchant_id' => $request->merchant_id,
                'category_id' => $request->category_id,
                'subcategory_id' => $request->subcategory_id,
                'brand_id' => $request->brand_id,
                'model_id' => $request->model_id,
                'gender_id' => $request->gender_id,
                'name' => $request->name,
                'slug' => $slug,
                'sku_short_code' => strtoupper($request->sku_short_code),
                'type' => $request->type,
                'status' => $request->status ?? 'draft',
                'short_description' => $request->short_description,
                'description' => $request->description,
                'images' => !empty($productImages) ? $productImages : null,
                'regular_price' => $request->type === 'simple' ? $request->regular_price : 0,
                'regular_point' => $request->type === 'simple' ? $request->regular_point : 0,
                'sale_price' => $request->type === 'simple' ? $request->sale_price : null,
                'sale_point' => $request->type === 'simple' ? $request->sale_point : null,
                'cost_price' => $request->type === 'simple' ? $request->cost_price : null,
                'unit_weight' => $request->unit_weight ?? 0,
            ]);

            // Create variations if variable product
            if ($request->type === 'variable' && !empty($request->variations)) {
                foreach ($request->variations as $index => $variationData) {
                    // Check SKU uniqueness across products and variations
                    $skuExists = Product::where('sku_short_code', strtoupper($variationData['sku']))->exists() ||
                                 ProductVariation::where('sku', strtoupper($variationData['sku']))->exists();

                    if ($skuExists) {
                        throw new \Exception("SKU '{$variationData['sku']}' already exists in the system");
                    }

                    // Handle variation images upload
                    $variationImages = [];
                    if (isset($variationData['images']) && is_array($variationData['images'])) {
                        foreach ($variationData['images'] as $image) {
                            if ($image instanceof \Illuminate\Http\UploadedFile) {
                                $uploadResult = CloudinaryHelper::uploadImage(
                                    $image,
                                    'maxreward/product-variations'
                                );
                                $variationImages[] = [
                                    'url' => $uploadResult['url'],
                                    'public_id' => $uploadResult['public_id']
                                ];
                            }
                        }
                    }

                    // Create variation
                    $variation = ProductVariation::create([
                        'product_id' => $product->id,
                        'sku' => strtoupper($variationData['sku']),
                        'regular_price' => $variationData['regular_price'],
                        'regular_point' => $variationData['regular_point'],
                        'sale_price' => $variationData['sale_price'] ?? null,
                        'sale_point' => $variationData['sale_point'] ?? null,
                        'cost_price' => $variationData['cost_price'] ?? null,
                        'actual_quantity' => $variationData['actual_quantity'],
                        'low_stock_threshold' => $variationData['low_stock_threshold'] ?? 2,
                        'ean_no' => $variationData['ean_no'] ?? null,
                        'unit_weight' => $variationData['unit_weight'] ?? 0,
                        'images' => !empty($variationImages) ? $variationImages : null,
                        'is_active' => true,
                    ]);

                    // Create variation attributes
                    if (!empty($variationData['attributes'])) {
                        foreach ($variationData['attributes'] as $attribute) {
                            ProductVariationAttribute::create([
                                'product_variation_id' => $variation->id,
                                'attribute_id' => $attribute['attribute_id'],
                                'attribute_item_id' => $attribute['attribute_item_id'],
                            ]);
                        }
                    }
                }
            }

            DB::commit();

            // Load relationships
            $product->load([
                'category',
                'subCategory',
                'brand',
                'model',
                'gender',
                'variations.variationAttributes.attribute',
                'variations.variationAttributes.attributeItem'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Product created successfully',
                'data' => $product
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            // Delete uploaded images from Cloudinary if product creation fails
            if (!empty($productImages)) {
                foreach ($productImages as $image) {
                    CloudinaryHelper::deleteImage($image['public_id']);
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to create product',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
