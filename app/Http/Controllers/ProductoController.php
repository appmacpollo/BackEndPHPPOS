<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductoController extends Controller
{
    public function ConsultarProducto($ean)
    {
        $grupoPrecios = config('app.grupoPrecios');
        $codigoBarras = $ean;

        $producto = DB::select('SELECT top 1 p.Producto producto, pr.UnidadMedidaVenta unidad, p.Nombre nombre,'
        . 'isnull(p.PesoPromedio, 0) pesoPromedio, pr.Precio precio, p.ValorImpuesto impuesto,'
        . 'p.Pesado pesado, isnull(p.PesoMinimo, 0) pesoMinimo,'
        . 'isnull(p.PesoMaximo, 0) pesoMaximo, p.ToleranciaMinima tolMinima,'
        . 'p.ToleranciaMaxima tolMaxima, isnull(p.Existencias, 0) existencias,'
        . "(select case when count(Componente) = 0 then '' else 'X' end Com from Combos where Combo = p.Producto and Estado = 'A') combo, "
        . "isnull(p.ExistenciasK, 0) existenciasK, ValorImpUltraprocesado as impProcesado,'' oferta "
        . 'FROM productos p INNER JOIN Precios pr on p.Producto = pr.Producto'
        . " WHERE pr.GrupoPrecios = '$grupoPrecios' and p.Ean = '$codigoBarras' "
        . " and pr.Estado = 'A' and p.Estado = 'A' and p.UnidadMedidaBase = 'S' and pr.Precio > 0 ");

        if(count($producto) == 0)
        {
            return response()->json([
                'status' => false,
                'message' => "Producto no encontrado, Producto sin precios y/o no fue encontrado.",
                'product' => array()
            ], 204);
        }

        $descuento = DB::select('SELECT top 1 valor,ClaseDescuento,TipoDescuento '
        . 'FROM Descuentos '
        . 'inner join productos on productos.producto = Descuentos.producto '
        . "where productos.Ean = '$codigoBarras' and GrupoPrecios = '$grupoPrecios' and Descuentos.Estado = 'A' "
        . 'and (select FechaProceso from Parametros ) between fechaInicio and fechaFin '
        . 'order by ClaseDescuento desc');

        $ofertas = DB::select('SELECT Ofertas.Producto,Cantidad,ProductoOferta,CantidadOferta,OfertasDetalle.UnidadMedidaVenta  '
        . 'FROM Ofertas '
        . 'inner join Productos on Productos.Producto = Ofertas.Producto '
        . 'inner join OfertasDetalle on Ofertas.Oferta = OfertasDetalle.Oferta '
        . "WHERE productos.Ean = '$codigoBarras' and GrupoPrecios = '$grupoPrecios' "
        . 'and (select FechaProceso from Parametros ) between FechaDesde and FechaHasta ');
 
        $productoOfertas = array();
        foreach ($ofertas as $value) 
        {
            $productoOfertas = DB::select('SELECT top 1 p.Producto producto, pr.UnidadMedidaVenta unidad, p.Nombre nombre,'
            . 'isnull(p.PesoPromedio, 0) pesoPromedio, pr.Precio precio, p.ValorImpuesto impuesto,'
            . 'p.Pesado pesado, isnull(p.PesoMinimo, 0) pesoMinimo,'
            . 'isnull(p.PesoMaximo, 0) pesoMaximo, p.ToleranciaMinima tolMinima,'
            . 'p.ToleranciaMaxima tolMaxima, isnull(p.Existencias, 0) existencias,'
            . "(select case when count(Componente) = 0 then '' else 'X' end Com from Combos where Combo = p.Producto and Estado = 'A') combo, "
            . "isnull(p.ExistenciasK, 0) existenciasK, ValorImpUltraprocesado as impProcesado,'X' oferta "
            . 'FROM productos p INNER JOIN Precios pr on p.Producto = pr.Producto'
            . " WHERE pr.GrupoPrecios = '$grupoPrecios' and p.producto = '". $value->ProductoOferta ."' "
            . " and pr.Estado = 'A' and p.Estado = 'A' and p.UnidadMedidaBase = 'S' and pr.Precio > 0 ");
        }

        $productos = array("Producto" => $producto, "Descuento" => $descuento, "Oferta" => $ofertas, "productoOferta" => $productoOfertas);

        return response()->json([
            'status' => true,
            'message' => "Producto Encontrado",
            'product' => $productos
        ], 200);
    }

    public function ValidarCaja()
    {
        //Validar que no este en la ventana de cierre
        $Ventanas = DB::select(" SELECT * FROM Ventanas where Opcion in ('INF_CIPRO','INV_FIS') ");
        if(count($Ventanas) >= 1)
        {
            return response()->json([
                'status' => false,
                'message' => "No es posible realizar venta. Caja principal se encuentra en Cierre, Inventario Fisico.",
            ], 200);
        }

        return response()->json([
            'status' => true,
            'message' => "",
        ], 200);
    }

    public function ConsultarProductoInterno($codId)
    {
        $grupoPrecios = config('app.grupoPrecios');
        $producto = DB::select('SELECT top 1 p.Producto producto, pr.UnidadMedidaVenta unidad, p.Nombre nombre,'
            . 'isnull(p.PesoPromedio, 0) pesoPromedio, pr.Precio precio, p.ValorImpuesto impuesto,'
            . 'p.Pesado pesado, isnull(p.PesoMinimo, 0) pesoMinimo,'
            . 'isnull(p.PesoMaximo, 0) pesoMaximo, p.ToleranciaMinima tolMinima,'
            . 'p.ToleranciaMaxima tolMaxima, isnull(p.Existencias, 0) existencias,'
            . "(select case when count(Componente) = 0 then '' else 'X' end Com from Combos where Combo = p.Producto and Estado = 'A') combo, "
            . "isnull(p.ExistenciasK, 0) existenciasK, ValorImpUltraprocesado as impProcesado,'' oferta "
            . 'FROM productos p INNER JOIN Precios pr on p.Producto = pr.Producto'
            . " WHERE pr.GrupoPrecios = '$grupoPrecios' and p.producto = '$codId' "
            . " and pr.Estado = 'A' and p.Estado = 'A' and p.UnidadMedidaBase = 'S' and pr.Precio > 0 ");

        $descuento = DB::select('SELECT top 1 valor,ClaseDescuento,TipoDescuento '
            . 'FROM Descuentos '
            . 'inner join productos on productos.producto = Descuentos.producto '
            . "where productos.producto = '$codId' and GrupoPrecios = '$grupoPrecios' and Descuentos.Estado = 'A' "
            . 'and (select FechaProceso from Parametros ) between fechaInicio and fechaFin '
            . 'order by ClaseDescuento desc');
  
        return $productos = array("Producto" => $producto, "Descuento" => $descuento);
    }

    
}
