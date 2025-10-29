<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\Setting;

class SettingController extends Controller
{
    /**
     * Get current settings
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSetting()
    {
        try {
            // Get the first (and only) setting record
            $setting = Setting::first();

            if (!$setting) {
                return response()->json([
                    'success' => false,
                    'message' => 'Settings not found. Please create settings first.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Settings retrieved successfully',
                'data' => $setting
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create or update settings (upsert)
     * First time: creates settings
     * Subsequent calls: updates existing settings
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function upsertSetting(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'settings' => 'required|array',
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

            // Get the first setting record
            $setting = Setting::first();

            if ($setting) {
                // Update existing setting
                $setting->update([
                    'setting_attribute' => $request->settings,
                ]);

                $message = 'Settings updated successfully';
                $statusCode = 200;
            } else {
                // Create new setting
                $setting = Setting::create([
                    'setting_attribute' => $request->settings,
                ]);

                $message = 'Settings created successfully';
                $statusCode = 201;
            }

            // Commit transaction
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $setting
            ], $statusCode);

        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to save settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete settings (optional - if needed)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function __deleteSetting()
    {
        try {
            // Start database transaction
            DB::beginTransaction();

            // Get the first setting record
            $setting = Setting::first();

            if (!$setting) {
                return response()->json([
                    'success' => false,
                    'message' => 'Settings not found'
                ], 404);
            }

            // Store info for response
            $settingInfo = [
                'id' => $setting->id,
                'setting_attribute' => $setting->setting_attribute,
            ];

            // Delete the setting
            $setting->delete();

            // Commit transaction
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Settings deleted successfully',
                'data' => $settingInfo
            ], 200);

        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
