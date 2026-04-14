<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class SwaggerController extends Controller
{
    public function docs()
    {
        $openapi = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'API POS Backend',
                'version' => '1.0.0',
                'description' => 'API para el sistema de punto de venta'
            ],
            'servers' => [
                ['url' => 'http://localhost:8000', 'description' => 'Servidor de desarrollo']
            ],
            'paths' => [
                '/api/ImprimirFactura/{factura}/{clase}/{prefijo}/{maquina}' => [
                    'get' => [
                        'tags' => ['Impresión'],
                        'summary' => 'Imprimir Factura',
                        'description' => 'Obtiene los datos de una factura para impresión',
                        'parameters' => [
                            ['name' => 'factura', 'in' => 'path', 'required' => true, 'description' => 'Número de factura', 'schema' => ['type' => 'string']],
                            ['name' => 'clase', 'in' => 'path', 'required' => true, 'description' => 'Clase de factura (ej: FC)', 'schema' => ['type' => 'string']],
                            ['name' => 'prefijo', 'in' => 'path', 'required' => true, 'description' => 'Prefijo de factura (ej: G09A)', 'schema' => ['type' => 'string']],
                            ['name' => 'maquina', 'in' => 'path', 'required' => true, 'description' => 'Número de máquina', 'schema' => ['type' => 'string']]
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'Factura encontrada o no encontrada',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'status' => ['type' => 'boolean', 'example' => true],
                                                'message' => ['type' => 'string', 'example' => 'Factura encontrada'],
                                                'cabecera' => ['type' => 'array', 'items' => ['type' => 'object']]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                '/api/ImprimirFacturaExpress/{factura}/{clase}/{prefijo}/{maquina}' => [
                    'get' => [
                        'tags' => ['Impresión'],
                        'summary' => 'Imprimir Factura Express',
                        'description' => 'Obtiene los datos de una factura express para impresión',
                        'parameters' => [
                            ['name' => 'factura', 'in' => 'path', 'required' => true, 'description' => 'Número de factura', 'schema' => ['type' => 'string']],
                            ['name' => 'clase', 'in' => 'path', 'required' => true, 'description' => 'Clase de factura', 'schema' => ['type' => 'string']],
                            ['name' => 'prefijo', 'in' => 'path', 'required' => true, 'description' => 'Prefijo de factura', 'schema' => ['type' => 'string']],
                            ['name' => 'maquina', 'in' => 'path', 'required' => true, 'description' => 'Número de máquina', 'schema' => ['type' => 'string']]
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'Factura encontrada o no encontrada',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'status' => ['type' => 'boolean'],
                                                'message' => ['type' => 'string'],
                                                'cabecera' => ['type' => 'array', 'items' => ['type' => 'object']]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                '/api/ImprimirAnulacionFactura/{factura}/{clase}/{prefijo}/{maquina}/{express}' => [
                    'get' => [
                        'tags' => ['Impresión'],
                        'summary' => 'Imprimir Anulación de Factura',
                        'description' => 'Obtiene los datos de una anulación de factura para impresión',
                        'parameters' => [
                            ['name' => 'factura', 'in' => 'path', 'required' => true, 'description' => 'Número de factura', 'schema' => ['type' => 'string']],
                            ['name' => 'clase', 'in' => 'path', 'required' => true, 'description' => 'Clase de factura', 'schema' => ['type' => 'string']],
                            ['name' => 'prefijo', 'in' => 'path', 'required' => true, 'description' => 'Prefijo de factura', 'schema' => ['type' => 'string']],
                            ['name' => 'maquina', 'in' => 'path', 'required' => true, 'description' => 'Número de máquina', 'schema' => ['type' => 'string']],
                            ['name' => 'express', 'in' => 'path', 'required' => true, 'description' => 'Indica si es factura express (true/false)', 'schema' => ['type' => 'string']]
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'Anulación encontrada o no encontrada',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'status' => ['type' => 'boolean'],
                                                'message' => ['type' => 'string'],
                                                'cabecera' => ['type' => 'array', 'items' => ['type' => 'object']]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                '/api/ConsultarProducto/{ean}' => [
                    'get' => [
                        'tags' => ['Productos'],
                        'summary' => 'Consultar Producto',
                        'description' => 'Consulta un producto por su código EAN',
                        'parameters' => [
                            ['name' => 'ean', 'in' => 'path', 'required' => true, 'description' => 'Código EAN del producto', 'schema' => ['type' => 'string']]
                        ],
                        'responses' => [
                            '200' => ['description' => 'Producto encontrado']
                        ]
                    ]
                ],
                '/api/ValidarVentas/{express}' => [
                    'get' => [
                        'tags' => ['Ventas'],
                        'summary' => 'Validar Ventas',
                        'description' => 'Valida el estado de la caja',
                        'parameters' => [
                            ['name' => 'express', 'in' => 'path', 'required' => true, 'description' => 'Indica si es express', 'schema' => ['type' => 'string']]
                        ],
                        'responses' => [
                            '200' => ['description' => 'Respuesta de validación']
                        ]
                    ]
                ],
                '/api/ValorPago' => [
                    'post' => [
                        'tags' => ['Facturación'],
                        'summary' => 'Valor de Pago',
                        'description' => 'Calcula el valor de pago',
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => ['type' => 'object']
                                ]
                            ]
                        ],
                        'responses' => [
                            '200' => ['description' => 'Valor calculado']
                        ]
                    ]
                ],
                '/api/facturar' => [
                    'post' => [
                        'tags' => ['Facturación'],
                        'summary' => 'Facturar',
                        'description' => 'Genera una nueva factura',
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => ['type' => 'object']
                                ]
                            ]
                        ],
                        'responses' => [
                            '200' => ['description' => 'Factura generada']
                        ]
                    ]
                ],
                '/api/AnularFactura' => [
                    'post' => [
                        'tags' => ['Facturación'],
                        'summary' => 'Anular Factura',
                        'description' => 'Anula una factura existente',
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => ['type' => 'object']
                                ]
                            ]
                        ],
                        'responses' => [
                            '200' => ['description' => 'Factura anulada']
                        ]
                    ]
                ],
                '/api/datosPuntoVenta' => [
                    'get' => [
                        'tags' => ['Común'],
                        'summary' => 'Datos Punto de Venta',
                        'description' => 'Obtiene los datos del punto de venta',
                        'responses' => [
                            '200' => ['description' => 'Datos del punto de venta']
                        ]
                    ]
                ]
            ]
        ];

        return response()->json($openapi, 200);
    }
}
