<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\StorePacienteRequest;
use App\Http\Requests\Api\V1\UpdatePacienteCorreosRequest;
use App\Http\Resources\PacienteResource;
use App\Models\Paciente;
use App\Services\PacienteCorreoService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class PacienteController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $search = $request->string('search')->trim()->toString();
        $pacientes = Paciente::query()->with('correos')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $inner) use ($search): void {
                    $inner->where('nombre_completo', 'like', "%{$search}%")
                        ->orWhere('dni', 'like', "%{$search}%");
                })->orderByRaw(
                    'CASE WHEN dni = ? THEN 0 WHEN dni LIKE ? THEN 1 WHEN nombre_completo LIKE ? THEN 2 WHEN nombre_completo LIKE ? THEN 3 ELSE 4 END',
                    [$search, "{$search}%", "{$search}%", "% {$search}%"],
                );
            })
            ->orderBy('nombre_completo')->orderBy('id')->paginate($request->integer('per_page', 15));

        return $this->success([
            'items' => PacienteResource::collection($pacientes),
            'current_page' => $pacientes->currentPage(),
            'last_page' => $pacientes->lastPage(),
            'total' => $pacientes->total(),
        ]);
    }

    public function store(StorePacienteRequest $request, PacienteCorreoService $emails): JsonResponse
    {
        $patient = DB::transaction(function () use ($request, $emails): Paciente {
            /** @var array<string, mixed> $data */
            $data = $request->validated();
            /** @var array<string, mixed> $attributes */
            $attributes = Arr::except($data, ['correos', 'correo_electronico']);
            $patient = Paciente::query()->create($attributes);

            return $emails->apply($patient, $data);
        });

        return $this->created(new PacienteResource($patient));
    }

    public function show(Paciente $paciente): JsonResponse
    {
        return $this->success(new PacienteResource($paciente->load('correos')));
    }

    public function porDni(string $dni): JsonResponse
    {
        return $this->success(new PacienteResource(Paciente::query()->with('correos')->where('dni', $dni)->firstOrFail()));
    }

    public function update(StorePacienteRequest $request, Paciente $paciente, PacienteCorreoService $emails): JsonResponse
    {
        $patient = DB::transaction(function () use ($request, $paciente, $emails): Paciente {
            $patient = Paciente::query()->lockForUpdate()->findOrFail($paciente->id);
            /** @var array<string, mixed> $data */
            $data = $request->validated();
            /** @var array<string, mixed> $attributes */
            $attributes = Arr::except($data, ['correos', 'correo_electronico']);
            $patient->update($attributes);

            return $emails->apply($patient, $data);
        });

        return $this->success(new PacienteResource($patient), 'Paciente actualizado');
    }

    public function updateCorreos(UpdatePacienteCorreosRequest $request, Paciente $paciente, PacienteCorreoService $emails): JsonResponse
    {
        /** @var list<array{correo: string, predeterminado: bool}> $rows */
        $rows = $request->validated('correos');
        $patient = $emails->sync($paciente, $rows);

        return $this->success(new PacienteResource($patient), 'Correos guardados');
    }
}
