<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AgentController;
use App\Http\Controllers\API\RegisterController;
use App\Http\Controllers\API\ProgramController;
use App\Http\Controllers\API\SupportChatController;
use App\Http\Controllers\API\AttendanceController;
use App\Http\Controllers\API\TrainerAttendanceController;
use App\Http\Controllers\API\TrainingProgramController;
use App\Http\Controllers\API\ProgramApplicationController;

/*
|--------------------------------------------------------------------------
| Public authentication routes
|--------------------------------------------------------------------------
*/
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
| Public program applications
|--------------------------------------------------------------------------
*/
Route::post('applications', [ProgramApplicationController::class, 'store']);

/*
|--------------------------------------------------------------------------
| Public support chat routes for website visitors
|--------------------------------------------------------------------------
*/
Route::prefix('support-chat')->group(function () {
    Route::get('status', [SupportChatController::class, 'status']);
    Route::post('session', [SupportChatController::class, 'startSession']);
    Route::get('session/{token}', [SupportChatController::class, 'showSession']);
    Route::post('session/{token}/message', [SupportChatController::class, 'sendGuestMessage']);
});

/*
|--------------------------------------------------------------------------
| Protected routes for authenticated users
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
        | User management routes
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
        | Helper option routes
        |--------------------------------------------------------------------------
        */
        Route::get('roles', 'roles');
        Route::get('users-program-options', 'programOptions');
        Route::get('programs/{program}/users', 'programUsers');
        Route::post('programs/{program}/users/sync', 'syncProgramUsers');
    });

    /*
    |--------------------------------------------------------------------------
    | Agent management routes
    |--------------------------------------------------------------------------
    */
    Route::prefix('agents')->controller(AgentController::class)->group(function () {
        // Admin / CEO
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{id}', 'show');
        Route::put('/{id}', 'update');
        Route::patch('/{id}', 'update');

        // Agent own box / dashboard
        Route::get('/me/dashboard', 'myDashboard');
        Route::post('/students/register', 'registerStudent');
    });

    /*
    |--------------------------------------------------------------------------
    | Protected program routes
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
    | Protected application management
    |--------------------------------------------------------------------------
    */
    Route::get('applications', [ProgramApplicationController::class, 'index']);
    Route::get('applications/{id}', [ProgramApplicationController::class, 'show']);
    Route::put('applications/{id}', [ProgramApplicationController::class, 'update']);
    Route::patch('applications/{id}', [ProgramApplicationController::class, 'update']);
    Route::delete('applications/{id}', [ProgramApplicationController::class, 'destroy']);

    /*
    |--------------------------------------------------------------------------
    | Student/Trainee attendance routes
    |--------------------------------------------------------------------------
    */
    Route::apiResource('attendances', AttendanceController::class);

    /*
    |--------------------------------------------------------------------------
    | Trainer attendance routes
    |--------------------------------------------------------------------------
    */
    Route::get('trainer-attendances', [TrainerAttendanceController::class, 'index']);
    Route::post('trainer-attendances', [TrainerAttendanceController::class, 'store']);
    Route::get('trainer-attendances/{id}', [TrainerAttendanceController::class, 'show']);
    Route::put('trainer-attendances/{id}', [TrainerAttendanceController::class, 'update']);
    Route::patch('trainer-attendances/{id}', [TrainerAttendanceController::class, 'update']);
    Route::delete('trainer-attendances/{id}', [TrainerAttendanceController::class, 'destroy']);
    Route::post('trainer-attendances/{id}/pay', [TrainerAttendanceController::class, 'pay']);

    /*
    |--------------------------------------------------------------------------
    | Protected support chat routes for agents/admin
    |--------------------------------------------------------------------------
    */
    Route::prefix('support-chat/agent')->group(function () {
        Route::get('conversations', [SupportChatController::class, 'agentConversations']);
        Route::get('conversations/{id}', [SupportChatController::class, 'agentShowConversation']);
        Route::post('conversations/{id}/message', [SupportChatController::class, 'agentSendMessage']);
        Route::post('conversations/{id}/take-over', [SupportChatController::class, 'takeOver']);
        Route::post('conversations/{id}/close', [SupportChatController::class, 'closeConversation']);
        Route::post('presence', [SupportChatController::class, 'agentPresence']);
    });
});