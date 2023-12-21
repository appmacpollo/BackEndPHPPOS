<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ImprimirController;
use App\Http\Controllers\DatafonoController;
use App\Http\Controllers\FacturaController;

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
Route::get('/ValidarVentas', [ProductoController::class, 'ValidarCaja']);
Route::post('/ValorPago', [FacturaController::class, 'ValorPago']);
Route::post('/facturar', [FacturaController::class, 'facturar']);

Route::get('/EnviarADatafono/{valorTotal}/{valorImpuestos}/{abreviatura}', [DatafonoController::class, 'EnviarADatafono']);
Route::get('/leerArchivoSalidaDatafono', [DatafonoController::class, 'SalidaDatafono']);

//Express
Route::get('/ValidarVentasExpress', [ProductoController::class, 'ValidarCajaExpress']);
Route::get('/ConsultarProductoExpress', [ProductoController::class, 'ConsultarProductoExpress']);
Route::get('/ImprimirFacturaExpress/{factura}/{clase}/{prefijo}/{maquina}', [ImprimirController::class, 'ImprimirFacturaExpress']);
