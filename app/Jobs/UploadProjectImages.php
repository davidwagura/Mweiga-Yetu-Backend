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

    public $timeout = 120;
    public $tries = 3;

    protected $projectId;
    protected $temporaryPaths;
    protected $uploadOptions;

    public function __construct($projectId, $temporaryPaths, $uploadOptions = [])
    {
        $this->projectId = $projectId;
        $this->temporaryPaths = $temporaryPaths;
        $this->uploadOptions = $uploadOptions;

        // Route to the configured database queue (falls back to "default")
        $this->onQueue(config('queue.connections.database.queue', 'default'));
    }

    public function handle()
    {
        Log::info("UploadProjectImages job started for project: {$this->projectId}");

        try {
            $project = Project::find($this->projectId);

            if (!$project) {
                Log::error("Project not found with ID: {$this->projectId}");
                return;
            }

            $uploadedUrls = [];

            foreach ($this->temporaryPaths as $tempPath) {
                try {
                    Log::info("Processing temporary file: {$tempPath}");

                    // Check if file exists
                    if (!Storage::exists($tempPath)) {
                        Log::error("Temporary file not found: {$tempPath}");
                        continue;
                    }

                    // Get the full path
                    $fullPath = Storage::path($tempPath);

                    // Upload to Cloudinary
                    $uploadResult = CloudinaryHelper::upload($fullPath, $this->uploadOptions);

                    if ($uploadResult && isset($uploadResult['secure_url'])) {
                        $uploadedUrls[] = $uploadResult['secure_url'];
                        Log::info("Successfully uploaded image: {$uploadResult['secure_url']}");
                    } else {
                        Log::error("Cloudinary upload failed for: {$tempPath}");
                    }

                    // Delete temporary file
                    Storage::delete($tempPath);
                    Log::info("Deleted temporary file: {$tempPath}");
                } catch (\Exception $e) {
                    Log::error("Failed to process image {$tempPath}: " . $e->getMessage());
                }
            }

            // Update project with new images
            if (!empty($uploadedUrls)) {
                $currentImages = $project->images ?? [];
                $updatedImages = array_merge($currentImages, $uploadedUrls);

                $project->update(['images' => $updatedImages]);
                Log::info("Successfully updated project {$this->projectId} with " . count($uploadedUrls) . " new images");
            }

            Log::info("UploadProjectImages job completed successfully for project {$this->projectId}");
        } catch (\Exception $e) {
            Log::error("UploadProjectImages job failed: " . $e->getMessage());
            throw $e;
        }
    }

    public function failed(\Exception $exception)
    {
        Log::error("UploadProjectImages job failed completely: " . $exception->getMessage());

        // Clean up temporary files
        foreach ($this->temporaryPaths as $tempPath) {
            try {
                if (Storage::exists($tempPath)) {
                    Storage::delete($tempPath);
                }
            } catch (\Exception $e) {
                Log::error("Failed to cleanup temp file: " . $e->getMessage());
            }
        }
    }
}
