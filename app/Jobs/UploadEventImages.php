<?php

namespace App\Jobs;

use App\Helpers\CloudinaryHelper;
use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class UploadEventImages implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;

    protected $eventId;
    protected $temporaryPaths;
    protected $uploadOptions;

    public function __construct($eventId, $temporaryPaths, $uploadOptions = [])
    {
        $this->eventId = $eventId;
        $this->temporaryPaths = $temporaryPaths;
        $this->uploadOptions = $uploadOptions;
        $this->onQueue(config('queue.connections.database.queue', 'default'));
    }

    public function handle()
    {
        try {
            Log::info("Starting image upload for event {$this->eventId} with " . count($this->temporaryPaths) . " images");

            $event = Event::find($this->eventId);
            if (!$event) {
                Log::error("Event not found with ID: {$this->eventId}");
                return;
            }

            $uploadedUrls = [];
            $successfulUploads = 0;

            foreach ($this->temporaryPaths as $tempPath) {
                try {
                    Log::info("Processing temp file: {$tempPath}");

                    if (!Storage::exists($tempPath)) {
                        Log::error("Temp file not found: {$tempPath}");
                        continue;
                    }

                    $fullPath = Storage::path($tempPath);
                    if (!file_exists($fullPath)) {
                        Log::error("File missing at path: {$fullPath}");
                        continue;
                    }

                    $uploadResult = CloudinaryHelper::upload($fullPath, $this->uploadOptions);
                    if ($uploadResult && isset($uploadResult['secure_url'])) {
                        $uploadedUrls[] = $uploadResult['secure_url'];
                        $successfulUploads++;
                    } else {
                        Log::error("Cloudinary upload failed for: {$tempPath}");
                    }

                    try {
                        Storage::delete($tempPath);
                    } catch (\Throwable $e) {
                        Log::warning("Failed to delete temp file {$tempPath}: " . $e->getMessage());
                    }
                } catch (\Throwable $e) {
                    Log::error("Failed to process image {$tempPath}: " . $e->getMessage());
                    try {
                        if (Storage::exists($tempPath)) {
                            Storage::delete($tempPath);
                        }
                    } catch (\Throwable $deleteException) {
                        Log::warning("Cleanup failed for temp file {$tempPath}: " . $deleteException->getMessage());
                    }
                }
            }

            if (!empty($uploadedUrls)) {
                $currentImages = $event->images ?? [];
                $event->update(['images' => array_merge($currentImages, $uploadedUrls)]);
                Log::info("Event {$this->eventId} updated with " . count($uploadedUrls) . " images");
            }

            Log::info("Event image upload job completed. Success: {$successfulUploads}/" . count($this->temporaryPaths));
        } catch (\Throwable $e) {
            Log::error("UploadEventImages job failed for event {$this->eventId}: " . $e->getMessage());
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("UploadEventImages job failed completely for event {$this->eventId}: " . $exception->getMessage());
        foreach ($this->temporaryPaths as $tempPath) {
            try {
                if (Storage::exists($tempPath)) {
                    Storage::delete($tempPath);
                }
            } catch (\Throwable $e) {
                Log::warning("Failed to cleanup temp file in failed handler: " . $e->getMessage());
            }
        }
    }
}
