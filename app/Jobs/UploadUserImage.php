<?php

namespace App\Jobs;

use App\Helpers\CloudinaryHelper;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class UploadUserImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Extend timeout and optionally memory for safer long uploads.
     */
    public $timeout = 180; // 3 minutes
    public $memory = 256; // MB (Laravel 11+)

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public $backoff = [10, 30, 60];

    /** @var int */
    protected $userId;

    /** @var string */
    protected $temporaryPath;

    /** @var array */
    protected $cloudinaryOptions;

    /**
     * Create a new job instance.
     *
     * @param  int    $userId
     * @param  string $temporaryPath
     * @param  array  $cloudinaryOptions
     */
    public function __construct($userId, $temporaryPath, array $cloudinaryOptions = [])
    {
        $this->userId = $userId;
        $this->temporaryPath = $temporaryPath;
        $this->cloudinaryOptions = $cloudinaryOptions;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $user = User::find($this->userId);

        if (!$user) {
            $this->fail(new \Exception("User not found (ID: {$this->userId})."));
            return;
        }

        if (!Storage::exists($this->temporaryPath)) {
            $this->fail(new \Exception("Temporary file not found: {$this->temporaryPath}"));
            return;
        }

        try {
            $absolutePath = Storage::path($this->temporaryPath);

            // Track upload progress and notify user
            $progressOptions = array_merge($this->cloudinaryOptions, [
                'notification_url' => route('upload.progress', ['userId' => $this->userId])
            ]);

            $result = CloudinaryHelper::upload($absolutePath, $progressOptions);

            if (!empty($result['secure_url'])) {
                // Delete old image if exists
                if ($user->image_path) {
                    $oldPublicId = $this->extractPublicIdFromUrl($user->image_path);
                    if ($oldPublicId) {
                        try {
                            CloudinaryHelper::destroy($oldPublicId);
                        } catch (\Throwable $e) {
                            Log::warning("Failed to delete old image: {$e->getMessage()}");
                        }
                    }
                }

                $user->update(['image_path' => $result['secure_url']]);
                Log::info("Successfully uploaded image for user {$user->email}");

                // Notify user of success
                $user->notify(new ImageUploadSuccessNotification($result['secure_url']));
            } else {
                throw new \Exception("No secure_url returned from Cloudinary");
            }
        } catch (\Throwable $e) {
            Log::error("Upload failed for user {$this->userId}: {$e->getMessage()}");

            // Determine if we should retry
            if ($this->attempts() < $this->tries) {
                $delay = $this->backoff[$this->attempts() - 1] ?? 60;
                $this->release($delay);
                return;
            }

            // Notify user of failure on final attempt
            $user->notify(new ImageUploadFailedNotification());
            throw $e;
        } finally {
            // Clean up temp file
            $this->cleanupTemporaryFile();
        }
    }

    /**
     * Clean up the temporary file.
     */
    private function cleanupTemporaryFile(): void
    {
        try {
            if (Storage::exists($this->temporaryPath)) {
                Storage::delete($this->temporaryPath);
                Log::info("Cleaned up temporary file: {$this->temporaryPath}");
            }
        } catch (\Throwable $e) {
            Log::warning("Failed to cleanup temporary file {$this->temporaryPath}: {$e->getMessage()}");
        }
    }

    /**
     * Extract public ID from Cloudinary URL.
     */
    private function extractPublicIdFromUrl(?string $url): ?string
    {
        if (empty($url)) {
            return null;
        }

        $parsedUrl = parse_url($url);
        $path = $parsedUrl['path'] ?? '';

        $patterns = [
            '/\/v\d+\/(.+)\.(jpg|jpeg|png|gif|webp)$/',
            '/\/image\/upload\/v\d+\/(.+)\.(jpg|jpeg|png|gif|webp)$/'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $path, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }
}
