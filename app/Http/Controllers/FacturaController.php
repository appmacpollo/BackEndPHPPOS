<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ComunController;
use Illuminate\Support\Facades\DB;

class FacturaController extends Controller
{

    public function ValorPago(Request $request) {    
        $data = $request->json()->all();
        $i = 1;
        foreach ($data as $values) {
            $producto = $values['producto'];
            $cantidad = $values['cantidad'];
            $oferta = $values['oferta'];
            $productos[] = array('id' => $i, 'producto' => $producto, 'cantidad' => $cantidad, 'oferta' => $oferta);
            $i++;
        }
        
        if(count($data) == 0)
        {
            return response()->json([
                'status' => true,
                'message' => "Valores no encontrados",
                'valorPago' => 0,
                'valorImpuesto' => 0,
            ], 200);
        }

        //Tomar Datos de resolucion.
        $claseFactura = env('claseFactura');
        $maquina = env('maquina');
        $prefijo = '';

        $resolucionFacturas = DB::connection('sqlsrv')->select('SELECT top 1 PrefijoFactura prefijo, Resolucion resolucion, Consecutivo consecutivo, NumeroDesde desde, '
            .' NumeroHasta hasta, FechaResolucionHasta fechaResHasta '
            .' from ResolucionFacturas '
            ."where ClaseFactura = '$claseFactura' AND Maquina = '$maquina' AND FechaAplicaHasta is null AND IndKiosco = 'X'");

        foreach ($resolucionFacturas as $value)
        {
            $prefijo = $value->prefijo;
            $consecutivo = $value->consecutivo;
            $consecutivoDesde = $value->desde;
            $consecutivoHasta = $value->hasta;
        }

        if(count($resolucionFacturas) == 0)
        {
            return response()->json([
                'status' => false,
                'message' => "Lo sentimos, No se encontro una resolucion valida.",
                'valorPago' => 0,
                'valorImpuesto' => 0,
            ], 200);
        }

        if( ($consecutivo < $consecutivoDesde) || ($consecutivo > $consecutivoHasta) )
        {
            return response()->json([
                'status' => false,
                'message' => "Lo sentimos, El consecutivo fuera del rango .",
                'valorPago' => 0,
                'valorImpuesto' => 0,
            ], 200);
        }

        if(count($productos) > 0) $items = $this->facturacionProductos($productos);

        $valorPago = 0;
        $valorImpuesto = 0;
        
        if(count($items) > 0)
        {
            foreach ($items as $value)
            {
                $valorPago += $value['valor'] - $value['descuento'];
                $valorImpuesto += $value['iva'] + $value['ivaUltra'];
            }
        }

        return response()->json([
            'status' => true,
            'message' => "Valores Calculados",
            'valorPago' => $valorPago + $valorImpuesto ,
            'valorImpuesto' => $valorImpuesto,
        ], 200);
    }

