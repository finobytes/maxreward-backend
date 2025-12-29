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
use App\Models\Attribute;
use App\Models\AttributeItem;

class ProductController extends Controller
{

    /**
     * Get all products with filters
     */
    public function index(Request $request)
    {
        $query = Product::with(['category', 'subCategory', 'brand', 'model', 'gender', 'variations']);

        // Search
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('sku', 'like', '%' . $request->search . '%');
        }

        // Filters
        if ($request->has('merchant_id')) {
            $query->where('merchant_id', $request->merchant_id);
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $perPage = $request->get('per_page', 15);
        $products = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    /**
     * Get single product
     */
    public function show($id)
    {
        $product = Product::with([
            'category',
            'subCategory',
            'brand',
            'model',
            'gender',
            'variations.variationAttributes.attribute',
            'variations.variationAttributes.attributeItem'
        ])->findOrFail($id);

        // Append grouped attributes
        $product->append('grouped_attributes');

        return response()->json([
            'success' => true,
            'data' => $product
        ]);
    }

    /**
     * Validate SKU uniqueness
     */
    public function validateSku(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sku' => 'required|string|max:100',
            'product_id' => 'nullable|exists:products,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $sku = strtoupper(trim($request->sku));
        $productId = $request->product_id;

        // Check in products table
        $productExists = Product::where('sku', $sku)
            ->when($productId, function($query) use ($productId) {
                return $query->where('id', '!=', $productId);
            })
            ->exists();

        // Check in product_variations table
        $variationExists = ProductVariation::where('sku', $sku)
            ->when($productId, function($query) use ($productId) {
                return $query->where('product_id', '!=', $productId);
            })
            ->exists();

        $exists = $productExists || $variationExists;

        return response()->json([
            'success' => true,
            'available' => !$exists,
            'message' => $exists 
                ? 'SKU already exists in the system' 
                : 'SKU is available'
        ]);
    }

    /**
     * Generate variation combinations with SKU validation
     */
    public function generateVariations(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sku_short_code' => 'required|string|max:50',
            'attributes' => 'required|array|min:1',
            'attributes.*.attribute_id' => 'required|exists:attributes,id',
            'attributes.*.attribute_item_ids' => 'required|array|min:1',
            'attributes.*.attribute_item_ids.*' => 'required|exists:attribute_items,id',
            'product_id' => 'nullable|exists:products,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $skuShortCode = strtoupper(trim($request->sku_short_code));
            $attributes = $request->input('attributes');
            // dd($attributes);
            $productId = $request->product_id;

            // Generate combinations
            $combinations = $this->generateCombinations($attributes);
            
            // Generate SKUs and check uniqueness
            $variationsWithValidation = [];
            $existingSkus = [];

            foreach ($combinations as $combination) {
                $sku = $this->generateVariationSKU($skuShortCode, $combination);
                
                // Check if SKU exists
                $skuExists = $this->checkSkuExists($sku, $productId);
                
                if ($skuExists) {
                    $existingSkus[] = $sku;
                }

                $variationsWithValidation[] = [
                    'sku' => $sku,
                    'sku_exists' => $skuExists,
                    'attributes' => $combination,
                    'formatted_attributes' => $this->formatAttributeNames($combination)
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'variations' => $variationsWithValidation,
                    'total_combinations' => count($variationsWithValidation),
                    'existing_skus_count' => count($existingSkus),
                    'existing_skus' => $existingSkus,
                    'has_conflicts' => count($existingSkus) > 0
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate variations',
                'error' => $e->getMessage()
            ], 500);
        }
    }


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
            'description' => 'required|string',
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
                'regular_price' => $request->regular_price ?? 0,
                'regular_point' => $request->regular_point ?? 0,
                'sale_price' => $request->sale_price ?? 0,
                'sale_point' => $request->sale_point ?? 0,
                'cost_price' => $request->cost_price ?? 0,
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

