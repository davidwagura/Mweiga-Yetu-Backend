<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Helpers\CloudinaryHelper;
use App\Jobs\UploadProjectImages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ProjectController extends Controller
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

    private function generateProjectUniqueId(): string
    {
        $timestamp = now()->format('YmdHis');
        $random = Str::upper(Str::random(5));

        $uniqueId = "PROJ-{$timestamp}-{$random}";

        while (Project::where('unique_id', $uniqueId)->exists()) {
            $random = Str::upper(Str::random(5));
            $uniqueId = "PROJ-{$timestamp}-{$random}";
        }

        return $uniqueId;
    }

    public function getAllProjects(Request $request)
    {
        try {
            $perPage = $request->query('per_page', 10);
            $searchTerm = $request->query('search_term');

            $query = Project::with('status')->latest();

            if ($searchTerm) {
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('title', 'like', "%{$searchTerm}%")
                        ->orWhere('description', 'like', "%{$searchTerm}%")
                        ->orWhere('location', 'like', "%{$searchTerm}%");
                });
            }

            $paginator = $query->paginate($perPage);

            $projects = collect($paginator->items())->map(function ($project) {
                return [
                    'id' => $project->id,
                    'unique_id' => $project->unique_id,
                    'title' => $project->title,
                    'description' => $project->description,
                    'status_id' => $project->status_id,
                    'progress_percentage' => $project->progress_percentage,
                    'timeline' => $project->timeline,
                    'budget' => $project->budget,
                    'beneficiaries' => $project->beneficiaries,
                    'location' => $project->location,
                    'start_date' => $project->start_date,
                    'status' => $project->status,
                    'images' => $project->images,
                    'created_at' => $project->created_at,
                    'updated_at' => $project->updated_at,
                ];
            });

            return $this->respond('Projects fetched successfully', [
                'projects' => $projects,
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch projects: ' . $e->getMessage());
            return $this->respond("Failed to fetch projects: {$e->getMessage()}", null, 500);
        }
    }

    public function createProject(Request $request)
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'required|string',
                'progress_percentage' => 'required|numeric|min:0|max:100',
                'timeline' => 'required|string',
                'budget' => 'required|numeric|min:0',
                'beneficiaries' => 'required|integer|min:0',
                'location' => 'required|string',
                'start_date' => 'required|date',
                'status_id' => 'required|exists:statuses,id',
                'images' => 'nullable|array',
                'images.*' => 'image|mimes:jpeg,png,jpg,gif,webp',
            ]);

            $imageUrls = [];
            $temporaryPaths = [];

            // Match PropertyController pattern for temp storage
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $storedPath = $image->store('temp/projects');
                    if ($storedPath) {
                        $temporaryPaths[] = $storedPath;
                    }
                }
            }

            $uniqueId = $this->generateProjectUniqueId();

            // Create project with empty images array - match PropertyController
            $project = Auth::user()->projects()->create(array_merge(
                Arr::except($validated, ['images']),
                [
                    'unique_id' => $uniqueId,
                    'images' => $imageUrls,
                ]
            ));

            // Dispatch image upload job - match PropertyController pattern
            if (!empty($temporaryPaths)) {
                UploadProjectImages::dispatch(
                    $project->id,
                    $temporaryPaths,
                    [
                        'folder' => 'projects',
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

            $project->refresh();

            return $this->respond('Project created successfully', [
                'project' => $project->load('status'),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->respond(
                'Validation failed: ' . collect($e->errors())->flatten()->first(),
                null,
                422
            );
        } catch (\Exception $e) {
            Log::error('Project creation failed: ' . $e->getMessage());

            // Clean up temporary files
            if (!empty($temporaryPaths)) {
                foreach ($temporaryPaths as $tempPath) {
                    try {
                        Storage::delete($tempPath);
                    } catch (\Exception $deleteException) {
                        Log::error('Failed to cleanup temp file: ' . $deleteException->getMessage());
                    }
                }
            }

            return $this->respond("Failed to create project: {$e->getMessage()}", null, 500);
        }
    }

    public function updateProject(Request $request, $unique_id)
    {
        try {
            $project = Project::where('unique_id', $unique_id)->firstOrFail();

            if ($project->user_id !== Auth::id()) {
                return $this->respond('Unauthorized to update this project', null, 403);
            }

            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'required|string',
                'progress_percentage' => 'required|numeric|min:0|max:100',
                'timeline' => 'required|string',
                'budget' => 'required|numeric|min:0',
                'beneficiaries' => 'required|integer|min:0',
                'location' => 'required|string',
                'start_date' => 'required|date',
                'status_id' => 'required|exists:statuses,id',
                'images' => 'nullable|array',
                'images.*' => 'image|mimes:jpeg,png,jpg,gif,webp',
                'images_to_delete' => 'nullable|array',
            ]);

            $currentImages = $project->images ?? [];

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
                    $storedPath = $image->store('temp/projects');
                    if ($storedPath) {
                        $temporaryPaths[] = $storedPath;
                    }
                }
            }

            $updateData = Arr::except($validated, ['images', 'images_to_delete']);
            $updateData['images'] = $imageUrls;

            $project->update($updateData);
            $project->refresh();

            // Dispatch job for new images - match PropertyController pattern
            if (!empty($temporaryPaths)) {
                UploadProjectImages::dispatch(
                    $project->id,
                    $temporaryPaths,
                    [
                        'folder' => 'projects',
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

            return $this->respond('Project updated successfully', [
                'project' => $project->load('status'),
            ]);
        } catch (ModelNotFoundException $e) {
            return $this->respond('Project not found', null, 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->respond(
                'Validation failed: ' . collect($e->errors())->flatten()->first(),
                null,
                422
            );
        } catch (\Exception $e) {
            Log::error('Project update failed: ' . $e->getMessage());
            return $this->respond("Failed to update project: {$e->getMessage()}", null, 500);
        }
    }

    public function getProjectById($unique_id)
    {
        try {
            $project = Project::with('status')->where('unique_id', $unique_id)->firstOrFail();
            return $this->respond('Project fetched successfully', ['project' => $project]);
        } catch (ModelNotFoundException $e) {
            return $this->respond("Project not found with ID: {$unique_id}", null, 404);
        } catch (\Exception $e) {
            Log::error('Failed to fetch project: ' . $e->getMessage());
            return $this->respond("Failed to fetch project: {$e->getMessage()}", null, 500);
        }
    }

    public function getUserProjects(Request $request)
    {
        try {
            $userId = Auth::id();
            $perPage = $request->query('per_page', 10);

            $paginator = Project::with('status')
                ->where('user_id', $userId)
                ->latest()
                ->paginate($perPage);

            $projects = collect($paginator->items())->map(function ($project) {
                return [
                    'id' => $project->id,
                    'unique_id' => $project->unique_id,
                    'title' => $project->title,
                    'description' => $project->description,
                    'progress_percentage' => $project->progress_percentage,
                    'timeline' => $project->timeline,
                    'status_id' => $project->status_id,
                    'budget' => $project->budget,
                    'beneficiaries' => $project->beneficiaries,
                    'location' => $project->location,
                    'start_date' => $project->start_date,
                    'status' => $project->status,
                    'images' => $project->images,
                    'created_at' => $project->created_at,
                    'updated_at' => $project->updated_at,
                ];
            });

            return $this->respond('User projects fetched successfully', [
                'projects' => $projects,
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch user projects: ' . $e->getMessage());
            return $this->respond("Failed to fetch user projects: {$e->getMessage()}", null, 500);
        }
    }

    public function deleteProject($unique_id)
    {
        try {
            $project = Project::where('unique_id', $unique_id)->firstOrFail();

            if ($project->user_id !== Auth::id()) {
                return $this->respond('Unauthorized to delete this project', null, 403);
            }

            // Delete images from Cloudinary - match PropertyController pattern
            if (!empty($project->images)) {
                foreach ($project->images as $image) {
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

            $project->delete();

            return $this->respond('Project deleted successfully', ['unique_id' => $project->unique_id]);
        } catch (ModelNotFoundException $e) {
            return $this->respond('Project not found', null, 404);
        } catch (\Exception $e) {
            Log::error('Failed to delete project: ' . $e->getMessage());
            return $this->respond("Failed to delete project: {$e->getMessage()}", null, 500);
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
