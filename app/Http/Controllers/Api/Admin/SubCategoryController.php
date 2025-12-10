<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\SubCategory;
use App\Models\Category;
use App\Helpers\CloudinaryHelper;

class SubCategoryController extends Controller
{
    /**
     * Get all sub-categories with pagination
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            // Query builder
            $query = SubCategory::with('category');

            // Search by name or slug (optional)
            if ($request->has('search') && !empty($request->search)) {
                $query->where(function($q) use ($request) {
                    $q->where('name', 'LIKE', '%' . $request->search . '%')
                      ->orWhere('slug', 'LIKE', '%' . $request->search . '%');
                });
            }

            // Filter by category_id (optional)
            if ($request->has('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            // Filter by is_active (optional)
            if ($request->has('is_active')) {
                $query->where('is_active', $request->is_active);
            }

            // Get pagination limit (default: 10)
            $perPage = $request->get('per_page', 10);

            // Fetch sub-categories with pagination
            $subCategories = $query->orderBy('sort_order', 'asc')
                                   ->orderBy('created_at', 'desc')
                                   ->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Sub-categories retrieved successfully',
                'data' => $subCategories
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve sub-categories',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all sub-categories without pagination
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllSubCategories(Request $request)
    {
        try {
            $query = SubCategory::with('category');

            // Filter by category_id if provided
            if ($request->has('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            $subCategories = $query->orderBy('sort_order', 'asc')
                                   ->orderBy('name', 'asc')
                                   ->get();

            return response()->json([
                'success' => true,
                'message' => 'Sub-categories retrieved successfully',
                'data' => [
                    'sub_categories' => $subCategories,
                    'total' => $subCategories->count(),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve sub-categories',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single sub-category by ID
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $subCategory = SubCategory::with('category')->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Sub-category retrieved successfully',
                'data' => $subCategory
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Sub-category not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve sub-category',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new sub-category
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:100',
            'slug' => 'nullable|string|max:120|unique:sub_categories,slug',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Start database transaction
            DB::beginTransaction();

            // Handle image upload to Cloudinary
            $imageUrl = null;
            $imageCloudinaryId = null;

            if ($request->hasFile('image')) {
                // Upload new image
                $uploadResult = CloudinaryHelper::uploadImage(
                    $request->file('image'),
                    'maxreward/sub_categories'
                );

                $imageUrl = $uploadResult['url'];
                $imageCloudinaryId = $uploadResult['public_id'];
            }

            // Create sub-category
            $subCategory = SubCategory::create([
                'category_id' => $request->category_id,
                'name' => $request->name,
                'slug' => $request->slug,
                'description' => $request->description,
                'image_url' => $imageUrl,
                'image_cloudinary_id' => $imageCloudinaryId,
                'sort_order' => $request->sort_order ?? 0,
                'is_active' => $request->is_active ?? true,
            ]);

            // Load category relationship
            $subCategory->load('category');

            // Commit transaction
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Sub-category created successfully',
                'data' => $subCategory
            ], 201);

        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create sub-category',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update sub-category information
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'category_id' => 'sometimes|required|exists:categories,id',
            'name' => 'sometimes|required|string|max:100',
            'slug' => 'nullable|string|max:120|unique:sub_categories,slug,' . $id,
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Start database transaction
            DB::beginTransaction();

            // Find sub-category
            $subCategory = SubCategory::findOrFail($id);

            // Handle image upload to Cloudinary
            if ($request->hasFile('image')) {
                // Delete old image from Cloudinary if exists
                if ($subCategory->image_cloudinary_id) {
                    CloudinaryHelper::deleteImage($subCategory->image_cloudinary_id);
                }

                // Upload new image
                $uploadResult = CloudinaryHelper::uploadImage(
                    $request->file('image'),
                    'maxreward/sub_categories'
                );

                $subCategory->image_url = $uploadResult['url'];
                $subCategory->image_cloudinary_id = $uploadResult['public_id'];
            }

            // Update sub-category data (only fields that are provided)
            if ($request->has('category_id')) {
                $subCategory->category_id = $request->category_id;
            }
            if ($request->has('name')) {
                $subCategory->name = $request->name;
            }
            if ($request->has('slug')) {
                $subCategory->slug = $request->slug;
            }
            if ($request->has('description')) {
                $subCategory->description = $request->description;
            }
            if ($request->has('sort_order')) {
                $subCategory->sort_order = $request->sort_order;
            }
            if ($request->has('is_active')) {
                $subCategory->is_active = $request->is_active;
            }

            // Save changes
            $subCategory->save();

            // Commit transaction
            DB::commit();

            // Refresh and load category relationship
            $subCategory->refresh();
            $subCategory->load('category');

            return response()->json([
                'success' => true,
                'message' => 'Sub-category updated successfully',
                'data' => $subCategory
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Sub-category not found'
            ], 404);
        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update sub-category',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete sub-category
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            // Start database transaction
            DB::beginTransaction();

            // Find sub-category
            $subCategory = SubCategory::findOrFail($id);

            // Delete image from Cloudinary if exists
            if ($subCategory->image_cloudinary_id) {
                CloudinaryHelper::deleteImage($subCategory->image_cloudinary_id);
            }

            // Store info for response
            $subCategoryInfo = [
                'id' => $subCategory->id,
                'name' => $subCategory->name,
                'slug' => $subCategory->slug,
                'category_id' => $subCategory->category_id,
            ];

            // Delete the sub-category
            $subCategory->delete();

            // Commit transaction
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Sub-category deleted successfully',
                'data' => $subCategoryInfo
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Sub-category not found'
            ], 404);
        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete sub-category',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
