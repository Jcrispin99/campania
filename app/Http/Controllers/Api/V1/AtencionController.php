<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\StoreAtencionRequest;
use App\Http\Resources\AtencionResource;
use App\Models\Atencion;
use App\Models\Paciente;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AtencionController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['dni' => ['nullable', 'string', 'regex:/^[0-9]{8}$/']]);
        $dni = $request->string('dni')->trim()->toString();
        $atenciones = Atencion::query()->with(['paciente', 'user'])
            ->when($dni !== '', fn (Builder $query): Builder => $query->whereHas('paciente', fn (Builder $pacientes): Builder => $pacientes->where('dni', $dni)))
            ->orderByDesc('fecha')->orderByDesc('id')->paginate(15);

        return $this->success([
            'items' => AtencionResource::collection($atenciones),
            'current_page' => $atenciones->currentPage(),
            'last_page' => $atenciones->lastPage(),
            'total' => $atenciones->total(),
        ]);
    }

    public function store(StoreAtencionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $paciente = Paciente::query()->where('dni', $data['dni'])->firstOrFail();
        $user = $request->user();
        abort_if($user === null, 401);
        $atencion = $paciente->atenciones()->create([
            'user_id' => $user->id,
            'valor_1' => $data['valor_1'],
            'fecha' => $data['fecha'],
        ]);

        return $this->created(new AtencionResource($atencion->load(['paciente', 'user'])));
    }
}
