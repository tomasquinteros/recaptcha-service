<?php

use App\Http\Controllers\Anses\LaboralController;
use Illuminate\Support\Facades\Route;

Route::prefix('anses')->group(function () {
    Route::get('laboral/{per_cuit}', [LaboralController::class, 'VL_CO']);
});
Route::get('dni', );
