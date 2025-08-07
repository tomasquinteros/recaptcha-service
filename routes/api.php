<?php

use App\Http\Controllers\RecaptchaAnsesController;
use Illuminate\Support\Facades\Route;

Route::prefix('recaptcha')->group(function () {

    Route::prefix('anses')->group(function () {
        Route::get('VL_CO/{per_cuit}', [RecaptchaAnsesController::class, 'VL_CO']);
    });

});
