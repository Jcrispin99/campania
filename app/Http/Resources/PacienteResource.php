<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Paciente;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Paciente */
final class PacienteResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'numero' => $this->numero,
            'nombre_completo' => $this->nombre_completo,
            'dni' => $this->dni,
            'edad' => $this->edad,
            'nombre_posicion' => $this->nombre_posicion,
            'nombre_area' => $this->nombre_area,
            'nombre_unidad_org' => $this->nombre_unidad_org,
            'telefono' => $this->telefono,
            'correo_electronico' => $this->correos->firstWhere('predeterminado', true)?->correo,
            'correos' => $this->correos->map(fn ($email) => [
                'id' => $email->id, 'correo' => $email->correo, 'predeterminado' => $email->predeterminado,
            ])->values(),
            'asistencia' => $this->asistencia,
            'presion_arterial' => $this->presion_arterial,
            'fecha' => $this->fecha?->format('Y-m-d'),
        ];
    }
}
