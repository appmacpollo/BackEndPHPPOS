<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ImprimirController extends Controller
{
    public function ImprimirFactura($factura, $clase, $prefijo, $maquina) {
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
        $cabeceraFactura = DB::select($sqlCabecera, $parametros);
        
        if(count($cabeceraFactura) == 0)
        {
            return response()->json([
                'status' => false,
                'message' => "Factura no encontrada, por favor revise.",
                'cabecera' => array()
            ], 200);
        }

        $cabeceraFactura[0]->RutaDian = "";
        $cabeceraFactura[0]->CUFE = "";

        $parametrosDetalle = ["factura" => $factura, "clase" => $clase, "prefijo" => $prefijo, "maquina" => $maquina, 
                              "factura1" => $factura, "clase1" => $clase, "prefijo1" => $prefijo, "maquina1" => $maquina];
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
        . "	fd.Unidades, fd.Kilos, 0 Precio, 0 ValorProducto, 0 ValorDescuento, 0 ValorImpuesto, 0 ValorOferta, "
        . "	'' SaborBebida, '' CodIva, '' Ean, fd.Empaque, 3 Orden, 'X' Mostrar, 0 ValorDomicilio, 0 DescuentoConvenio, 0 valordescConvenio, '' ImpUltra "
        . "	from FacturasDetalle fd inner join Productos p on fd.Producto = p.Producto "
        . "	where fd.Factura = :factura1 and fd.ClaseFactura = :clase1 "
        . "	and fd.PrefijoFactura = :prefijo1 and fd.Maquina = :maquina1 "
        . "	and p.GrupoArticulos in (select isnull(FamiliaEmpaques, '') from Parametros) "
        . ") p where p.Mostrar = 'X'";
        $detalleFactura = DB::select($sqlDetalle, $parametrosDetalle);

        if(count($detalleFactura) > 0)
        {
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

        $impuestos = DB::select($sqlImpuestos, $parametrosDetalle);

        if(count($impuestos) > 0)
        {
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
        $formasDePago = DB::select($sqlFormasDePago, $parametros);

        if(count($formasDePago) > 0)
        {
            $cabeceraFactura[0]->formasDePago = $formasDePago;
        }

        return response()->json([
            'status' => true,
            'message' => "Factura encontrada",
            'cabecera' => $cabeceraFactura
        ], 200);
    }

}