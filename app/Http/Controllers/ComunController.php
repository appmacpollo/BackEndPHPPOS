<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ComunController extends Controller
{
    public function DatosGenerales()
    {
        $infoFactura = DB::select('SELECT p.MensajeEmpresa empresa, p.NombreCentroLogistico centro, '
        .'p.DireccionCentroLogistico direccion, p.TelefonoCentroLogistico telefono, '
        .'p.MensajeFactura1 mensajeUno, p.MensajeFactura2 mensajeDos, p.MensajeFactura3 mensajeTres, '
        .'p.MensajeFactura4 mensajeCuatro, p.MensajeFactura5 mensajeCinco, '
        ."isnull(p.CiudadCentroLogistico, '') ciudad, isnull(c.NombreCiudad, '') nombreCiudad, "
        ."isnull(c.Departamento, '') departamento, isnull(c.NombreDepartamento, '') nombreDepartamento "
        .'from Parametros p left join Ciudades c on p.CiudadCentroLogistico = c.Ciudad' );

        $grupoPrecios = config('app.grupoPrecios');
        $documento = config('app.CLI_DOC_MOS');
        $maquina = config('app.maquina');

        $ClientesDocGrupoPrecios = DB::select('SELECT TOP 1 Cliente cliente, DocumentoIdentidad documento, Nombre nombre, Direccion direccion,'
        .'Telefono telefono, Barrio barrio, Estado estado '
        ."from Clientes where DocumentoIdentidad = '$documento' and GrupoPrecios like '$grupoPrecios' "
        .'order by Estado, Cliente' );

        $parametros = DB::select('SELECT TOP 1 Usuario,FechaProceso,Turno,* '
        .'from caja '
        .'inner join Parametros on Parametros.FechaProceso = caja.fecha '
        ."where Maquina = '$maquina' and Turno = '9' ");

        $datos = array("infoFactura" => $infoFactura, "ClientesDocGrupoPrecios" => $ClientesDocGrupoPrecios, "parametros" => $parametros );

        return $datos;
    }
}
