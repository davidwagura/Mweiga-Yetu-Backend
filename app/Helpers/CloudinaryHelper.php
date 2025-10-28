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
        $cloudinary = self::getInstance();
        return $cloudinary->uploadApi()->upload($file, $options);
    }

    public static function destroy($publicId)
    {
        $cloudinary = self::getInstance();
        return $cloudinary->uploadApi()->destroy($publicId);
    }
}
