<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class TurneroController extends Controller
{
    private const ACTIVE_STATUSES = ['pendiente', 'llamado', 'en_atencion'];
    private const FIXED_BOX_NAME = 'Caja 1';
    private const MAX_COMPLETED_HISTORY = 10;

    public function index(Request $request)
    {
        $incluirHistorial = filter_var($request->query('incluirHistorial', false), FILTER_VALIDATE_BOOLEAN);
        $estado = $this->loadState();

        return response()->json([
            'status' => true,
            'message' => 'Turnos consultados correctamente.',
            'data' => $this->buildBoardData($estado, $incluirHistorial),
        ], 200);
    }

    public function asignarTurno(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'documentoIdentidad' => 'required|string|max:30',
            'nombre' => 'required|string|max:120',
            'area' => 'nullable|string|max:80',
            'prefijo' => 'nullable|string|max:5',
            'esUsuarioMostrador' => 'nullable|boolean',
            'observaciones' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Los datos enviados para asignar el turno no son validos.',
                'errors' => $validator->errors(),
            ], 200);
        }

        $payload = $validator->validated();

        $turno = $this->withStateLock(function (array $estado) use ($payload) {
            $prefijo = $this->normalizePrefix($payload['prefijo'] ?? 'T');
            $fecha = now()->format('Y-m-d');
            $consecutivoKey = $fecha.'_'.$prefijo;
            $consecutivo = ((int) ($estado['consecutivos'][$consecutivoKey] ?? 0)) + 1;
            $estado['consecutivos'][$consecutivoKey] = $consecutivo;

            $turno = [
                'id' => (string) Str::uuid(),
                'codigo' => sprintf('%s-%03d', $prefijo, $consecutivo),
                'prefijo' => $prefijo,
                'consecutivo' => $consecutivo,
                'documentoIdentidad' => $payload['documentoIdentidad'],
                'nombre' => $payload['nombre'],
                'area' => $payload['area'] ?? 'General',
                'estado' => 'pendiente',
                'caja' => null,
                'modulo' => null,
                'asesor' => null,
                'esUsuarioMostrador' => (bool) ($payload['esUsuarioMostrador'] ?? false),
                'observaciones' => $payload['observaciones'] ?? '',
                'created_at' => now()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
                'llamado_at' => null,
                'atendido_at' => null,
                'finalizado_at' => null,
                'eliminado_at' => null,
                'orden_procesado' => null,
            ];

            $estado['turnos'][] = $turno;

            return [
                'state' => $estado,
                'result' => $turno,
            ];
        });

        return response()->json([
            'status' => true,
            'message' => 'Turno asignado correctamente.',
            'turno' => $turno,
            'data' => $this->buildBoardData($this->loadState()),
        ], 200);
    }

    public function avanzarTurno(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'asesor' => 'nullable|string|max:120',
            'observaciones' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Los datos enviados para procesar el turno no son validos.',
                'errors' => $validator->errors(),
            ], 200);
        }

        $payload = $validator->validated();

        try {
            $resultado = $this->withStateLock(function (array $estado) use ($payload) {
                $turnos = $estado['turnos'] ?? [];
                $resultado = $this->advanceTurns($turnos, $payload);

                $turnos = $this->trimCompletedHistory($resultado['turnos']);

                $estado['turnos'] = $turnos;

                return [
                    'state' => $estado,
                    'result' => [
                        'turnoProcesado' => $resultado['turnoProcesado'],
                        'turnoActual' => $resultado['turnoActual'],
                    ],
                ];
            });
        } catch (\RuntimeException $exception) {
            return response()->json([
                'status' => false,
                'message' => $exception->getMessage(),
            ], 200);
        }

        return response()->json([
            'status' => true,
            'message' => 'Turno avanzado correctamente.',
            'turnoProcesado' => $resultado['turnoProcesado'],
            'turno' => $resultado['turnoActual'],
            'data' => $this->buildBoardData($this->loadState()),
        ], 200);
    }

    public function retrocederTurno(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'asesor' => 'nullable|string|max:120',
            'observaciones' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Los datos enviados para retroceder el turno no son validos.',
                'errors' => $validator->errors(),
            ], 200);
        }

        $payload = $validator->validated();

        try {
            $resultado = $this->withStateLock(function (array $estado) use ($payload) {
                $turnos = $estado['turnos'] ?? [];
                $resultado = $this->rollbackTurns($turnos, $payload);

                $turnos = $this->trimCompletedHistory($resultado['turnos']);
                $estado['turnos'] = $turnos;

                return [
                    'state' => $estado,
                    'result' => [
                        'turnoProcesado' => $resultado['turnoProcesado'],
                        'turnoActual' => $resultado['turnoActual'],
                    ],
                ];
            });
        } catch (\RuntimeException $exception) {
            return response()->json([
                'status' => false,
                'message' => $exception->getMessage(),
            ], 200);
        }

        return response()->json([
            'status' => true,
            'message' => 'Turno retrocedido correctamente.',
            'turnoProcesado' => $resultado['turnoProcesado'],
            'turno' => $resultado['turnoActual'],
            'data' => $this->buildBoardData($this->loadState()),
        ], 200);
    }

    private function buildBoardData(array $estado, bool $incluirHistorial = false): array
    {
        $turnos = $estado['turnos'] ?? [];
        $activos = array_values(array_filter($turnos, fn ($turno) => in_array($turno['estado'], self::ACTIVE_STATUSES, true)));
        $turnoActual = null;
        $turnosPendientes = [];

        foreach ($activos as $turno) {
            if ($turnoActual === null && in_array($turno['estado'], ['llamado', 'en_atencion'], true)) {
                $turnoActual = $turno;
                continue;
            }

            if (($turno['estado'] ?? '') === 'pendiente') {
                $turnosPendientes[] = $turno;
            }
        }

        usort($turnosPendientes, fn ($a, $b) => strcmp($a['created_at'], $b['created_at']));
        $activos = array_values(array_merge($turnoActual ? [$turnoActual] : [], $turnosPendientes));

        $respuesta = [
            'siguienteTurno' => $turnosPendientes[0] ?? null,
            'turnos' => $activos,
            'resumen' => [
                'pendientes' => count(array_filter($activos, fn ($turno) => $turno['estado'] === 'pendiente')),
                'llamados' => count(array_filter($activos, fn ($turno) => $turno['estado'] === 'llamado')),
                'enAtencion' => count(array_filter($activos, fn ($turno) => $turno['estado'] === 'en_atencion')),
                'totalActivos' => count($activos),
            ],
            'cajas' => [[
                'name' => self::FIXED_BOX_NAME,
                'advisor' => $turnoActual['asesor'] ?? 'Pendiente',
                'state' => $turnoActual ? $this->presentStatus($turnoActual['estado']) : 'Disponible',
                'currentTurn' => $turnoActual['codigo'] ?? '--',
            ]],
            'actualizadoEn' => now()->toIso8601String(),
        ];

        if ($incluirHistorial) {
            $respuesta['historial'] = array_values(array_filter($turnos, fn ($turno) => !in_array($turno['estado'], self::ACTIVE_STATUSES, true)));
        }

        return $respuesta;
    }

    private function advanceTurns(array $turnos, array $payload): array
    {
        $indiceActual = $this->findTurnIndex($turnos, ['llamado', 'en_atencion']);
        $turnoProcesado = null;
        $ordenProcesado = 1;

        foreach ($turnos as $turno) {
            $ordenProcesado = max($ordenProcesado, ((int) ($turno['orden_procesado'] ?? 0)) + 1);
        }

        if ($indiceActual !== null) {
            $turnoProcesado = $this->applyTurnChanges($turnos[$indiceActual], [
                'estado' => 'finalizado',
                'finalizado_at' => now()->toIso8601String(),
                'orden_procesado' => $ordenProcesado,
                'observaciones' => $payload['observaciones'] ?? $turnos[$indiceActual]['observaciones'],
            ]);
            $turnos[$indiceActual] = $turnoProcesado;
        }

        $indiceSiguiente = $this->findTurnIndex($turnos, ['pendiente']);
        $turnoActual = null;

        if ($indiceSiguiente !== null) {
            $turnoActual = $this->applyTurnChanges($turnos[$indiceSiguiente], [
                'estado' => 'llamado',
                'caja' => self::FIXED_BOX_NAME,
                'modulo' => self::FIXED_BOX_NAME,
                'asesor' => $payload['asesor'] ?? $turnos[$indiceSiguiente]['asesor'],
                'llamado_at' => now()->toIso8601String(),
                'finalizado_at' => null,
                'observaciones' => $turnoProcesado === null && !empty($payload['observaciones'])
                    ? $payload['observaciones']
                    : $turnos[$indiceSiguiente]['observaciones'],
            ]);
            $turnos[$indiceSiguiente] = $turnoActual;
        }

        if ($turnoProcesado === null && $turnoActual === null) {
            throw new \RuntimeException('No hay turnos pendientes disponibles para llamar.');
        }

        return [
            'turnos' => $turnos,
            'turnoProcesado' => $turnoProcesado,
            'turnoActual' => $turnoActual,
        ];
    }

    private function rollbackTurns(array $turnos, array $payload): array
    {
        $indiceActual = $this->findTurnIndex($turnos, ['llamado', 'en_atencion']);
        $turnoProcesado = null;

        if ($indiceActual !== null) {
            $turnoProcesado = $this->applyTurnChanges($turnos[$indiceActual], [
                'estado' => 'pendiente',
                'caja' => null,
                'modulo' => null,
                'asesor' => null,
                'llamado_at' => null,
                'atendido_at' => null,
            ]);
            $turnos[$indiceActual] = $turnoProcesado;
        }

        $indiceRetroceso = $this->findTurnIndex($turnos, ['finalizado'], 'orden_procesado', true);

        if ($indiceRetroceso === null) {
            if ($turnoProcesado !== null) {
                return [
                    'turnos' => $turnos,
                    'turnoProcesado' => null,
                    'turnoActual' => null,
                ];
            }

            throw new \RuntimeException('No hay turnos recientes para retroceder.');
        }

        $turnoActual = $this->applyTurnChanges($turnos[$indiceRetroceso], [
            'estado' => 'llamado',
            'caja' => self::FIXED_BOX_NAME,
            'modulo' => self::FIXED_BOX_NAME,
            'asesor' => $payload['asesor'] ?? $turnos[$indiceRetroceso]['asesor'],
            'finalizado_at' => null,
            'eliminado_at' => null,
            'orden_procesado' => null,
            'llamado_at' => now()->toIso8601String(),
            'observaciones' => $payload['observaciones'] ?? $turnos[$indiceRetroceso]['observaciones'],
        ]);
        $turnos[$indiceRetroceso] = $turnoActual;

        return [
            'turnos' => $turnos,
            'turnoProcesado' => null,
            'turnoActual' => $turnoActual,
        ];
    }

    private function applyTurnChanges(array $turno, array $changes): array
    {
        return array_merge($turno, $changes, [
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    private function presentStatus(string $status): string
    {
        return match ($status) {
            'pendiente' => 'En espera',
            'llamado' => 'Llamando',
            'en_atencion' => 'Atendiendo',
            'finalizado' => 'Finalizado',
            'cancelado' => 'Cancelado',
            'eliminado' => 'Eliminado',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    private function findTurnIndex(
        array $turnos,
        array $statuses,
        string $sortBy = 'created_at',
        bool $descending = false
    ): ?int
    {
        $candidatos = [];

        foreach ($turnos as $indice => $turno) {
            if (!in_array($turno['estado'] ?? '', $statuses, true)) {
                continue;
            }

            $candidatos[$indice] = $turno;
        }

        if (count($candidatos) === 0) {
            return null;
        }

        uasort($candidatos, function ($a, $b) use ($sortBy, $descending) {
            $valorA = $a[$sortBy] ?? '';
            $valorB = $b[$sortBy] ?? '';

            if (is_numeric($valorA) || is_numeric($valorB)) {
                $resultado = ((int) $valorA) <=> ((int) $valorB);
            } else {
                $resultado = strcmp((string) $valorA, (string) $valorB);
            }

            return $descending ? -$resultado : $resultado;
        });

        return array_key_first($candidatos);
    }

    private function normalizePrefix(string $prefijo): string
    {
        $normalizado = strtoupper(trim($prefijo));
        $normalizado = preg_replace('/[^A-Z]/', '', $normalizado) ?: 'T';

        return substr($normalizado, 0, 3);
    }

    private function trimCompletedHistory(array $turnos): array
    {
        $completados = [];

        foreach ($turnos as $indice => $turno) {
            if (($turno['estado'] ?? '') === 'finalizado' && !empty($turno['orden_procesado'])) {
                $completados[$indice] = $turno;
            }
        }

        if (count($completados) <= self::MAX_COMPLETED_HISTORY) {
            return $turnos;
        }

        uasort($completados, fn ($a, $b) => ((int) ($b['orden_procesado'] ?? 0)) <=> ((int) ($a['orden_procesado'] ?? 0)));
        $permitidos = array_slice(array_keys($completados), 0, self::MAX_COMPLETED_HISTORY);

        foreach (array_keys($completados) as $indice) {
            if (!in_array($indice, $permitidos, true)) {
                unset($turnos[$indice]);
            }
        }

        return array_values($turnos);
    }

    private function loadState(): array
    {
        $this->ensureStorage();

        if (!File::exists($this->statePath())) {
            return $this->defaultState();
        }

        $decoded = json_decode(File::get($this->statePath()), true);

        return is_array($decoded) ? array_merge($this->defaultState(), $decoded) : $this->defaultState();
    }

    private function withStateLock(callable $callback): mixed
    {
        $this->ensureStorage();
        $handle = fopen($this->lockPath(), 'c+');

        if ($handle === false) {
            throw new \RuntimeException('No fue posible abrir el archivo de bloqueo de turnos.');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new \RuntimeException('No fue posible bloquear la persistencia de turnos.');
            }

            $estado = $this->loadState();
            $resultado = $callback($estado);

            File::put(
                $this->statePath(),
                json_encode($resultado['state'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );

            flock($handle, LOCK_UN);

            return $resultado['result'];
        } finally {
            fclose($handle);
        }
    }

    private function ensureStorage(): void
    {
        if (!File::isDirectory($this->storageDirectory())) {
            File::makeDirectory($this->storageDirectory(), 0755, true);
        }
    }

    private function defaultState(): array
    {
        return [
            'version' => 1,
            'consecutivos' => [],
            'turnos' => [],
        ];
    }

    private function storageDirectory(): string
    {
        return storage_path('app/turnero');
    }

    private function statePath(): string
    {
        return $this->storageDirectory().DIRECTORY_SEPARATOR.'turnos.json';
    }

    private function lockPath(): string
    {
        return $this->storageDirectory().DIRECTORY_SEPARATOR.'turnos.lock';
    }
}
