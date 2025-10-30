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
    public $memory = 512; // MB (Laravel 11+ supports memory property)

    /** @var int */
    protected $announcementId;

    /** @var array<string> */
    protected $temporaryPaths;

    /** @var array */
    protected $cloudinaryOptions;

    /**
     * @param int $announcementId
     * @param array<string> $temporaryPaths Local temporary storage paths
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

        // If too many images, split into smaller batches automatically
        $chunks = array_chunk($this->temporaryPaths, 3);
        foreach ($chunks as $chunk) {
            $this->processBatch($announcement, $chunk);
        }
    }

    /**
     * Process a small batch of images (reduces memory & CPU usage)
     */
    protected function processBatch(Announcement $announcement, array $paths): void
    {
        $uploadedUrls = [];

        foreach ($paths as $path) {
            try {
                // Use public disk for temporary files
                $absolutePath = Storage::disk('public')->path($path);

                // Use stream uploads to avoid loading full image into memory
                $result = CloudinaryHelper::upload($absolutePath, $this->cloudinaryOptions);

                if (isset($result['secure_url'])) {
                    $uploadedUrls[] = $result['secure_url'];
                    Log::info("Uploaded image for announcement {$announcement->id}: {$result['secure_url']}");
                }
            } catch (\Throwable $e) {
                Log::error("Cloudinary upload failed for {$path}: {$e->getMessage()}");
            } finally {
                // Cleanup temp file
                try {
                    Storage::disk('public')->delete($path);
                } catch (\Throwable $e) {
                    Log::warning("Failed to delete temp file {$path}: {$e->getMessage()}");
                }
            }
        }

        if (!empty($uploadedUrls)) {
            $currentImages = is_array($announcement->images) ? $announcement->images : [];
            $announcement->update([
                'images' => array_values(array_unique(array_merge($currentImages, $uploadedUrls))),
            ]);

            Log::info("Successfully updated announcement {$announcement->id} with " . count($uploadedUrls) . " new images");
        }

        // Release memory explicitly
        unset($uploadedUrls);
        gc_collect_cycles();
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("UploadAnnouncementImages job failed for announcement {$this->announcementId}: {$exception->getMessage()}");

        // Clean up temporary files even if job fails
        $this->cleanupTemporaryFiles();
    }

    /**
     * Clean up any remaining temporary files
     */
    protected function cleanupTemporaryFiles(): void
    {
        foreach ($this->temporaryPaths as $path) {
            try {
                if (Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                    Log::info("Cleaned up temporary file: {$path}");
                }
            } catch (\Throwable $e) {
                Log::warning("Failed to cleanup temp file {$path}: {$e->getMessage()}");
            }
        }
    }
}
