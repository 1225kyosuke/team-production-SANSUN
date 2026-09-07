<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CsvController;
use App\Http\Controllers\GradeController;
use App\Http\Controllers\MasterController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/password/setup', [AuthController::class, 'setupPassword']);
Route::post('/password/request-reset', [AuthController::class, 'requestReset']);
Route::middleware('api.auth')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/dashboard', [MasterController::class, 'dashboard']);
    Route::get('/subjects', [GradeController::class, 'subjects']);
    Route::get('/subjects/{subject}/grades', [GradeController::class, 'index']);
    Route::put('/subjects/{subject}/grades', [GradeController::class, 'bulkSave']);
    Route::put('/subjects/{subject}/weights', [GradeController::class, 'updateWeights']);
    Route::post('/subjects/{subject}/finalize', [GradeController::class, 'finalize']);
    Route::post('/subjects/{subject}/unfinalize', [GradeController::class, 'unfinalize']);
    Route::get('/subjects/{subject}/histories', [GradeController::class, 'histories']);
    Route::get('/masters/users/{user}', [MasterController::class, 'showUser']);
    Route::post('/masters/users/{user}/password-reset', [MasterController::class, 'sendUserPasswordReset']);
    Route::post('/masters/users/{user}/role-approval', [MasterController::class, 'approveUserRole']);
    Route::get('/masters/{type}', [MasterController::class, 'index']);
    Route::post('/masters/{type}', [MasterController::class, 'store']);
    Route::put('/masters/{type}/{id}', [MasterController::class, 'update']);
    Route::delete('/masters/{type}/{id}', [MasterController::class, 'destroy']);
    Route::put('/subjects/{subject}/assign', [MasterController::class, 'assign']);
    Route::post('/csv/import', [CsvController::class, 'import']);
    Route::get('/reports/csv', [ReportController::class, 'csv']);
    Route::get('/reports/pdf', [ReportController::class, 'pdf']);
    Route::get('/students/{student}/history', [ReportController::class, 'studentHistory']);
});
