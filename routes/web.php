<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ImprimirController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/ConsultarProducto/{ean}', [ProductoController::class, 'ConsultarProducto']);
Route::get('/ImprimirFactura/{factura}/{clase}/{prefijo}/{maquina}', [ImprimirController::class, 'ImprimirFactura']);
