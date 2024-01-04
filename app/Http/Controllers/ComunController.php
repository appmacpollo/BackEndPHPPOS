<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ComunController extends Controller
{
    public function DatosGenerales($express)
    {
        if($express) $conexion = 'sqlsrv2';
        else $conexion = 'sqlsrv';
        $infoFactura = DB::connection($conexion)->select('SELECT p.MensajeEmpresa empresa, p.NombreCentroLogistico centro, '
            .'p.DireccionCentroLogistico direccion, p.TelefonoCentroLogistico telefono, '
            .'p.MensajeFactura1 mensajeUno, p.MensajeFactura2 mensajeDos, p.MensajeFactura3 mensajeTres, '
            .'p.MensajeFactura4 mensajeCuatro, p.MensajeFactura5 mensajeCinco, '
            ."isnull(p.CiudadCentroLogistico, '') ciudad, isnull(c.NombreCiudad, '') nombreCiudad, "
            ."isnull(c.Departamento, '') departamento, isnull(c.NombreDepartamento, '') nombreDepartamento "
            .'from Parametros p left join Ciudades c on p.CiudadCentroLogistico = c.Ciudad' );

        $grupoPrecios = env('grupoPrecios');
        $documento = env('CLI_DOC_MOS');
        $maquina = env('maquina');

        $ClientesDocGrupoPrecios = DB::connection($conexion)->select('SELECT TOP 1 Cliente cliente, DocumentoIdentidad documento, Nombre nombre, Direccion direccion,'
        .'Telefono telefono, Barrio barrio, Estado estado '
        ."from Clientes where DocumentoIdentidad = '$documento' and GrupoPrecios like '$grupoPrecios' "
        .'order by Estado, Cliente' );

        $parametros = DB::connection($conexion)->select('SELECT TOP 1 Usuario,FechaProceso,Turno,* '
        .'from caja '
        .'inner join Parametros on Parametros.FechaProceso = caja.fecha '
        ."where Maquina = '$maquina' and FechaHasta is null ");

        $datos = array("infoFactura" => $infoFactura, "ClientesDocGrupoPrecios" => $ClientesDocGrupoPrecios, "parametros" => $parametros );

        return $datos;
    }

    public function Autorizacion(Request $request)
    {
        $data = $request->json()->all(); 
        $sqlsrv = ($data['conexion']['express']) ? 'sqlsrv2' : 'sqlsrv' ;     
        $usuario = $data['autorizacion']['credenciales'] ;

        $usuarios = DB::connection($sqlsrv)->select('SELECT * '
            .'from Usuarios '
            ." where Cedula = '$usuario' and Estado = 'A' " );

        if(count($usuarios) == 0)
        {
            return response()->json([
                'status' => false,
                'message' => "Usuario NO Autorizado"
            ], 200);
        }
        else
        {
            return response()->json([
                'status' => true,
                'message' => "Usuario Autorizado"
            ], 200);
        }

    }
}
