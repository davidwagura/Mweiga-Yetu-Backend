<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class RolesController extends Controller
{
    public function updateUserRoles(Request $request, $userId)
    {
        $request->validate([
            'roles' => 'required|array',
            'roles.*' => 'string|exists:roles,name',
        ]);

        $user = User::findOrFail($userId);
        $user->syncRoles($request->roles);

        return response()->json([
            'message' => 'User roles updated successfully.',
            'user' => $user->only(['id', 'name', 'email']),
            'roles' => $user->getRoleNames(), 
        ], 200);
    }
}
