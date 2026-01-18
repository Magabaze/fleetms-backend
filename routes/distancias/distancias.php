<?php
// routes/distancias/distancias.php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DistanciaController;

Route::prefix('distancias')->group(function () {
    // Listar distâncias
    Route::get('/', [DistanciaController::class, 'index']);
    
    // Criar distância
    Route::post('/', [DistanciaController::class, 'store']);
    
    // Rotas com ID
    Route::prefix('{distancia}')->group(function () {
        // Ver distância específica
        Route::get('/', [DistanciaController::class, 'show']);
        
        // Atualizar distância
        Route::put('/', [DistanciaController::class, 'update']);
        
        // Excluir distância
        Route::delete('/', [DistanciaController::class, 'destroy']);
    });
});