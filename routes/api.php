<?php

use App\Http\Controllers\Anses\LaboralController;
use Illuminate\Support\Facades\Route;

Route::prefix('anses')->group(function () {
    Route::get('laboral/VL_CO/{per_cuit}', [LaboralController::class, 'VL_CO']);
});
