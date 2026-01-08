<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\Section;

class SectionController extends Controller
{
    /**
     * Get all sections with pagination
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            // Query builder
            $query = Section::query();

            // Search by name (optional)
            if ($request->has('search') && !empty($request->search)) {
                $query->where('name', 'LIKE', '%' . $request->search . '%');
            }

            // Filter by status (optional)
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Get pagination limit (default: 10)
            $perPage = $request->get('per_page', 10);

            // Fetch sections with pagination
            $sections = $query->orderBy('created_at', 'desc')
                             ->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Sections retrieved successfully',
                'data' => $sections
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve sections',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all sections without pagination
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllSections()
    {
        try {
            $sections = Section::orderBy('name', 'asc')->get();

            return response()->json([
                'success' => true,
                'message' => 'Sections retrieved successfully',
                'data' => [
                    'sections' => $sections,
                    'total' => $sections->count(),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve sections',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single section by ID
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $section = Section::findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Section retrieved successfully',
                'data' => $section
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Section not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve section',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new section
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100|unique:sections,name',
            'status' => 'nullable|boolean',
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

            // Create section
            $section = Section::create([
                'name' => $request->name,
                'status' => $request->status ?? true,
            ]);

            // Commit transaction
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Section created successfully',
                'data' => $section
            ], 201);

        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create section',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update section information
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:100|unique:sections,name,' . $id,
            'status' => 'nullable|boolean',
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

            // Find section
            $section = Section::findOrFail($id);

            // Update section data (only fields that are provided)
            if ($request->has('name')) {
                $section->name = $request->name;
            }
            if ($request->has('status')) {
                $section->status = $request->status;
            }

            // Save changes
            $section->save();

            // Commit transaction
            DB::commit();

            // Refresh section data
            $section->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Section updated successfully',
                'data' => $section
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Section not found'
            ], 404);
        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update section',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete section
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            // Start database transaction
            DB::beginTransaction();

            // Find section
            $section = Section::findOrFail($id);

            // Store info for response
            $sectionInfo = [
                'id' => $section->id,
                'name' => $section->name,
            ];

            // Delete the section
            $section->delete();

            // Commit transaction
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Section deleted successfully',
                'data' => $sectionInfo
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Section not found'
            ], 404);
        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete section',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
