<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class EventController extends Controller
{
    private function respond($message, $data = null, $code = 200)
    {
        return response()->json([
            'status' => $code >= 200 && $code < 300 ? 'success' : 'error',
            'code' => $code,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    private function generateEventUniqueId(): string
    {
        $timestamp = now()->format('YmdHis');
        $random = Str::upper(Str::random(5));
        $uniqueId = "EVT-{$timestamp}-{$random}";

        while (Event::where('unique_id', $uniqueId)->exists()) {
            $random = Str::upper(Str::random(5));
            $uniqueId = "EVT-{$timestamp}-{$random}";
        }

        return $uniqueId;
    }

    public function getAllEvents(Request $request)
    {
        try {
            $user = Auth::user();
            $perPage = $request->query('per_page', 10);

            $events = Event::with(['category', 'attendees:id'])
                ->latest()
                ->paginate($perPage);

            $events->getCollection()->transform(function ($event) use ($user) {
                $event->is_attending = $user ? $event->attendees->contains('id', $user->id) : false;
                unset($event->attendees);
                return $event;
            });

            return $this->respond('Events fetched successfully', [
                'events' => $events->items(),
                'pagination' => [
                    'current_page' => $events->currentPage(),
                    'per_page' => $events->perPage(),
                    'total' => $events->total(),
                    'last_page' => $events->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching events: ' . $e->getMessage());
            return $this->respond('Failed to fetch events', null, 500);
        }
    }

    public function getUserEvents(Request $request)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return $this->respond('Unauthorized', null, 401);
            }

            $perPage = $request->query('per_page', 10);

            $events = Event::with('category')
                ->where('user_id', $user->id)
                ->latest()
                ->paginate($perPage);

            return $this->respond('User events fetched successfully', [
                'events' => $events->items(),
                'pagination' => [
                    'current_page' => $events->currentPage(),
                    'per_page' => $events->perPage(),
                    'total' => $events->total(),
                    'last_page' => $events->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching user events: ' . $e->getMessage());
            return $this->respond('Failed to fetch user events', null, 500);
        }
    }

    public function createEvent(Request $request)
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'required|string',
                'start_dateTime' => 'required|date',
                'end_dateTime' => 'required|date|after:start_dateTime',
                'category_id' => 'required|exists:categories,id',
                'urgent' => 'required|boolean',
                'location' => 'required|string',
            ]);

            $uniqueId = $this->generateEventUniqueId();

            $event = Event::create([
                'unique_id' => $uniqueId,
                'user_id' => Auth::id(),
                ...$validated,
            ]);

            return $this->respond('Event created successfully', [
                'event' => $event,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->respond('Validation failed: ' . collect($e->errors())->flatten()->first(), null, 422);
        } catch (\Exception $e) {
            Log::error('Error creating event: ' . $e->getMessage());
            return $this->respond('Failed to create event', null, 500);
        }
    }

    public function getEventById($unique_id)
    {
        try {
            $event = Event::with('category')->where('unique_id', $unique_id)->firstOrFail();

            return $this->respond('Event fetched successfully', [
                'event' => $event,
            ]);
        } catch (ModelNotFoundException $e) {
            return $this->respond("Event not found with ID: {$unique_id}", null, 404);
        } catch (\Exception $e) {
            Log::error('Error fetching event: ' . $e->getMessage());
            return $this->respond('Failed to fetch event', null, 500);
        }
    }

    public function updateEvent(Request $request, $unique_id)
    {
        try {
            $event = Event::where('unique_id', $unique_id)->firstOrFail();

            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'required|string',
                'start_dateTime' => 'required|date',
                'end_dateTime' => 'required|date|after:start_dateTime',
                'category_id' => 'required|exists:categories,id',
                'urgent' => 'required|boolean',
                'location' => 'required|string',
            ]);

            $event->update($validated);

            return $this->respond('Event updated successfully', [
                'event' => $event,
            ]);
        } catch (ModelNotFoundException $e) {
            return $this->respond('Event not found', null, 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->respond('Validation failed: ' . collect($e->errors())->flatten()->first(), null, 422);
        } catch (\Throwable $e) {
            Log::error('Error updating event: ' . $e->getMessage());
            return $this->respond('Failed to update event: ' . $e->getMessage(), null, 500);
        }
    }

    public function updateAttendance($unique_id)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return $this->respond('Unauthorized', null, 401);
            }

            $event = Event::where('unique_id', $unique_id)->firstOrFail();

            if ($event->attendees()->where('user_id', $user->id)->exists()) {
                return $this->respond('You have already marked attendance for this event', null, 409);
            }

            $event->attendees()->attach($user->id);
            $event->increment('attending_count');

            return $this->respond('Attendance marked successfully', [
                'event_id' => $event->id,
                'user_id' => $user->id,
                'attending_count' => $event->attending_count,
            ]);
        } catch (ModelNotFoundException $e) {
            return $this->respond('Event not found', null, 404);
        } catch (\Throwable $e) {
            Log::error('Error incrementing attendance: ' . $e->getMessage());
            return $this->respond('Failed to update attendance: ' . $e->getMessage(), null, 500);
        }
    }

    public function deleteEvent($unique_id)
    {
        try {
            $event = Event::where('unique_id', $unique_id)->firstOrFail();
            $event->delete();

            return $this->respond('Event deleted successfully', [
                'unique_id' => $unique_id,
            ]);
        } catch (ModelNotFoundException $e) {
            return $this->respond('Event not found', null, 404);
        } catch (\Exception $e) {
            Log::error('Error deleting event: ' . $e->getMessage());
            return $this->respond('Failed to delete event', null, 500);
        }
    }
}
