<?php

namespace App\Helpers;

use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Http\UploadedFile;

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
            $uploadedFileUrl = Cloudinary::upload($file->getRealPath(), [
                'folder' => $folder,
                'resource_type' => 'image'
            ])->getSecurePath();

            $publicId = Cloudinary::getPublicId();

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
            Cloudinary::destroy($publicId);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
