<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ImprimirController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::get('/ConsultarProducto/{ean}', [ProductoController::class, 'ConsultarProducto']);
Route::get('/ImprimirFactura/{factura}/{clase}/{prefijo}/{maquina}', [ImprimirController::class, 'ImprimirFactura']);