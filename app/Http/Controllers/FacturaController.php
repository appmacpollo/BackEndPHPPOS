<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ComunController;
use Illuminate\Support\Facades\DB;

class FacturaController extends Controller
{

    public function ValorPago(Request $request)
    {
        $data = $request->json()->all();
        $sqlsrv = ($data['conexion']['express']) ? 'sqlsrv2' : 'sqlsrv';
        $i = 1;
        foreach ($data['Producto'] as $values) {
            $producto = $values['producto'];
            $cantidad = $values['cantidad'];
            $oferta = $values['oferta'];
            $kilos = $values['pesoPromedio'];
            $precio = $values['precio'];
            $pesado = trim($values['pesado']);
            $productos[] = array('id' => $i, 'producto' => $producto, 'cantidad' => $cantidad, 'oferta' => $oferta, 'kilos' => $kilos, 'precio' => $precio, 'pesado' => $pesado);
            $i++;
        }

        if (count($data) == 0) {
            return response()->json([
                'status' => true,
                'message' => "Valores no encontrados",
                'valorPago' => 0,
                'valorImpuesto' => 0,
                'valorInc' => 0,
            ], 200);
        }

        //Tomar Datos de resolucion.
        $claseFactura = env('claseFactura');
        $maquina = env('maquina');
        $prefijo = '';

        $resolucionFacturas = DB::connection($sqlsrv)->select('SELECT top 1 PrefijoFactura prefijo, Resolucion resolucion, Consecutivo consecutivo, NumeroDesde desde, '
            . ' NumeroHasta hasta, FechaResolucionHasta fechaResHasta '
            . ' from ResolucionFacturas '
            . "where ClaseFactura = '$claseFactura' AND Maquina = '$maquina' AND FechaAplicaHasta is null ");

        foreach ($resolucionFacturas as $value) {
            $prefijo = $value->prefijo;
            $consecutivo = $value->consecutivo;
            $consecutivoDesde = $value->desde;
            $consecutivoHasta = $value->hasta;
        }

        if (count($resolucionFacturas) == 0) {
            return response()->json([
                'status' => false,
                'message' => "Lo sentimos, No se encontro una resolucion valida.",
                'valorPago' => 0,
                'valorImpuesto' => 0,
                'valorInc' => 0,
            ], 200);
        }

        if (($consecutivo < $consecutivoDesde) || ($consecutivo > $consecutivoHasta)) {
            return response()->json([
                'status' => false,
                'message' => "Lo sentimos, El consecutivo fuera del rango .",
                'valorPago' => 0,
                'valorImpuesto' => 0,
                'valorInc' => 0,
            ], 200);
        }

        if (count($productos) > 0)
            $items = $this->facturacionProductos($productos, $data['conexion']['express']);

        $valorPago = 0;
        $valorImpuesto = 0;
        $valorInc = 0;

        if (count($items) > 0) {
            foreach ($items as $value) {
                $valorPago += $value['valor'] - $value['descuento'];
                if ($value['indExpress'] == 'X') {
                    $valorInc += $value['iva'] + $value['ivaUltra'] + $value['impConsumo'];
                    $valorImpuesto += 0;
                } else {
                    $valorInc += $value['impConsumo'];
                    $valorImpuesto += $value['iva'] + $value['ivaUltra'];
                }
            }
        }

        return response()->json([
            'status' => true,
            'message' => "Valores Calculados",
            'valorPago' => $valorPago + $valorImpuesto + $valorInc,
            'valorImpuesto' => $valorImpuesto,
            'valorInc' => $valorInc,
        ], 200);
    }

