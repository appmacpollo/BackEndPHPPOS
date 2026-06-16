<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TurneroController extends Controller
{
    private const ACTIVE_STATUSES = ['pendiente', 'llamado', 'en_atencion'];
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

    public function boxesConfig()
    {
        return response()->json([
            'status' => true,
            'message' => 'Configuracion de cajas consultada correctamente.',
            'data' => [
                'cantidadCajas' => $this->configuredBoxesCount(),
                'cajas' => $this->configuredBoxes(),
            ],
        ], 200);
    }

    public function asignarTurno(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'documentoIdentidad' => 'required|string|max:30',
            'nombre' => 'required|string|max:120',
            'prefijo' => 'nullable|string|max:5',
            'esUsuarioMostrador' => 'nullable|boolean',
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
                'estado' => 'pendiente',
                'caja' => null,
                'esUsuarioMostrador' => (bool) ($payload['esUsuarioMostrador'] ?? false),
                'created_at' => now()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
                'llamado_at' => null,
                'finalizado_at' => null,
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
            'turno' => $this->publicTurn($turno),
            'data' => $this->buildBoardData($this->loadState()),
        ], 200);
    }

    public function avanzarTurno(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'caja' => ['required', 'string', Rule::in($this->configuredBoxes())],
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
            'turnoProcesado' => $this->publicTurnOrNull($resultado['turnoProcesado']),
            'turno' => $this->publicTurnOrNull($resultado['turnoActual']),
            'data' => $this->buildBoardData($this->loadState()),
        ], 200);
    }

    public function retrocederTurno(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'caja' => ['required', 'string', Rule::in($this->configuredBoxes())],
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
            'turnoProcesado' => $this->publicTurnOrNull($resultado['turnoProcesado']),
            'turno' => $this->publicTurnOrNull($resultado['turnoActual']),
            'data' => $this->buildBoardData($this->loadState()),
        ], 200);
    }

    private function buildBoardData(array $estado, bool $incluirHistorial = false): array
    {
        $turnos = $estado['turnos'] ?? [];
        $turnosAsignados = array_values(array_filter(
            $turnos,
            fn ($turno) => in_array($turno['estado'] ?? '', ['llamado', 'en_atencion'], true)
        ));
        usort($turnosAsignados, fn ($a, $b) => $this->compareBoxes($a['caja'] ?? '', $b['caja'] ?? ''));

        $turnosPendientes = array_values(array_filter($turnos, fn ($turno) => ($turno['estado'] ?? '') === 'pendiente'));
        usort($turnosPendientes, fn ($a, $b) => strcmp($a['created_at'], $b['created_at']));

        $cajas = [];
        foreach ($this->configuredBoxes() as $boxName) {
            $turnoActual = $this->findTurnByBox($turnos, $boxName, ['llamado', 'en_atencion']);
            $cajas[] = [
                'name' => $boxName,
                'state' => $turnoActual ? $this->presentStatus($turnoActual['estado']) : 'Disponible',
                'currentTurn' => $turnoActual['codigo'] ?? '--',
            ];
        }

        $respuesta = [
            'siguienteTurno' => $this->publicTurnOrNull($turnosPendientes[0] ?? null),
            'turnos' => array_map(fn ($turno) => $this->publicTurn($turno), array_values(array_merge($turnosAsignados, $turnosPendientes))),
            'resumen' => [
                'pendientes' => count(array_filter($turnos, fn ($turno) => ($turno['estado'] ?? '') === 'pendiente')),
                'llamados' => count(array_filter($turnos, fn ($turno) => ($turno['estado'] ?? '') === 'llamado')),
                'enAtencion' => count(array_filter($turnos, fn ($turno) => ($turno['estado'] ?? '') === 'en_atencion')),
                'totalActivos' => count(array_filter($turnos, fn ($turno) => in_array($turno['estado'] ?? '', self::ACTIVE_STATUSES, true))),
            ],
            'cajas' => $cajas,
            'actualizadoEn' => now()->toIso8601String(),
        ];

        if ($incluirHistorial) {
            $respuesta['historial'] = array_values(array_map(
                fn ($turno) => $this->publicTurn($turno),
                array_filter($turnos, fn ($turno) => !in_array($turno['estado'] ?? '', self::ACTIVE_STATUSES, true))
            ));
        }

        return $respuesta;
    }

    private function advanceTurns(array $turnos, array $payload): array
    {
        $boxName = $payload['caja'];
        $indiceActual = $this->findTurnIndex($turnos, ['llamado', 'en_atencion'], 'created_at', false, $boxName);
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
            ]);
            $turnos[$indiceActual] = $turnoProcesado;
        }

        $indiceSiguiente = $this->findTurnIndex($turnos, ['pendiente']);
        $turnoActual = null;

        if ($indiceSiguiente !== null) {
            $turnoActual = $this->applyTurnChanges($turnos[$indiceSiguiente], [
                'estado' => 'llamado',
                'caja' => $boxName,
                'llamado_at' => now()->toIso8601String(),
                'finalizado_at' => null,
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
        $boxName = $payload['caja'];
        $indiceActual = $this->findTurnIndex($turnos, ['llamado', 'en_atencion'], 'created_at', false, $boxName);
        $turnoProcesado = null;

        if ($indiceActual !== null) {
            $turnoProcesado = $this->applyTurnChanges($turnos[$indiceActual], [
                'estado' => 'pendiente',
                'caja' => null,
                'llamado_at' => null,
            ]);
            $turnos[$indiceActual] = $turnoProcesado;
        }

        $indiceRetroceso = $this->findTurnIndex($turnos, ['finalizado'], 'orden_procesado', true, $boxName);

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
            'caja' => $boxName,
            'finalizado_at' => null,
            'orden_procesado' => null,
            'llamado_at' => now()->toIso8601String(),
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
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    private function findTurnIndex(
        array $turnos,
        array $statuses,
        string $sortBy = 'created_at',
        bool $descending = false,
        ?string $boxName = null
    ): ?int
    {
        $candidatos = [];

        foreach ($turnos as $indice => $turno) {
            if (!in_array($turno['estado'] ?? '', $statuses, true)) {
                continue;
            }

            if ($boxName !== null && ($turno['caja'] ?? null) !== $boxName) {
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

    private function publicTurn(array $turno): array
    {
        return [
            'id' => $turno['id'] ?? null,
            'codigo' => $turno['codigo'] ?? null,
            'prefijo' => $turno['prefijo'] ?? null,
            'consecutivo' => $turno['consecutivo'] ?? null,
            'documentoIdentidad' => $turno['documentoIdentidad'] ?? null,
            'nombre' => $turno['nombre'] ?? null,
            'estado' => $turno['estado'] ?? null,
            'caja' => $turno['caja'] ?? null,
            'esUsuarioMostrador' => (bool) ($turno['esUsuarioMostrador'] ?? false),
            'created_at' => $turno['created_at'] ?? null,
            'updated_at' => $turno['updated_at'] ?? null,
            'llamado_at' => $turno['llamado_at'] ?? null,
            'finalizado_at' => $turno['finalizado_at'] ?? null,
        ];
    }

    private function publicTurnOrNull(?array $turno): ?array
    {
        return $turno !== null ? $this->publicTurn($turno) : null;
    }

    private function configuredBoxes(): array
    {
        $boxes = [];

        for ($index = 1; $index <= $this->configuredBoxesCount(); $index++) {
            $boxes[] = 'Caja '.$index;
        }

        return $boxes;
    }

    private function configuredBoxesCount(): int
    {
        return max(1, (int) config('turnero.boxes', 1));
    }

    private function findTurnByBox(array $turnos, string $boxName, array $statuses): ?array
    {
        $index = $this->findTurnIndex($turnos, $statuses, 'created_at', false, $boxName);

        return $index !== null ? $turnos[$index] : null;
    }

    private function compareBoxes(string $boxA, string $boxB): int
    {
        return $this->boxPosition($boxA) <=> $this->boxPosition($boxB);
    }

    private function boxPosition(string $boxName): int
    {
        $boxes = $this->configuredBoxes();
        $position = array_search($boxName, $boxes, true);

        return $position === false ? PHP_INT_MAX : $position;
    }

    private function loadState(): array
    {
        $this->ensureStorage();

        if (!File::exists($this->statePath())) {
            return $this->defaultState();
        }

        $decoded = json_decode(File::get($this->statePath()), true);

        if (!is_array($decoded)) {
            return $this->defaultState();
        }

        return $this->normalizeState(array_merge($this->defaultState(), $decoded));
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
                json_encode($this->normalizeState($resultado['state']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
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

    private function normalizeState(array $state): array
    {
        $turnos = array_map(
            fn ($turno) => $this->normalizeStoredTurn(is_array($turno) ? $turno : []),
            $state['turnos'] ?? []
        );

        return [
            'version' => $state['version'] ?? 1,
            'consecutivos' => is_array($state['consecutivos'] ?? null) ? $state['consecutivos'] : [],
            'turnos' => array_values($turnos),
        ];
    }

    private function normalizeStoredTurn(array $turno): array
    {
        return [
            'id' => $turno['id'] ?? (string) Str::uuid(),
            'codigo' => $turno['codigo'] ?? null,
            'prefijo' => $turno['prefijo'] ?? null,
            'consecutivo' => isset($turno['consecutivo']) ? (int) $turno['consecutivo'] : null,
            'documentoIdentidad' => $turno['documentoIdentidad'] ?? null,
            'nombre' => $turno['nombre'] ?? null,
            'estado' => $turno['estado'] ?? 'pendiente',
            'caja' => $turno['caja'] ?? null,
            'esUsuarioMostrador' => (bool) ($turno['esUsuarioMostrador'] ?? false),
            'created_at' => $turno['created_at'] ?? now()->toIso8601String(),
            'updated_at' => $turno['updated_at'] ?? now()->toIso8601String(),
            'llamado_at' => $turno['llamado_at'] ?? null,
            'finalizado_at' => $turno['finalizado_at'] ?? null,
            'orden_procesado' => isset($turno['orden_procesado']) ? (int) $turno['orden_procesado'] : null,
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
