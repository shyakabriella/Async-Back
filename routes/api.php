<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\RegisterController;
use App\Http\Controllers\API\ProgramController;
use App\Http\Controllers\API\SupportChatController;
use App\Http\Controllers\API\AttendanceController;
use App\Http\Controllers\API\TrainingProgramController;
use App\Http\Controllers\API\ProgramApplicationController;

Route::controller(RegisterController::class)->group(function () {
    Route::post('register', 'register');
    Route::post('login', 'login');

    // Password reset / account setup
    Route::post('forgot-password', 'forgotPassword');
    Route::post('reset-password', 'resetPassword');
});

/*
|--------------------------------------------------------------------------
| Temporary test route
|--------------------------------------------------------------------------
*/
Route::get('programs-test', function () {
    return response()->json([
        'success' => true,
        'message' => 'Programs test route is working',
    ]);
});

/*
|--------------------------------------------------------------------------
| Public routes for website/frontend
|--------------------------------------------------------------------------
*/
Route::apiResource('programs', ProgramController::class)->only(['index', 'show']);
Route::apiResource('training-programs', TrainingProgramController::class)->only(['index', 'show']);

/*
|--------------------------------------------------------------------------
| Public curriculum routes
|--------------------------------------------------------------------------
*/
Route::get('programs/{program}/curriculum', [ProgramController::class, 'curriculumIndex']);

/*
|--------------------------------------------------------------------------
| Student submits application
|--------------------------------------------------------------------------
*/
Route::post('applications', [ProgramApplicationController::class, 'store']);

/*
|--------------------------------------------------------------------------
| Public support chat routes for website visitors
|--------------------------------------------------------------------------
*/
Route::prefix('support-chat')->group(function () {
    Route::get('/status', [SupportChatController::class, 'status']);
    Route::post('/session', [SupportChatController::class, 'startSession']);
    Route::get('/session/{token}', [SupportChatController::class, 'showSession']);
    Route::post('/session/{token}/message', [SupportChatController::class, 'sendGuestMessage']);
});

/*
|--------------------------------------------------------------------------
| Protected routes for admin/dashboard
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    /*
    |--------------------------------------------------------------------------
    | Authenticated account routes
    |--------------------------------------------------------------------------
    */
    Route::controller(RegisterController::class)->group(function () {
        Route::get('me', 'me');
        Route::post('logout', 'logout');

        /*
        |--------------------------------------------------------------------------
        | User management routes (inside RegisterController)
        |--------------------------------------------------------------------------
        */
        Route::get('users', 'index');
        Route::post('users', 'store');
        Route::get('users/{id}', 'show');
        Route::put('users/{id}', 'update');
        Route::patch('users/{id}', 'update');
        Route::delete('users/{id}', 'destroy');
        Route::post('users/{id}/toggle-status', 'toggleStatus');

        /*
        |--------------------------------------------------------------------------
        | Optional helper routes
        |--------------------------------------------------------------------------
        */
        Route::get('roles', 'roles');
        Route::get('users-program-options', 'programOptions');
        Route::get('programs/{program}/users', 'programUsers');
        Route::post('programs/{program}/users/sync', 'syncProgramUsers');
    });

    /*
    |--------------------------------------------------------------------------
    | Programs
    |--------------------------------------------------------------------------
    */
    Route::apiResource('programs', ProgramController::class)->except(['index', 'show']);
    Route::apiResource('training-programs', TrainingProgramController::class)->except(['index', 'show']);

    /*
    |--------------------------------------------------------------------------
    | Protected curriculum routes
    |--------------------------------------------------------------------------
    */
    Route::post('programs/{program}/curriculum', [ProgramController::class, 'curriculumStore']);
    Route::put('programs/{program}/curriculum/{curriculumId}', [ProgramController::class, 'curriculumUpdate']);
    Route::patch('programs/{program}/curriculum/{curriculumId}', [ProgramController::class, 'curriculumUpdate']);
    Route::delete('programs/{program}/curriculum/{curriculumId}', [ProgramController::class, 'curriculumDestroy']);

    /*
    |--------------------------------------------------------------------------
    | Admin manages applications
    |--------------------------------------------------------------------------
    */
    Route::get('applications', [ProgramApplicationController::class, 'index']);
    Route::get('applications/{id}', [ProgramApplicationController::class, 'show']);
    Route::patch('applications/{id}', [ProgramApplicationController::class, 'update']);
    Route::put('applications/{id}', [ProgramApplicationController::class, 'update']);
    Route::delete('applications/{id}', [ProgramApplicationController::class, 'destroy']);

    /*
    |--------------------------------------------------------------------------
    | Attendance routes
    |--------------------------------------------------------------------------
    */
    Route::apiResource('attendances', AttendanceController::class);

    /*
    |--------------------------------------------------------------------------
    | Protected support chat routes for agents/admin
    |--------------------------------------------------------------------------
    */
    Route::prefix('support-chat/agent')->group(function () {
        Route::get('/conversations', [SupportChatController::class, 'agentConversations']);
        Route::get('/conversations/{id}', [SupportChatController::class, 'agentShowConversation']);
        Route::post('/conversations/{id}/message', [SupportChatController::class, 'agentSendMessage']);
        Route::post('/conversations/{id}/take-over', [SupportChatController::class, 'takeOver']);
        Route::post('/conversations/{id}/close', [SupportChatController::class, 'closeConversation']);
        Route::post('/presence', [SupportChatController::class, 'agentPresence']);
    });
});