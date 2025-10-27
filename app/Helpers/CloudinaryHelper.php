<?php

namespace App\Helpers;

use Illuminate\Http\UploadedFile;
use Cloudinary\Cloudinary as CloudinarySDK;

class CloudinaryHelper
{
    /**
     * Upload image to Cloudinary
     *
     * @param UploadedFile $file
     * @param string $folder
     * @return array ['url' => string, 'public_id' => string]
     */
    public static function uploadImage(UploadedFile $file, string $folder = 'maxreward'): array
    {
        try {
            // Debug: Check if file is valid
            if (!$file || !$file->isValid()) {
                throw new \Exception('Invalid file uploaded');
            }

            // Initialize Cloudinary SDK
            $cloudinary = new CloudinarySDK([
                'cloud' => [
                    'cloud_name' => config('cloudinary.cloud_name'),
                    'api_key' => config('cloudinary.api_key'),
                    'api_secret' => config('cloudinary.api_secret'),
                ],
                'url' => [
                    'secure' => true
                ]
            ]);

            // Upload to Cloudinary
            $uploadResult = $cloudinary->uploadApi()->upload($file->getRealPath(), [
                'folder' => $folder,
                'resource_type' => 'image'
            ]);

            // Debug: Check if result is null or empty
            if (!$uploadResult || empty($uploadResult)) {
                throw new \Exception('Cloudinary upload returned null or empty result');
            }

            // Get URL and public ID from result array
            $uploadedFileUrl = $uploadResult['secure_url'] ?? null;
            $publicId = $uploadResult['public_id'] ?? null;

            if (!$uploadedFileUrl || !$publicId) {
                throw new \Exception('Failed to get URL or Public ID from upload result. Result keys: ' . implode(', ', array_keys($uploadResult)));
            }

            return [
                'url' => $uploadedFileUrl,
                'public_id' => $publicId
            ];
        } catch (\Exception $e) {
            throw new \Exception('Failed to upload image to Cloudinary: ' . $e->getMessage());
        }
    }

    /**
     * Delete image from Cloudinary
     *
     * @param string $publicId
     * @return bool
     */
    public static function deleteImage(string $publicId): bool
    {
        try {
            // Initialize Cloudinary SDK
            $cloudinary = new CloudinarySDK([
                'cloud' => [
                    'cloud_name' => config('cloudinary.cloud_name'),
                    'api_key' => config('cloudinary.api_key'),
                    'api_secret' => config('cloudinary.api_secret'),
                ],
                'url' => [
                    'secure' => true
                ]
            ]);

            $cloudinary->uploadApi()->destroy($publicId);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
