<?php

namespace App\Http\Controllers;

use App\Models\Opportunity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class OpportunityController extends Controller
{
    private function respond(string $message, $data = null, int $code = 200)
    {
        return response()->json([
            'status' => $code >= 200 && $code < 300 ? 'success' : 'error',
            'code' => $code,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    private function generateOpportunityUniqueId(): string
    {
        $timestamp = now()->format('YmdHis');
        $random = Str::upper(Str::random(5));
        $uniqueId = "OPP-{$timestamp}-{$random}";

        while (Opportunity::where('unique_id', $uniqueId)->exists()) {
            $random = Str::upper(Str::random(5));
            $uniqueId = "OPP-{$timestamp}-{$random}";
        }

        return $uniqueId;
    }

    public function getAllOpportunities(Request $request)
    {
        try {
            $perPage = $request->query('per_page', 10);

            $opportunities = Opportunity::with(['category', 'user'])
                ->latest()
                ->paginate($perPage);

            return $this->respond('Opportunities fetched successfully', [
                'opportunities' => $opportunities->items(),
                'pagination' => [
                    'current_page' => $opportunities->currentPage(),
                    'per_page' => $opportunities->perPage(),
                    'total' => $opportunities->total(),
                    'last_page' => $opportunities->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching opportunities: ' . $e->getMessage());
            return $this->respond('Failed to fetch opportunities', null, 500);
        }
    }

    public function getUserOpportunities(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return $this->respond('Unauthorized', null, 401);
            }

            $perPage = $request->query('per_page', 10);

            $opportunities = Opportunity::with('category')
                ->where('user_id', $user->id)
                ->latest()
                ->paginate($perPage);

            return $this->respond('User opportunities fetched successfully', [
                'opportunities' => $opportunities->items(),
                'pagination' => [
                    'current_page' => $opportunities->currentPage(),
                    'per_page' => $opportunities->perPage(),
                    'total' => $opportunities->total(),
                    'last_page' => $opportunities->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching user opportunities: ' . $e->getMessage());
            return $this->respond('Failed to fetch user opportunities', null, 500);
        }
    }

    public function createOpportunity(Request $request)
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'required|string',
                'location' => 'required|string|max:255',
                'deadline' => 'required|date',
                'category_id' => 'required|exists:categories,id',
                'organization' => 'required|string|max:255',
                'application_link' => 'required|url',
            ]);

            $uniqueId = $this->generateOpportunityUniqueId();

            $opportunity = Opportunity::create([
                'unique_id' => $uniqueId,
                'user_id' => Auth::id(),
                ...$validated,
            ]);

            return $this->respond('Opportunity created successfully', [
                'opportunity' => $opportunity,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->respond('Validation failed: ' . collect($e->errors())->flatten()->first(), null, 422);
        } catch (\Exception $e) {
            Log::error('Error creating opportunity: ' . $e->getMessage());
            return $this->respond('Failed to create opportunity', null, 500);
        }
    }

    public function getOpportunityById(string $unique_id)
    {
        try {
            $opportunity = Opportunity::with(['category', 'user'])
                ->where('unique_id', $unique_id)
                ->firstOrFail();

            return $this->respond('Opportunity fetched successfully', [
                'opportunity' => $opportunity,
            ]);
        } catch (ModelNotFoundException $e) {
            return $this->respond("Opportunity not found with ID: {$unique_id}", null, 404);
        } catch (\Exception $e) {
            Log::error('Error fetching opportunity: ' . $e->getMessage());
            return $this->respond('Failed to fetch opportunity', null, 500);
        }
    }

    public function updateOpportunity(Request $request, string $unique_id)
    {
        try {
            $opportunity = Opportunity::where('unique_id', $unique_id)->firstOrFail();

            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'required|string',
                'location' => 'required|string|max:255',
                'deadline' => 'required|date',
                'category_id' => 'required|exists:categories,id',
                'organization' => 'required|string|max:255',
                'application_link' => 'required|url',
            ]);

            $opportunity->update($validated);

            return $this->respond('Opportunity updated successfully', [
                'opportunity' => $opportunity,
            ]);
        } catch (ModelNotFoundException $e) {
            return $this->respond('Opportunity not found', null, 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->respond('Validation failed: ' . collect($e->errors())->flatten()->first(), null, 422);
        } catch (\Throwable $e) {
            Log::error('Error updating opportunity: ' . $e->getMessage());
            return $this->respond('Failed to update opportunity', null, 500);
        }
    }

    public function deleteOpportunity(string $unique_id)
    {
        try {
            $opportunity = Opportunity::where('unique_id', $unique_id)->firstOrFail();
            $opportunity->delete();

            return $this->respond('Opportunity deleted successfully', [
                'unique_id' => $unique_id,
            ]);
        } catch (ModelNotFoundException $e) {
            return $this->respond('Opportunity not found', null, 404);
        } catch (\Exception $e) {
            Log::error('Error deleting opportunity: ' . $e->getMessage());
            return $this->respond('Failed to delete opportunity', null, 500);
        }
    }
}
