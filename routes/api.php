<?php

use App\Http\Controllers\Anses\LaboralController;
use App\Http\Controllers\BCRAController;
use App\Http\Controllers\BusquedaART;
use App\Http\Controllers\RegistroGraduadosController;
use Illuminate\Support\Facades\Route;

Route::get('dni', LaboralController::class);
Route::get('art', BusquedaART::class);
Route::get('bcra/{cuit}', BcraController::class);
Route::get('registro_graduados', RegistroGraduadosController::class);
