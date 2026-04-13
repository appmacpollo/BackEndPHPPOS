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

    public function procesarTurno(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'turnoId' => 'nullable|string',
            'codigo' => 'nullable|string',
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
                $indiceActual = $this->findCurrentProcessingIndex($turnos);
                $indiceSolicitado = $this->findTurnIndex($turnos, $payload['turnoId'] ?? null, $payload['codigo'] ?? null);
                $turnoProcesado = null;

                if ($indiceActual !== null) {
                    $turnoProcesado = $turnos[$indiceActual];
                    $turnoProcesado['estado'] = 'finalizado';
                    $turnoProcesado['finalizado_at'] = now()->toIso8601String();
                    $turnoProcesado['updated_at'] = now()->toIso8601String();

                    if (!empty($payload['observaciones'])) {
                        $turnoProcesado['observaciones'] = $payload['observaciones'];
                    }

                    $turnos[$indiceActual] = $turnoProcesado;
                }

                $indiceSiguiente = null;

                if ($indiceActual === null && $indiceSolicitado !== null && ($turnos[$indiceSolicitado]['estado'] ?? '') === 'pendiente') {
                    $indiceSiguiente = $indiceSolicitado;
                } else {
                    $indiceSiguiente = $this->findFirstPendingIndex($turnos);
                }

                $turnoActual = null;

                if ($indiceSiguiente !== null) {
                    $turnoActual = $turnos[$indiceSiguiente];
                    $turnoActual['estado'] = 'llamado';
                    $turnoActual['caja'] = self::FIXED_BOX_NAME;
                    $turnoActual['modulo'] = self::FIXED_BOX_NAME;
                    $turnoActual['asesor'] = $payload['asesor'] ?? $turnoActual['asesor'];
                    $turnoActual['llamado_at'] = now()->toIso8601String();
                    $turnoActual['updated_at'] = now()->toIso8601String();

                    if (!empty($payload['observaciones']) && $turnoProcesado === null) {
                        $turnoActual['observaciones'] = $payload['observaciones'];
                    }

                    $turnos[$indiceSiguiente] = $turnoActual;
                }

                if ($turnoProcesado === null && $turnoActual === null) {
                    throw new \RuntimeException('No hay turnos pendientes disponibles para llamar.');
                }

                $estado['turnos'] = $turnos;

                return [
                    'state' => $estado,
                    'result' => [
                        'turnoProcesado' => $turnoProcesado,
                        'turnoActual' => $turnoActual,
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
            'message' => 'Turno procesado correctamente.',
            'turnoProcesado' => $resultado['turnoProcesado'],
            'turno' => $resultado['turnoActual'],
            'data' => $this->buildBoardData($this->loadState()),
        ], 200);
    }

    private function buildBoardData(array $estado, bool $incluirHistorial = false): array
    {
        $turnos = $estado['turnos'] ?? [];
        $activos = array_values(array_filter($turnos, fn ($turno) => in_array($turno['estado'], self::ACTIVE_STATUSES, true)));
        usort($activos, fn ($a, $b) => $this->compareTurns($a, $b));

        $turnosPendientes = array_values(array_filter($activos, fn ($turno) => ($turno['estado'] ?? '') === 'pendiente'));
        usort($turnosPendientes, fn ($a, $b) => strcmp($a['created_at'], $b['created_at']));

        $siguienteTurno = $this->resolveNextTurn($turnosPendientes);
        $cajas = $this->buildServiceBoxes($activos);

        $respuesta = [
            'siguienteTurno' => $siguienteTurno,
            'turnos' => $activos,
            'resumen' => [
                'pendientes' => count(array_filter($activos, fn ($turno) => $turno['estado'] === 'pendiente')),
                'llamados' => count(array_filter($activos, fn ($turno) => $turno['estado'] === 'llamado')),
                'enAtencion' => count(array_filter($activos, fn ($turno) => $turno['estado'] === 'en_atencion')),
                'totalActivos' => count($activos),
            ],
            'cajas' => $cajas,
            'actualizadoEn' => now()->toIso8601String(),
        ];

        if ($incluirHistorial) {
            $respuesta['historial'] = array_values(array_filter($turnos, fn ($turno) => !in_array($turno['estado'], self::ACTIVE_STATUSES, true)));
        }

        return $respuesta;
    }

    private function buildServiceBoxes(array $turnos): array
    {
        $turnoActual = null;
        foreach ($turnos as $turno) {
            if (($turno['estado'] ?? '') === 'llamado') {
                $turnoActual = $turno;
                break;
            }
        }

        if ($turnoActual === null && count($turnos) > 0) {
            $turnoActual = $turnos[0];
        }

        return [[
            'name' => self::FIXED_BOX_NAME,
            'advisor' => $turnoActual['asesor'] ?? 'Pendiente',
            'state' => $turnoActual ? $this->presentStatus($turnoActual['estado']) : 'Disponible',
            'currentTurn' => $turnoActual['codigo'] ?? '--',
        ]];
    }

    private function resolveNextTurn(array $turnos): ?array
    {
        if (count($turnos) === 0) {
            return null;
        }

        $turno = $turnos[0];

        return [
            'id' => $turno['id'],
            'code' => $turno['codigo'],
            'area' => $turno['area'],
            'box' => 'Por asignar',
            'note' => 'Tu turno permanece en cola y sera llamado pronto.',
            'status' => $turno['estado'],
        ];
    }

    private function compareTurns(array $left, array $right): int
    {
        $leftWeight = $this->statusWeight($left['estado']);
        $rightWeight = $this->statusWeight($right['estado']);

        if ($leftWeight !== $rightWeight) {
            return $leftWeight <=> $rightWeight;
        }

        return strcmp($left['created_at'], $right['created_at']);
    }

    private function statusWeight(string $status): int
    {
        return match ($status) {
            'llamado' => 0,
            'pendiente' => 1,
            'en_atencion' => 2,
            'Atendiendo' => 2,
            'Llamando' => 0,
            default => 3,
        };
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

    private function findFirstPendingIndex(array $turnos): ?int
    {
        $pendientes = [];

        foreach ($turnos as $indice => $turno) {
            if (($turno['estado'] ?? '') === 'pendiente') {
                $pendientes[$indice] = $turno;
            }
        }

        if (count($pendientes) === 0) {
            return null;
        }

        uasort($pendientes, fn ($a, $b) => strcmp($a['created_at'], $b['created_at']));

        return array_key_first($pendientes);
    }

    private function findCurrentProcessingIndex(array $turnos): ?int
    {
        foreach ($turnos as $indice => $turno) {
            if (($turno['estado'] ?? '') === 'llamado') {
                return $indice;
            }
        }

        foreach ($turnos as $indice => $turno) {
            if (($turno['estado'] ?? '') === 'en_atencion') {
                return $indice;
            }
        }

        return null;
    }

    private function findTurnIndex(array $turnos, ?string $turnoId, ?string $codigo): ?int
    {
        foreach ($turnos as $indice => $turno) {
            if ($turnoId !== null && ($turno['id'] ?? '') === $turnoId) {
                return $indice;
            }

            if ($codigo !== null && strcasecmp($turno['codigo'] ?? '', $codigo) === 0) {
                return $indice;
            }
        }

        return null;
    }

    private function normalizePrefix(string $prefijo): string
    {
        $normalizado = strtoupper(trim($prefijo));
        $normalizado = preg_replace('/[^A-Z]/', '', $normalizado) ?: 'T';

        return substr($normalizado, 0, 3);
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
