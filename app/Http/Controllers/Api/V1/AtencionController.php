<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\StoreAtencionRequest;
use App\Http\Resources\AtencionResource;
use App\Models\Atencion;
use App\Models\Paciente;
use App\Models\User;
use App\Services\AtencionExcelExporter;
use App\Services\PacienteCorreoService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class AtencionController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $atenciones = $this->filteredQuery($request)->orderByDesc('fecha')->orderByDesc('id')->paginate(15);

        return $this->success([
            'items' => AtencionResource::collection($atenciones),
            'current_page' => $atenciones->currentPage(),
            'last_page' => $atenciones->lastPage(),
            'total' => $atenciones->total(),
        ]);
    }

    public function export(Request $request, AtencionExcelExporter $exporter): BinaryFileResponse
    {
        $path = $exporter->create($this->filteredQuery($request));

        return response()->download($path, 'atenciones-'.now()->format('Y-m-d_H-i-s').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
        ])->deleteFileAfterSend(true);
    }

    public function store(StoreAtencionRequest $request, PacienteCorreoService $emails): JsonResponse
    {
        $data = $request->validated();
        $paciente = Paciente::query()->where('dni', $data['dni'])->firstOrFail();
        /** @var User|null $user */
        $user = $request->user('sanctum');
        $atencion = DB::transaction(function () use ($paciente, $user, $data, $emails): Atencion {
            $paciente = Paciente::query()->lockForUpdate()->findOrFail($paciente->id);
            if (isset($data['edad'])) {
                $paciente->update(['edad' => $data['edad']]);
            }
            $emails->apply($paciente, $data);

            return $paciente->atenciones()->create([
                'user_id' => $user?->id,
                'valor_1' => $data['valor_1'] ?? null,
                'diabetes' => $data['diabetes'] ?? false,
                'hipertension' => $data['hipertension'] ?? false,
                'ayuna' => $data['ayuna'] ?? false,
                'otros' => $data['otros'] ?? null,
                'fecha' => $data['fecha'],
            ]);
        });

        return $this->created(new AtencionResource($atencion->load(['paciente.correos', 'user'])));
    }

    /** @return Builder<Atencion> */
    private function filteredQuery(Request $request): Builder
    {
        $rules = [
            'dni' => ['nullable', 'string', 'regex:/^[0-9]{8}$/'],
            'search' => ['nullable', 'string', 'max:100'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d'],
        ];
        if ($request->filled('desde')) {
            $rules['hasta'][] = 'after_or_equal:desde';
        }
        $request->validate($rules, ['hasta.after_or_equal' => 'La fecha hasta debe ser igual o posterior a la fecha desde.']);
        $dni = $request->string('dni')->trim()->toString();
        $search = $request->string('search')->trim()->toString();

        return Atencion::query()->with(['paciente.correos', 'user'])
            ->when($dni !== '', fn (Builder $query): Builder => $query->whereHas('paciente', fn (Builder $patients): Builder => $patients->where('dni', $dni)))
            ->when($search !== '', fn (Builder $query): Builder => $query->whereHas('paciente', fn (Builder $patients): Builder => $patients->where(
                fn (Builder $inner): Builder => $inner->where('nombre_completo', 'like', "%{$search}%")->orWhere('dni', 'like', "%{$search}%")
            )))
            ->when($request->filled('desde'), fn (Builder $query): Builder => $query->whereDate('fecha', '>=', $request->string('desde')->toString()))
            ->when($request->filled('hasta'), fn (Builder $query): Builder => $query->whereDate('fecha', '<=', $request->string('hasta')->toString()));
    }
}
