<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\StorePacienteRequest;
use App\Http\Resources\PacienteResource;
use App\Models\Paciente;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PacienteController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $search = $request->string('search')->trim()->toString();
        $pacientes = Paciente::query()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $inner) use ($search): void {
                    $inner->where('nombre_completo', 'like', "%{$search}%")
                        ->orWhere('dni', 'like', "%{$search}%");
                });
            })
            ->orderBy('nombre_completo')->orderBy('id')->paginate(15);

        return $this->success([
            'items' => PacienteResource::collection($pacientes),
            'current_page' => $pacientes->currentPage(),
            'last_page' => $pacientes->lastPage(),
            'total' => $pacientes->total(),
        ]);
    }

    public function store(StorePacienteRequest $request): JsonResponse
    {
        return $this->created(new PacienteResource(Paciente::query()->create($request->validated())));
    }

    public function show(Paciente $paciente): JsonResponse
    {
        return $this->success(new PacienteResource($paciente));
    }

    public function porDni(string $dni): JsonResponse
    {
        return $this->success(new PacienteResource(Paciente::query()->where('dni', $dni)->firstOrFail()));
    }

    public function update(StorePacienteRequest $request, Paciente $paciente): JsonResponse
    {
        $paciente->update($request->validated());

        return $this->success(new PacienteResource($paciente->refresh()), 'Paciente actualizado');
    }
}
