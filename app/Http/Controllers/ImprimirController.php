<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ImprimirController extends Controller
{
    public function ImprimirFactura($factura, $clase, $prefijo, $maquina)
    {
        $impresion = $this->ImprimirFacturaGeneral($factura, $clase, $prefijo, $maquina, false);
        if (count($impresion) > 0) {
            return response()->json([
                'status' => true,
                'message' => "Factura encontrada",
                'cabecera' => $impresion
            ], 200);
        } else {
            return response()->json([
                'status' => false,
                'message' => "Factura no encontrada, por favor revise.",
                'cabecera' => array()
            ], 200);
        }
    }

    public function ImprimirFacturaExpress($factura, $clase, $prefijo, $maquina)
    {
        $impresion = $this->ImprimirFacturaGeneral($factura, $clase, $prefijo, $maquina, true);
        if (count($impresion) > 0) {
            return response()->json([
                'status' => true,
                'message' => "Factura encontrada",
                'cabecera' => $impresion
            ], 200);
        } else {
            return response()->json([
                'status' => false,
                'message' => "Factura no encontrada, por favor revise.",
                'cabecera' => array()
            ], 200);
        }
    }

    public function ImprimirFacturaGeneral($factura, $clase, $prefijo, $maquina, $express)
    {

        if ($express)
            $conexion = 'sqlsrv2';
        else
            $conexion = 'sqlsrv';

        $parametros = ["factura" => $factura, "clase" => $clase, "prefijo" => $prefijo, "maquina" => $maquina];
        $sqlCabecera = "select f.Factura, f.PrefijoFactura, f.Fecha, f.FechaNovedad, f.Maquina, f.Vendedor, uv.Nombre NombreVendedor, "
            . "f.Cliente, c.DocumentoIdentidad, c.Nombre NombreCliente, isnull(c.Identificado, '') Identificado, "
            . "f.Direccion, c.Telefono, f.Barrio, c.Empleado, c.Empresa, e.Nombre NombreEmpresa, f.TipoVenta, "
            . "isnull(f.Domiciliario, '') Domiciliario, isnull(ud.Nombre, '') NombreDomiciliario, "
            . "f.NroResolucion, rf.FechaResolucionDesde, rf.FechaResolucionHasta, rf.NumeroDesde, rf.NumeroHasta, "
            . "f.NombresEntrega + ' ' + f.ApellidosEntrega NombreEntrega, f.DireccionEntrega, f.BarrioEntrega, "
            . "f.TelefonoEntrega, f.NombreRecibe, f.Efectivo, f.Cambio, (f.ValorDomicilio + f.IvaDomicilioExpress) ValorDomicilio, f.IvaDomicilioExpress, "
            . "f.GrupoPrecios, isnull(f.Pedido, 0) Pedido, isnull(pc.Observaciones, '') Observaciones, "
            . "isnull(pc.TipoPago, '') TipoPago, f.Estado, gp.Nombre NombreGrupoPrecios, "
            . "isnull(c.CondicionPago, 'PI') CondicionPago, isnull(f.DiasPlazo, 0) DiasPlazo, "
            . "isnull(f.NombreClienteDegustacion, '') ClienteDegusta, isnull(f.LugarDegustacion, '') LugarDegusta, isnull(f.MotivoDegustacion, '') MotivoDegusta, isnull(md.Nombre, '') NombreMotivoDegusta, isnull(f.SolicitanteDegustacion, '') SolicitaDegusta, isnull(f.CargoDegustacion, '') CargoDegusta, "
            . "f.Degustacion Degusta, gp.ImprimeUnidades, isnull(f.OrigenPedido, 'E') OrigenPedido, f.Turno, "
            . "f.Usuario, f.ClaseFactura, f.DomicilioGratis, isnull(pa.TipoAlmacen, 'P') TipoAlmacen, "
            . "isnull(f.Atendio, '') Atendio, isnull(uat.Nombre, '') NombreAtendio, isnull(pc.Cupon, '') Cupon, "
            . "isnull(pc.PagoLinea, '') PagoLinea, isnull(pc.EstadoPago, '') EstadoPago, "
            . "isnull(pc.NumeroAprobacion, '') NumAprobacion, isnull(pc.NumeroEpayco, '') NumEpayco, "
            . "isnull(pc.TipoTransaccion, '') TipoTransaccion, isnull(pc.ViaPago, '') ViaPago, "
            . "isnull(pc.Plataforma, '') Plataforma, op.DescripcionOrigen, "
            . "rf.NombreEmpresa MensajeEmpresa, pa.NombreCentroLogistico, pa.DireccionCentroLogistico, "
            . "pa.TelefonoCentroLogistico, pa.CiudadCentroLogistico, "
            . "isnull((select NombreCiudad from Ciudades where Ciudad = pa.CiudadCentroLogistico), '') NombreCiudadCentroLogistico, "
            . "pa.MensajeFactura1, pa.MensajeFactura2, pa.MensajeFactura3, pa.MensajeFactura4, pa.MensajeFactura5, "
            . "dateadd(dd, f.DiasPlazo, f.FechaNovedad) FechaVence, f.EnviaFacEle, isnull(c.Ciudad, '') Ciudad, "
            . "isnull(cd.NombreCiudad, '') NombreCiudad, isnull(c.TipoDocumento, '') TipoDocumento, "
            . "c.Gestionado, c.DocumentoVerificado, f.IndConvenio Convenio, isnull(em.Nombre,'') NomConvenioEmp, f.NombreConvenio NomConvenio, pa.NavidadQR "
            . "from Facturas f inner join Clientes c on f.Cliente = c.Cliente "
            . "inner join ResolucionFacturas rf on f.PrefijoFactura = rf.PrefijoFactura "
            . "and f.NroResolucion = rf.Resolucion "
            . "inner join Usuarios uv on f.Vendedor = uv.Cedula "
            . "inner join Empresas e on c.Empresa = e.Empresa "
            . "left join Usuarios ud on f.Domiciliario = ud.Cedula "
            . "left join PedidosClientes pc on f.Pedido = pc.Pedido "
            . "inner join GrupoPrecios gp on f.GrupoPrecios = gp.GrupoPrecios "
            . "left join MotivosDegustaciones md on f.MotivoDegustacion = md.MotivoDegustacion "
            . "left join Usuarios uat on f.Atendio = uat.Cedula "
            . "left join OrigenesPedidos op on f.OrigenPedido = op.OrigenPedido "
            . "left join Ciudades cd on c.Ciudad = cd.Ciudad "
            . "full join Parametros pa on 1 = 1 "
            . "left join empresas em on em.Empresa = f.empresaConvenio "
            . "where f.Factura = :factura and f.ClaseFactura = :clase and f.PrefijoFactura = :prefijo and f.Maquina = :maquina";
        $cabeceraFactura = DB::connection($conexion)->select($sqlCabecera, $parametros);

        if (count($cabeceraFactura) == 0) {
            return array();
        }

        $cufe = $ruta = "";
        $CufeQrFacturaElectronica = $this->getCufeQrFacturaElectronica($factura, $clase, $prefijo, $maquina, $conexion, $express);

        $cufe = $CufeQrFacturaElectronica['cufe'];
        $ruta = $CufeQrFacturaElectronica['qr'];

        $cabeceraFactura[0]->RutaDian = $ruta;
        $cabeceraFactura[0]->CUFE = $cufe;

        $parametrosDetalle = [
            "factura" => $factura,
            "clase" => $clase,
            "prefijo" => $prefijo,
            "maquina" => $maquina,
            "factura1" => $factura,
            "clase1" => $clase,
            "prefijo1" => $prefijo,
            "maquina1" => $maquina
        ];
        $sqlDetalle = "select row_number() over (order by p.Orden, p.Producto) Item, * "
            . " from ( "
            . "	select fd.Producto, fd.UnidadMedidaVenta, p.Nombre, fd.Oferta, "
            . "	fd.Unidades, fd.Kilos, fd.Precio, fd.ValorProducto, fd.ValorDescuento, fd.ValorImpuesto, fd.ValorOferta, "
            . "	fd.SaborBebida, isnull(i.CodIva, '') CodIva, p.Ean, fd.Empaque, 1 Orden, 'X' Mostrar, 0 ValorDomicilio, "
            . "	fd.ValorDescuentoConvenio DescuentoConvenio, pa.ValorDescuentoConvenio valordescConvenio, "
            . "	case when fd.PorcImpUltraprocesado <> 0 then 'U' else '' end ImpUltra "
            . "	from Facturas f inner join FacturasDetalle fd on f.Factura = fd.Factura "
            . "	and f.ClaseFactura = fd.ClaseFactura and f.PrefijoFactura = fd.PrefijoFactura "
            . "	and f.Maquina = fd.Maquina "
            . "	inner join Productos p on fd.Producto = p.Producto "
            . "	left join Impuestos i on CONCAT('IVV' , fd.PorcImpuesto) = i.Codigo "
            . "	inner join Parametros pa on 1=1 "
            . "	where fd.Factura = :factura and fd.ClaseFactura = :clase "
            . "	and fd.PrefijoFactura = :prefijo and fd.Maquina = :maquina "
            . "	and p.GrupoArticulos <> (select isnull(FamiliaEmpaques, '') from Parametros) "
            . "	union "
            . "	select fd.Producto, fd.UnidadMedidaVenta, p.Nombre, '' Oferta, "
            . "	fd.Unidades, fd.Kilos, 0 Precio, ( ( CASE WHEN fd.ValorImpConsumo != 0 THEN fd.ValorProducto ELSE fd.ValorImpConsumo END ) ) as ValorProducto, 0 ValorDescuento, 0 ValorImpuesto, 0 ValorOferta, "
            . "	'' SaborBebida, '' CodIva, '' Ean, fd.Empaque, 3 Orden, 'X' Mostrar, 0 ValorDomicilio, 0 DescuentoConvenio, 0 valordescConvenio, '' ImpUltra "
            . "	from FacturasDetalle fd inner join Productos p on fd.Producto = p.Producto "
            . "	where fd.Factura = :factura1 and fd.ClaseFactura = :clase1 "
            . "	and fd.PrefijoFactura = :prefijo1 and fd.Maquina = :maquina1 "
            . "	and p.GrupoArticulos in (select isnull(FamiliaEmpaques, '') from Parametros) "
            . ") p where p.Mostrar = 'X'";
        $detalleFactura = DB::connection($conexion)->select($sqlDetalle, $parametrosDetalle);

        if (count($detalleFactura) > 0) {
            $cabeceraFactura[0]->detalleFactura = $detalleFactura;
        }
        $parametrosDetalle = [
            "factura" => $factura,
            "clase" => $clase,
            "prefijo" => $prefijo,
            "maquina" => $maquina,
            "factura1" => $factura,
            "clase1" => $clase,
            "prefijo1" => $prefijo,
            "maquina1" => $maquina
        ];

        $sqlImpuestos = "select imp.CodIva, imp.Iva, sum(imp.Base) Base, sum(imp.Impuesto) Impuesto, "
            . "(select IvaDomiExpress from Parametros) IvaDomiExpress, Descripcion "
            . "from "
            . "( "
            . "    select isnull(i.CodIva, '') CodIva, isnull(i.Valor, 0) Iva, "
            . "    case when f.Degustacion = 'X' then fd.ValorProducto "
            . "    else (fd.ValorProducto - fd.ValorDescuento - fd.ValorDescuentoConvenio) end Base,  "
            . "    fd.ValorImpuesto Impuesto, CASE WHEN isnull(i.Valor, 0) = 8 AND P.GrupoMateriales = 90 THEN 'IMPUESTO AL CONSUMO' ELSE i.Descripcion END Descripcion "
            . "    from Facturas f inner join FacturasDetalle fd on f.Factura = fd.Factura "
            . "    and f.ClaseFactura = fd.ClaseFactura and f.PrefijoFactura = fd.PrefijoFactura "
            . "    and f.Maquina = fd.Maquina "
            . "    inner join Productos p on fd.Producto = p.Producto "
            . "    left join Impuestos i on CONCAT('IVV' , fd.PorcImpuesto) = i.Codigo "
            . "    where fd.Factura = '$factura' and fd.ClaseFactura = '$clase' "
            . "    and fd.PrefijoFactura = '$prefijo' and fd.Maquina = '$maquina' "
            . "    and p.GrupoArticulos <> (select isnull(FamiliaEmpaques, '') from Parametros) "
            . "    and GrupoMateriales not in ( select GrupoMaterialesExpress from Parametros ) "
            . "    and indExcluido = '' "
            . "    Union ALL "
            . "    select isnull(i.CodIva, '') CodIva, isnull(i.Valor, 0) Iva, "
            . "    case when f.Degustacion = 'X' then fd.ValorProducto "
            . "    else (fd.ValorProducto - fd.ValorDescuento - fd.ValorDescuentoConvenio) end Base,  "
            . "    fd.ValorImpConsumo Impuesto, CASE WHEN isnull(i.Valor, 0) = 8 AND P.GrupoMateriales = 90 THEN 'IMPUESTO AL CONSUMO' ELSE i.Descripcion END Descripcion "
            . "    from Facturas f inner join FacturasDetalle fd on f.Factura = fd.Factura "
            . "    and f.ClaseFactura = fd.ClaseFactura and f.PrefijoFactura = fd.PrefijoFactura "
            . "    and f.Maquina = fd.Maquina "
            . "    inner join Productos p on fd.Producto = p.Producto "
            . "    inner join Impuestos i on CONCAT('CON' , fd.PorcImpConsumo) = i.Codigo "
            . "    where fd.Factura = '$factura' and fd.ClaseFactura = '$clase' "
            . "    and fd.PrefijoFactura = '$prefijo' and fd.Maquina = '$maquina' "
            . "    and p.GrupoArticulos <> (select isnull(FamiliaEmpaques, '') from Parametros) "
            . "    and GrupoMateriales in ( select GrupoMaterialesExpress from Parametros ) "
            . "    and indExcluido = '' "
            . "    Union ALL "
            . "    select isnull(i.CodIva, '') CodIva, isnull(i.Valor, 0) Iva, "
            . "    case when f.Degustacion = 'X' then fd.ValorProducto "
            . "    else (fd.ValorProducto - fd.ValorDescuento - fd.ValorDescuentoConvenio) end Base,  "
            . "    fd.ValorImpUltraprocesado Impuesto, i.Descripcion Descripcion "
            . "    from Facturas f inner join FacturasDetalle fd on f.Factura = fd.Factura "
            . "    and f.ClaseFactura = fd.ClaseFactura and f.PrefijoFactura = fd.PrefijoFactura "
            . "    and f.Maquina = fd.Maquina "
            . "    inner join Productos p on fd.Producto = p.Producto "
            . "    inner join Impuestos i on CONCAT('ULT' ,fd.PorcImpUltraprocesado) = i.Codigo "
            . "    where fd.Factura = '$factura' and fd.ClaseFactura = '$clase' "
            . "    and fd.PrefijoFactura = '$prefijo' and fd.Maquina = '$maquina' "
            . "    and p.GrupoArticulos <> (select isnull(FamiliaEmpaques, '') from Parametros) "
            . "    and indExcluido = '' "
            . "    Union ALL "
            . "    select isnull(i.CodIva, '') CodIva, isnull(i.Valor, 0) Iva, "
            . "    case when f.Degustacion = 'X' then fd.ValorProducto "
            . "    else (fd.ValorProducto - fd.ValorDescuento - fd.ValorDescuentoConvenio) end Base,  "
            . "    fd.ValorImpUltraprocesado Impuesto, i.Descripcion Descripcion "
            . "    from Facturas f inner join FacturasDetalle fd on f.Factura = fd.Factura "
            . "    and f.ClaseFactura = fd.ClaseFactura and f.PrefijoFactura = fd.PrefijoFactura "
            . "    and f.Maquina = fd.Maquina "
            . "    inner join Productos p on fd.Producto = p.Producto "
            . "    inner join Impuestos i on CONCAT('EXC' , fd.PorcImpuesto) = i.Codigo "
            . "    where fd.Factura = '$factura' and fd.ClaseFactura = '$clase' "
            . "    and fd.PrefijoFactura = '$prefijo' and fd.Maquina = '$maquina' "
            . "    and p.GrupoArticulos <> (select isnull(FamiliaEmpaques, '') from Parametros) "
            . "    and indExcluido = 'X' "
            . ") imp "
            . "where imp.CodIva <> '' group by imp.CodIva, imp.Iva, imp.Descripcion";

        $impuesto = DB::connection($conexion)->select($sqlImpuestos);

        $SQLimpBolsa = "select '' CodIva, '' Iva, '' Base, ( ( CASE WHEN fd.ValorImpConsumo != 0 THEN fd.ValorImpConsumo ELSE fd.ValorProducto END ) ) as Impuesto, "
            . "'' IvaDomiExpress, 'INC Bolsa Plástica:' Descripcion "
            . "from FacturasDetalle fd inner join Productos p on fd.Producto = p.Producto "
            . "where fd.Factura = '$factura' and fd.ClaseFactura = '$clase' "
            . "and fd.PrefijoFactura = '$prefijo' and fd.Maquina = '$maquina' "
            . "and p.GrupoArticulos in (select isnull(FamiliaEmpaques, '') from Parametros) ";

        $impuestosBolsa = DB::connection($conexion)->select($SQLimpBolsa);
        if (count($impuesto) > 0) {
            if ($express) {
                for ($i = 0; $i < count($impuesto); $i++) {
                    $impuesto[$i]->Descripcion = str_replace('IVA DE VENTAS AL 8 %', 'Impuesto al Consumo', $impuesto[$i]->IvaDomiExpress . '% ' . $impuesto[$i]->Descripcion);
                }
            }
        }

        $impuestoBolsaINC = (Object) [
            "CodIva" => "",
            "Iva" => "",
            "Base" => "",
            "Impuesto" => 0,
            "IvaDomiExpress" => "",
            "Descripcion" => "INC Bolsa Plástica:"
        ];

        foreach ($impuestosBolsa as $impuestoBolsa) {
            $impuestoBolsaINC->Impuesto += intval($impuestoBolsa->Impuesto);
        }

        $impuestos = array_merge($impuesto, array($impuestoBolsaINC));

        if (count($impuestos) > 0) {
            $cabeceraFactura[0]->totalImpuestos = $impuestos;
        }

        $sqlFormasDePago = "select tm.Abreviatura, tm.Nombre, md.ValorMovimiento, isnull(m.CantidadBonos, 0) Bonos, "
            . "isnull(m.NumeroReferencia, '') NumeroRef, isnull(m.DigitosTarjeta, '') DigitosTarjeta, "
            . "isnull(m.Franquicia, '') Franquicia, isnull(m.TipoCuenta, '') TipoCuenta, "
            . "isnull(m.NumeroRecibo, '') NumeroRecibo, m.Movimiento, m.TipoMovimiento "
            . "from Movimientos m inner join MovimientosDetalle md "
            . "on m.TipoMovimiento = md.TipoMovimiento and m.Movimiento = md.Movimiento "
            . "inner join TiposMovimientos tm on m.TipoMovimiento = tm.TipoMovimiento "
            . "where m.MovimientoReferencia = :factura and m.ClaseFacturaReferencia = :clase "
            . "and m.PrefijoFacturaReferencia = :prefijo and m.Maquina = :maquina and m.Estado like '%%' "
            . "and rtrim(tm.Abreviatura) <> '' and md.ValorMovimiento is not null "
            . "and isnull(m.Observaciones, '') not in ('ANUCUADOM', 'ANURECFOR')";
        $formasDePago = DB::connection($conexion)->select($sqlFormasDePago, $parametros);

        if (count($formasDePago) > 0) {
            $cabeceraFactura[0]->formasDePago = $formasDePago;
        }
        return $cabeceraFactura;
    }

    public function getCufeQrFacturaElectronica($factura, $clase, $prefijo, $maquina, $conexion, $express)
    {
        $parametros = ["factura" => $factura, "clase" => $clase, "prefijo" => $prefijo, "maquina" => $maquina];
        $SqlFacturasCufe = 'select f.Factura factura, f.PrefijoFactura prefijo, f.Fecha fecha, f.FechaNovedad fechaNovedad, '
            . ' c.DocumentoIdentidad documento, c.DocumentoVerificado documentoVer, c.Gestionado gestionado, '
            . " c.Identificado identificado, f.EnviaFacEle enviaFacEle, isnull(rf.ClaveTecnica, '') claveTecnica, "
            . ' ValorDomicilio valorDomicilio, IvaDomicilioExpress ivaDomicilio, '
            . ' f.DomicilioGratis domicilioGratis, f.Degustacion degusta, isnull(f.NumeroAnula, 0) numeroAnula, '
            . ' f.FechaAnula fechaAnula, rf.SoftwarePin softwarePin '
            . ' from Facturas f inner join Clientes c on f.Cliente = c.Cliente '
            . ' inner join ResolucionFacturas rf on f.PrefijoFactura = rf.PrefijoFactura '
            . ' and f.NroResolucion = rf.Resolucion '
            . " where f.Factura = :factura and f.ClaseFactura = :clase and f.PrefijoFactura = :prefijo and f.Maquina = :maquina ";
        $FacturasCufe = DB::connection($conexion)->select($SqlFacturasCufe, $parametros);

        $SqlFacturasDetalleCufe = 'select isnull(sum(fd.ValorProducto), 0) valorProducto, '
            . ' isnull(sum(fd.ValorDescuento), 0) valorDescuento, isnull(sum(fd.ValorImpuesto), 0) valorImpuesto, '
            . ' isnull(sum(fd.ValorImpUltraprocesado), 0) ValorImpUltra, '
            . ' isnull(sum(fd.ValorImpConsumo), 0) ValorImpConsumo'
            . ' from FacturasDetalle fd inner join Productos p on fd.Producto = p.Producto '
            . " where fd.Factura = :factura and fd.ClaseFactura = :clase and fd.PrefijoFactura = :prefijo and fd.Maquina = :maquina "
            . " and p.GrupoArticulos <> (select isnull(FamiliaEmpaques, '') from Parametros) ";
        $FacturasDetalleCufe = DB::connection($conexion)->select($SqlFacturasDetalleCufe, $parametros);

        $SqlFacturasOfertasCufe = 'select fd.ValorOferta valorOferta, fd.PorcImpuesto porcImpuesto,fd.ValorImpConsumo,fd.PorcImpConsumo PorcImpConsumo, '
            . 'fd.PorcImpUltraprocesado PorcImpUltra '
            . ' from FacturasDetalle fd inner join Productos p on fd.Producto = p.Producto '
            . ' where fd.Factura = :factura and fd.ClaseFactura = :clase and fd.PrefijoFactura = :prefijo and fd.Maquina = :maquina '
            . " and fd.Oferta = 'X' and p.GrupoArticulos <> (select isnull(FamiliaEmpaques, '') from Parametros) ";
        $FacturasOfertasCufe = DB::connection($conexion)->select($SqlFacturasOfertasCufe, $parametros);

        $SqlFacturasOfertasEmpaque = 'select isnull(sum(fd.ValorProducto), 0) valorProducto, '
            . '    isnull(sum(fd.ValorDescuento), 0) valorDescuento, isnull(sum(fd.ValorImpuesto + fd.ValorImpUltraprocesado + fd.ValorImpConsumo), 0) valorImpuesto '
            . '    from FacturasDetalle fd inner join Productos p on fd.Producto = p.Producto '
            . ' where fd.Factura = :factura and fd.ClaseFactura = :clase and fd.PrefijoFactura = :prefijo and fd.Maquina = :maquina '
            . "    and p.GrupoArticulos in (select isnull(FamiliaEmpaques, '') from Parametros) ";
        $FacturasEmpaqueCufe = DB::connection($conexion)->select($SqlFacturasOfertasEmpaque, $parametros);

        $express = false;
        $degusta = false;

        $fac = $fec = $hor = $docAdq = $cla = "";
        $vf = $vi1 = $vi2 = $vi3 = $ia = $iaC = $iaUlt = $vb = $viUlt = 0;

        $SqlCentroLogisto = "select CentroLogistico from Parametros";
        $CentroLogisto = DB::connection($conexion)->select($SqlCentroLogisto);
        $centro = $CentroLogisto[0]->CentroLogistico;

        if (count($FacturasCufe) <> 0) {
            foreach ($FacturasCufe as $value) {

                $fac = $value->prefijo . $value->factura;
                $fec = date('Y-m-d', strtotime($value->fechaNovedad));
                $hor = date('H:i:s', strtotime($value->fechaNovedad)) . "-05:00";

                if ($value->identificado == "X" || $value->enviaFacEle == 'X') {
                    if ($value->gestionado == 'S')
                        $docAdq = $value->documentoVer == "" ? $value->documento : $value->documentoVer;
                    else
                        $docAdq = $value->documento;
                } else
                    $docAdq = env('CLI_DOC_MOS');

                $cla = $value->claveTecnica;

                if ($value->domicilioGratis == "X") {
                    $ia = $value->ivaDomicilio;
                } else {
                    $vf += $value->ivaDomicilio;
                    $vi1 += $express ? 0 : $value->ivaDomicilio;
                    $vi2 += $express ? $value->ivaDomicilio : 0;
                }
                $degusta = $value->degusta == 'X';
            }
        }

        if (count($FacturasDetalleCufe) > 0) {
            foreach ($FacturasDetalleCufe as $value) {
                $vf += ($value->valorProducto - $value->valorDescuento);
                $vi1 += $value->valorImpuesto;
                $vi2 += $value->ValorImpConsumo;
                $viUlt += $value->ValorImpUltra;
            }
        }

        if (count($FacturasOfertasCufe) > 0) {
            foreach ($FacturasOfertasCufe as $value) {
                $i = round($value->valorOferta * ($value->porcImpuesto / 100));
                $iC = round($value->valorOferta * ($value->PorcImpConsumo / 100));
                $iUlt = round($value->valorOferta * ($value->PorcImpUltra / 100));
                $ia += $i;
                $iaC += $iC;
                $iaUlt += $iUlt;  
            }
        }

        $vbi = 0;
        if (count($FacturasEmpaqueCufe) > 0) {
            foreach ($FacturasEmpaqueCufe as $value) {
                $vb += $value->valorProducto;
                $vbi += $value->valorImpuesto;
            }
        }

        $ajuste = 0;
        $cuponV = 0;
        $vi1 += $ia;
        $vi2 += $iaC;
        $viUlt += $iaUlt;

        $vt = $degusta ? 0 : ($vf + $vi1 + $vi2 + $vi3 + $viUlt + $vb + $vbi - $ajuste - $cuponV - $ia - $iaC - $iaUlt);

        if($vb <= 0 || $vbi <= 0)  $vb = 0;

        $valFac = intval($vf + $vb) . ".00";
        $valImp1 = intval($vi1) . ".00";
        $valImp2 = intval($vi2) . ".00";
        $valImp3 = intval($vi3) . ".00";
        $valTot = intval($vt) . ".00";

        $SqlNitEmp = "select isnull(NitEmpresa, '') NitEmpresa from Parametros";
        $nitEmp = DB::connection($conexion)->select($SqlNitEmp);

        $Sqlamb = "select isnull(AmbienteDian, 1) ambienteDian from Parametros";
        $amb = DB::connection($conexion)->select($Sqlamb);

        $cadenaCufe = $fac . $fec . $hor . $valFac . "01" . $valImp1 . "04" . $valImp2 . "03" . $valImp3 . $valTot . $nitEmp[0]->NitEmpresa . $docAdq . $cla . $amb[0]->ambienteDian;

        $messageDigest = hash('sha384', $cadenaCufe, true);

        $result = '';
        for ($i = 0; $i < strlen($messageDigest); $i++) {
            $result .= sprintf("%02x", ord($messageDigest[$i]));
        }

        $cufe = $result;

        $SqlRutaDian = " select isnull(RutaDian, '') rutaDian from Parametros ";
        $RutaDian = DB::connection($conexion)->select($SqlRutaDian);

        $vio = $vi2 + $vi3 + $viUlt;
        $valImpOtros = intval($vio) . ".00";

        $qr = "NumFac:" . $fac
            . "FecFac:" . $fec
            . "HorFac:" . $hor
            . "NitFac:" . $nitEmp[0]->NitEmpresa
            . "DocAdq:" . $docAdq
            . "ValFac:" . $valFac
            . "ValIva:" . $valImp1
            . "ValOtroIm:" . $valImpOtros
            . "ValTolFac:" . $valTot
            . "CUFE:" . $cufe . " "
            . $RutaDian[0]->rutaDian . $cufe;

        $return = array('cufe' => $cufe, 'qr' => $qr);
        return $return;

    }

    function is_true($val, $return_null = false)
    {
        $boolval = (is_string($val) ? filter_var($val, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : (bool) $val);
        return ($boolval === null && !$return_null ? false : $boolval);
    }


    public function ImprimirAnulacionFactura($factura, $clase, $prefijo, $maquina, $express)
    {
        $conexion = ($this->is_true($express) == true) ? 'sqlsrv2' : 'sqlsrv';
        $parametros = ["factura" => $factura, "clase" => $clase, "prefijo" => $prefijo, "maquina" => $maquina];

        $sqlCabecera = "select f.Factura, f.PrefijoFactura, f.Fecha, f.FechaNovedad, f.Maquina, f.Cliente, c.DocumentoIdentidad, "
            . "c.Nombre NombreCliente, isnull(c.Identificado, '') Identificado, f.Direccion, c.Telefono, f.Barrio, c.Empleado, c.Empresa, f.TipoVenta,  "
            . "isnull(f.Domiciliario, '') Domiciliario,f.NombresEntrega + ' ' + f.ApellidosEntrega NombreEntrega, f.DireccionEntrega, f.BarrioEntrega, "
            . "f.TelefonoEntrega, f.NombreRecibe, f.Efectivo, f.Cambio, f.ValorDomicilio, f.IvaDomicilioExpress, "
            . "f.GrupoPrecios, isnull(f.Pedido, 0) Pedido, isnull(pc.Observaciones, '') Observaciones, "
            . "isnull(pc.TipoPago, '') TipoPago, f.Estado, gp.Nombre NombreGrupoPrecios, "
            . "isnull(c.CondicionPago, 'PI') CondicionPago, isnull(f.DiasPlazo, 0) DiasPlazo, "
            . "gp.ImprimeUnidades, isnull(f.OrigenPedido, 'E') OrigenPedido, f.Turno, "
            . "f.Usuario, f.ClaseFactura, f.DomicilioGratis, isnull(pa.TipoAlmacen, 'P') TipoAlmacen, "
            . "isnull(pc.Cupon, '') Cupon, isnull(pc.PagoLinea, '') PagoLinea, isnull(pc.EstadoPago, '') EstadoPago, "
            . "isnull(pc.NumeroAprobacion, '') NumAprobacion, isnull(pc.NumeroEpayco, '') NumEpayco, "
            . "isnull(pc.TipoTransaccion, '') TipoTransaccion, isnull(pc.ViaPago, '') ViaPago, "
            . "isnull(pc.Plataforma, '') Plataforma, pa.CentroLogistico, pa.MensajeEmpresa, pa.NombreCentroLogistico, pa.DireccionCentroLogistico,  "
            . "pa.TelefonoCentroLogistico, pa.CiudadCentroLogistico, "
            . "isnull((select NombreCiudad from Ciudades where Ciudad = pa.CiudadCentroLogistico), '') NombreCiudadCentroLogistico, "
            . "pa.MensajeFactura1, pa.MensajeFactura2, pa.MensajeFactura3, pa.MensajeFactura4, pa.MensajeFactura5, "
            . "dateadd(dd, f.DiasPlazo, f.Fecha) FechaVence, f.UsuarioAnula, u.Nombre NombreAnula, f.FechaAnula, "
            . "f.MotivoAnulaFactura, ma.Nombre NombreMotivoAnula, f.EnviaFacEle, isnull(f.NumeroAnula, 0) NumeroAnula,  "
            . "c.Gestionado, c.DocumentoVerificado, isnull(c.Ciudad, '') Ciudad, isnull(cd.NombreCiudad, '') NombreCiudad,  "
            . "isnull(c.TipoDocumento, '') TipoDocumento, "
            . "isnull(( "
            . "    select sum(md.ValorMovimiento) valorMovimiento "
            . "    from Movimientos m inner join MovimientosDetalle md "
            . "    on m.TipoMovimiento = md.TipoMovimiento and m.Movimiento = md.Movimiento "
            . "    inner join TiposMovimientos tm on m.TipoMovimiento = tm.TipoMovimiento "
            . "    where m.MovimientoReferencia = '$factura' and m.ClaseFacturaReferencia = '$clase' "
            . "    and m.PrefijoFacturaReferencia = '$prefijo' and m.Maquina = '$maquina' and m.Estado like '%%' "
            . "    and m.TipoMovimiento = '500' and isnull(m.Observaciones, '') not in ('ANUCUADOM', 'ANURECFOR') "
            . "), 0) AjusteFraccion "
            . "from Facturas f inner join Clientes c on f.Cliente = c.Cliente "
            . "inner join Empresas e on c.Empresa = e.Empresa "
            . "left join PedidosClientes pc on f.Pedido = pc.Pedido "
            . "inner join GrupoPrecios gp on f.GrupoPrecios = gp.GrupoPrecios "
            . "left join Usuarios u on f.UsuarioAnula = u.Cedula "
            . "left join MotivosAnulaFacturas ma on f.MotivoAnulaFactura = ma.MotivoAnulaFactura "
            . "left join Ciudades cd on c.Ciudad = cd.Ciudad "
            . "full join Parametros pa on 1 = 1 "
            . "where f.Factura = '$factura' and f.ClaseFactura = '$clase' and f.PrefijoFactura = '$prefijo' and f.Maquina = '$maquina' ";
        $cabeceraFactura = DB::connection($conexion)->select($sqlCabecera);

        if (count($cabeceraFactura) == 0) {
            return response()->json([
                'status' => false,
                'message' => "Factura no encontrada, por favor revise.",
                'cabecera' => array()
            ], 200);
        }

        $cabeceraFactura[0]->Titulo = 'NOTA CREDITO No. A' . $cabeceraFactura[0]->CentroLogistico . ' - ' . $cabeceraFactura[0]->NumeroAnula;
        $cabeceraFactura[0]->TituloVenta = 'FE de Venta: ' . $prefijo . ' - ' . $factura;

        $cufe = $ruta = "";
        $CufeQrFacturaElectronica = $this->getCudeQrAnulacion($factura, $clase, $prefijo, $maquina, $conexion, $express);

        $cude = $CufeQrFacturaElectronica['cude'];
        $cufe = $CufeQrFacturaElectronica['cufe'];
        $ruta = $CufeQrFacturaElectronica['qr'];

        $cabeceraFactura[0]->CUFE = $cufe;
        $cabeceraFactura[0]->RutaDian = $ruta;
        $cabeceraFactura[0]->CUDE = $cude;

        $parametrosDetalle = [
            "factura" => $factura,
            "clase" => $clase,
            "prefijo" => $prefijo,
            "maquina" => $maquina,
            "factura1" => $factura,
            "clase1" => $clase,
            "prefijo1" => $prefijo,
            "maquina1" => $maquina
        ];
        $sqlDetalle = "select row_number() over (order by p.Orden, p.Producto) Item, * "
            . " from ( "
            . "	select fd.Producto, fd.UnidadMedidaVenta, p.Nombre, fd.Oferta, "
            . "	fd.Unidades, fd.Kilos, fd.Precio, fd.ValorProducto, fd.ValorDescuento, fd.ValorImpuesto, fd.ValorOferta, "
            . "	fd.SaborBebida, isnull(i.CodIva, '') CodIva, p.Ean, fd.Empaque, 1 Orden, 'X' Mostrar, 0 ValorDomicilio, "
            . "	fd.ValorDescuentoConvenio DescuentoConvenio, pa.ValorDescuentoConvenio valordescConvenio, "
            . "	case when fd.PorcImpUltraprocesado <> 0 then 'U' else '' end ImpUltra "
            . "	from Facturas f inner join FacturasDetalle fd on f.Factura = fd.Factura "
            . "	and f.ClaseFactura = fd.ClaseFactura and f.PrefijoFactura = fd.PrefijoFactura "
            . "	and f.Maquina = fd.Maquina "
            . "	inner join Productos p on fd.Producto = p.Producto "
            . "	left join Impuestos i on CONCAT('IVV' , fd.PorcImpuesto) = i.Codigo "
            . "	inner join Parametros pa on 1=1 "
            . "	where fd.Factura = :factura and fd.ClaseFactura = :clase "
            . "	and fd.PrefijoFactura = :prefijo and fd.Maquina = :maquina "
            //. "	and p.GrupoArticulos <> (select isnull(FamiliaEmpaques, '') from Parametros) "
            . "	union "
            . "	select fd.Producto, fd.UnidadMedidaVenta, p.Nombre, '' Oferta, "
            . "	fd.Unidades, fd.Kilos, 0 Precio, 0 ValorProducto, 0 ValorDescuento, 0 ValorImpuesto, 0 ValorOferta, "
            . "	'' SaborBebida, '' CodIva, '' Ean, fd.Empaque, 3 Orden, 'X' Mostrar, 0 ValorDomicilio, 0 DescuentoConvenio, 0 valordescConvenio, '' ImpUltra "
            . "	from FacturasDetalle fd inner join Productos p on fd.Producto = p.Producto "
            . "	where fd.Factura = :factura1 and fd.ClaseFactura = :clase1 "
            . "	and fd.PrefijoFactura = :prefijo1 and fd.Maquina = :maquina1 "
            . "	and fd.Precio = 0 and p.GrupoArticulos in (select isnull(FamiliaEmpaques, '') from Parametros) "
            . ") p where p.Mostrar = 'X' ";

        $detalleFactura = DB::connection($conexion)->select($sqlDetalle, $parametrosDetalle);

        if (count($detalleFactura) > 0) {
            $cabeceraFactura[0]->detalleFactura = $detalleFactura;
        }

        $sqlImpuestos = "select imp.CodIva, imp.Iva, sum(imp.Base) Base, sum(imp.Impuesto) Impuesto, "
            . "(select IvaDomiExpress from Parametros) IvaDomiExpress, Descripcion "
            . "from "
            . "( "
            . "    select isnull(i.CodIva, '') CodIva, isnull(i.Valor, 0) Iva, "
            . "    case when f.Degustacion = 'X' then fd.ValorProducto "
            . "    else (fd.ValorProducto - fd.ValorDescuento - fd.ValorDescuentoConvenio) end Base,  "
            . "    fd.ValorImpuesto Impuesto, i.Descripcion Descripcion "
            . "    from Facturas f inner join FacturasDetalle fd on f.Factura = fd.Factura "
            . "    and f.ClaseFactura = fd.ClaseFactura and f.PrefijoFactura = fd.PrefijoFactura "
            . "    and f.Maquina = fd.Maquina "
            . "    inner join Productos p on fd.Producto = p.Producto "
            . "    left join Impuestos i on CONCAT('IVV' , fd.PorcImpuesto) = i.Codigo "
            . "    where fd.Factura = :factura and fd.ClaseFactura = :clase "
            . "    and fd.PrefijoFactura = :prefijo and fd.Maquina = :maquina "
            . "    and p.GrupoArticulos <> (select isnull(FamiliaEmpaques, '') from Parametros) "
            . "    Union ALL "
            . "    select isnull(i.CodIva, '') CodIva, isnull(i.Valor, 0) Iva, "
            . "    case when f.Degustacion = 'X' then fd.ValorProducto "
            . "    else (fd.ValorProducto - fd.ValorDescuento - fd.ValorDescuentoConvenio) end Base,  "
            . "    fd.ValorImpUltraprocesado Impuesto, i.Descripcion Descripcion "
            . "    from Facturas f inner join FacturasDetalle fd on f.Factura = fd.Factura "
            . "    and f.ClaseFactura = fd.ClaseFactura and f.PrefijoFactura = fd.PrefijoFactura "
            . "    and f.Maquina = fd.Maquina "
            . "    inner join Productos p on fd.Producto = p.Producto "
            . "    inner join Impuestos i on CONCAT('ULT' ,fd.PorcImpUltraprocesado) = i.Codigo "
            . "    where fd.Factura = :factura1 and fd.ClaseFactura = :clase1 "
            . "    and fd.PrefijoFactura = :prefijo1 and fd.Maquina = :maquina1 "
            . "    and p.GrupoArticulos <> (select isnull(FamiliaEmpaques, '') from Parametros) "
            . ") imp "
            . "where imp.CodIva <> '' group by imp.CodIva, imp.Iva, imp.Descripcion";

        $impuesto = DB::connection($conexion)->select($sqlImpuestos, $parametrosDetalle);

        $SQLimpBolsa = "select '' CodIva, '' Iva, '' Base, fd.ValorImpConsumo as Impuesto, "
            . "'' IvaDomiExpress, 'INC Bolsa Plástica:' Descripcion "
            . "from FacturasDetalle fd inner join Productos p on fd.Producto = p.Producto "
            . "where fd.Factura = '$factura' and fd.ClaseFactura = '$clase' "
            . "and fd.PrefijoFactura = '$prefijo' and fd.Maquina = '$maquina' "
            . "and p.GrupoArticulos in (select isnull(FamiliaEmpaques, '') from Parametros) ";

        $impuestosBolsa = DB::connection($conexion)->select($SQLimpBolsa);
        if (count($impuesto) > 0) {
            if ($express) {
                for ($i = 0; $i < count($impuesto); $i++) {
                    $impuesto[$i]->Descripcion = str_replace('IVA DE VENTAS AL 8 %', 'Impuesto al Consumo', $impuesto[$i]->IvaDomiExpress . '% ' . $impuesto[$i]->Descripcion);
                }
            }
        }

        $impuestoBolsaINC = (Object) [
            "CodIva" => "",
            "Iva" => "",
            "Base" => "",
            "Impuesto" => 0,
            "IvaDomiExpress" => "",
            "Descripcion" => "INC Bolsa Plástica:"
        ];

        foreach ($impuestosBolsa as $impuestoBolsa) {
            $impuestoBolsaINC->Impuesto += intval($impuestoBolsa->Impuesto);
        }

        $impuestos = array_merge($impuesto, array($impuestoBolsaINC));

        if (count($impuestos) > 0) {
            $cabeceraFactura[0]->totalImpuestos = $impuestos;
        }

        $sqlFormasDePago = "select tm.Abreviatura, tm.Nombre, md.ValorMovimiento, isnull(m.CantidadBonos, 0) Bonos, "
            . "isnull(m.NumeroReferencia, '') NumeroRef, isnull(m.DigitosTarjeta, '') DigitosTarjeta, "
            . "isnull(m.Franquicia, '') Franquicia, isnull(m.TipoCuenta, '') TipoCuenta, "
            . "isnull(m.NumeroRecibo, '') NumeroRecibo, m.Movimiento, m.TipoMovimiento "
            . "from Movimientos m inner join MovimientosDetalle md "
            . "on m.TipoMovimiento = md.TipoMovimiento and m.Movimiento = md.Movimiento "
            . "inner join TiposMovimientos tm on m.TipoMovimiento = tm.TipoMovimiento "
            . "where m.MovimientoReferencia = :factura and m.ClaseFacturaReferencia = :clase "
            . "and m.PrefijoFacturaReferencia = :prefijo and m.Maquina = :maquina and m.Estado like '%%' "
            . "and rtrim(tm.Abreviatura) <> '' and md.ValorMovimiento is not null "
            . "and isnull(m.Observaciones, '') not in ('ANUCUADOM', 'ANURECFOR')";
        $formasDePago = DB::connection($conexion)->select($sqlFormasDePago, $parametros);

        if (count($formasDePago) > 0) {
            $cabeceraFactura[0]->formasDePago = $formasDePago;
        }

        if (count($cabeceraFactura) > 0) {
            return response()->json([
                'status' => true,
                'message' => "Factura encontrada",
                'cabecera' => $cabeceraFactura
            ], 200);
        } else {
            return response()->json([
                'status' => false,
                'message' => "Factura no encontrada, por favor revise.",
                'cabecera' => array()
            ], 200);
        }
    }


    public function getCudeQrAnulacion($factura, $clase, $prefijo, $maquina, $conexion, $express)
    {
        $parametros = ["factura" => $factura, "clase" => $clase, "prefijo" => $prefijo, "maquina" => $maquina];
        $SqlFacturasCufe = 'select f.Factura factura, f.PrefijoFactura prefijo, f.Fecha fecha, f.FechaNovedad fechaNovedad, '
            . ' c.DocumentoIdentidad documento, c.DocumentoVerificado documentoVer, c.Gestionado gestionado, '
            . " c.Identificado identificado, f.EnviaFacEle enviaFacEle, isnull(rf.ClaveTecnica, '') claveTecnica, "
            . ' ValorDomicilio valorDomicilio, IvaDomicilioExpress ivaDomicilio, '
            . ' f.DomicilioGratis domicilioGratis, f.Degustacion degusta, isnull(f.NumeroAnula, 0) numeroAnula, '
            . ' f.FechaAnula fechaAnula, rf.SoftwarePin softwarePin '
            . ' from Facturas f inner join Clientes c on f.Cliente = c.Cliente '
            . ' inner join ResolucionFacturas rf on f.PrefijoFactura = rf.PrefijoFactura '
            . ' and f.NroResolucion = rf.Resolucion '
            . " where f.Factura = :factura and f.ClaseFactura = :clase and f.PrefijoFactura = :prefijo and f.Maquina = :maquina ";
        $FacturasCufe = DB::connection($conexion)->select($SqlFacturasCufe, $parametros);

        $SqlFacturasDetalleCufe = 'select isnull(sum(fd.ValorProducto), 0) valorProducto, '
            . ' isnull(sum(fd.ValorDescuento), 0) valorDescuento, isnull(sum(fd.ValorImpuesto), 0) valorImpuesto, '
            . ' isnull(sum(fd.ValorImpUltraprocesado), 0) ValorImpUltra, '
            . ' isnull(sum(fd.ValorImpConsumo), 0) ValorImpConsumo'
            . ' from FacturasDetalle fd inner join Productos p on fd.Producto = p.Producto '
            . " where fd.Factura = :factura and fd.ClaseFactura = :clase and fd.PrefijoFactura = :prefijo and fd.Maquina = :maquina "
            . " and p.GrupoArticulos <> (select isnull(FamiliaEmpaques, '') from Parametros) ";
        $FacturasDetalleCufe = DB::connection($conexion)->select($SqlFacturasDetalleCufe, $parametros);

        $SqlFacturasOfertasCufe = 'select fd.ValorOferta valorOferta, fd.PorcImpuesto porcImpuesto,fd.ValorImpConsumo,fd.PorcImpConsumo PorcImpConsumo, '
            . 'fd.PorcImpUltraprocesado PorcImpUltra '
            . ' from FacturasDetalle fd inner join Productos p on fd.Producto = p.Producto '
            . ' where fd.Factura = :factura and fd.ClaseFactura = :clase and fd.PrefijoFactura = :prefijo and fd.Maquina = :maquina '
            . " and fd.Oferta = 'X' and p.GrupoArticulos <> (select isnull(FamiliaEmpaques, '') from Parametros) ";
        $FacturasOfertasCufe = DB::connection($conexion)->select($SqlFacturasOfertasCufe, $parametros);

        $SqlFacturasOfertasEmpaque = 'select isnull(sum(fd.ValorProducto), 0) valorProducto, '
            . '    isnull(sum(fd.ValorDescuento), 0) valorDescuento, isnull(sum(fd.ValorImpuesto + fd.ValorImpUltraprocesado), 0) valorImpuesto '
            . '    from FacturasDetalle fd inner join Productos p on fd.Producto = p.Producto '
            . ' where fd.Factura = :factura and fd.ClaseFactura = :clase and fd.PrefijoFactura = :prefijo and fd.Maquina = :maquina '
            . "    and p.GrupoArticulos in (select isnull(FamiliaEmpaques, '') from Parametros) ";
        $FacturasEmpaqueCufe = DB::connection($conexion)->select($SqlFacturasOfertasEmpaque, $parametros);

        $express = false;
        $degusta = false;

        $fac = $fec = $hor = $docAdq = $cla = "";
        $vf = $vi1 = $vi2 = $vi3 = $ia = $iaC = $iaUlt = $vb = $viUlt = 0;

        $SqlCentroLogisto = "select CentroLogistico from Parametros";
        $CentroLogisto = DB::connection($conexion)->select($SqlCentroLogisto);
        $centro = $CentroLogisto[0]->CentroLogistico;

        if (count($FacturasCufe) <> 0) {
            foreach ($FacturasCufe as $value) {

                $fac = "A" . $centro . $value->numeroAnula;
                $fec = date('Y-m-d', strtotime($value->fechaAnula));
                $hor = date('H:i:s', strtotime($value->fechaAnula)) . "-05:00";

                if ($value->identificado == "X" || $value->enviaFacEle == 'X') {
                    if ($value->gestionado == 'S')
                        $docAdq = $value->documentoVer == "" ? $value->documento : $value->documentoVer;
                    else
                        $docAdq = $value->documento;
                } else
                    $docAdq = env('CLI_DOC_MOS');

                $cla = $value->softwarePin;

                if ($value->domicilioGratis == "X") {
                    $ia = $value->ivaDomicilio;
                } else {
                    $vf += $value->ivaDomicilio;
                    $vi1 += $express ? 0 : $value->ivaDomicilio;
                    $vi2 += $express ? $value->ivaDomicilio : 0;
                }
                $degusta = $value->degusta == 'X';
            }
        }

        if (count($FacturasDetalleCufe) > 0) {
            foreach ($FacturasDetalleCufe as $value) {
                $vf += ($value->valorProducto - $value->valorDescuento);
                $vi1 += $value->valorImpuesto;
                $vi2 += $value->ValorImpConsumo;
                $viUlt += $value->ValorImpUltra;
            }
        }

        if (count($FacturasOfertasCufe) > 0) {
            foreach ($FacturasOfertasCufe as $value) {
                $i = round($value->valorOferta * ($value->porcImpuesto / 100));
                $iC = round($value->valorOferta * ($value->PorcImpConsumo / 100));
                $iUlt = round($value->valorOferta * ($value->PorcImpUltra / 100));
                $ia += $i;
                $iaC += $iC;
                $iaUlt += $iUlt;  
            }
        }

        if (count($FacturasEmpaqueCufe) > 0) {
            foreach ($FacturasEmpaqueCufe as $value) {
                $vb += $value->valorProducto;
            }
        }

        $ajuste = 0;
        $cuponV = 0;
        $vi1 += $ia;
        $vi2 += $iaC;
        $viUlt += $iaUlt;

        $vt = $degusta ? 0 : ($vf + $vi1 + $vi2 + $vi3 + $viUlt + $vb - $ajuste - $cuponV - $ia);

        $valFac = intval($vf) . ".00";
        $valImp1 = intval($vi1) . ".00";
        $valImp2 = intval($vi2) . ".00";
        $valImp3 = intval($vi3) . ".00";
        $valTot = intval($vt) . ".00";

        $SqlNitEmp = "select isnull(NitEmpresa, '') NitEmpresa from Parametros";
        $nitEmp = DB::connection($conexion)->select($SqlNitEmp);

        $Sqlamb = "select isnull(AmbienteDian, 1) ambienteDian from Parametros";
        $amb = DB::connection($conexion)->select($Sqlamb);

        $cadenaCufe = $fac . $fec . $hor . $valFac . "01" . $valImp1 . "04" . $valImp2 . "03" . $valImp3 . $valTot . $nitEmp[0]->NitEmpresa . $docAdq . $cla . $amb[0]->ambienteDian;

        $messageDigest = hash('sha384', $cadenaCufe, true);

        $result = '';
        for ($i = 0; $i < strlen($messageDigest); $i++) {
            $result .= sprintf("%02x", ord($messageDigest[$i]));
        }

        $cude = $result;

        $CufeQrFacturaElectronica = $this->getCufeQrFacturaElectronica($factura, $clase, $prefijo, $maquina, $conexion, $express);

        $cufe = $CufeQrFacturaElectronica['cufe'];

        $SqlRutaDian = " select isnull(RutaDian, '') rutaDian from Parametros ";
        $RutaDian = DB::connection($conexion)->select($SqlRutaDian);

        $vio = $vi2 + $vi3;
        $valImpOtros = intval($vio) . ".00";

        $qr = "NumFac:" . $fac
            . "FecFac:" . $fec
            . "HorFac:" . $hor
            . "NitFac:" . $nitEmp[0]->NitEmpresa
            . "DocAdq:" . $docAdq
            . "ValFac:" . $valFac
            . "ValIva:" . $valImp1
            . "ValOtroIm:" . $valImpOtros
            . "ValTolFac:" . $valTot
            . "CUDE:" . $cude
            . $RutaDian[0]->rutaDian . $cude;

        $return = array('cude' => $cude, 'cufe' => $cufe, 'qr' => $qr);
        return $return;

    }


}