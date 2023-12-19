<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ImprimirController;
use App\Http\Controllers\DatafonoController;

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

Route::get('/EnviarADatafono/{valorTotal}/{valorImpuestos}/{abreviatura}', [DatafonoController::class, 'EnviarADatafono']);
Route::get('/leerArchivoSalidaDatafono', [DatafonoController::class, 'SalidaDatafono']);
