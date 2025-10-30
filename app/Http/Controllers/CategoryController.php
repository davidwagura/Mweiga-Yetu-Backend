<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CategoryController extends Controller
{
    public function getAllCategories()
    {
        try {
            $categories = Category::all();
            return response()->json([
                'status' => 'success',
                'code' => 200,
                'data' => $categories
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching categories: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'code' => 500,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    public function getAnnouncementCategories()
    {
        try {
            $categories = Category::where('type', 'announcement')->get();
            return response()->json([
                'status' => 'success',
                'code' => 200,
                'data' => $categories
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching announcement categories: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'code' => 500,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    public function getOpportunityCategories()
    {
        try {
            $categories = Category::where('type', 'opportunity')->get();
            return response()->json([
                'status' => 'success',
                'code' => 200,
                'data' => $categories
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching opportunity categories: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'code' => 500,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    public function getEventCategories()
    {
        try {
            $categories = Category::where('type', 'event')->get();
            return response()->json([
                'status' => 'success',
                'code' => 200,
                'data' => $categories
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching event categories: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'code' => 500,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    public function createCategory(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'type' => 'required|in:announcement,opportunity,event'
            ]);

            $category = Category::create($request->validated());

            return response()->json([
                'status' => 'success',
                'code' => 201,
                'message' => 'Category created successfully',
                'data' => $category
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating category: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'code' => 500,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    public function updateCategory(Request $request, $id)
    {
        try {
            $category = Category::findOrFail($id);

            $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'type' => 'required|in:announcement,opportunity,event'
            ]);

            $category->update($request->validated());

            return response()->json([
                'status' => 'success',
                'code' => 200,
                'message' => 'Category updated successfully',
                'data' => $category
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating category: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'code' => $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException ? 404 : 500,
                'message' => $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException ? 'Category not found' : 'Internal server error'
            ], $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException ? 404 : 500);
        }
    }

    public function deleteCategory($id)
    {
        try {
            $category = Category::findOrFail($id);
            $category->delete();

            return response()->json([
                'status' => 'success',
                'code' => 200,
                'message' => 'Category deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting category: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'code' => $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException ? 404 : 500,
                'message' => $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException ? 'Category not found' : 'Internal server error'
            ], $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException ? 404 : 500);
        }
    }
}
