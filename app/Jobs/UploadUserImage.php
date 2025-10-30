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
            Log::warning("UploadUserImage: User not found (ID: {$this->userId}).");
            return;
        }

        try {
            $absolutePath = Storage::path($this->temporaryPath);

            $result = CloudinaryHelper::upload($absolutePath, $this->cloudinaryOptions);

            if (!empty($result['secure_url'])) {
                $user->update(['image_path' => $result['secure_url']]);
                Log::info("Uploaded user image for {$user->email}: {$result['secure_url']}");
            } else {
                Log::warning("UploadUserImage: No secure_url returned for {$this->temporaryPath}");
            }
        } catch (\Throwable $e) {
            Log::error("UploadUserImage failed for user {$this->userId}: {$e->getMessage()}");
        } finally {
            try {
                if (Storage::exists($this->temporaryPath)) {
                    Storage::delete($this->temporaryPath);
                    Log::info("Temp file deleted: {$this->temporaryPath}");
                }
            } catch (\Throwable $e) {
                Log::warning("Failed to delete temp file {$this->temporaryPath}: {$e->getMessage()}");
            }
        }

        gc_collect_cycles();
    }
}