    public function facturar(Request $request)
    {

        $data = $request->json()->all();
        $i = 1;
        $numeroReferencia = $numRrn = $numRec = $abreviatura = $digitos = 0;
        $franquicia = $tipoCuenta = '';
        foreach ($data['Producto'] as $values) {
            $producto = $values['producto'];
            $cantidad = $values['cantidad'];
            $oferta = trim($values['oferta']);
            $kilos = $values['pesoPromedio'];
            $precio = $values['precio'];
            $pesado = trim($values['pesado']);
            $productos[] = array('id' => $i, 'producto' => $producto, 'cantidad' => $cantidad, 'oferta' => $oferta, 'kilos' => $kilos, 'precio' => $precio, 'pesado' => $pesado);
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

        if (count($data) == 0) {
            return response()->json([
                'status' => true,
                'message' => "Valores no encontrados",
                'valorPago' => 0,
                'valorImpuesto' => 0,
            ], 200);
        }

        if (count($productos) > 0)
            $items = $this->facturacionProductos($productos, false);

        $documento = env('CLI_DOC_MOS');
        $grupoPrecios = env('grupoPrecios');

        $valorPago = 0;
        $valorImpuesto = 0;

        if (count($items) > 0) {
            DB::connection('sqlsrv')->beginTransaction();

            //Tomar Datos de resolucion.
            $claseFactura = env('claseFactura');
            $maquina = env('maquina');
            $prefijo = '';

            $ComunController = new ComunController();
            $datos = $ComunController->DatosGenerales(false);

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
                $Nombre = $value->nombre;
                $telefono = $value->telefono;
                //$direccion = $value->direccion;
                $barrio = $value->barrio;
            }

            $cliente = $data['cliente']['Cliente'] ;
            $documento = $data['cliente']['DocumentoIdentidad'] ;
            $Nombre = $data['cliente']['Nombre'] ;
            //$telefono = $data['cliente']['Telefono'] ;
            $tipoDocumento = $data['cliente']['TipoDocumento'] ;

            foreach ($datos['parametros'] as $value) {
                $usuario = $value->Usuario;
                $fechaProceso = $value->FechaProceso;
                $turno = $value->Turno;
                if($data['cliente']['Cliente'] == "") $cliente = $value->ConsecutivoClientes;
            }

            $resolucionFacturas = DB::connection('sqlsrv')->select('SELECT top 1 PrefijoFactura prefijo, Resolucion resolucion, Consecutivo consecutivo, NumeroDesde desde, '
                . ' NumeroHasta hasta, FechaResolucionHasta fechaResHasta '
                . ' from ResolucionFacturas '
                . "where ClaseFactura = '$claseFactura' AND Maquina = '$maquina' AND FechaAplicaHasta is null ");

            foreach ($resolucionFacturas as $value) {
                $prefijo = $value->prefijo;
                $resolucion = $value->resolucion;
                $consecutivo = $value->consecutivo;
                $consecutivoDesde = $value->desde;
                $consecutivoHasta = $value->hasta;
            }

            if (count($resolucionFacturas) == 0) {
                DB::connection('sqlsrv')->rollBack();
                return response()->json([
                    'status' => false,
                    'message' => "Lo sentimos, No se encontro una resolucion valida.",
                    'factura' => '',
                    'claseFactura' => '',
                    'prefijoFactura' => '',
                    'maquina' => '',
                ], 200);
            }

            if (($consecutivo < $consecutivoDesde) || ($consecutivo > $consecutivoHasta)) {
                DB::connection('sqlsrv')->rollBack();
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
                ->where(['PrefijoFactura' => $prefijo, 'Resolucion' => $resolucion, 'NumeroDesde' => $consecutivoDesde])
                ->update(['Consecutivo' => $consecutivo + 1]);

            if ($affected == 0) {
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

            if($data['cliente']['Cliente'] == "")
            {
                DB::connection('sqlsrv')->table('Clientes')->insert([
                    'Cliente' => $cliente,
                    'Empleado' => "",
                    'Empresa' => "00",
                    'Tratamiento' => "",
                    'Sexo' => "",
                    'DocumentoIdentidad' => $documento,
                    'Sucursal' => '00',
                    'Nombre' => $Nombre,
                    'Direccion' => $direccion,
                    'Barrio' => "ALMACEN",
                    'Telefono' => $telefono,
                    'Celular' => $telefono,
                    'GrupoPrecios' => $grupoPrecios,
                    'TipoNegocio' => "044",
                    'Email' => "",
                    'Observaciones' => "Cliente creado desde el Kiosco",
                    'FrecuenciaCompra' => "",
                    'FechaUltVenta' => date('d.m.Y H:i:s'),
                    'CallCenter' => "",
                    'ClienteDiario' => "",
                    'HorarioLlamada' => "12:00:00 AM - 12:00:00 AM",
                    'CodigoSAP' => "",
                    'FechaProxLlamada' => "1900-01-01",
                    'CondicionPago' => "PI",
                    'ValorCupo' => "0",
                    'FechaDiaAnterior' => "1900-01-01",
                    'VentasDiaAnterior' => "",
                    'BloqueoCartera' => "",
                    'Identificado' => "",
                    'PagaDomicilio' => "",
                    'FechaCrea' => date('d.m.Y H:i:s'),
                    'UsuarioCrea' => $usuario,
                    'FechaModifica' => date('d.m.Y H:i:s'),
                    'UsuarioModifica' => $usuario,
                    'Gestionado' => "S",
                    'Estado' => "A",
                    'DocumentoVerificado' => $documento,
                    'BarrioCliente' => "",
                    'OrigenCliente' => "K",
                    'OrigenUltVenta' => "K",
                    'Ciudad' => $ciudad,
                    'TipoDocumento' => $tipoDocumento
                ]);
                
                DB::connection('sqlsrv')->table('Parametros')
                ->update(['ConsecutivoClientes' => $cliente + 1]);
            }
           
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
                'EnviaFacEle' => 'X',
                'Enviado' => '',
                'IndConvenio' => 'N',
                'DocumentoConvenio' => '',
                'EmpresaConvenio' => '',
                'NombreConvenio' => ''
            ]);

            foreach ($items as $value) {
                if ($value['indExpress'] != 'X') {
                    $iva = $value['iva'];
                    $impuesto = $value['impuesto'];
                    $PorcImpConsumo = 0;
                    $valorImpConsumo = $value['impConsumo'];
                } else {
                    $iva = 0;
                    $impuesto = 0;
                    $PorcImpConsumo = $value['impuesto'];
                    $valorImpConsumo = $value['iva'] + $value['impConsumo'];
                }

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
                    'ValorImpuesto' => $iva,
                    'Precio' => $value['precio'],
                    'ValorOferta' => $value['valorOferta'],
                    'SaborBebida' => '',
                    'Empaque' => '',
                    'PorcImpuesto' => $impuesto,
                    'ClaseDescuento' => $value['claseDescuento'],
                    'ValorDescuentoConvenio' => 0,
                    'ValorImpUltraprocesado' => $value['ivaUltra'],
                    'PorcImpUltraprocesado' => $value['impProcesado'],
                    'ValorImpConsumo' => $valorImpConsumo,
                    'PorcImpConsumo' => $PorcImpConsumo,
                ]);

                $valorPago += ($value['valor'] - $value['descuento']) + $value['iva'] + $value['ivaUltra'] + $value['impConsumo'];
            }