    public function facturar(Request $request) {     

        $data = $request->json()->all();
        $i = 1;
        $numeroReferencia = $numRrn = $numRec = $abreviatura =  $digitos = 0;
        $franquicia = $tipoCuenta = '';
        foreach ($data['Producto'] as $values) {
            $producto = $values['producto'];
            $cantidad = $values['cantidad'];
            $oferta = $values['oferta'];
            $productos[] = array('id' => $i, 'producto' => $producto, 'cantidad' => $cantidad, 'oferta' => $oferta);
            $i++;
        }

        foreach ($data['movimiento'] as $values) {
            $numeroReferencia = $values['numeroReferencia'];
            $franquicia = $values['franquicia'];
            $digitos = $values['digitos'];
            $numRec = $values['numRec'];
            $tipoCuenta = $values['tipoCuenta'];
            $numRrn = $values['numRrn'];      
            $abreviatura = $values['abreviatura'];      
            $numMovimiento = $values['numMovimiento'];            
        }
        
        if(count($data) == 0)
        {
            return response()->json([
                'status' => true,
                'message' => "Valores no encontrados",
                'valorPago' => 0,
                'valorImpuesto' => 0,
            ], 200);
        }

        if(count($productos) > 0) $items = $this->facturacionProductos($productos); 

        $documento = env('CLI_DOC_MOS');
        $grupoPrecios = env('grupoPrecios');

        $valorPago = 0;
        $valorImpuesto = 0;
        
        if(count($items) > 0)
        {
            DB::connection('sqlsrv')->beginTransaction();

            //Tomar Datos de resolucion.
            $claseFactura = env('claseFactura');
            $maquina = env('maquina');
            $prefijo = '';

            $ComunController = new ComunController();
            $datos = $ComunController->DatosGenerales();  

            foreach ($datos['infoFactura'] as $value) {
                $empresa = $value->empresa;
                $centro = $value->centro;
                $direccion = $value->direccion;
                $telefono = $value->telefono;
                $mensajeUno = $value->mensajeUno;
                $mensajeDos = $value->mensajeDos;
                $mensajeTres = $value->mensajeTres;
                $mensajeCuatro = $value->mensajeCuatro;
                $mensajeCinco = $value->mensajeCinco;
                $ciudad = $value->ciudad;
                $nombreCiudad = $value->nombreCiudad;
                $departamento = $value->departamento;
                $nombreDepartamento = $value->nombreDepartamento;
            }

            foreach ($datos['ClientesDocGrupoPrecios'] as $value) {
                $cliente = $value->cliente;
                $documento = $value->documento;
                $nombre = $value->nombre;
                $telefono = $value->telefono;
                $direccion = $value->direccion;
                $barrio = $value->barrio;
            }

            foreach ($datos['parametros'] as $value) {
                $usuario = $value->Usuario;
                $fechaProceso = $value->FechaProceso;
                $turno = $value->Turno;
            }

            $resolucionFacturas = DB::connection('sqlsrv')->select('SELECT top 1 PrefijoFactura prefijo, Resolucion resolucion, Consecutivo consecutivo, NumeroDesde desde, '
            .' NumeroHasta hasta, FechaResolucionHasta fechaResHasta '
            .' from ResolucionFacturas '
            ."where ClaseFactura = '$claseFactura' AND Maquina = '$maquina' AND FechaAplicaHasta is null AND IndKiosco = 'X'");

            foreach ($resolucionFacturas as $value)
            {
                $prefijo = $value->prefijo;
                $resolucion = $value->resolucion;
                $consecutivo = $value->consecutivo;
                $consecutivoDesde = $value->desde;
                $consecutivoHasta = $value->hasta;
            }

            if(count($resolucionFacturas) == 0)
            {
                return response()->json([
                    'status' => false,
                    'message' => "Lo sentimos, No se encontro una resolucion valida.",
                    'factura' => '',
                    'claseFactura' => '',
                    'prefijoFactura' => '',
                    'maquina' => '',
                ], 200);
            }

            if( ($consecutivo < $consecutivoDesde) || ($consecutivo > $consecutivoHasta) )
            {
                return response()->json([
                    'status' => false,
                    'message' => "Lo sentimos, El consecutivo fuera del rango .",
                    'factura' => '',
                    'claseFactura' => '',
                    'prefijoFactura' => '',
                    'maquina' => '',
                ], 200);
            }

            $affected = DB::connection('sqlsrv')->table('ResolucionFacturas')
                        ->where(['PrefijoFactura' => $prefijo , 'Resolucion' => $resolucion , 'NumeroDesde' => $consecutivoDesde] )
                        ->update(['Consecutivo' => $consecutivo + 1 ]);

            if($affected == 0) {
                DB::connection('sqlsrv')->rollBack();
                return response()->json([
                    'status' => false,
                    'message' => "Lo sentimos, No se puede Actualizar la resolucion de Facturacion.",
                    'factura' => '',
                    'claseFactura' => '',
                    'prefijoFactura' => '',
                    'maquina' => '',
                ], 200);
            }

            $factura = $consecutivo;

            DB::connection('sqlsrv')->table('Facturas')->insert([
                'Factura' => $factura,
                'ClaseFactura' => $claseFactura,
                'PrefijoFactura' => $prefijo,
                'Maquina' => $maquina,
                'Fecha' => $fechaProceso,
                'Turno' => $turno,
                'GrupoPrecios' => $grupoPrecios,
                'Cliente' => $cliente,
                'TipoVenta' => 'M',
                'ValorDomicilio' => 0,
                'IvaDomicilioExpress' => 0,
                'Vendedor' => $usuario,
                'Usuario' => $usuario,
                'FechaNovedad' => date('d.m.Y H:i:s'),
                'FechaEntrega' => date('d.m.Y H:i:s'),
                'FechaLegalizacion' => date('d.m.Y H:i:s'),
                'NroResolucion' => $resolucion,
                'Transmitido' => '',
                'Efectivo' => 0,
                'Cambio' => 0,
                'Degustacion' => '',
                'OrigenPedido' => 'K',
                'Barrio' => $barrio,
                'Direccion' => $direccion,
                'Bascula' => 'A',
                'Estado' => 'R',
                'NombresEntrega' => '',
                'ApellidosEntrega' => '',
                'BarrioEntrega' => '',
                'DireccionEntrega' => '',
                'TelefonoEntrega' => '',
                'NombreRecibe' => '',
                'DiasPlazo' => 0,
                'DomicilioGratis' => '',
                'Atendio' => $usuario,
                'EnviaFacEle' => '',
                'Enviado' => '',
                'IndConvenio' => 'N',
                'DocumentoConvenio' => '',
                'EmpresaConvenio' => '',
                'NombreConvenio' => ''                
            ]);

            foreach ($items as $value)
            {
                DB::connection('sqlsrv')->table('FacturasDetalle')->insert([
                    'Factura' => $factura,
                    'ClaseFactura' => $claseFactura,
                    'PrefijoFactura' => $prefijo,
                    'Maquina' => $maquina,
                    'Producto' => $value['producto'],
                    'UnidadMedidaVenta' => $value['unidad'],
                    'Oferta' => $value['oferta'],
                    'Unidades' => $value['cantidad'],
                    'Kilos' => $value['pesoPromedio'],
                    'ValorProducto' => $value['valor'],
                    'ValorDescuento' => $value['descuento'],
                    'ValorImpuesto' => $value['iva'],
                    'Precio' => $value['precio'],
                    'ValorOferta' => $value['valorOferta'],
                    'SaborBebida' => '',
                    'Empaque' => '',
                    'PorcImpuesto' => $value['impuesto'],
                    'ClaseDescuento' => $value['claseDescuento'],
                    'ValorDescuentoConvenio' => 0,
                    'ValorImpUltraprocesado' => $value['ivaUltra'],
                    'PorcImpUltraprocesado' => $value['impProcesado'],
                ]);

                $affected = DB::connection('sqlsrv')->table('Productos')
                            ->where('Producto', $value['producto'])
                            ->update([
                                'Existencias' => DB::connection('sqlsrv')->raw('ISNULL(Existencias, 0) - ' . $value['cantidad']),
                                'ExistenciasK' => DB::connection('sqlsrv')->raw('ISNULL(ExistenciasK, 0) - ' . $value['pesoPromedio']),
                            ]);

                if($affected == 0) {
                    DB::connection('sqlsrv')->rollBack();
                    return response()->json([
                        'status' => false,
                        'message' => "Lo sentimos, No se puede Actualizar el inventario del punto.",
                        'factura' => '',
                        'claseFactura' => '',
                        'prefijoFactura' => '',
                        'maquina' => '',
                    ], 200);
                }

                $valorPago += ($value['valor'] - $value['descuento']) + $value['iva'] + $value['ivaUltra'];
            }

            $resolucionFacturas = DB::connection('sqlsrv')->select('SELECT top 1 Consecutivo,TipoMovimiento '
            .' from TiposMovimientos '
            ."where Abreviatura = '$abreviatura' ");

            foreach ($resolucionFacturas as $value)
            {
                $Consecutivo = $value->Consecutivo;
                $TipoMovimiento = $value->TipoMovimiento;
            }

            if($Consecutivo != $numMovimiento)
            {
                $numMovimiento = $Consecutivo;
            }
            
            DB::connection('sqlsrv')->table('TiposMovimientos')
                ->where(['TipoMovimiento' => $TipoMovimiento] )
                ->update(['Consecutivo' => $numMovimiento + 1 ]);
            
            DB::connection('sqlsrv')->table('Movimientos')->insert([
                'TipoMovimiento' => $TipoMovimiento,
                'Movimiento' => $numMovimiento,
                'Fecha' => $fechaProceso, 
                'Maquina' => $maquina,
                'Turno' => $turno,
                'MovimientoReferencia' => $factura,
                'ClaseFacturaReferencia' => $claseFactura,
                'PrefijoFacturaReferencia' => $prefijo,
                'FechaMovimientoReferencia' => $fechaProceso,
                'Cliente' => $cliente,
                'NumeroReferencia' => $numeroReferencia,
                'DigitosTarjeta' => $digitos,  
                'Franquicia' => $franquicia,
                'TipoCuenta' => $tipoCuenta,
                'NumeroRecibo' => $numRec,      
                'NumeroRrn' => $numRrn,             
                'Estado' => 'A',
                'FechaNovedad' => date('d.m.Y H:i:s'),
                'Usuario' => $usuario
            ]);

            DB::connection('sqlsrv')->table('MovimientosDetalle')->insert([
                'TipoMovimiento' => $TipoMovimiento,
                'Movimiento' => $numMovimiento,
                'Posicion' => 1, 
                'ValorMovimiento' => $valorPago
            ]);

            $affected = DB::connection('sqlsrv')->table('Caja')
                    ->where(['Fecha' => $fechaProceso , 'Maquina' => $maquina , 'Turno' => $turno ] )
                    ->update(['ValorCaja' => DB::connection('sqlsrv')->raw(' ValorCaja + ' . $valorPago ) ]);

            if($affected == 0) {
                DB::connection('sqlsrv')->rollBack();
                return response()->json([
                    'status' => false,
                    'message' => "Lo sentimos, Error al actualizar el valor de la caja",
                    'factura' => '',
                    'claseFactura' => '',
                    'prefijoFactura' => '',
                    'maquina' => '',
                ], 200);
            }

            DB::connection('sqlsrv')->commit();
        }

        return response()->json([
            'status' => true,
            'message' => "Valores Calculados",
            'factura' => $factura,
            'claseFactura' => $claseFactura,
            'prefijoFactura' => $prefijo,
            'maquina' => $maquina
        ], 200);
        
    }

