<?php

namespace App\Helpers;

use Cloudinary\Cloudinary;
use Exception;

class CloudinaryHelper
{
    private static $cloudinary;

    public static function getInstance()
    {
        if (!self::$cloudinary) {
            $cloudName = config('cloudinary.cloud_name');
            $apiKey = config('cloudinary.api_key');
            $apiSecret = config('cloudinary.api_secret');

            if (empty($cloudName) || empty($apiKey) || empty($apiSecret)) {
                throw new Exception('Cloudinary credentials are not configured properly');
            }

            self::$cloudinary = new Cloudinary([
                'cloud' => [
                    'cloud_name' => $cloudName,
                    'api_key' => $apiKey,
                    'api_secret' => $apiSecret,
                ],
                'url' => [
                    'secure' => true
                ]
            ]);
        }

        return self::$cloudinary;
    }

    public static function upload($file, $options = [])
    {
        if (!file_exists($file)) {
            throw new \InvalidArgumentException('File does not exist: ' . $file);
        }

        $mimeType = mime_content_type($file);
        if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
            throw new \InvalidArgumentException('Invalid image type: ' . $mimeType);
        }

        // Add default optimization options if not specified
        $defaultOptions = [
            'quality' => 'auto',
            'fetch_format' => 'auto',
            'flags' => 'progressive',
            'transformation' => [
                ['width' => 'auto', 'crop' => 'scale', 'dpr' => 'auto'],
                ['quality' => 'auto']
            ]
        ];

        $uploadOptions = array_merge($defaultOptions, $options);

        try {
            $cloudinary = self::getInstance();
            return $cloudinary->uploadApi()->upload($file, $uploadOptions);
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to upload image: ' . $e->getMessage(), 0, $e);
        }
    }

    public static function destroy($publicId)
    {
        $cloudinary = self::getInstance();
        return $cloudinary->uploadApi()->destroy($publicId);
    }
}
