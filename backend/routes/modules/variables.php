<?php

use App\Presentation\Http\Controllers\Api\DatasetController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Variables Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::put('/{variable}', [DatasetController::class, 'updateVariable']);
    Route::post('/bulk-update', [DatasetController::class, 'bulkUpdateVariables']);
});
