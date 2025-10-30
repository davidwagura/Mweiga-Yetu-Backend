<?php

namespace App\Jobs;

use App\Models\Announcement;
use App\Helpers\CloudinaryHelper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class UploadAnnouncementImages implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;

    protected $announcementId;
    protected $temporaryPaths;
    protected $uploadOptions;

    public function __construct($announcementId, $temporaryPaths, $uploadOptions = [])
    {
        $this->announcementId = $announcementId;
        $this->temporaryPaths = $temporaryPaths;
        $this->uploadOptions = $uploadOptions;
        $this->onQueue(config('queue.connections.database.queue', 'default'));
    }

    public function handle()
    {
        try {
            Log::info("Starting image upload for announcement {$this->announcementId} with " . count($this->temporaryPaths) . " images");

            $announcement = Announcement::find($this->announcementId);

            if (!$announcement) {
                Log::error("Announcement not found with ID: {$this->announcementId}");
                return;
            }

            $uploadedUrls = [];
            $successfulUploads = 0;

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

                    if (!file_exists($fullPath)) {
                        Log::error("File does not exist at path: {$fullPath}");
                        continue;
                    }

                    // Upload to Cloudinary
                    $uploadResult = CloudinaryHelper::upload($fullPath, $this->uploadOptions);

                    if ($uploadResult && isset($uploadResult['secure_url'])) {
                        $uploadedUrls[] = $uploadResult['secure_url'];
                        $successfulUploads++;
                        Log::info("Successfully uploaded image: {$uploadResult['secure_url']}");
                    } else {
                        Log::error("Cloudinary upload failed for: {$tempPath}");
                    }

                    // Delete temporary file regardless of upload success
                    try {
                        Storage::delete($tempPath);
                        Log::info("Deleted temporary file: {$tempPath}");
                    } catch (\Exception $e) {
                        Log::error("Failed to delete temporary file {$tempPath}: " . $e->getMessage());
                    }
                } catch (\Exception $e) {
                    Log::error("Failed to process image {$tempPath}: " . $e->getMessage());

                    // Clean up temporary file on error
                    try {
                        if (Storage::exists($tempPath)) {
                            Storage::delete($tempPath);
                        }
                    } catch (\Exception $deleteException) {
                        Log::error("Failed to cleanup temp file {$tempPath}: " . $deleteException->getMessage());
                    }
                }
            }

            // Update announcement with new images
            if (!empty($uploadedUrls)) {
                $currentImages = $announcement->images ?? [];
                $updatedImages = array_merge($currentImages, $uploadedUrls);

                $announcement->update(['images' => $updatedImages]);
                Log::info("Successfully updated announcement {$this->announcementId} with " . count($uploadedUrls) . " new images");
            }

            Log::info("Image upload job completed for announcement {$this->announcementId}. Successful: {$successfulUploads}/" . count($this->temporaryPaths));
        } catch (\Exception $e) {
            Log::error("UploadAnnouncementImages job failed for announcement {$this->announcementId}: " . $e->getMessage());
            throw $e;
        }
    }

    public function failed(\Exception $exception)
    {
        Log::error("UploadAnnouncementImages job failed completely for announcement {$this->announcementId}: " . $exception->getMessage());

        // Clean up any remaining temporary files
        foreach ($this->temporaryPaths as $tempPath) {
            try {
                if (Storage::exists($tempPath)) {
                    Storage::delete($tempPath);
                }
            } catch (\Exception $e) {
                Log::error("Failed to cleanup temp file in failed method: " . $e->getMessage());
            }
        }
    }
}
