<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductoController extends Controller
{
    public function ConsultarProducto($ean)
    {
        $grupoPrecios = env('grupoPrecios');
        $codigoBarras = $ean;

        $producto = DB::connection('sqlsrv')->select('SELECT top 1 p.Producto producto, pr.UnidadMedidaVenta unidad, p.Nombre nombre,'
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
            ], 200);
        }

        $descuento = DB::connection('sqlsrv')->select('SELECT top 1 valor,ClaseDescuento,TipoDescuento '
        . 'FROM Descuentos '
        . 'inner join productos on productos.producto = Descuentos.producto '
        . "where productos.Ean = '$codigoBarras' and GrupoPrecios = '$grupoPrecios' and Descuentos.Estado = 'A' "
        . 'and (select FechaProceso from Parametros ) between fechaInicio and fechaFin '
        . 'order by ClaseDescuento desc');

        $ofertas = DB::connection('sqlsrv')->select('SELECT Ofertas.Producto,Cantidad,ProductoOferta,CantidadOferta,OfertasDetalle.UnidadMedidaVenta  '
        . 'FROM Ofertas '
        . 'inner join Productos on Productos.Producto = Ofertas.Producto '
        . 'inner join OfertasDetalle on Ofertas.Oferta = OfertasDetalle.Oferta '
        . "WHERE productos.Ean = '$codigoBarras' and GrupoPrecios = '$grupoPrecios' "
        . 'and (select FechaProceso from Parametros ) between FechaDesde and FechaHasta ');
 
        $productoOfertas = array();
        foreach ($ofertas as $value) 
        {
            $productoOfertas = DB::connection('sqlsrv')->select('SELECT top 1 p.Producto producto, pr.UnidadMedidaVenta unidad, p.Nombre nombre,'
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

    public function ValidarCaja($express)
    {
        $sqlsrv = ($this->is_true($express) == true ) ? 'sqlsrv2' : 'sqlsrv' ;   
        //Validar que no este en la ventana de cierre
        $Ventanas = DB::connection($sqlsrv)->select(" SELECT * FROM Ventanas where Opcion in ('INF_CIPRO','INV_FIS') ");
        if(count($Ventanas) >= 1)
        {
            return response()->json([
                'status' => false,
                'message' => "No es posible realizar venta. Caja principal se encuentra en Cierre, Inventario Fisico.",
            ], 200);
        }

        $claseFactura = env('claseFactura');
        $maquina = env('maquina');
        $resolucionFacturas = DB::connection($sqlsrv)->select('SELECT top 1 PrefijoFactura prefijo, Resolucion resolucion, Consecutivo consecutivo, NumeroDesde desde, '
            .' NumeroHasta hasta, FechaResolucionHasta fechaResHasta '
            .' from ResolucionFacturas '
            ."where ClaseFactura = '$claseFactura' AND Maquina = '$maquina' AND FechaAplicaHasta is null ");

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
                'message' => "No se encuentra Resolución de Factura activa para " . $claseFactura . " en la Máquina " . $maquina 
            ], 200);
        }

        if( ($consecutivo < $consecutivoDesde) || ($consecutivo > $consecutivoHasta) )
        {
            return response()->json([
                'status' => false,
                'message' => "Lo sentimos, El consecutivo fuera del rango .",
            ], 200);
        }

        return response()->json([
            'status' => true,
            'message' => "",
        ], 200);

    }

    public function ConsultarProductoInterno($codId)
    {
        $grupoPrecios = env('grupoPrecios');
        $producto = DB::connection('sqlsrv')->select('SELECT top 1 p.Producto producto, pr.UnidadMedidaVenta unidad, p.Nombre nombre,'
            . 'isnull(p.PesoPromedio, 0) pesoPromedio, pr.Precio precio, p.ValorImpuesto impuesto,'
            . 'p.Pesado pesado, isnull(p.PesoMinimo, 0) pesoMinimo,'
            . 'isnull(p.PesoMaximo, 0) pesoMaximo, p.ToleranciaMinima tolMinima,'
            . 'p.ToleranciaMaxima tolMaxima, isnull(p.Existencias, 0) existencias,'
            . "(select case when count(Componente) = 0 then '' else 'X' end Com from Combos where Combo = p.Producto and Estado = 'A') combo, "
            . "isnull(p.ExistenciasK, 0) existenciasK, ValorImpUltraprocesado as impProcesado,'' oferta "
            . 'FROM productos p INNER JOIN Precios pr on p.Producto = pr.Producto'
            . " WHERE pr.GrupoPrecios = '$grupoPrecios' and p.producto = '$codId' "
            . " and pr.Estado = 'A' and p.Estado = 'A' and p.UnidadMedidaBase = 'S' and pr.Precio > 0 ");

        $descuento = DB::connection('sqlsrv')->select('SELECT top 1 valor,ClaseDescuento,TipoDescuento '
            . 'FROM Descuentos '
            . 'inner join productos on productos.producto = Descuentos.producto '
            . "where productos.producto = '$codId' and GrupoPrecios = '$grupoPrecios' and Descuentos.Estado = 'A' "
            . 'and (select FechaProceso from Parametros ) between fechaInicio and fechaFin '
            . 'order by ClaseDescuento desc');
  
        return $productos = array("Producto" => $producto, "Descuento" => $descuento);
    }

    public function ValidarCajaExpress()
    {
        //Validar que no este en la ventana de cierre
        $Ventanas = DB::connection('sqlsrv2')->select(" SELECT * FROM Ventanas where Opcion in ('INF_CIPRO','INV_FIS') ");
        if(count($Ventanas) >= 1)
        {
            return response()->json([
                'status' => false,
                'message' => "No es posible realizar venta. Caja principal se encuentra en Cierre, Inventario Fisico.",
            ], 200);
        }

        $claseFactura = env('claseFactura');
        $maquina = env('maquina');
        $resolucionFacturas = DB::connection('sqlsrv2')->select('SELECT top 1 PrefijoFactura prefijo, Resolucion resolucion, Consecutivo consecutivo, NumeroDesde desde, '
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
                'message' => "No se encuentra Resolución de Factura activa para " . $claseFactura . " en la Máquina " . $maquina . " Express" 
            ], 200);
        }

        if( ($consecutivo < $consecutivoDesde) || ($consecutivo > $consecutivoHasta) )
        {
            return response()->json([
                'status' => false,
                'message' => "Lo sentimos, El consecutivo fuera del rango .",
            ], 200);
        }

        return response()->json([
            'status' => true,
            'message' => "",
        ], 200);

    }

    public function ConsultarProductoExpress($grupo)
    {
        $grupoPrecios = env('grupoPrecios');
        $producto = DB::connection('sqlsrv2')->select('SELECT p.Producto producto, pr.UnidadMedidaVenta unidad, p.Nombre nombre, '
        .'isnull(pua.PesoPromedio, p.PesoPromedio) pesoPromedio, pr.Precio precio, p.ValorImpuesto impuesto, '
        .'p.Pesado pesado, isnull(pua.PesoMinimo, p.PesoMinimo) pesoMinimo, '
        .'isnull(pua.PesoMaximo, p.PesoMaximo) pesoMaximo, p.ToleranciaMinima tolMinima, '
        .'p.ToleranciaMaxima tolMaxima, isnull(p.Existencias, 0) existencias, '
        ."(select case when count(Componente) = 0 then '' else 'X' end Com from Combos "
        ."where Combo = p.Producto and Estado = 'A') combo, isnull(p.ExistenciasK, 0) existenciasK, "
        .'ValorImpUltraprocesado as impProcesado , p.GrupoArticulos '
        .'from Productos p left join Precios pr on p.Producto = pr.Producto '
        .'left join PesosUnidadesAlternas pua on pr.Producto = pua.Producto and pr.UnidadMedidaVenta = pua.UnidadMedidaVenta '
        .'inner join ProductosPortal on ProductosPortal.material = p.producto '
        ."where ProductosPortal.categoria = '$grupo' "
        ."and pr.Estado = 'A' and p.Estado = 'A' and p.UnidadMedidaBase = 'S' and pr.Precio > 0 and GrupoPrecios = '$grupoPrecios' " ) ;
        if(count($producto) == 0)
        {
            return response()->json([
                'status' => false,
                'message' => "Producto no encontrado, Producto sin precios y/o no fue encontrado.",
                'product' => array()
            ], 200);
        }

        for ($i=0; $i < count($producto); $i++) { 
            $producto[$i]->url = "https://web.macpollo.com/app01/". $producto[$i]->producto .".png";
        }

        $descuento = DB::connection('sqlsrv2')->select('SELECT valor,ClaseDescuento,TipoDescuento,Descuentos.producto as producto '
            . 'FROM Descuentos '
            . 'inner join productos on productos.producto = Descuentos.producto '
            . "where GrupoPrecios = '$grupoPrecios' and Descuentos.Estado = 'A' "
            . 'and (select FechaProceso from Parametros ) between fechaInicio and fechaFin '
            . 'order by Descuentos.producto,ClaseDescuento desc');

        $descuentos = array();
        $productoOld = '';
        foreach ($descuento as $value) 
        {
            if($value->producto != $productoOld) 
            {
                $descuentos[] = $value;
                $productoOld = $value->producto;
            }
        }

        return $productos = array("Producto" => $producto, "Descuento" => $descuentos);

        return response()->json([
            'status' => true,
            'message' => "Producto Encontrado",
            'product' => $productos
        ], 200);
    }
    
    public function ConsultarGruposExpress()
    {
        $categorias = DB::connection('sqlsrv2')->select('SELECT categoria,descripcion,material '
            . 'FROM CategoriasPortal '
            . "where tipo = 'G' "
            . 'order by categoria ');
        
        if(count($categorias) == 0)
        {
            return response()->json([
                'status' => false,
                'message' => "Grupos No Encontrado",
                'grupos' => $categorias
            ], 200);
        }
        else
        {
            for ($i=0; $i < count($categorias); $i++) { 
                $categorias[$i]->url = "https://web.macpollo.com/app01/". $categorias[$i]->material .".png";
            }

            return response()->json([
                'status' => true,
                'message' => "Grupos Encontrado",
                'grupos' => $categorias
            ], 200);
        }
    }

    public function ProductoOferta(Request $request) {     

        $data = $request->json()->all(); 
        $grupoPrecios = env('grupoPrecios');
        $sqlsrv = ($data['conexion']['express']) ? 'sqlsrv2' : 'sqlsrv' ;        
        $i = 0;
        $Ofertas = array();
        foreach ($data['Producto'] as $values) {
            $producto = $values['producto'];
            $cantidad = $values['cantidad'];

            $productoOfertas = DB::connection($sqlsrv)->select("SELECT ofertas.Producto, FLOOR($cantidad/Cantidad) Cantidad,ProductoOferta,"
            ." (FLOOR($cantidad/Cantidad) * CantidadOferta) CantidadOferta,Productos.Nombre "
            . 'FROM ofertas '
            . 'inner join OfertasDetalle on ofertas.Oferta = OfertasDetalle.Oferta '
            . 'inner join Productos on OfertasDetalle.ProductoOferta = Productos.Producto '
            . " WHERE GrupoPrecios = '$grupoPrecios' and ofertas.Producto = '$producto' "
            . " and (select fechaproceso from Parametros) BETWEEN  fechaDesde and FechaHasta "); 
            
            $Ofertas[$i] = $productoOfertas; 
            $i++;
        }

        if(count($Ofertas) != 0)
        {
            return response()->json([
                'status' => true,
                'message' => "Ofertas Encontradas.",
                'producto' => $Ofertas
            ], 200);
        }
        else
        {
            return response()->json([
                'status' => false,
                'message' => "Ofertas No encontradas.",
                'producto' => array()
            ], 200);
        }

                
    }

    public function ConsultarProductoInternoExpress($codId)
    {
        $grupoPrecios = env('grupoPrecios');
        $producto = DB::connection('sqlsrv2')->select('SELECT p.Producto producto, pr.UnidadMedidaVenta unidad, p.Nombre nombre, '
        .'isnull(pua.PesoPromedio, p.PesoPromedio) pesoPromedio, pr.Precio precio, p.ValorImpuesto impuesto, '
        .'p.Pesado pesado, isnull(pua.PesoMinimo, p.PesoMinimo) pesoMinimo, '
        .'isnull(pua.PesoMaximo, p.PesoMaximo) pesoMaximo, p.ToleranciaMinima tolMinima, '
        .'p.ToleranciaMaxima tolMaxima, isnull(p.Existencias, 0) existencias, '
        ."(select case when count(Componente) = 0 then '' else 'X' end Com from Combos "
        ."where Combo = p.Producto and Estado = 'A') combo, isnull(p.ExistenciasK, 0) existenciasK, "
        .'ValorImpUltraprocesado as impProcesado , p.GrupoArticulos '
        .'from Productos p left join Precios pr on p.Producto = pr.Producto '
        .'left join PesosUnidadesAlternas pua on pr.Producto = pua.Producto and pr.UnidadMedidaVenta = pua.UnidadMedidaVenta '
        .'inner join ProductosPortal on ProductosPortal.material = p.producto '
        ."where p.producto = '$codId' and pr.Estado = 'A' and p.Estado = 'A' and p.UnidadMedidaBase = 'S' and pr.Precio > 0 and GrupoPrecios = '$grupoPrecios' " ) ;
        if(count($producto) == 0)
        {
            return array();
        }

        $descuento = DB::connection('sqlsrv2')->select('SELECT top 1 valor,ClaseDescuento,TipoDescuento,Descuentos.producto as producto '
        . 'FROM Descuentos '
        . 'inner join productos on productos.producto = Descuentos.producto '
        . "where productos.producto = '$codId' AND GrupoPrecios = '$grupoPrecios' and Descuentos.Estado = 'A' "
        . 'and (select FechaProceso from Parametros ) between fechaInicio and fechaFin '
        . 'order by ClaseDescuento desc');

        $descuentos = array();
        $productoOld = '';
        foreach ($descuento as $value) 
        {
            if($value->producto != $productoOld) 
            {
                $descuentos[] = $value;
                $productoOld = $value->producto;
            }
        }

        return $productos = array("Producto" => $producto, "Descuento" => $descuento);
    
    }
}
