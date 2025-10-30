<?php

namespace App\Jobs;

use App\Models\Project;
use App\Helpers\CloudinaryHelper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class UploadProjectImages implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $project;
    protected $images;

    public function __construct(Project $project, array $images)
    {
        $this->project = $project;
        $this->images = $images;
    }

    public function handle(): void
    {
        $uploadedImages = [];

        foreach ($this->images as $image) {
            try {
                // Get the full path to the image
                $path = Storage::disk('public')->path($image);

                // Upload to Cloudinary
                $result = CloudinaryHelper::uploadImage($path, 'projects');

                if (is_array($result) && isset($result['secure_url'])) {
                    $uploadedImages[] = $result['secure_url'];
                }

                // Clean up the temporary file
                Storage::disk('public')->delete($image);
            } catch (\Exception $e) {
                // Log error but continue with other images
                Log::error('Error uploading project image: ' . $e->getMessage());
            }
        }

        // Update project with new images
        if (!empty($uploadedImages)) {
            $existingImages = $this->project->images ?? [];
            $this->project->update([
                'images' => array_merge($existingImages, $uploadedImages)
            ]);
        }
    }
}
