<?php

use App\Http\Controllers\Api\ScriptExecutorCallbackController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Internal API Routes
|--------------------------------------------------------------------------
|
| These routes are for internal communication between services, such as
| the Deno script executor calling back to Laravel for API operations.
|
*/

Route::prefix('internal')->group(function () {
    // Script executor callback endpoint
    Route::post('/script-executor/callback', [ScriptExecutorCallbackController::class, 'handleCallback'])
        ->name('api.internal.script-executor.callback')
        ->middleware(['throttle:1000,1']); // High rate limit for internal calls
});