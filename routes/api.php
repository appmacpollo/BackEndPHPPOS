<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ImprimirController;
use App\Http\Controllers\DatafonoController;
use App\Http\Controllers\FacturaController;
use App\Http\Controllers\ComunController;

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
Route::get('/ValidarVentas/{express}', [ProductoController::class, 'ValidarCaja']);
Route::post('/ValorPago', [FacturaController::class, 'ValorPago']);
Route::post('/facturar', [FacturaController::class, 'facturar']);
Route::post('/ProductoOferta', [ProductoController::class, 'ProductoOferta']);
Route::post('/Facturas', [FacturaController::class, 'Facturas']);
Route::post('/AnularFactura', [FacturaController::class, 'AnularFactura']);
Route::get('/ConsultarBolsas/{express}', [ProductoController::class, 'ConsultarBolsas']);
Route::post('/Autorizacion', [ComunController::class, 'Autorizacion']);
Route::get('/ImprimirAnulacionFactura/{factura}/{clase}/{prefijo}/{maquina}/{express}', [ImprimirController::class, 'ImprimirAnulacionFactura']);

//Datafono
Route::get('/EnviarADatafono/{valorTotal}/{valorImpuestos}/{abreviatura}/{express}', [DatafonoController::class, 'EnviarADatafono']);
Route::get('/leerArchivoSalidaDatafono', [DatafonoController::class, 'SalidaDatafono']);
Route::post('/AnulacionDatafono', [DatafonoController::class, 'AnulacionDatafono']);

//Express
Route::get('/ConsultarProductoExpress/{grupo}', [ProductoController::class, 'ConsultarProductoExpress']);
Route::get('/ImprimirFacturaExpress/{factura}/{clase}/{prefijo}/{maquina}', [ImprimirController::class, 'ImprimirFacturaExpress']);
Route::get('/ConsultarGrupos', [ProductoController::class, 'ConsultarGruposExpress']);
Route::post('/facturarExpress', [FacturaController::class, 'facturarExpress']);