            $ind_inventario = $this->procesarExistenciasProductos($items, false, "-");
            if (!$ind_inventario) {
                DB::connection('sqlsrv')->rollBack();
                return response()->json([
                    'status' => false,
                    'message' => "Lo sentimos, Error al actualizar el inventario",
                    'factura' => '',
                    'claseFactura' => '',
                    'prefijoFactura' => '',
                    'maquina' => '',
                ], 200);
            }

            $resolucionFacturas = DB::connection('sqlsrv')->select('SELECT top 1 Consecutivo,TipoMovimiento '
                . ' from TiposMovimientos '
                . "where Abreviatura = '$abreviatura' ");

            foreach ($resolucionFacturas as $value) {
                $Consecutivo = $value->Consecutivo;
                $TipoMovimiento = $value->TipoMovimiento;
            }

            if ($Consecutivo != $numMovimiento) {
                $numMovimiento = $Consecutivo;
            }

            DB::connection('sqlsrv')->table('TiposMovimientos')
                ->where(['TipoMovimiento' => $TipoMovimiento])
                ->update(['Consecutivo' => $numMovimiento + 1]);

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
                ->where(['Fecha' => $fechaProceso, 'Maquina' => $maquina, 'Turno' => $turno])
                ->update(['ValorCaja' => DB::connection('sqlsrv')->raw(' ValorCaja + ' . $valorPago)]);

