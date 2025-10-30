<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\RolesController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\OpportunityController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\StatusController;
use App\Http\Controllers\NotificationController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::get('/user/email/{email}', [AuthController::class, 'getUserDataByEmail']);

// Public status endpoints (list and show)
Route::get('/projects/statuses', [StatusController::class, 'index']);
Route::get('/statuses/{id}', [StatusController::class, 'show']);

Route::get('/notifications', [NotificationController::class, 'getUserNotifications']);
Route::post('/notifications/read', [NotificationController::class, 'markAllAsRead']);
Route::post('/notifications/{notificationId}/read', [NotificationController::class, 'markAsReadById']);

Route::middleware('auth:sanctum')->group(function () {
    Route::put('/update-user/{userId}', [AuthController::class, 'updateUser']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
    Route::get('/user', [AuthController::class, 'getUserData']);

    Route::put('/user/{userId}/roles', [RolesController::class, 'updateUserRoles']);
    Route::get('/user/{userId}/access-controls', [AuthController::class, 'getUserAccessControls']);

    // Categories
    Route::prefix('categories')->group(function () {
        Route::get('/', [CategoryController::class, 'getAllCategories']);
        Route::get('/announcements', [CategoryController::class, 'getAnnouncementCategories']);
        Route::get('/opportunities', [CategoryController::class, 'getOpportunityCategories']);
        Route::get('/events', [CategoryController::class, 'getEventCategories']);
        Route::post('/', [CategoryController::class, 'createCategory']);
        Route::put('/{id}', [CategoryController::class, 'updateCategory']);
        Route::delete('/{id}', [CategoryController::class, 'deleteCategory']);
    });

    // Announcements
    Route::get('/announcements', [AnnouncementController::class, 'getAllAnnouncements']);
    Route::post('/announcements', [AnnouncementController::class, 'createAnnouncement']);
    Route::get('/announcements/{unique_id}', [AnnouncementController::class, 'getAnnouncementById']);
    Route::put('/announcements/{unique_id}', [AnnouncementController::class, 'updateAnnouncement']);
    Route::delete('/announcements/{unique_id}', [AnnouncementController::class, 'deleteAnnouncement']);

    // Statuses
    Route::apiResource('statuses', StatusController::class);

    // Opportunities
    Route::get('/opportunities', [OpportunityController::class, 'getAllOpportunities']);
    Route::post('/opportunities', [OpportunityController::class, 'createOpportunity']);
    Route::get('/opportunities/{unique_id}', [OpportunityController::class, 'getOpportunityById']);
    Route::put('/opportunities/{unique_id}', [OpportunityController::class, 'updateOpportunity']);
    Route::delete('/opportunities/{unique_id}', [OpportunityController::class, 'deleteOpportunity']);
    Route::get('/opportunities/my-opportunities', [OpportunityController::class, 'myOpportunities']);

    // Projects
    Route::get('/projects', [ProjectController::class, 'getAllProjects']);
    Route::post('/projects', [ProjectController::class, 'createProject']);
    Route::get('/projects/{unique_id}', [ProjectController::class, 'getProjectById']);
    Route::put('/projects/{unique_id}', [ProjectController::class, 'updateProject']);
    Route::delete('/projects/{unique_id}', [ProjectController::class, 'deleteProject']);
    Route::get('/projects/my-projects', [EventController::class, 'getUserProjects']);

    // Events
    Route::get('/events', [EventController::class, 'getAllEvents']);
    Route::post('/events', [EventController::class, 'createEvent']);
    Route::get('/events/my-events', [EventController::class, 'getUserEvents']);
    Route::get('/events/{unique_id}', [EventController::class, 'getEventById']);
    Route::put('/events/{unique_id}', [EventController::class, 'updateEvent']);
    Route::patch('/events/{unique_id}/attending', [EventController::class, 'updateAttendance']);
    Route::delete('/events/{unique_id}', [EventController::class, 'deleteEvent']);
});
