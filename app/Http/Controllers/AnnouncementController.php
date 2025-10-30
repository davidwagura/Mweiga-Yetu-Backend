<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Category;
use App\Helpers\CloudinaryHelper;
use App\Jobs\UploadAnnouncementImages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class AnnouncementController extends Controller
{
    private function respond($message, $data = null, $code = 200)
    {
        return response()->json([
            'message' => $message,
            'status' => $code >= 200 && $code < 300 ? 'success' : 'error',
            'code' => $code,
            'data' => $data,
        ], $code);
    }

    private function generateAnnouncementUniqueId(): string
    {
        $timestamp = now()->format('YmdHis');
        $random = Str::upper(Str::random(5));

        $uniqueId = "ANN-{$timestamp}-{$random}";

        while (Announcement::where('unique_id', $uniqueId)->exists()) {
            $random = Str::upper(Str::random(5));
            $uniqueId = "ANN-{$timestamp}-{$random}";
        }

        return $uniqueId;
    }

    public function getAllAnnouncements(Request $request)
    {
        try {
            $perPage = $request->query('per_page', 10);
            $searchTerm = $request->query('search_term');
            $categoryId = $request->query('category_id');

            $query = Announcement::with(['category', 'user'])->latest();

            if ($searchTerm) {
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('title', 'like', "%{$searchTerm}%")
                        ->orWhere('description', 'like', "%{$searchTerm}%");
                });
            }

            if ($categoryId) {
                $query->where('category_id', $categoryId);
            }

            $paginator = $query->paginate($perPage);

            $announcements = collect($paginator->items())->map(function ($announcement) {
                return [
                    'id' => $announcement->id,
                    'unique_id' => $announcement->unique_id,
                    'title' => $announcement->title,
                    'category_id' => $announcement->category_id,
                    'description' => $announcement->description,
                    'category' => $announcement->category,
                    'date' => $announcement->date,
                    'urgent' => $announcement->urgent,
                    'images' => $announcement->images,
                    'user' => $announcement->user,
                    'created_at' => $announcement->created_at,
                    'updated_at' => $announcement->updated_at,
                ];
            });

            return $this->respond('Announcements fetched successfully', [
                'announcements' => $announcements,
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch announcements: ' . $e->getMessage());
            return $this->respond("Failed to fetch announcements: {$e->getMessage()}", null, 500);
        }
    }

    public function createAnnouncement(Request $request)
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'required|string',
                'category_id' => 'required|exists:categories,id',
                'date' => 'required|date',
                'urgent' => 'boolean',
                'images' => 'nullable|array',
                'images.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:10240',
            ]);

            $imageUrls = [];
            $temporaryPaths = [];

            // Store files temporarily - match PropertyController pattern
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $storedPath = $image->store('temp/announcements');
                    if ($storedPath) {
                        $temporaryPaths[] = $storedPath;
                    }
                }
            }

            $uniqueId = $this->generateAnnouncementUniqueId();

            // Create announcement with empty images array initially - match PropertyController
            $announcement = Auth::user()->announcements()->create(array_merge(
                Arr::except($validated, ['images']),
                [
                    'unique_id' => $uniqueId,
                    'images' => $imageUrls,
                ]
            ));

            // Dispatch image upload job - match PropertyController pattern exactly
            if (!empty($temporaryPaths)) {
                UploadAnnouncementImages::dispatch(
                    $announcement->id,
                    $temporaryPaths,
                    [
                        'folder' => 'announcements',
                        'transformation' => [
                            'width' => 800,
                            'height' => 600,
                            'crop' => 'limit',
                            'quality' => 'auto',
                            'format' => 'auto'
                        ],
                    ]
                );
            }

            $announcement->refresh();

            return $this->respond('Announcement created successfully', [
                'announcement' => $announcement->load(['category', 'user']),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->respond(
                'Validation failed: ' . collect($e->errors())->flatten()->first(),
                null,
                422
            );
        } catch (\Exception $e) {
            Log::error('Announcement creation failed: ' . $e->getMessage());

            // Clean up any temporary files if creation fails
            if (!empty($temporaryPaths)) {
                foreach ($temporaryPaths as $tempPath) {
                    try {
                        Storage::delete($tempPath);
                    } catch (\Exception $deleteException) {
                        Log::error('Failed to cleanup temp file: ' . $deleteException->getMessage());
                    }
                }
            }

            return $this->respond('Failed to create announcement: ' . $e->getMessage(), null, 500);
        }
    }

    public function updateAnnouncement(Request $request, $unique_id)
    {
        try {
            $announcement = Announcement::where('unique_id', $unique_id)->firstOrFail();

            if ($announcement->user_id !== Auth::id()) {
                return $this->respond('Unauthorized to update this announcement', null, 403);
            }

            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'required|string',
                'category_id' => 'required|exists:categories,id',
                'date' => 'required|date',
                'urgent' => 'boolean',
                'images' => 'nullable|array',
                'images.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:10240',
                'images_to_delete' => 'nullable|array',
            ]);

            $currentImages = $announcement->images ?? [];

            // Handle image deletion - match PropertyController pattern
            if (!empty($validated['images_to_delete'])) {
                foreach ($validated['images_to_delete'] as $imageUrl) {
                    try {
                        $publicId = $this->extractPublicIdFromUrl($imageUrl);
                        if ($publicId) {
                            CloudinaryHelper::destroy($publicId);
                        }
                    } catch (\Exception $e) {
                        Log::error('Failed to delete image from Cloudinary: ' . $e->getMessage());
                        continue;
                    }
                }

                $currentImages = array_values(array_diff($currentImages, $validated['images_to_delete']));
            }

            $imageUrls = $currentImages;
            $temporaryPaths = [];

            // Handle new image uploads - match PropertyController pattern
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $storedPath = $image->store('temp/announcements');
                    if ($storedPath) {
                        $temporaryPaths[] = $storedPath;
                    }
                }
            }

            $updateData = Arr::except($validated, ['images', 'images_to_delete']);
            $updateData['images'] = $imageUrls;

            $announcement->update($updateData);
            $announcement->refresh();

            // Dispatch job for new images - match PropertyController pattern
            if (!empty($temporaryPaths)) {
                UploadAnnouncementImages::dispatch(
                    $announcement->id,
                    $temporaryPaths,
                    [
                        'folder' => 'announcements',
                        'transformation' => [
                            'width' => 800,
                            'height' => 600,
                            'crop' => 'limit',
                            'quality' => 'auto',
                            'format' => 'auto'
                        ],
                    ]
                );
            }

            return $this->respond('Announcement updated successfully', [
                'announcement' => $announcement->load(['category', 'user']),
            ]);
        } catch (ModelNotFoundException $e) {
            return $this->respond('Announcement not found', null, 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->respond(
                'Validation failed: ' . collect($e->errors())->flatten()->first(),
                null,
                422
            );
        } catch (\Exception $e) {
            Log::error('Announcement update failed: ' . $e->getMessage());
            return $this->respond('Failed to update announcement: ' . $e->getMessage(), null, 500);
        }
    }

    public function getAnnouncementById($unique_id)
    {
        try {
            $announcement = Announcement::with(['category', 'user'])
                ->where('unique_id', $unique_id)
                ->firstOrFail();

            return $this->respond('Announcement fetched successfully', [
                'announcement' => $announcement,
            ]);
        } catch (ModelNotFoundException $e) {
            return $this->respond("Announcement not found with ID: {$unique_id}", null, 404);
        } catch (\Exception $e) {
            Log::error('Failed to fetch announcement: ' . $e->getMessage());
            return $this->respond('Failed to fetch announcement: ' . $e->getMessage(), null, 500);
        }
    }

    public function getUserAnnouncements(Request $request)
    {
        try {
            $userId = Auth::id();
            $perPage = $request->query('per_page', 10);

            $paginator = Announcement::with(['category', 'user'])
                ->where('user_id', $userId)
                ->latest()
                ->paginate($perPage);

            $announcements = collect($paginator->items())->map(function ($announcement) {
                return [
                    'id' => $announcement->id,
                    'unique_id' => $announcement->unique_id,
                    'title' => $announcement->title,
                    'description' => $announcement->description,
                    'category' => $announcement->category,
                    'category_id' => $announcement->category_id,
                    'date' => $announcement->date,
                    'urgent' => $announcement->urgent,
                    'images' => $announcement->images,
                    'user' => $announcement->user,
                    'created_at' => $announcement->created_at,
                    'updated_at' => $announcement->updated_at,
                ];
            });

            return $this->respond('User announcements fetched successfully', [
                'announcements' => $announcements,
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch user announcements: ' . $e->getMessage());
            return $this->respond('Failed to fetch user announcements', null, 500);
        }
    }

    public function deleteAnnouncement($unique_id)
    {
        try {
            $announcement = Announcement::where('unique_id', $unique_id)->firstOrFail();

            if ($announcement->user_id !== Auth::id()) {
                return $this->respond('Unauthorized to delete this announcement', null, 403);
            }

            // Delete images from Cloudinary - match PropertyController pattern
            if (!empty($announcement->images)) {
                foreach ($announcement->images as $image) {
                    try {
                        $publicId = $this->extractPublicIdFromUrl($image);
                        if ($publicId) {
                            CloudinaryHelper::destroy($publicId);
                        }
                    } catch (\Exception $e) {
                        Log::error('Failed to delete image from Cloudinary: ' . $e->getMessage());
                    }
                }
            }

            $announcement->delete();

            return $this->respond('Announcement deleted successfully', [
                'unique_id' => $announcement->unique_id,
            ]);
        } catch (ModelNotFoundException $e) {
            return $this->respond('Announcement not found', null, 404);
        } catch (\Exception $e) {
            Log::error('Failed to delete announcement: ' . $e->getMessage());
            return $this->respond('Failed to delete announcement: ' . $e->getMessage(), null, 500);
        }
    }

    private function extractPublicIdFromUrl($url)
    {
        try {
            if (empty($url)) {
                return null;
            }

            $parsedUrl = parse_url($url);
            $path = $parsedUrl['path'] ?? '';

            $patterns = [
                '/\/v\d+\/(.+)\.(jpg|jpeg|png|gif|webp)$/',
                '/\/image\/upload\/v\d+\/(.+)\.(jpg|jpeg|png|gif|webp)$/',
                '/\/upload\/v\d+\/(.+)\.(jpg|jpeg|png|gif|webp)$/'
            ];

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $path, $matches)) {
                    return $matches[1];
                }
            }

            Log::warning('Could not extract public_id from URL: ' . $url);
            return null;
        } catch (\Exception $e) {
            Log::error('Failed to extract public_id from URL: ' . $e->getMessage());
            return null;
        }
    }
}
