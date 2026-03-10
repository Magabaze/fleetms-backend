<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PublicTrackingController;

/*
|--------------------------------------------------------------------------
| API Routes Públicas (Sem Autenticação)
|--------------------------------------------------------------------------
*/

// Rastreamento público
Route::prefix('public')->group(function () {
    // Rastrear por código: BD-56778/1 ou BD-56778-1
    Route::get('/tracking/{code}', [PublicTrackingController::class, 'trackByCode'])
        ->where('code', '.*'); // Aceita slashes no código
});