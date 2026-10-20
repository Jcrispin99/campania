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
use Illuminate\Support\Facades\DB;

final class AtencionController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'dni' => ['nullable', 'string', 'regex:/^[0-9]{8}$/'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $dni = $request->string('dni')->trim()->toString();
        $search = $request->string('search')->trim()->toString();
        $atenciones = Atencion::query()->with(['paciente', 'user'])
            ->when($dni !== '', fn (Builder $query): Builder => $query->whereHas('paciente', fn (Builder $pacientes): Builder => $pacientes->where('dni', $dni)))
            ->when($search !== '', fn (Builder $query): Builder => $query->whereHas('paciente', fn (Builder $pacientes): Builder => $pacientes->where(
                fn (Builder $inner): Builder => $inner->where('nombre_completo', 'like', "%{$search}%")->orWhere('dni', 'like', "%{$search}%")
            )))
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
        $atencion = DB::transaction(function () use ($paciente, $user, $data): Atencion {
            $paciente->update([
                'edad' => $data['edad'],
                'correo_electronico' => array_key_exists('correo_electronico', $data) ? $data['correo_electronico'] : $paciente->correo_electronico,
            ]);

            return $paciente->atenciones()->create([
                'user_id' => $user->id,
                'valor_1' => $data['valor_1'],
                'diabetes' => $data['diabetes'] ?? false,
                'hipertension' => $data['hipertension'] ?? false,
                'ayuna' => $data['ayuna'] ?? false,
                'otros' => $data['otros'] ?? null,
                'fecha' => $data['fecha'],
            ]);
        });

        return $this->created(new AtencionResource($atencion->load(['paciente', 'user'])));
    }
}