            if ($affected == 0) {
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

    public function facturacionProductos($productos, $express)
    {
        $facturacion = array();

        $i = $cantidad = 0;
        foreach ($productos as $producto) {
            $lOferta = ' ';
            $id = $producto['id'];
            $lProducto = $producto['producto'];
            $lCantidad = $producto['cantidad'];
            $lOferta = $producto['oferta'];
            $lkilos = $producto['kilos'];
            $lprecio = $producto['precio'];
            $lpesado = $producto['pesado'];

            $producto = $unidad = $nombre = $claseDescuento = $tipoDescuento = '';
            $pesoPromedio = $precio = $impuesto = $pesoMinimo = $pesoMaximo = $tolMinima = $tolMaxima = $existencias = $existenciasK = $impProcesado = $valorDescuento = 0;

            $ProductoController = new ProductoController();
            if ($express == true)
                $productoConsulta = $ProductoController->ConsultarProductoInternoExpress($lProducto);
            else
                $productoConsulta = $ProductoController->ConsultarProductoInterno($lProducto);

            if (count($productoConsulta) == 0) {
                return array();
            }

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
                $indExpress = $value->indExpress;
                $valorImpBolsa = $value->valorImpBolsa;
            }

            if ($express == false && $lpesado == 'X') {
                if (floatval($lkilos) != floatval($pesoPromedio)) {
                    $pesoPromedio = $lkilos;
                }
                if (floatval($precio) != floatval($lprecio)) {
                    //$precio = ($lprecio > 0) ? $lprecio : $precio ;
                }
            }

            if (count($productoConsulta['Descuento']) > 0) {
                foreach ($productoConsulta['Descuento'] as $value) {
                    $valorDescuento = $value->valor;
                    $claseDescuento = $value->ClaseDescuento;
                    $tipoDescuento = $value->TipoDescuento;
                }
            }

            $validador = false;
            $cantidad = $lCantidad;

            if (count($facturacion) > 0 && $lOferta != 'X') {
                foreach ($facturacion as $value) {
                    if ($value['producto'] == $producto && $value['oferta'] != 'X') {
                        $cantidad = $value['cantidad'] + $lCantidad;
                        $i = $value['item'];
                        $validador = true;
                        break;
                    }
                }
            }

            $valor = $this->calcularValorProducto($unidad, $cantidad, $pesoPromedio, $precio);
            $descuento = $this->calcularDescuentoProducto($valor, $valorDescuento, $tipoDescuento);
            $iva = $this->calcularImpuestoProducto($valor, $descuento, $impuesto);
            $ivaUltra = $this->calcularImpuestoProducto($valor, $descuento, $impProcesado);
            $impConsumo = $this->calcularImpuestoFijoProducto($valorImpBolsa, $cantidad);

            $valorOferta = 0;
            if ($lOferta == 'X') {
                $valor = 0;
                $valorOferta = intval($precio) * intval($cantidad);
                $descuento = 0;
                $validador = false;
                $claseDescuento = '';
                $ivaUltra = 0;
                $iva = 0;
            } else
                $lOferta = ' ';

            if (!$validador)
                $i++;
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
            $facturacion[$i]['impConsumo'] = $impConsumo;
            $facturacion[$i]['claseDescuento'] = $claseDescuento;
            $facturacion[$i]['oferta'] = $lOferta;
            $facturacion[$i]['valorOferta'] = $valorOferta;
            $facturacion[$i]['indExpress'] = $indExpress;
        }
        return $facturacion;
    }

    public function calcularImpuestoProducto($valor, $descuento, $imp)
    {
        $valor = round(($valor - $descuento) * $imp / 100);
        return $valor;
    }

    public function calcularImpuestoFijoProducto($imp, $cantidad)
    {
        $total_impuesto = round($imp * $cantidad);
        return $total_impuesto;
    }

    public function calcularValorProducto($unidad, $cantidad, $kilos, $precio)
    {
        $valor = 0;
        if ($unidad == "KG")
            $valor = $kilos * $precio;
        else
            $valor = $cantidad * $precio;
        return round($valor);
    }

    public function calcularDescuentoProducto($valor, $descuento, $tipo)
    {
        $valorDescuento = 0;
        if ($tipo != "") {
            if ($tipo == "%" && $descuento != 0)
                $valorDescuento = $valor * ($descuento / 100);
            else
                $valorDescuento = $valor - $descuento;
        }
        return round($valorDescuento);
    }

