<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\RegisterController;
use App\Http\Controllers\API\ProgramController;
use App\Http\Controllers\API\TrainingProgramController;

Route::controller(RegisterController::class)->group(function () {
    Route::post('register', 'register');
    Route::post('login', 'login');
});

/*
|--------------------------------------------------------------------------
| Temporary test route
|--------------------------------------------------------------------------
*/
Route::get('programs-test', function () {
    return response()->json([
        'success' => true,
        'message' => 'Programs test route is working'
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
| Protected routes for admin/dashboard
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('programs', ProgramController::class)->except(['index', 'show']);
    Route::apiResource('training-programs', TrainingProgramController::class)->except(['index', 'show']);
});