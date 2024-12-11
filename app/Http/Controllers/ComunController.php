<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

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

        $parametros = DB::connection($conexion)->select('SELECT TOP 1 Usuario,FechaProceso,Turno,ConsecutivoClientes '
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

    public function ConsultarCliente(Request $request)
    {
        $data = $request->json()->all(); 
        $sqlsrv = 'sqlsrv' ;     
        $documentoIdentidad = $data['consulta']['documentoIdentidad'] ;

        $clientes = DB::connection($sqlsrv)->select('SELECT Top 1 Cliente,DocumentoIdentidad,Clientes.Nombre,Telefono,TiposDocumentos.TipoDocumento TipoDocumento '
                    .'from Clientes '
                    .'inner join TiposDocumentos on TiposDocumentos.TipoDocumento = Clientes.TipoDocumento '
                    ." WHERE DocumentoIdentidad = '$documentoIdentidad' and GrupoPrecios in ('20','27') "
                    .' order by Clientes.Estado asc ' );

        if(count($clientes) == 0)
        {
            // Configurar la autenticación básica
            $username = 'apiRISK@macpollo.com';
            $password = 'twNQ1uaUi3*';
            $basicAuth = base64_encode("$username:$password");
            
            // Definir los datos a enviar en la solicitud
            $tipoDoc = 'CC';
            
            // Enviar la solicitud POST
            $response = Http::withHeaders([
                    'Authorization' => 'Basic ' . $basicAuth,
                    'Content-Type' => 'application/json',
                ])
                ->timeout(30) // Establece el tiempo de espera en segundos
                ->withOptions([
                    'verify' => false, // Desactivar la verificación del certificado SSL 
                ])
                ->post('https://app.compliance.com.co/validador/ws/NombreService/consultarNombre', [
                    'documento' => $documentoIdentidad,
                    'tipoDocumento' => $tipoDoc,
                ]);
            
            // Manejo de la respuesta
            if ($response->successful()) {
                $data = $response->json();
                $clientes['Cliente'] = '';
                $clientes['DocumentoIdentidad'] = $response['documento'];
                $clientes['Nombre'] = $response['nombre'];
                $clientes['Telefono'] = '';
                $clientes['TipoDocumento'] = '13';

                return response()->json([
                    'status' => true,
                    'message' => "Cliente Encontrado",
                    'cliente' => $clientes
                ], 200);
            } else {
                $error = $response->body();
                $data = json_decode($error, true);
                return response()->json([
                    'status' => true,
                    'message' => $data['error'] ,
                    'cliente' =>  array()
                ], 200);
            }
        }
        else
        {
            return response()->json([
                'status' => true,
                'message' => "Cliente Encontrado",
                'cliente' => $clientes[0]
            ], 200);
        }
    }

    public function datosPuntoVenta()
    {
        $sqlsrv = 'sqlsrv' ;     
        $datosPunto = DB::connection($sqlsrv)->select('SELECT CentroLogistico,NombreCentroLogistico from parametros ' );

        if(count($datosPunto) == 0)
        {
            return response()->json([
                'status' => false,
                'message' => "Datos NO encontrados",
            ], 200);
        }
        else
        {
            return response()->json([
                'status' => true,
                'message' => "Datos encontrados",
                'data' => $datosPunto
            ], 200);
        }

    }

}
