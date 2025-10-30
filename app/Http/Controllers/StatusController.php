<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Status;
use Illuminate\Support\Facades\Log;

class StatusController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $statuses = Status::all();
            return response()->json([
                'status' => 'success',
                'code' => 200,
                'data' => $statuses
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching statuses: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'code' => 500,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // For now, leave protected store behavior to apiResource routes (auth required)
        return response()->json(['message' => 'Not implemented'], 501);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $status = Status::findOrFail($id);
            return response()->json([
                'status' => 'success',
                'code' => 200,
                'data' => $status
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching status: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'code' => $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException ? 404 : 500,
                'message' => $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException ? 'Status not found' : 'Internal server error'
            ], $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException ? 404 : 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        return response()->json(['message' => 'Not implemented'], 501);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        return response()->json(['message' => 'Not implemented'], 501);
    }
}