    /**
     * Update product
     */
    public function update(Request $request, $id)
    {
        // return $request->all();
        // return response()->json([
        //     'debug' => true,
        //     'data' => $request->all(),
        // ]);

        // return $request->images;
        $product = Product::find($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'sku' => 'sometimes|string|max:100|unique:products,sku,' . $id,
            'status' => 'sometimes|in:draft,pending,published,archived',
            'images.*' => 'nullable|image|mimes:jpeg,jpg,png,gif,webp|max:5120',
            'delete_images' => 'nullable|array',
            
            'variations' => 'nullable|array',
            'variations.*.id' => 'nullable|exists:product_variations,id',
            'variations.*.sku' => 'required_with:variations|string|max:100',
            'variations.*.images.*' => 'nullable|image|mimes:jpeg,jpg,png,gif,webp|max:5120',
            'variations.*.delete_images' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Handle product image deletion
            if ($request->has('delete_images')) {
                $currentImages = is_array($product->images) ? $product->images : [];
                $deleteImages = $request->delete_images;
                
                $this->cloudinaryService->deleteMultipleImages($deleteImages);
                $product->images = array_values(array_diff($currentImages, $deleteImages));
            }

            // Handle new product images
            if ($request->hasFile('images')) {
                $uploadResult = $this->cloudinaryService->uploadMultipleImages(
                    $request->file('images'),
                    'products'
                );
                
                $currentImages = is_array($product->images) ? $product->images : [];
                $product->images = array_merge($currentImages, $uploadResult['uploaded']);
            }

            // Update product
            $product->update($request->only([
                'name', 'sku', 'status', 'description', 'short_description',
                'regular_price', 'regular_point', 'sale_price', 'sale_point',
                'cost_price', 'actual_quantity', 'low_stock_threshold'
            ]));

            // Update variations
            if ($request->has('variations')) {
                foreach ($request->variations as $index => $variationData) {
                    if (isset($variationData['id'])) {
                        $variation = ProductVariation::findOrFail($variationData['id']);
                        
                        if ($variation->sku !== strtoupper($variationData['sku'])) {
                            if ($this->checkSkuExists($variationData['sku'], $product->id)) {
                                throw new \Exception("SKU '{$variationData['sku']}' already exists");
                            }
                        }

                        // Handle variation image deletion
                        if (isset($variationData['delete_images'])) {
                            $currentImages = is_array($variation->images) ? $variation->images : [];
                            $this->cloudinaryService->deleteMultipleImages($variationData['delete_images']);
                            $variation->images = array_values(array_diff($currentImages, $variationData['delete_images']));
                        }

                        // Handle new variation images
                        $imageKey = "variations.{$index}.images";
                        if ($request->hasFile($imageKey)) {
                            $files = $request->file($imageKey);
                            $currentImages = is_array($variation->images) ? $variation->images : [];
                            $remainingSlots = 12 - count($currentImages);
                            
                            if (count($files) > $remainingSlots) {
                                throw new \Exception("Maximum 12 images per variation. You can add {$remainingSlots} more.");
                            }

                            $uploadResult = $this->cloudinaryService->uploadMultipleImages($files, "products/variations");
                            $variation->images = array_merge($currentImages, $uploadResult['uploaded']);
                        }

                        $variation->update([
                            'sku' => strtoupper($variationData['sku']),
                            'regular_price' => $variationData['regular_price'] ?? $variation->regular_price,
                            'regular_point' => $variationData['regular_point'] ?? $variation->regular_point,
                            'sale_price' => $variationData['sale_price'] ?? $variation->sale_price,
                            'actual_quantity' => $variationData['actual_quantity'] ?? $variation->actual_quantity,
                        ]);
                    }
                }
            }

            DB::commit();

            $product->load(['variations.variationAttributes.attribute', 'variations.variationAttributes.attributeItem']);

            return response()->json([
                'success' => true,
                'message' => 'Product updated successfully',
                'data' => $product
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update product',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete product
     */
    public function destroy($id)
    {
        try {
            $product = Product::findOrFail($id);
            $product->delete();

            return response()->json([
                'success' => true,
                'message' => 'Product deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete product',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ==================== Helper Methods ====================

    private function checkSkuExists($sku, $productId = null): bool
    {
        $productExists = Product::where('sku_short_code', $sku)
            ->when($productId, function($query) use ($productId) {
                return $query->where('id', '!=', $productId);
            })
            ->exists();

        $variationExists = ProductVariation::where('sku', $sku)
            ->when($productId, function($query) use ($productId) {
                return $query->where('product_id', '!=', $productId);
            })
            ->exists();

        return $productExists || $variationExists;
    }

    private function generateCombinations($attributes): array
    {
        if (empty($attributes)) {
            return [];
        }

        $result = [[]];
        
        foreach ($attributes as $attribute) {
            $temp = [];
            foreach ($result as $combination) {
                foreach ($attribute['attribute_item_ids'] as $itemId) {
                    $newCombination = $combination;
                    $newCombination[] = [
                        'attribute_id' => $attribute['attribute_id'],
                        'attribute_item_id' => $itemId,
                        'attribute_name' => Attribute::find($attribute['attribute_id'])->name,
                        'attribute_item_name' => AttributeItem::find($itemId)->name
                    ];
                    $temp[] = $newCombination;
                }
            }
            $result = $temp;
        }

        return $result;
    }

    /**
     * Generate variation SKU with proper attribute ordering
     * Attributes are sorted by attribute_id to ensure consistent SKU format
     * Example: TSHIRT-GREEN-S (Color first, then Size if attribute_id of Color < Size)
     */
    private function generateVariationSKU($baseSkuCode, $attributeCombination): string
    {
        // Collect attribute data with their IDs for sorting
        $attributeData = [];
        
        foreach ($attributeCombination as $attr) {
            $attributeItem = AttributeItem::find($attr['attribute_item_id']);
            if ($attributeItem) {
                $attributeData[] = [
                    'attribute_id' => $attr['attribute_id'],
                    'item_slug' => strtoupper(Str::slug($attributeItem->name, ''))
                ];
            }
        }

        // Sort by attribute_id to maintain consistent order (e.g., Size before Color)
        usort($attributeData, function($a, $b) {
            return $a['attribute_id'] <=> $b['attribute_id'];
        });

        // Build SKU parts
        $skuParts = array_map(function($item) {
            return $item['item_slug'];
        }, $attributeData);

        return strtoupper($baseSkuCode) . '_' . implode('_', $skuParts);
    }

    /**
     * Format attribute names for display
     * Maintains same order as SKU generation (sorted by attribute_id)
     */
    private function formatAttributeNames($attributeCombination): string
    {
        // Collect attribute data
        $attributeData = [];
        
        foreach ($attributeCombination as $attr) {
            $attributeItem = AttributeItem::find($attr['attribute_item_id']);
            if ($attributeItem) {
                $attributeData[] = [
                    'attribute_id' => $attr['attribute_id'],
                    'name' => $attributeItem->name
                ];
            }
        }

        // Sort by attribute_id to maintain same order as SKU
        usort($attributeData, function($a, $b) {
            return $a['attribute_id'] <=> $b['attribute_id'];
        });

        // Extract names
        $names = array_map(function($item) {
            return $item['name'];
        }, $attributeData);

        return implode(' / ', $names);
    }


}
