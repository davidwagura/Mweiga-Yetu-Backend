<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Events\ImageUploadProgress;

class UploadProgressController extends Controller
{
    public function handleProgress(Request $request, $userId)
    {
        $user = User::find($userId);

        if (!$user) {
            Log::warning("Upload progress: User not found (ID: {$userId})");
            return response()->json(['error' => 'User not found'], 404);
        }

        $bytes = $request->input('bytes_received', 0);
        $total = $request->input('bytes_total', 0);

        if ($total > 0) {
            $progress = round(($bytes / $total) * 100);

            // Broadcast progress event
            event(new ImageUploadProgress($user->id, $progress));
        }

        return response()->json(['success' => true]);
    }
}