    public function facturacionProductos($productos) {
        $facturacion = array();
   
        $i = $cantidad = 0 ;
        foreach ($productos as $producto)
        {
            $lOferta = ' ';
            $id = $producto['id'];
            $lProducto = $producto['producto'];
            $lCantidad = $producto['cantidad'];
            $lOferta = $producto['oferta'];

            $producto = $unidad = $nombre = $claseDescuento = $tipoDescuento = '';
            $pesoPromedio = $precio = $impuesto = $pesoMinimo = $pesoMaximo = $tolMinima = $tolMaxima = $existencias = $existenciasK =  $impProcesado = $valorDescuento = 0;           
            
            $ProductoController = new ProductoController();
            $productoConsulta = $ProductoController->ConsultarProductoInterno($lProducto);  
           
            foreach ($productoConsulta['Producto'] as $value) {
                $producto = $value->producto;
                $unidad = $value->unidad;
                $nombre = $value->nombre;
                $pesoPromedio = $value->pesoPromedio;
                $precio = $value->precio;
                $impuesto = $value->impuesto;
                $pesoMinimo = $value->pesoMinimo;
                $pesoMaximo = $value->pesoMaximo;
                $tolMinima = $value->tolMinima;
                $tolMaxima = $value->tolMaxima;
                $existencias = $value->existencias;
                $existenciasK = $value->existenciasK;
                $impProcesado = $value->impProcesado;
            }

            if(count($productoConsulta['Descuento']) > 0)
            {
                foreach ($productoConsulta['Descuento'] as $value) {
                    $valorDescuento = $value->valor;
                    $claseDescuento = $value->ClaseDescuento;
                    $tipoDescuento = $value->TipoDescuento;
                }
            }

            $validador = false;
            $cantidad = $lCantidad;
            if(count($facturacion) > 0)
            {
                foreach ($facturacion as $value)
                {
                    if($value['producto'] == $producto && $value['oferta'] == '')
                    {
                        $cantidad += $value['cantidad'] ;
                        $i = $value['item'];
                        $validador = true;
                    }
                }
            }

            $valor = $this->calcularValorProducto($unidad,$cantidad,$pesoPromedio,$precio);
            $descuento = $this->calcularDescuentoProducto($valor,$valorDescuento,$tipoDescuento);
            $iva = $this->calcularImpuestoProducto($valor,$descuento,$impuesto);
            $ivaUltra = $this->calcularImpuestoProducto($valor,$descuento,$impProcesado);

            $valorOferta = 0;
            if($lOferta == 'X')
            {
                $valor = 0;
                $valorOferta = $valor;
                $descuento = 0;
            }
            else $lOferta = ' ';

            $facturacion[$i]['item'] = $i;
            $facturacion[$i]['producto'] = $producto;
            $facturacion[$i]['unidad'] = $unidad;
            $facturacion[$i]['pesoPromedio'] = $pesoPromedio;
            $facturacion[$i]['precio'] = $precio;
            $facturacion[$i]['impuesto'] = $impuesto;
            $facturacion[$i]['impProcesado'] = $impProcesado;
            $facturacion[$i]['cantidad'] = $cantidad;
            $facturacion[$i]['valor'] = $valor;
            $facturacion[$i]['descuento'] = $descuento;
            $facturacion[$i]['iva'] = $iva;
            $facturacion[$i]['ivaUltra'] = $ivaUltra;
            $facturacion[$i]['claseDescuento'] = $claseDescuento;
            $facturacion[$i]['oferta'] = $lOferta;
            $facturacion[$i]['valorOferta'] = $valorOferta;
            if(!$validador) $i++;
        }
        return $facturacion;
    }

    public function calcularImpuestoProducto($valor,$descuento,$imp) {
        $valor = round( ($valor - $descuento) * $imp / 100 );
        return $valor;
    }

    public function calcularValorProducto($unidad,$cantidad,$kilos,$precio) {
        $valor = 0;
        if($unidad == "KG") $valor = $kilos * $precio;
        else $valor = $cantidad * $precio;
        return round($valor);
    }

    public function calcularDescuentoProducto($valor,$descuento,$tipo) {
        $valorDescuento = 0;
        if($tipo != ""){
            if($tipo == "%" && $descuento!= 0)  $valorDescuento = $valor * ($descuento / 100);
            else $valorDescuento = $valor - $descuento ;
        }
        return round($valorDescuento);
    }

}
