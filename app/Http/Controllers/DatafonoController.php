<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DatafonoController extends Controller
{
    public function EnviarADatafono($valorTotal, $valorImpuestos, $abreviatura) {
        $maquina = "01";
        $parametroMov = ["abreviatura" => $abreviatura];
        $sqlMovimiento = "select isnull(Consecutivo, 1) cons from TiposMovimientos where Abreviatura = :abreviatura";
        $tipoMovimiento = DB::select($sqlMovimiento, $parametroMov);

        if(count($tipoMovimiento) == 0)
        {
            return response()->json([
                'status' => false,
                'message' => "Tipo Movimiento no encontrado, por favor revise.",
                'cabecera' => array()
            ], 200);
        }

        $parametroVendedor = ["maquina" => $maquina];
        $sqlVendedor = "select c.Usuario from Caja c inner join Parametros p on c.Fecha = p.FechaProceso  where c.Maquina = :maquina and c.FechaHasta is null";
        $CCVendedor = DB::select($sqlVendedor, $parametroVendedor);

        if(count($CCVendedor) == 0)
        {
            return response()->json([
                'status' => false,
                'message' => "NO se encuentra vendedor asignado, por favor revise.",
                'cabecera' => array()
            ], 200);
        }

        $devTrans = "0";
        $separador = ",";
        $linea =  "01".$separador;
        $linea .= str_pad($valorTotal, 12, " ", STR_PAD_RIGHT).$separador;
        $linea .= str_pad($valorImpuestos, 12, " ", STR_PAD_RIGHT).$separador;
        $linea .= str_pad($devTrans, 12, " ", STR_PAD_RIGHT).$separador;
        $linea .= str_pad($maquina, 10, " ", STR_PAD_RIGHT).$separador;
        $linea .= str_pad($tipoMovimiento[0] -> cons, 10, " ", STR_PAD_RIGHT).$separador; 
        $linea .= str_pad($CCVendedor[0] -> Usuario, 12, " ", STR_PAD_RIGHT).$separador; 
        $linea .= $this->getValidacionLRC($linea);

        $this->EntradaDatafono($maquina, $linea);
        $this->llamarLibreriaDatafono();
    }

    public function llamarLibreriaDatafono() 
    {
        //pclose(popen("start /B cd D:\datafono\dis startTefServer.bat", "r"));
        //exec('START /B java -jar lib\tefServer.jar');
        //system("cmd /d D:\datafono\dis\startTefServer.bat");
        echo 'se ejecuto';
        // exec("java -jar D:\datafono\dis\lib\\tefServer.jar", $output);
        // print_r($output);
    } 

    public function EntradaDatafono($maquina, $linea) {
        $rutaInp = "D:\datafono/dis/IOFile/inp/";
        $rutaOut = "D:\datafono/dis/IOFile/out/"; 

        $this->limpiardirectorio($rutaInp);
        $this->limpiardirectorio($rutaOut);

        $file = fopen($rutaInp."dataf0".$maquina."_inp.eft", "w");
        fwrite($file, $linea);
        fclose($file);
    }

    function limpiardirectorio($dir) { 
        if (is_dir($dir)) { 
          $objects = scandir($dir);
          foreach ($objects as $object) { 
            if ($object != "." && $object != "..") { 
              if (is_dir($dir. DIRECTORY_SEPARATOR .$object) && !is_link($dir."/".$object))
                rrmdir($dir. DIRECTORY_SEPARATOR .$object);
              else
                unlink($dir. DIRECTORY_SEPARATOR .$object); 
            } 
          }
          //rmdir($dir); 
        } 
     }

    public function getValidacionLRC($linea)
    {
        $lineaLRC = "";
        if (strlen($linea) > 0) {
            $arrarStr = str_split($linea);
            $auxLRC = ord($arrarStr[0]);
            for ($i = 1; $i < count($arrarStr); $i++)
            {
                $auxLRC = $auxLRC ^ ord($arrarStr[$i]);
            }
            $lineaLRC = base_convert($auxLRC, 10, 16);
        }
        if (strlen($lineaLRC) == 1) {
            $lineaLRC = "0".$lineaLRC;
        }
        
        return $lineaLRC;
    }

    public function SalidaDatafono() {
        $file = 'D:\datafono\dis\IOFile\out\dataf001_OUT.eft';//the path of your file
        if (file_exists($file)) {
            $contenido = file_get_contents($file);
            $arrayContenido = explode(",", $contenido);
            if ($arrayContenido[0] == "00") {
                $numeroReferencia = $arrayContenido[1];
                $franquicia = $arrayContenido[11];
                $digitos = $arrayContenido[14];
                $numRec = $arrayContenido[5];
                $tipoCuenta = $arrayContenido[12];
                $numRrn = $arrayContenido[6];

                $valores = [$numeroReferencia, $franquicia, $digitos, $numRec, $tipoCuenta, $numRrn];

                return response()->json([
                    'status' => true,
                    'message' => "Información, Transacción Aprobada.",
                    'respuesta' => $valores
                ], 200);

            } else if ($arrayContenido[0] == "XX" || $arrayContenido[0] == "02") {
                return response()->json([
                    'status' => false,
                    'message' => "Advertencia, Transacción Rechazada.",
                    'respuesta' => array()
                ], 200);
            } else if ($arrayContenido[0] == "05") {
                return response()->json([
                    'status' => false,
                    'message' => "Advertencia, Transacción Negada.",
                    'respuesta' => array()
                ], 200);
            } else if ($arrayContenido[0] == "03") {
                return response()->json([
                    'status' => false,
                    'message' => "Advertencia, Transacción Sin Respuesta.",
                    'respuesta' => array()
                ], 200);
            } else {
                return response()->json([
                    'status' => false,
                    'message' => "Advertencia, Transacción NO Completada.",
                    'respuesta' => array()
                ], 200);
            }
        } else {
            return response()->json([
                'status' => false,
                'message' => "Archivo de salida datafono no encontrado, por favor intentelo mas tarde.",
                'respuesta' => array()
            ], 200);
        }
    }
}