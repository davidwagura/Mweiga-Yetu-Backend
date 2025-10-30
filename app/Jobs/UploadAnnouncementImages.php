<?php

namespace App\Jobs;

use App\Helpers\CloudinaryHelper;
use App\Models\Announcement;
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

    /**
     * Increase timeout and memory allowance for large uploads.
     */
    public $timeout = 300; // seconds (5 minutes)
    public $tries = 3; // Number of retry attempts

    /** @var int */
    protected $announcementId;

    /** @var array */
    protected $temporaryPaths;

    /** @var array */
    protected $cloudinaryOptions;

    /**
     * @param int $announcementId
     * @param array $temporaryPaths Array with path and filename
     * @param array $cloudinaryOptions
     */
    public function __construct($announcementId, array $temporaryPaths, array $cloudinaryOptions = [])
    {
        $this->announcementId = $announcementId;
        $this->temporaryPaths = $temporaryPaths;
        $this->cloudinaryOptions = $cloudinaryOptions;
    }

    public function handle(): void
    {
        $announcement = Announcement::find($this->announcementId);

        if (!$announcement) {
            Log::warning("Announcement not found for image upload (ID: {$this->announcementId}).");
            $this->cleanupTemporaryFiles();
            return;
        }

        Log::info("Starting image upload for announcement {$this->announcementId} with " . count($this->temporaryPaths) . " images");

        $uploadedUrls = [];

        foreach ($this->temporaryPaths as $tempFile) {
            try {
                $filePath = $tempFile['path'];

                // Check if file exists and is readable
                if (!Storage::disk('public')->exists($filePath)) {
                    Log::error("Temporary file not found: {$filePath}");
                    continue;
                }

                // Get absolute path for Cloudinary upload
                $absolutePath = Storage::disk('public')->path($filePath);

                // Upload to Cloudinary with options
                $result = CloudinaryHelper::upload($absolutePath, $this->cloudinaryOptions);

                if (isset($result['secure_url'])) {
                    $uploadedUrls[] = $result['secure_url'];
                    Log::info("Successfully uploaded image for announcement {$this->announcementId}: {$result['secure_url']}");
                } else {
                    Log::error("Cloudinary upload failed - no secure_url in response for file: {$filePath}");
                }

            } catch (\Throwable $e) {
                Log::error("Cloudinary upload failed for {$tempFile['path']}: {$e->getMessage()}");
                // Continue with other files even if one fails
            } finally {
                // Always cleanup temp file
                $this->deleteTemporaryFile($tempFile['path']);
            }
        }

        // Update announcement with new image URLs
        if (!empty($uploadedUrls)) {
            try {
                $currentImages = is_array($announcement->images) ? $announcement->images : [];
                $updatedImages = array_values(array_unique(array_merge($currentImages, $uploadedUrls)));

                $announcement->update([
                    'images' => $updatedImages,
                ]);

                Log::info("Successfully updated announcement {$this->announcementId} with " . count($uploadedUrls) . " new images. Total images: " . count($updatedImages));
            } catch (\Throwable $e) {
                Log::error("Failed to update announcement images in database: {$e->getMessage()}");
            }
        } else {
            Log::warning("No images were successfully uploaded for announcement {$this->announcementId}");
        }

        // Release memory
        unset($uploadedUrls);
        gc_collect_cycles();
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("UploadAnnouncementImages job failed for announcement {$this->announcementId}: {$exception->getMessage()}");
        Log::error("Stack trace: {$exception->getTraceAsString()}");

        // Clean up temporary files even if job fails
        $this->cleanupTemporaryFiles();
    }

    /**
     * Delete a single temporary file
     */
    protected function deleteTemporaryFile(string $path): void
    {
        try {
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
                Log::info("Cleaned up temporary file: {$path}");
            }
        } catch (\Throwable $e) {
            Log::warning("Failed to cleanup temp file {$path}: {$e->getMessage()}");
        }
    }

    /**
     * Clean up all temporary files
     */
    protected function cleanupTemporaryFiles(): void
    {
        foreach ($this->temporaryPaths as $tempFile) {
            $this->deleteTemporaryFile($tempFile['path']);
        }
    }
}
