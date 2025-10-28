<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\Notification;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Mail;
use App\Mail\PasswordResetMail;
use App\Mail\UserPasswordResetMail;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Helpers\CloudinaryHelper;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Jobs\UploadUserImage;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        try {
            $request->validate([
                'name'             => 'required|string|max:255',
                'email'            => 'required|email|unique:users,email',
                'phone_number'     => 'required|string|unique:users,phone_number',
                'password'         => 'required|string|min:8',
                'confirm_password' => 'required|same:password',
            ]);

            $user = User::create([
                'name'         => $request->name,
                'email'        => $request->email,
                'phone_number' => $request->phone_number,
                'password'     => Hash::make($request->password),
                'image_path'   => $request->image_path ?? null,
                'is_active'    => true,
            ]);

            $user->assignRole('user');

            return response()->json([
                'status'  => 'success',
                'message' => 'User created successfully',
                'code'    => 201,
                'data'    => $user,
            ], 200);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first();

            return response()->json([
                'status'  => 'error',
                'message' => $message,
                'code'    => 422,
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
                'code'    => 500,
            ], 500);
        }
    }

    public function login(Request $request)
    {
        try {
            $request->validate([
                'email'    => 'required|string|email',
                'password' => 'required|string',
            ]);

            if (!Auth::attempt($request->only('email', 'password'))) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Invalid email or password',
                    'code'    => 401,
                ], 401);
            }

            $user = Auth::user();
            $user->makeHidden(['roles', 'permissions']);

            return response()->json([
                'status'  => 'success',
                'message' => 'Login successful',
                'code'    => 201,
                'data'    => [
                    'user'        => $user,
                    'token'       => $user->createToken('ApiToken')->plainTextToken,
                ],
            ], 200);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first();
            return response()->json([
                'status'  => 'error',
                'message' => $message,
                'code'    => 422,
            ], 422);
        }
    }

    public function logout(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'User not authenticated',
                    'code'    => 401,
                ], 401);
            }

            $user->tokens()->delete();

            return response()->json([
                'status'  => 'success',
                'message' => 'Successfully logged out',
                'code'    => 201,
                'data'    => null,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
                'code'    => 500,
            ], 500);
        }
    }

    public function changePassword(Request $request)
    {
        try {
            $request->validate([
                'current_password' => 'required',
                'new_password'     => 'required|min:8',
                'confirm_password' => 'required|same:new_password',
            ]);

            $user = $request->user();

            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Current password is incorrect',
                    'code'    => 400,
                ], 400);
            }

            if (Hash::check($request->new_password, $user->password)) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'New password must be different from the current one',
                    'code'    => 400,
                ], 400);
            }

            $user->password = Hash::make($request->new_password);
            $user->save();

            Notification::create([
                'user_id' => $user->id,
                'type'    => 'password_change',
                'title'   => 'Password Changed',
                'message' => 'Your account password was changed successfully.',
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Password changed successfully',
                'code'    => 201,
                'data'    => null,
            ], 200);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first();
            return response()->json([
                'status'  => 'error',
                'message' => $message,
                'code'    => 422,
            ], 422);
        }
    }

    public function forgotPassword(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email|exists:users,email',
            ]);

            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'User with this email does not exist',
                    'code'    => 404,
                ], 404);
            }

            $existingToken = DB::table('password_reset_tokens')
                ->where('email', $user->email)
                ->first();

            if ($existingToken && Carbon::parse($existingToken->created_at)->addMinutes(5)->isFuture()) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Password reset already requested. Try again later.',
                    'code'    => 429,
                ], 429);
            }

            $token = Str::random(40);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                [
                    'token'      => Hash::make($token),
                    'created_at' => Carbon::now()
                ]
            );

            $expiresAt = Carbon::now()->addMinutes(30)->toDateTimeString();
            $resetLink = url("http://localhost:5174/reset-password?token=$token&email=" . urlencode($user->email));

            Mail::to($user->email)->send(new UserPasswordResetMail($resetLink, $expiresAt));

            return response()->json([
                'status'  => 'success',
                'message' => 'Password reset link sent to your email',
                'code'    => 201,
            ], 200);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first();
            return response()->json([
                'status'  => 'error',
                'message' => $message,
                'code'    => 422,
            ], 422);
        }
    }

    public function resetPassword(Request $request)
    {
        try {
            $request->validate([
                'token'           => 'required',
                'email'           => 'required|email',
                'new_password'    => 'required|min:8',
                'confirm_password' => 'required|same:new_password',
            ]);

            $record = DB::table('password_reset_tokens')
                ->where('email', $request->email)
                ->first();

            if (!$record) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Invalid password reset token',
                    'code'    => 400,
                ], 400);
            }

            if (Carbon::parse($record->created_at)->addMinutes(30)->isPast()) {
                DB::table('password_reset_tokens')->where('email', $request->email)->delete();
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Password reset token has expired',
                    'code'    => 400,
                ], 400);
            }

            if (!Hash::check($request->token, $record->token)) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Invalid password reset token',
                    'code'    => 400,
                ], 400);
            }

            $user = User::where('email', $request->email)->first();
            $user->password = Hash::make($request->new_password);
            $user->save();

            DB::table('password_reset_tokens')->where('email', $request->email)->delete();

            Notification::create([
                'user_id' => $user->id,
                'type'    => 'password_change',
                'title'   => 'Password Changed',
                'message' => 'Your account password was changed successfully.',
            ]);
            return response()->json([
                'status'  => 'success',
                'message' => 'Password has been reset successfully',
                'code'    => 201,
            ], 200);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first();
            return response()->json([
                'status'  => 'error',
                'message' => $message,
                'code'    => 422,
            ], 422);
        }
    }

    public function getUserData(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'User not authenticated',
                    'code'    => 401,
                ], 401);
            }

            $user->setHidden(['roles', 'permissions']);

            return response()->json([
                'status'  => 'success',
                'message' => 'User fetched successfully',
                'code'    => 200,
                'data'    => [
                    'user' => $user,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
                'code'    => 500,
            ], 500);
        }
    }

    public function getUserAccessControls($user_id)
    {
        try {
            $user = User::find($user_id);

            if (!$user) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'User not found',
                    'code'    => 404,
                ], 404);
            }

            $role        = $user->getRoleNames()->first();
            $permissions = $user->getAllPermissions()->pluck('name');

            return response()->json([
                'status'  => 'success',
                'message' => 'User access controls retrieved',
                'code'    => 200,
                'data'    => [
                    'roles'       => $role,
                    'permissions' => $permissions,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
                'code'    => 500,
            ], 500);
        }
    }

    public function updateUser(Request $request, $id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'User not found',
                    'code'    => 404,
                ], 404);
            }

            $validated = $request->validate([
                'name'            => 'sometimes|string|max:255',
                'email'           => 'sometimes|email|unique:users,email,' . $user->id,
                'phone_number'    => 'sometimes|string|unique:users,phone_number,' . $user->id,
                'whatsApp_number' => 'sometimes|string|nullable',
                'image'           => 'nullable|image|mimes:jpeg,png,jpg,gif,webp',
                'delete_image'    => 'nullable|string|in:true,false',
            ]);

            $deleteImage = $request->has('delete_image')
                ? filter_var($request->input('delete_image'), FILTER_VALIDATE_BOOLEAN)
                : false;

            if ($deleteImage && $user->image_path) {
                try {
                    $publicId = $this->extractPublicIdFromUrl($user->image_path);
                    if ($publicId) {
                        CloudinaryHelper::destroy($publicId);
                    }
                    $user->image_path = null;
                } catch (\Exception $e) {
                    Log::error('Failed to delete old user image from Cloudinary: ' . $e->getMessage());
                }
            }

            if ($request->hasFile('image')) {
                $storedPath = $request->file('image')->store('temp/user-images');
                if ($storedPath) {
                    UploadUserImage::dispatch($user->id, $storedPath, ['folder' => 'users']);
                }
            }

            $user->name            = $request->input('name', $user->name);
            $user->email           = $request->input('email', $user->email);
            $user->phone_number    = $request->input('phone_number', $user->phone_number);
            $user->whatsApp_number = $request->input('whatsApp_number', $user->whatsApp_number);
            $user->save();

            Notification::create([
                'user_id' => $user->id,
                'type'    => 'profile_update',
                'title'   => 'Profile Updated',
                'message' => 'Your profile information was updated successfully.',
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => 'User updated successfully',
                'code'    => 200,
                'data'    => $user->fresh(),
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            $message = collect($e->errors())->flatten()->first();
            return response()->json([
                'status'  => 'error',
                'message' => $message,
                'code'    => 422,
            ], 422);
        } catch (\Exception $e) {
            Log::error('User update failed: ' . $e->getMessage());
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
                'code'    => 500,
            ], 500);
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

    public function getUserDataByEmail($email)
    {
        try {
            $user = User::where('email', $email)->first();

            if (!$user) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'User not found',
                    'code'    => 404,
                ], 404);
            }

            $user->setHidden(['roles', 'permissions']);

            return response()->json([
                'status'  => 'success',
                'message' => 'User fetched successfully',
                'code'    => 200,
                'data'    => $user,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
                'code'    => 500,
            ], 500);
        }
    }
}
