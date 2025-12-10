<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\Brand;
use App\Helpers\CloudinaryHelper;

class BrandController extends Controller
{
    /**
     * Get all brands with pagination
     */
    public function index(Request $request)
    {
        try {
            $query = Brand::query();

            // Search by name or slug
            if ($request->has('search') && !empty($request->search)) {
                $query->where(function ($q) use ($request) {
                    $q->where('name', 'LIKE', '%' . $request->search . '%')
                        ->orWhere('slug', 'LIKE', '%' . $request->search . '%');
                });
            }

            // Filter by status
            if ($request->has('is_active')) {
                $query->where('is_active', $request->is_active);
            }

            // Filter featured brands
            if ($request->has('is_featured')) {
                $query->where('is_featured', $request->is_featured);
            }

            $perPage = $request->get('per_page', 10);

            $brands = $query->orderBy('sort_order', 'asc')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Brands retrieved successfully',
                'data' => $brands
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve brands',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all brands without pagination
     */
    public function getAllBrands()
    {
        try {
            $brands = Brand::orderBy('sort_order', 'asc')
                ->orderBy('name', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Brands retrieved successfully',
                'data' => [
                    'brands' => $brands,
                    'total' => $brands->count(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve brands',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show single brand
     */
    public function show($id)
    {
        try {
            $brand = Brand::findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Brand retrieved successfully',
                'data' => $brand
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Brand not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve brand',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new brand
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'       => 'required|string|max:100|unique:brands,name',
            'slug'       => 'nullable|string|max:120|unique:brands,slug',
            'description' => 'nullable|string',
            'image'       => 'nullable|image|mimes:jpeg,png,jpg,webp,svg|max:2048',
            'sort_order' => 'nullable|integer',
            'is_active'  => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $imageUrl = null;
            $imageCloudinaryId = null;

            if ($request->hasFile('image')) {
                $upload = CloudinaryHelper::uploadImage(
                    $request->file('image'),
                    'maxreward/brands'
                );

                $imageUrl = $upload['url'];
                $imageCloudinaryId = $upload['public_id'];
            }

            $name = str_replace(' ', '', $request->name);

            $brand = Brand::create([
                'name' => $name,
                'slug' => $name,
                'description' => $request->description,
                'image_url' => $imageUrl,
                'image_cloudinary_id' => $imageCloudinaryId,
                'sort_order' => $request->sort_order ?? 0,
                'is_active' => $request->is_active ?? true,
                'is_featured' => $request->is_featured ?? false,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Brand created successfully',
                'data' => $brand
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create brand',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update brand
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name'       => 'sometimes|required|string|max:100|unique:brands,name,' . $id,
            'slug'       => 'nullable|string|max:120|unique:brands,slug,' . $id,
            'description' => 'nullable|string',
            'image'       => 'nullable|image|mimes:jpeg,png,jpg,webp,svg|max:2048',
            'sort_order' => 'nullable|integer',
            'is_active'  => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $brand = Brand::findOrFail($id);

            // Replace image
            if ($request->hasFile('image')) {
                if ($brand->image_cloudinary_id) {
                    CloudinaryHelper::deleteImage($brand->image_cloudinary_id);
                }

                $upload = CloudinaryHelper::uploadImage(
                    $request->file('image'),
                    'maxreward/brands'
                );

                $brand->image_url = $upload['url'];
                $brand->image_cloudinary_id = $upload['public_id'];
            }

            // Update text fields
            foreach (['name', 'slug', 'description', 'sort_order', 'is_active', 'is_featured'] as $field) {
                if ($request->has($field)) {
                    $brand->$field = $request->$field;
                }
            }

            $brand->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Brand updated successfully',
                'data' => $brand
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Brand not found'
            ], 404);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update brand',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete brand
     */
    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $brand = Brand::findOrFail($id);

            if ($brand->image_cloudinary_id) {
                CloudinaryHelper::deleteImage($brand->image_cloudinary_id);
            }

            $brandInfo = [
                'id' => $brand->id,
                'name' => $brand->name,
                'slug' => $brand->slug,
            ];

            $brand->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Brand deleted successfully',
                'data' => $brandInfo
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Brand not found'
            ], 404);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete brand',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
