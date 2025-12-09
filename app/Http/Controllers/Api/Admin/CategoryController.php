<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\Category;
use App\Helpers\CloudinaryHelper;

class CategoryController extends Controller
{
    /**
     * Get all categories with pagination
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            // Query builder
            $query = Category::query();

            // Search by name or slug (optional)
            if ($request->has('search') && !empty($request->search)) {
                $query->where(function($q) use ($request) {
                    $q->where('name', 'LIKE', '%' . $request->search . '%')
                      ->orWhere('slug', 'LIKE', '%' . $request->search . '%');
                });
            }

            // Filter by is_active (optional)
            if ($request->has('is_active')) {
                $query->where('is_active', $request->is_active);
            }

            // Get pagination limit (default: 10)
            $perPage = $request->get('per_page', 10);

            // Fetch categories with pagination
            $categories = $query->orderBy('sort_order', 'asc')
                               ->orderBy('created_at', 'desc')
                               ->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Categories retrieved successfully',
                'data' => $categories
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve categories',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all categories without pagination
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllCategories()
    {
        try {
            $categories = Category::orderBy('sort_order', 'asc')
                                 ->orderBy('name', 'asc')
                                 ->get();

            return response()->json([
                'success' => true,
                'message' => 'Categories retrieved successfully',
                'data' => [
                    'categories' => $categories,
                    'total' => $categories->count(),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve categories',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single category by ID
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $category = Category::findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Category retrieved successfully',
                'data' => $category
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve category',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new category
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100|unique:categories,name',
            'slug' => 'nullable|string|max:120|unique:categories,slug',
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
                    'maxreward/categories'
                );

                $imageUrl = $uploadResult['url'];
                $imageCloudinaryId = $uploadResult['public_id'];
            }

            // Create category
            $category = Category::create([
                'name' => $request->name,
                'slug' => $request->slug,
                'description' => $request->description,
                'image_url' => $imageUrl,
                'image_cloudinary_id' => $imageCloudinaryId,
                'sort_order' => $request->sort_order ?? 0,
                'is_active' => $request->is_active ?? true,
            ]);

            // Commit transaction
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Category created successfully',
                'data' => $category
            ], 201);

        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create category',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update category information
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:100|unique:categories,name,' . $id,
            'slug' => 'nullable|string|max:120|unique:categories,slug,' . $id,
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

            // Find category
            $category = Category::findOrFail($id);

            // Handle image upload to Cloudinary
            if ($request->hasFile('image')) {
                // Delete old image from Cloudinary if exists
                if ($category->image_cloudinary_id) {
                    CloudinaryHelper::deleteImage($category->image_cloudinary_id);
                }

                // Upload new image
                $uploadResult = CloudinaryHelper::uploadImage(
                    $request->file('image'),
                    'maxreward/categories'
                );

                $category->image_url = $uploadResult['url'];
                $category->image_cloudinary_id = $uploadResult['public_id'];
            }

            // Update category data (only fields that are provided)
            if ($request->has('name')) {
                $category->name = $request->name;
            }
            if ($request->has('slug')) {
                $category->slug = $request->slug;
            }
            if ($request->has('description')) {
                $category->description = $request->description;
            }
            if ($request->has('sort_order')) {
                $category->sort_order = $request->sort_order;
            }
            if ($request->has('is_active')) {
                $category->is_active = $request->is_active;
            }

            // Save changes
            $category->save();

            // Commit transaction
            DB::commit();

            // Refresh category data
            $category->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Category updated successfully',
                'data' => $category
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Category not found'
            ], 404);
        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update category',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete category
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            // Start database transaction
            DB::beginTransaction();

            // Find category
            $category = Category::findOrFail($id);

            // Delete image from Cloudinary if exists
            if ($category->image_cloudinary_id) {
                CloudinaryHelper::deleteImage($category->image_cloudinary_id);
            }

            // Store info for response
            $categoryInfo = [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
            ];

            // Delete the category
            $category->delete();

            // Commit transaction
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Category deleted successfully',
                'data' => $categoryInfo
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Category not found'
            ], 404);
        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete category',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
