<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DatafonoController extends Controller
{
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