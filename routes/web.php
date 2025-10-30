<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GoogleAuthController;

Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect']);
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback']);

Route::get('/', function () {
    return view('welcome');
    Route::get('/debug-cloudinary', function () {
    try {
        $envVars = [
            'CLOUDINARY_URL' => env('CLOUDINARY_URL'),
            'CLOUDINARY_CLOUD_NAME' => env('CLOUDINARY_CLOUD_NAME'),
            'CLOUDINARY_API_KEY' => env('CLOUDINARY_API_KEY'),
            'CLOUDINARY_API_SECRET' => env('CLOUDINARY_API_SECRET'),
        ];

        $configVars = [
            'cloud_url' => config('cloudinary.cloud_url'),
            'cloud_name' => config('cloudinary.cloud_name'),
            'api_key' => config('cloudinary.api_key'),
            'api_secret' => config('cloudinary.api_secret'),
        ];

        return response()->json([
            'environment_variables' => $envVars,
            'config_values' => $configVars,
            'has_cloudinary_url' => !empty(env('CLOUDINARY_URL')),
            'has_individual_vars' => !empty(env('CLOUDINARY_CLOUD_NAME')) &&
                                   !empty(env('CLOUDINARY_API_KEY')) &&
                                   !empty(env('CLOUDINARY_API_SECRET')),
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});

});