    public function Facturas(Request $request)
    {
        $data = $request->json()->all();
        $sqlsrv = ($data['conexion']['express']) ? 'sqlsrv2' : 'sqlsrv';
        $maquina = env('maquina');

        $facturas = DB::connection($sqlsrv)->select('SELECT Facturas.Factura,Facturas.PrefijoFactura,Facturas.ClaseFactura,Facturas.Maquina,Movimientos.Movimiento,'
            . ' Movimientos.NumeroReferencia,Movimientos.NumeroRecibo,NumeroRrn,MovimientosDetalle.ValorMovimiento,Facturas.FechaNovedad,  '
            . ' (select Abreviatura from TiposMovimientos where  TiposMovimientos.TipoMovimiento = Movimientos.TipoMovimiento ) as Abreviatura'
            . ' FROM Facturas '
            . ' inner join Movimientos on Movimientos.MovimientoReferencia = Facturas.Factura and Movimientos.PrefijoFacturaReferencia = Facturas.PrefijoFactura '
            . ' and Movimientos.ClaseFacturaReferencia = Facturas.ClaseFactura and Movimientos.Maquina = Facturas.Maquina '
            . ' inner join MovimientosDetalle on Movimientos.TipoMovimiento = MovimientosDetalle.TipoMovimiento and '
            . ' Movimientos.Movimiento = MovimientosDetalle.Movimiento '
            . " WHERE Facturas.Fecha = (select FechaProceso from Parametros ) and Facturas.OrigenPedido = 'K' AND Facturas.Maquina = '$maquina' and Facturas.Estado not in ('I')  "
            . ' order by Facturas.Factura desc');

        if (count($facturas) == 0) {
            return response()->json([
                'status' => false,
                'message' => "Facturas no encontradas",
                'facturas' => array()
            ], 200);
        } else {
            return response()->json([
                'status' => true,
                'message' => "Facturas encontrados",
                'facturas' => $facturas,
            ], 200);
        }
    }

    public function facturarExpress(Request $request)
    {

        $data = $request->json()->all();
        $i = 1;
        $numeroReferencia = $numRrn = $numRec = $abreviatura = $digitos = 0;
        $franquicia = $tipoCuenta = '';
        foreach ($data['Producto'] as $values) {
            $producto = $values['producto'];
            $cantidad = $values['cantidad'];
            $oferta = $values['oferta'];
            $kilos = $values['pesoPromedio'];
            $precio = $values['precio'];
            $pesado = trim($values['pesado']);
            $productos[] = array('id' => $i, 'producto' => $producto, 'cantidad' => $cantidad, 'oferta' => $oferta, 'kilos' => $kilos, 'precio' => $precio, 'pesado' => $pesado);
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

        if (count($data) == 0) {
            return response()->json([
                'status' => true,
                'message' => "Valores no encontrados",
                'factura' => '',
                'claseFactura' => '',
                'prefijoFactura' => '',
                'maquina' => '',
            ], 200);
        }
        $valorPago = 0;
        $valorImpuesto = 0;

        if (count($productos) > 0)
            $items = $this->facturacionProductos($productos, true);

        if (count($items) <= 0) {
            return response()->json([
                'status' => false,
                'message' => "Lo sentimos, Error no se encontraron productos validos",
                'factura' => '',
                'claseFactura' => '',
                'prefijoFactura' => '',
                'maquina' => '',
            ], 200);
        }

        DB::connection('sqlsrv2')->beginTransaction();

        //Tomar Datos de resolucion.
        $claseFactura = env('claseFactura');
        $maquina = env('maquina');
        $prefijo = '';
        $documento = env('CLI_DOC_MOS');
        $grupoPrecios = env('grupoPrecios');

        $ComunController = new ComunController();
        $datos = $ComunController->DatosGenerales(true);

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

        $resolucionFacturas = DB::connection('sqlsrv2')->select('SELECT top 1 PrefijoFactura prefijo, Resolucion resolucion, Consecutivo consecutivo, NumeroDesde desde, '
            . ' NumeroHasta hasta, FechaResolucionHasta fechaResHasta '
            . ' from ResolucionFacturas '
            . "where ClaseFactura = '$claseFactura' AND Maquina = '$maquina' AND FechaAplicaHasta is null ");

        foreach ($resolucionFacturas as $value) {
            $prefijo = $value->prefijo;
            $resolucion = $value->resolucion;
            $consecutivo = $value->consecutivo;
            $consecutivoDesde = $value->desde;
            $consecutivoHasta = $value->hasta;
        }

        if (count($resolucionFacturas) == 0) {
            return response()->json([
                'status' => false,
                'message' => "Lo sentimos, No se encontro una resolucion valida.",
                'factura' => '',
                'claseFactura' => '',
                'prefijoFactura' => '',
                'maquina' => '',
            ], 200);
        }

        $affected = DB::connection('sqlsrv2')->table('ResolucionFacturas')
            ->where(['PrefijoFactura' => $prefijo, 'Resolucion' => $resolucion, 'NumeroDesde' => $consecutivoDesde])
            ->update(['Consecutivo' => $consecutivo + 1]);

        if ($affected == 0) {
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

        $ind_inventario = $this->procesarExistenciasProductos($items, true, "-");
        if (!$ind_inventario) {
            DB::connection('sqlsrv2')->rollBack();
            return response()->json([
                'status' => false,
                'message' => "Lo sentimos, Error al actualizar el inventario",
                'factura' => '',
                'claseFactura' => '',
                'prefijoFactura' => '',
                'maquina' => '',
            ], 200);
        }

        DB::connection('sqlsrv2')->table('Facturas')->insert([
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

        foreach ($items as $value) {
            DB::connection('sqlsrv2')->table('FacturasDetalle')->insert([
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

            $valorPago += ($value['valor'] - $value['descuento']) + $value['iva'] + $value['ivaUltra'] + $value['impConsumo'];
        }

        $resolucionFacturas = DB::connection('sqlsrv2')->select('SELECT top 1 Consecutivo,TipoMovimiento '
            . ' from TiposMovimientos '
            . "where Abreviatura = '$abreviatura' ");

        foreach ($resolucionFacturas as $value) {
            $Consecutivo = $value->Consecutivo;
            $TipoMovimiento = $value->TipoMovimiento;
        }

        if ($Consecutivo != $numMovimiento) {
            $numMovimiento = $Consecutivo;
        }

        DB::connection('sqlsrv2')->table('TiposMovimientos')
            ->where(['TipoMovimiento' => $TipoMovimiento])
            ->update(['Consecutivo' => $numMovimiento + 1]);

        DB::connection('sqlsrv2')->table('Movimientos')->insert([
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

        DB::connection('sqlsrv2')->table('MovimientosDetalle')->insert([
            'TipoMovimiento' => $TipoMovimiento,
            'Movimiento' => $numMovimiento,
            'Posicion' => 1,
            'ValorMovimiento' => $valorPago
        ]);

        $affected = DB::connection('sqlsrv2')->table('Caja')
            ->where(['Fecha' => $fechaProceso, 'Maquina' => $maquina, 'Turno' => $turno])
            ->update(['ValorCaja' => DB::connection('sqlsrv')->raw(' ValorCaja + ' . $valorPago)]);

        if ($affected == 0) {
            DB::connection('sqlsrv2')->rollBack();
            return response()->json([
                'status' => false,
                'message' => "Lo sentimos, Error al actualizar el valor de la caja",
                'factura' => '',
                'claseFactura' => '',
                'prefijoFactura' => '',
                'maquina' => '',
            ], 200);
        }

        DB::connection('sqlsrv2')->commit();

        return response()->json([
            'status' => true,
            'message' => "Valores Calculados",
            'factura' => $factura,
            'claseFactura' => $claseFactura,
            'prefijoFactura' => $prefijo,
            'maquina' => $maquina
        ], 200);

    }

    public function AnularFactura(Request $request)
    {

        $data = $request->json()->all();
        $sqlsrv = ($data['conexion']['express']) ? 'sqlsrv2' : 'sqlsrv';
        $maquina = env('maquina');

        foreach ($data['movimientos'] as $values) {
            $Factura = $values['Factura'];
            $PrefijoFactura = $values['PrefijoFactura'];
            $ClaseFactura = $values['ClaseFactura'];
            $numeroReferencia = $values['NumeroReferencia'];
            $NumeroRecibo = $values['NumeroRecibo'];
            $NumeroRrn = $values['NumeroRrn'];
            $Movimiento = $values['Movimiento'];
            $ValorMovimiento = $values['ValorMovimiento'];
            $abreviatura = $values['Abreviatura'];
        }

        $ComunController = new ComunController();
        $datos = $ComunController->DatosGenerales($data['conexion']['express']);

        foreach ($datos['parametros'] as $value) {
            $usuario = $value->Usuario;
            $fechaProceso = $value->FechaProceso;
            $turno = $value->Turno;
        }

        DB::connection($sqlsrv)->beginTransaction();

        $affected = DB::connection($sqlsrv)->table('Caja')
            ->where(['Fecha' => $fechaProceso, 'Maquina' => $maquina, 'Turno' => $turno])
            ->update(['ValorCaja' => DB::connection($sqlsrv)->raw(' ValorCaja - ' . $ValorMovimiento)]);

        if ($affected == 0) {
            DB::connection($sqlsrv)->rollBack();
            return response()->json([
                'status' => false,
                'message' => "Lo sentimos, No se puede Actualizar el valor de la caja",
            ], 200);
        }

        $affectedM = DB::connection($sqlsrv)->table('Movimientos')
            ->where(['MovimientoReferencia' => $Factura, 'ClaseFacturaReferencia' => $ClaseFactura, 'PrefijoFacturaReferencia' => $PrefijoFactura, 'Maquina' => $maquina])
            ->update(['Estado' => 'I', 'UsuarioAnula' => $usuario, 'FechaAnula' => date('d.m.Y H:i:s')]);

        if ($affectedM == 0) {
            DB::connection($sqlsrv)->rollBack();
            return response()->json([
                'status' => false,
                'message' => "Lo sentimos, No se puede Actualizar el estado del Movimiento",
            ], 200);
        }

        // Ejecutar la consulta
        $resultadoConsulta = DB::connection($sqlsrv)->table('facturas')->selectRaw('ISNULL(MAX(NumeroAnula), 0) AS numeroAnula')->get();

        // Obtener el valor de 'numeroAnula' del resultado
        $numeroAnula = $resultadoConsulta[0]->numeroAnula;

        $affectedF = DB::connection($sqlsrv)->table('Facturas')
            ->where(['Factura' => $Factura, 'ClaseFactura' => $ClaseFactura, 'Turno' => $turno])
            ->update(['Estado' => 'I', 'MotivoAnulaFactura' => '031', 'UsuarioAnula' => $usuario, 'FechaAnula' => date('d.m.Y H:i:s'), 'NumeroAnula' => $numeroAnula,]);

        if ($affectedF == 0) {
            DB::connection($sqlsrv)->rollBack();
            return response()->json([
                'status' => false,
                'message' => "Lo sentimos, No se puede Actualizar el estado de la factura",
            ], 200);
        }

        $productoLocal = DB::connection($sqlsrv)->select('SELECT fd.Producto producto, fd.UnidadMedidaVenta unidadVenta, p.Nombre nombre, fd.Oferta oferta, '
            . 'fd.Unidades unidades, fd.Kilos kilos, fd.Precio precio, fd.ValorProducto valorProducto, '
            . 'fd.ValorDescuento valorDescuento, fd.ValorImpuesto valorImpuesto, fd.ValorOferta valorOferta, '
            . "fd.SaborBebida saborBebida,isnull(i.CodIva, '') codIva, p.Ean ean, fd.Empaque empaque, "
            . "fd.PorcImpuesto porcImpuesto, isnull(fd.ClaseDescuento, '') claseDescuento, "
            . 'fd.ValorDescuentoConvenio valDescuentoConvenio, fd.ValorImpUltraprocesado impUltra, p.Pesado Pesado '
            . 'from Facturas f inner join FacturasDetalle fd on f.Factura = fd.Factura '
            . 'and f.ClaseFactura = fd.ClaseFactura and f.PrefijoFactura = fd.PrefijoFactura '
            . 'and f.Maquina = fd.Maquina '
            . 'inner join Productos p on fd.Producto = p.Producto '
            . "left join Impuestos i on CONCAT('IVV' , fd.PorcImpuesto) = i.Codigo "
            . "where fd.Factura = $Factura and fd.ClaseFactura = '$ClaseFactura' and fd.PrefijoFactura = '$PrefijoFactura' and fd.Maquina = $maquina  "
            . "and p.GrupoArticulos not in (select isnull(FamiliaEmpaques, '') from Parametros)");

        $i = 0;
        foreach ($productoLocal as $values) {
            $producto = $values->producto;
            $cantidad = $values->unidades;
            $oferta = $values->oferta;
            $kilos = $values->kilos;
            $precio = $values->precio;
            $pesado = $values->Pesado;
            $productos[] = array('id' => $i, 'producto' => $producto, 'cantidad' => $cantidad, 'oferta' => $oferta, 'kilos' => $kilos, 'precio' => $precio, 'pesado' => $pesado);
            $i++;
        }

        if (count($productos) > 0)
            $items = $this->facturacionProductos($productos, $data['conexion']['express']);

        if (count($items) <= 0) {
            return response()->json([
                'status' => false,
                'message' => "Lo sentimos, Error no se encontraron productos para la anulacion de la factura "
            ], 200);
        }

        $ind_inventario = $this->procesarExistenciasProductos($items, $data['conexion']['express'], "+");
        if (!$ind_inventario) {
            DB::connection($sqlsrv)->rollBack();
            return response()->json([
                'status' => false,
                'message' => "Lo sentimos, Error al actualizar el inventario Anulacion de Facturas"
            ], 200);
        }

        DB::connection($sqlsrv)->commit();

        return response()->json([
            'status' => true,
            'message' => "Factura Anulada Con exito."
        ], 200);
    }

    public function procesarExistenciasProductos($productos, $express, $operacion)
    {
        $sqlsrv = ($this->is_true($express) == true) ? 'sqlsrv2' : 'sqlsrv';
        $proceso = false;
        $factor = $operacion == '+' ? 1 : ($operacion == '-' ? -1 : 1);
        foreach ($productos as $value) {
            $infoProducto = DB::connection($sqlsrv)->select('SELECT c.Combo combo, c.Componente componente, p.Nombre nombre, c.Cantidad cantidad,'
                . ' EmpaqueAlmacen empAlmacen, EmpaqueDomicilio empDomicilio, p.PesoPromedio pesoPromedio,  '
                . ' isnull(p.Existencias, 0) existencias,p.UnidadMedidaVenta '
                . ' FROM Combos c '
                . ' inner join Productos p on c.Componente = p.Producto '
                . " WHERE c.Estado = 'A' AND c.Combo = '" . $value['producto'] . "' and c.EmpaqueAlmacen = 'X' and c.EmpaqueDomicilio = 'X' "
                . ' order by c.Combo');

            $cantidades = $value['cantidad'];
            $pesoPromedio = $value['pesoPromedio'];
            $UnidadMedidaVenta = $value['unidad'];

            if (count($infoProducto) != 0) {
                foreach ($infoProducto as $values) {
                    $componente = $values->componente;
                    $pesoPromedio = $values->pesoPromedio;
                    $UnidadMedidaVenta = $values->UnidadMedidaVenta;

                    $affected = DB::connection($sqlsrv)->table('Productos')
                        ->where('Producto', $componente)
                        ->update([
                            'Existencias' => DB::connection($sqlsrv)->raw('ISNULL(Existencias, 0) + ' . (($cantidades * $values->cantidad) * $factor)),
                            'ExistenciasK' => DB::connection($sqlsrv)->raw('ISNULL(ExistenciasK, 0) + ' . ($pesoPromedio * $factor)),
                        ]);
                    if ($affected == 0)
                        return false;
                }
            } else {
                $affected = DB::connection($sqlsrv)->table('Productos')
                    ->where('Producto', $value['producto'])
                    ->update([
                        'Existencias' => DB::connection($sqlsrv)->raw('ISNULL(Existencias, 0) + ' . ($cantidades * $factor)),
                        'ExistenciasK' => DB::connection($sqlsrv)->raw('ISNULL(ExistenciasK, 0) + ' . ($pesoPromedio * $factor)),
                    ]);
                if ($affected == 0)
                    return false;
            }
            $proceso = true;
        }
        return $proceso;
    }

    function is_true($val, $return_null = false)
    {
        $boolval = (is_string($val) ? filter_var($val, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : (bool) $val);
        return ($boolval === null && !$return_null ? false : $boolval);
    }
}
